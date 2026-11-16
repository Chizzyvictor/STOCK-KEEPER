<?php
declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';


function partnerResponse(string $status, string $text, array $data = [], int $code = 200): void
{
    apiRespond($status, $text, $data, $code);
}

$cid = (int) $_SESSION['cid'];


require_once __DIR__ . '/INC/schema_extensions.php';

try {
    $db = stockKeeperOpenDatabase();
    ensureStockKeeperSchemaExtensions($db);
    ensureStockKeeperDefaultPartners($db, $cid);

    $action = apiInput('action');
    $input = static fn(string $key): string => apiInput($key);
    $run = static function (string $sql, array $params = []) use ($db): SQLite3Result {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException($db->lastErrorMsg());
        }
        foreach ($params as $key => $value) {
            $type = $value === null ? SQLITE3_NULL : (is_int($value) ? SQLITE3_INTEGER : (is_float($value) ? SQLITE3_FLOAT : SQLITE3_TEXT));
            $stmt->bindValue(':' . $key, $value, $type);
        }
        $result = $stmt->execute();
        if (!$result) {
            throw new RuntimeException($db->lastErrorMsg());
        }
        return $result;
    };

    if ($action === 'list') {
        $result = $run(
            'SELECT partner_id, name, phone, email, address, role, status, notes, is_system, system_key,
                    customer_outstanding, supplier_outstanding,
                    customer_advance, supplier_advance, created_at
             FROM partners WHERE cid = :cid AND deleted_at IS NULL
             ORDER BY name COLLATE NOCASE',
            ['cid' => $cid]
        );
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        partnerResponse('success', 'Partners loaded.', ['partners' => $rows]);
    }

    if ($action === 'history') {
        $idRaw = $input('partner_id') === '' ? '0' : $input('partner_id');
        if (!preg_match('/^\d+$/', $idRaw)) partnerResponse('error', 'Invalid partner selection.', [], 422);
        $id = (int)$idRaw;
        $owner = $run('SELECT partner_id FROM partners WHERE partner_id = :id AND cid = :cid AND deleted_at IS NULL', ['id'=>$id, 'cid'=>$cid]);
        if (!$owner->fetchArray(SQLITE3_ASSOC)) {
            partnerResponse('error', 'Partner not found.', [], 404);
        }
        $result = $run('SELECT entry_id, account_type, entry_type, amount, note, created_at
                        FROM partner_ledger WHERE partner_id = :id AND cid = :cid
                        ORDER BY entry_id DESC LIMIT 200', ['id'=>$id, 'cid'=>$cid]);
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        partnerResponse('success', 'Account activity loaded.', ['entries'=>$rows]);
    }

    if ($action === 'statement') {
        $id = (int)$input('partner_id');
        $account = $input('account_type');
        $from = $input('from_date');
        $to = $input('to_date');
        $validDate = static function (string $value): bool {
            if ($value === '') return true;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            return $date !== false && $date->format('Y-m-d') === $value;
        };
        if (!in_array($account, ['customer', 'supplier'], true) || !$validDate($from) || !$validDate($to) || ($from !== '' && $to !== '' && $from > $to)) {
            partnerResponse('error', 'Choose a valid account and date range.', [], 422);
        }
        $partnerResult = $run('SELECT partner_id, name, created_at, customer_outstanding, supplier_outstanding,
                customer_advance, supplier_advance
            FROM partners WHERE partner_id = :id AND cid = :cid AND deleted_at IS NULL', ['id'=>$id, 'cid'=>$cid]);
        $partner = $partnerResult->fetchArray(SQLITE3_ASSOC);
        if (!$partner) partnerResponse('error', 'Partner not found.', [], 404);

        $activityResult = $run('SELECT entry_id, entry_type, amount, balance_effect, note, created_at
            FROM partner_ledger WHERE partner_id = :id AND cid = :cid AND account_type = :account
            ORDER BY created_at ASC, entry_id ASC', ['id'=>$id, 'cid'=>$cid, 'account'=>$account]);
        $activity = [];
        $knownActivity = 0.0;
        $firstOpeningIndex = null;
        $needsOpeningReconstruction = false;
        while ($entry = $activityResult->fetchArray(SQLITE3_ASSOC)) {
            $type = (string)$entry['entry_type'];
            $amount = (float)$entry['amount'];
            if ($type === 'opening') {
                if ($firstOpeningIndex === null) $firstOpeningIndex = count($activity);
                $effect = $entry['balance_effect'] === null ? 0.0 : (float)$entry['balance_effect'];
                if ($entry['balance_effect'] === null) $needsOpeningReconstruction = true;
            } elseif (in_array($type, ['sale', 'purchase'], true)) {
                $effect = $entry['balance_effect'] === null ? $amount : (float)$entry['balance_effect'];
                $knownActivity += $effect;
            } else {
                // Payments and refunds reduce the account's net amount owed.
                $effect = $entry['balance_effect'] === null ? -$amount : (float)$entry['balance_effect'];
                $knownActivity += $effect;
            }
            $activity[] = [
                'entry_id'=>(int)$entry['entry_id'],
                'entry_type'=>$type,
                'amount'=>$amount,
                'note'=>(string)($entry['note'] ?? ''),
                'created_at'=>(string)$entry['created_at'],
                'effect'=>$effect,
                'needs_opening_reconstruction'=>$type === 'opening' && $entry['balance_effect'] === null,
            ];
        }

        $outstanding = (float)$partner[$account === 'customer' ? 'customer_outstanding' : 'supplier_outstanding'];
        $advance = (float)$partner[$account === 'customer' ? 'customer_advance' : 'supplier_advance'];
        // Old opening ledger rows only store a positive amount. Reconstruct their sign
        // from the current due/advance balance and all subsequent signed activity.
        $openingEffect = ($outstanding - $advance) - $knownActivity;
        if ($firstOpeningIndex !== null && $needsOpeningReconstruction) {
            $activity[$firstOpeningIndex]['effect'] = $openingEffect;
            $activity[$firstOpeningIndex]['amount'] = abs($openingEffect);
            $activity[$firstOpeningIndex]['needs_opening_reconstruction'] = false;
            $activity[$firstOpeningIndex]['note'] = trim($activity[$firstOpeningIndex]['note']) !== ''
                ? $activity[$firstOpeningIndex]['note']
                : 'Opening balance';
        } elseif ($firstOpeningIndex === null && abs($openingEffect) > 0.000001) {
            // Preserve a visible brought-forward balance even for legacy accounts
            // whose opening activity row is missing.
            array_unshift($activity, [
                'entry_id'=>0, 'entry_type'=>'opening', 'amount'=>abs($openingEffect),
                'note'=>'Opening balance (reconstructed)', 'created_at'=>(string)$partner['created_at'],
                'effect'=>$openingEffect,
                'needs_opening_reconstruction'=>false,
            ]);
        }

        $periodRows = [];
        $openingBalance = 0.0;
        $periodDebits = 0.0;
        $periodCredits = 0.0;
        $closingBalance = 0.0;
        foreach ($activity as $entry) {
            $entryDate = substr((string)$entry['created_at'], 0, 10);
            $effect = (float)$entry['effect'];
            if ($from !== '' && $entryDate < $from) {
                $openingBalance += $effect;
                continue;
            }
            if ($to !== '' && $entryDate > $to) continue;
            $closingBalance += $effect;
            if ($effect > 0) $periodDebits += $effect;
            if ($effect < 0) $periodCredits += abs($effect);
            $periodRows[] = [
                'entry_id'=>$entry['entry_id'],
                'entry_type'=>$entry['entry_type'],
                'amount'=>$entry['amount'],
                'debit'=>max(0.0, $effect),
                'credit'=>max(0.0, -$effect),
                'note'=>$entry['note'],
                'created_at'=>$entry['created_at'],
                'balance_after'=>$openingBalance + $closingBalance,
            ];
        }

        $currentBalance = $outstanding - $advance;
        partnerResponse('success', 'Partner statement loaded.', [
            'partner'=>['partner_id'=>$id, 'name'=>$partner['name']],
            'account_type'=>$account,
            'from_date'=>$from,
            'to_date'=>$to,
            'opening_balance'=>$openingBalance,
            'total_debits'=>$periodDebits,
            'total_credits'=>$periodCredits,
            'closing_balance'=>$openingBalance + $closingBalance,
            'current_balance'=>$currentBalance,
            'entries'=>$periodRows,
        ]);
    }

    if ($action === 'save') {
        $idRaw = $input('partner_id') === '' ? '0' : $input('partner_id');
        if (!preg_match('/^\d+$/', $idRaw)) partnerResponse('error', 'Invalid partner selection.', [], 422);
        $id = (int)$idRaw;
        $name = $input('name');
        $phone = $input('phone');
        $email = strtolower($input('email'));
        $address = $input('address');
        $role = $input('role');
        $status = $input('status');
        $notes = $input('notes');
        $opening = [];
        foreach (['customer_outstanding', 'supplier_outstanding', 'customer_advance', 'supplier_advance'] as $field) {
            $raw = $input($field);
            if ($id === 0 && $raw !== '' && !apiValidMoney($raw)) {
                partnerResponse('error', 'Opening balances must be zero or greater.', [], 422);
            }
            $opening[$field] = $raw === '' ? 0.0 : (float)$raw;
        }
        if (($opening['customer_outstanding'] > 0 && $opening['customer_advance'] > 0)
            || ($opening['supplier_outstanding'] > 0 && $opening['supplier_advance'] > 0)) {
            partnerResponse('error', 'For each account, enter either an outstanding balance or an advance, not both.', [], 422);
        }
        $nameLength = apiTextLength($name);
        if ($name === '' || $nameLength > 120) {
            partnerResponse('error', 'Enter a partner name of 1 to 120 characters.', [], 422);
        }
        if (!in_array($role, ['supplier', 'customer', 'both'], true) || !in_array($status, ['active', 'inactive'], true)) {
            partnerResponse('error', 'Choose a valid partner role and status.', [], 422);
        }
        if ($id === 0 && (($role === 'supplier' && ($opening['customer_outstanding'] > 0 || $opening['customer_advance'] > 0))
            || ($role === 'customer' && ($opening['supplier_outstanding'] > 0 || $opening['supplier_advance'] > 0)))) {
            partnerResponse('error', 'Opening balances must match the partner’s selected role, or choose both roles.', [], 422);
        }
        if ($email !== '' && !apiValidEmail($email)) {
            partnerResponse('error', 'Enter a valid email address.', [], 422);
        }
        if (strlen($phone) > 40 || strlen($email) > 160 || strlen($address) > 500 || strlen($notes) > 1000) {
            partnerResponse('error', 'Phone, email, address, or notes exceed their allowed length.', [], 422);
        }
        $duplicate = $run('SELECT partner_id, deleted_at FROM partners WHERE cid = :cid AND name = :name COLLATE NOCASE AND partner_id <> :id LIMIT 1', ['cid' => $cid, 'name' => $name, 'id' => $id]);
        $duplicateRow = $duplicate->fetchArray(SQLITE3_ASSOC);
        if ($duplicateRow && $duplicateRow['deleted_at'] === null) {
            partnerResponse('error', 'A partner with this name already exists.', [], 409);
        }
        if ($duplicateRow && $id === 0) {
            $restoredId = (int)$duplicateRow['partner_id'];
            $run('UPDATE partners SET phone = :phone, email = :email, address = :address,
                    role = :role, status = :status, notes = :notes, deleted_at = NULL,
                    updated_at = CURRENT_TIMESTAMP WHERE partner_id = :id AND cid = :cid',
                ['phone'=>$phone ?: null, 'email'=>$email ?: null, 'address'=>$address ?: null, 'role'=>$role, 'status'=>$status, 'notes'=>$notes ?: null, 'id'=>$restoredId, 'cid'=>$cid]);
            partnerResponse('success', 'Archived partner restored. Its account balances and history were preserved.');
        }
        if ($id > 0) {
            $existingPartner = $run('SELECT is_system FROM partners WHERE partner_id = :id AND cid = :cid AND deleted_at IS NULL', ['id'=>$id, 'cid'=>$cid])->fetchArray(SQLITE3_ASSOC);
            if (!$existingPartner) partnerResponse('error', 'Partner not found.', [], 404);
            if ((int)$existingPartner['is_system'] === 1) {
                partnerResponse('error', 'System partners cannot be renamed or edited.', [], 409);
            }
            $run('UPDATE partners SET name = :name, phone = :phone, email = :email,
                    address = :address, role = :role, status = :status, notes = :notes,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE partner_id = :id AND cid = :cid AND deleted_at IS NULL',
                ['name'=>$name, 'phone'=>$phone ?: null, 'email'=>$email ?: null, 'address'=>$address ?: null, 'role'=>$role, 'status'=>$status, 'notes'=>$notes ?: null, 'id'=>$id, 'cid'=>$cid]);
            partnerResponse('success', 'Partner updated.');
        }
        $db->exec('BEGIN IMMEDIATE');
        try {
            $run('INSERT INTO partners (cid, name, phone, email, address, role, status, notes,
                    customer_outstanding, supplier_outstanding, customer_advance, supplier_advance)
                  VALUES (:cid, :name, :phone, :email, :address, :role, :status, :notes,
                    :customer_outstanding, :supplier_outstanding, :customer_advance, :supplier_advance)',
                array_merge(['cid'=>$cid, 'name'=>$name, 'phone'=>$phone ?: null, 'email'=>$email ?: null, 'address'=>$address ?: null, 'role'=>$role, 'status'=>$status, 'notes'=>$notes ?: null], $opening));
            $partnerId = (int)$db->lastInsertRowID();
            foreach ([
                'customer_outstanding' => ['customer', 'opening', 1.0],
                'supplier_outstanding' => ['supplier', 'opening', 1.0],
                'customer_advance' => ['customer', 'opening', -1.0],
                'supplier_advance' => ['supplier', 'opening', -1.0],
            ] as $field => [$account, $entryType, $direction]) {
                if ($opening[$field] > 0) {
                    $run('INSERT INTO partner_ledger (partner_id, cid, account_type, entry_type, amount, balance_effect, note)
                          VALUES (:partner_id, :cid, :account, :entry_type, :amount, :balance_effect, :note)',
                        ['partner_id'=>$partnerId, 'cid'=>$cid, 'account'=>$account, 'entry_type'=>$entryType,
                         'amount'=>$opening[$field], 'balance_effect'=>$opening[$field] * $direction, 'note'=>'Opening balance']);
                }
            }
            $db->exec('COMMIT');
        } catch (Throwable $error) {
            $db->exec('ROLLBACK');
            throw $error;
        }
        partnerResponse('success', 'Partner added.');
    }

    if ($action === 'archive') {
        $id = (int)$input('partner_id');
        $balanceResult = $run('SELECT customer_outstanding, supplier_outstanding,
                customer_advance, supplier_advance
            FROM partners WHERE partner_id = :id AND cid = :cid AND deleted_at IS NULL',
            ['id'=>$id, 'cid'=>$cid]);
        $balances = $balanceResult->fetchArray(SQLITE3_ASSOC);
        if (!$balances) {
            partnerResponse('error', 'Partner not found.', [], 404);
        }
        $systemCheck = $run('SELECT is_system FROM partners WHERE partner_id = :id AND cid = :cid', ['id'=>$id, 'cid'=>$cid])->fetchArray(SQLITE3_ASSOC);
        if ($systemCheck && (int)$systemCheck['is_system'] === 1) {
            partnerResponse('error', 'System partners cannot be archived.', [], 409);
        }
        if (array_sum(array_map('floatval', $balances)) > 0.000001) {
            partnerResponse('error', 'Settle this partner’s outstanding balances and advances before archiving. You can mark the partner inactive in Edit instead.', [], 409);
        }
        $run('UPDATE partners SET status = :status, deleted_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
             WHERE partner_id = :id AND cid = :cid AND deleted_at IS NULL',
            ['status'=>'inactive', 'id'=>$id, 'cid'=>$cid]);
        partnerResponse('success', 'Partner archived. Existing stock and account history is preserved.');
    }

    if ($action === 'payment') {
        $idRaw = $input('partner_id');
        if (!preg_match('/^\d+$/', $idRaw)) partnerResponse('error', 'Invalid partner selection.', [], 422);
        $id = (int)$idRaw;
        $account = $input('account_type');
        $amountRaw = $input('amount');
        $note = $input('notes');
        if (!in_array($account, ['customer', 'supplier'], true) || !apiValidMoney($amountRaw) || (float)$amountRaw <= 0) {
            partnerResponse('error', 'Choose an account and enter a payment amount greater than zero.', [], 422);
        }
        if (strlen($note) > 300) partnerResponse('error', 'Payment notes must not exceed 300 characters.', [], 422);
        $amount = (float)$amountRaw;
        $db->exec('BEGIN IMMEDIATE');
        try {
            $rowResult = $run('SELECT * FROM partners WHERE partner_id = :id AND cid = :cid AND deleted_at IS NULL', ['id'=>$id, 'cid'=>$cid]);
            $partner = $rowResult->fetchArray(SQLITE3_ASSOC);
            if (!$partner) {
                throw new RuntimeException('Partner not found.');
            }
            $outstandingColumn = $account === 'customer' ? 'customer_outstanding' : 'supplier_outstanding';
            $advanceColumn = $account === 'customer' ? 'customer_advance' : 'supplier_advance';
            $due = (float)$partner[$outstandingColumn];
            $advance = (float)$partner[$advanceColumn];
            $settled = min($due, $amount);
            $newDue = $due - $settled;
            $newAdvance = $advance + ($amount - $settled);
            $run("UPDATE partners SET {$outstandingColumn} = :due, {$advanceColumn} = :advance,
                    updated_at = CURRENT_TIMESTAMP WHERE partner_id = :id AND cid = :cid",
                ['due'=>$newDue, 'advance'=>$newAdvance, 'id'=>$id, 'cid'=>$cid]);
            $run("INSERT INTO partner_ledger (partner_id, cid, account_type, entry_type, amount, balance_effect, note)
                  VALUES (:partner_id, :cid, :account, 'payment', :amount, :balance_effect, :note)",
                ['partner_id'=>$id, 'cid'=>$cid, 'account'=>$account, 'amount'=>$amount, 'balance_effect'=>-$amount, 'note'=>$note ?: null]);
            $db->exec('COMMIT');
        } catch (Throwable $error) {
            $db->exec('ROLLBACK');
            throw $error;
        }
        partnerResponse('success', 'Payment recorded. Any amount above the outstanding balance is held as an advance.');
    }

    partnerResponse('error', 'Unknown partner action.', [], 400);
} catch (Throwable $error) {
    error_log('apiPartners.php: ' . $error->getMessage());
    partnerResponse('error', 'The partners service encountered a server error.', [], 500);
}
