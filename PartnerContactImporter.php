<?php
declare(strict_types=1);

/** Validates and imports contact rows without changing existing partner records. */
final class PartnerContactImporter
{
    public function __construct(private SQLite3 $db, private int $cid)
    {
    }

    public function preview(array $contacts): array
    {
        if ($this->cid < 1) throw new InvalidArgumentException('A business account is required.');
        if (count($contacts) < 1 || count($contacts) > 5000) {
            throw new InvalidArgumentException('Choose a contact file containing between 1 and 5,000 contacts.');
        }

        $seenNames = [];
        $seenEmails = [];
        $rows = [];
        foreach (array_values($contacts) as $index => $contact) {
            $line = $index + 2;
            $row = is_array($contact) ? $contact : [];
            $name = trim((string)($row['name'] ?? ''));
            $phone = trim((string)($row['phone'] ?? ''));
            $email = strtolower(trim((string)($row['email'] ?? '')));
            $address = trim((string)($row['address'] ?? ''));
            $role = strtolower(trim((string)($row['role'] ?? '')));
            $notes = trim((string)($row['notes'] ?? ''));
            if ($role === '') $role = 'both';

            $errors = [];
            if ($name === '' || self::textLength($name) > 120) $errors[] = 'Name must be 1 to 120 characters.';
            if (strlen($phone) > 40) $errors[] = 'Phone must be 40 characters or fewer.';
            if ($email !== '' && (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) $errors[] = 'Email address is invalid or too long.';
            if (strlen($address) > 500) $errors[] = 'Address must be 500 characters or fewer.';
            if (strlen($notes) > 1000) $errors[] = 'Notes must be 1000 characters or fewer.';
            if (!in_array($role, ['supplier', 'customer', 'both'], true)) $errors[] = 'Role must be supplier, customer, or both.';

            $status = 'ready';
            $reason = '';
            if ($errors) {
                $status = 'invalid';
                $reason = implode(' ', $errors);
            } else {
                $nameKey = self::key($name);
                $emailKey = $email === '' ? '' : self::key($email);
                if (isset($seenNames[$nameKey])) {
                    $status = 'conflict';
                    $reason = 'Duplicate name in this file (row ' . $seenNames[$nameKey] . ').';
                } elseif ($emailKey !== '' && isset($seenEmails[$emailKey])) {
                    $status = 'conflict';
                    $reason = 'Duplicate email in this file (row ' . $seenEmails[$emailKey] . ').';
                } else {
                    $seenNames[$nameKey] = $line;
                    if ($emailKey !== '') $seenEmails[$emailKey] = $line;
                    $existingName = apiFetchOne($this->db,
                        'SELECT partner_id, deleted_at FROM partners WHERE cid = :cid AND name = :name COLLATE NOCASE LIMIT 1',
                        ['cid'=>$this->cid, 'name'=>$name]);
                    $existingEmail = $email === '' ? null : apiFetchOne($this->db,
                        'SELECT partner_id, name FROM partners WHERE cid = :cid AND email = :email COLLATE NOCASE AND deleted_at IS NULL LIMIT 1',
                        ['cid'=>$this->cid, 'email'=>$email]);
                    if ($existingEmail && (!$existingName || (int)$existingEmail['partner_id'] !== (int)$existingName['partner_id'])) {
                        $status = 'conflict';
                        $reason = 'This email is already used by ' . $existingEmail['name'] . '.';
                    } elseif ($existingName && $existingName['deleted_at'] === null) {
                        $status = 'existing';
                        $reason = 'A partner with this name already exists; existing data will be preserved.';
                    } elseif ($existingName) {
                        $status = 'conflict';
                        $reason = 'An archived partner has this name; restore it from Partners instead.';
                    }
                }
            }
            $rows[] = ['row'=>$line, 'name'=>$name, 'phone'=>$phone, 'email'=>$email, 'address'=>$address,
                'role'=>$role, 'notes'=>$notes, 'status'=>$status, 'reason'=>$reason];
        }

        $counts = ['ready'=>0, 'existing'=>0, 'conflict'=>0, 'invalid'=>0];
        foreach ($rows as $row) $counts[$row['status']]++;
        return ['rows'=>$rows, 'counts'=>$counts, 'total'=>count($rows)];
    }

    public function import(array $contacts): array
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            // Recheck while holding the write lock so a concurrent request cannot turn
            // a clean preview into an overwrite or duplicate.
            $report = $this->preview($contacts);
            $created = 0;
            foreach ($report['rows'] as &$row) {
                if ($row['status'] !== 'ready') continue;
                apiQuery($this->db, 'INSERT INTO partners
                    (cid, name, phone, email, address, role, status, notes)
                    VALUES (:cid, :name, :phone, :email, :address, :role, :status, :notes)', [
                    'cid'=>$this->cid, 'name'=>$row['name'], 'phone'=>$row['phone'] ?: null,
                    'email'=>$row['email'] ?: null, 'address'=>$row['address'] ?: null,
                    'role'=>$row['role'], 'status'=>'active', 'notes'=>$row['notes'] ?: null,
                ]);
                $created++;
                $row['status'] = 'imported';
                $row['reason'] = 'Created successfully.';
            }
            unset($row);
            $report['counts']['ready'] -= $created;
            $report['counts']['imported'] = $created;
            $this->db->exec('COMMIT');
            $report['created'] = $created;
            return $report;
        } catch (Throwable $error) {
            $this->db->exec('ROLLBACK');
            throw $error;
        }
    }

    /** Return exportable, non-system partners from this business account only. */
    public function exportContacts(): array
    {
        $result = apiQuery($this->db, 'SELECT name, phone, email, address, role, notes
            FROM partners WHERE cid = :cid AND deleted_at IS NULL AND is_system = 0
            ORDER BY name COLLATE NOCASE', ['cid'=>$this->cid]);
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = [
                'name'=>(string)$row['name'], 'phone'=>(string)($row['phone'] ?? ''),
                'email'=>(string)($row['email'] ?? ''), 'address'=>(string)($row['address'] ?? ''),
                'role'=>(string)$row['role'], 'notes'=>(string)($row['notes'] ?? ''),
            ];
        }
        return $rows;
    }

    private static function key(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
