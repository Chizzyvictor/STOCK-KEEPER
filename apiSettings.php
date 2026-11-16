<?php
declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/ProductCatalogueInstaller.php';
require_once __DIR__ . '/PartnerContactImporter.php';


function settingsRespond(string $status, string $text = '', array $data = [], int $code = 200): void
{
    apiRespond($status, $text, $data, $code);
}

$cid = (int) $_SESSION['cid'];

try {
    $db = stockKeeperOpenDatabase();
    $db->exec("CREATE TABLE IF NOT EXISTS company_settings (
        cid INTEGER NOT NULL,
        setting_key TEXT NOT NULL,
        setting_value TEXT NOT NULL,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (cid, setting_key),
        FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS product_catalogue_installations (
        installation_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        catalogue_key TEXT NOT NULL,
        catalogue_name TEXT NOT NULL,
        products_created INTEGER NOT NULL DEFAULT 0,
        products_skipped INTEGER NOT NULL DEFAULT 0,
        conflict_count INTEGER NOT NULL DEFAULT 0,
        report_json TEXT NOT NULL,
        installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE
    )");
    $db->exec('CREATE INDEX IF NOT EXISTS idx_catalogue_installations_account_time ON product_catalogue_installations(cid, installed_at DESC)');
    $action = apiInput('action');
    if ($action === 'export_contacts') {
        $importer = new PartnerContactImporter($db, $cid);
        settingsRespond('success', 'Contacts loaded for export.', ['contacts'=>$importer->exportContacts()]);
    }
    if (in_array($action, ['preview_contact_import', 'import_contacts'], true)) {
        $contacts = json_decode((string)($_POST['contacts_json'] ?? ''), true);
        if (!is_array($contacts)) settingsRespond('error', 'The contact list could not be read. Select a CSV and preview it again.', [], 422);
        $importer = new PartnerContactImporter($db, $cid);
        try {
            if ($action === 'preview_contact_import') {
                settingsRespond('success', 'Contact preview is ready.', $importer->preview($contacts));
            }
            settingsRespond('success', 'Contact import completed.', $importer->import($contacts));
        } catch (InvalidArgumentException $error) {
            settingsRespond('error', $error->getMessage(), [], 422);
        }
    }
    $run = static function (string $sql, array $params = []) use ($db): SQLite3Result {
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? SQLITE3_INTEGER : (is_float($value) ? SQLITE3_FLOAT : ($value === null ? SQLITE3_NULL : SQLITE3_TEXT));
            $stmt->bindValue(':' . ltrim((string)$key, ':'), $value, $type);
        }
        return $stmt->execute();
    };
    $saveSetting = static function (string $key, string $value) use ($run, $cid): void {
        $run('INSERT INTO company_settings (cid, setting_key, setting_value, updated_at)
              VALUES (:cid, :key, :value, CURRENT_TIMESTAMP)
              ON CONFLICT(cid, setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP',
            ['cid'=>$cid, 'key'=>$key, 'value'=>$value]);
    };
    $readSettings = static function () use ($run, $cid): array {
        $result = $run('SELECT setting_key, setting_value FROM company_settings WHERE cid = :cid', ['cid'=>$cid]);
        $values = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) $values[$row['setting_key']] = $row['setting_value'];
        return $values;
    };

    if ($action === 'install_catalogue') {
        $catalogueKey = apiInput('catalogue_key');
        $report = (new ProductCatalogueInstaller($db, $cid))->install($catalogueKey);
        settingsRespond('success', 'Product catalogue installation completed.', ['report' => $report]);
    }

    if ($action === 'catalogue_management') {
        $catalogues = [];
        $latestByCatalogue = [];
        $historyResult = $run(
            'SELECT installation_id, catalogue_key, catalogue_name, products_created,
                    products_skipped, conflict_count, report_json, installed_at
             FROM product_catalogue_installations
             WHERE cid = :cid
             ORDER BY installation_id DESC
             LIMIT 20',
            ['cid' => $cid]
        );
        $history = [];
        while ($row = $historyResult->fetchArray(SQLITE3_ASSOC)) {
            $report = json_decode((string) $row['report_json'], true);
            $row['report'] = is_array($report) ? $report : [];
            unset($row['report_json']);
            $history[] = $row;
            if (!isset($latestByCatalogue[$row['catalogue_key']])) {
                $latestByCatalogue[$row['catalogue_key']] = true;
            }
        }
        foreach (ProductCatalogueSeeder::catalogues() as $key => $catalogue) {
            $productCount = array_sum(array_map(
                static fn(array $category): int => count($category['products']),
                $catalogue['categories']
            ));
            $catalogues[] = [
                'key' => $key,
                'name' => $catalogue['name'],
                'category_count' => count($catalogue['categories']),
                'product_count' => $productCount,
                'installed' => isset($latestByCatalogue[$key]),
            ];
        }
        settingsRespond('success', 'Catalogue management loaded.', [
            'catalogues' => $catalogues,
            'history' => $history,
        ]);
    }

    if ($action === 'load') {
        $company = $run('SELECT cName, cEmail, regDate, cLogo, question FROM company WHERE cid = :cid', ['cid'=>$cid])->fetchArray(SQLITE3_ASSOC);
        if (!$company) settingsRespond('error', 'Business account not found.', [], 404);
        $company['settings'] = $readSettings();
        settingsRespond('success', 'Settings loaded.', $company);
    }

    if ($action === 'save_business') {
        $name = apiInput('business_name');
        $email = strtolower(apiInput('business_email'));
        if ($name === '' || strlen($name) > 480) settingsRespond('error', 'Business name is required and must be 120 characters or fewer.');
        if (!apiValidEmail($email)) settingsRespond('error', 'Enter a valid business email address.');
        $duplicateName = $run('SELECT cid FROM company WHERE LOWER(cName) = LOWER(:name) AND cid <> :cid LIMIT 1', ['name'=>$name, 'cid'=>$cid])->fetchArray(SQLITE3_ASSOC);
        if ($duplicateName) settingsRespond('error', 'That business name is already in use.');
        $existing = $run('SELECT cid FROM company WHERE LOWER(cEmail) = LOWER(:email) AND cid <> :cid LIMIT 1', ['email'=>$email, 'cid'=>$cid])->fetchArray(SQLITE3_ASSOC);
        if ($existing) settingsRespond('error', 'That email address is already used by another account.');
        $run('UPDATE company SET cName = :name, cEmail = :email WHERE cid = :cid', ['name'=>$name, 'email'=>$email, 'cid'=>$cid]);
        settingsRespond('success', 'Business profile saved.');
    }

    if ($action === 'save_inventory') {
        $unit = trim((string)($_POST['default_unit'] ?? 'pcs'));
        $reorder = filter_var($_POST['default_reorder_level'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($unit === '' || strlen($unit) > 160) settingsRespond('error', 'Choose a valid default product unit.');
        if ($reorder === false || $reorder < 0 || $reorder > 1000000000) settingsRespond('error', 'Default reorder level must be zero or greater.');
        $saveSetting('default_unit', $unit);
        $saveSetting('default_reorder_level', (string)$reorder);
        settingsRespond('success', 'Inventory defaults saved.');
    }

    if ($action === 'save_stock_rules') {
        $saveSetting('low_stock_warning', isset($_POST['low_stock_warning']) && $_POST['low_stock_warning'] === '1' ? '1' : '0');
        $saveSetting('out_of_stock_warning', isset($_POST['out_of_stock_warning']) && $_POST['out_of_stock_warning'] === '1' ? '1' : '0');
        settingsRespond('success', 'Stock warning preferences saved.');
    }

    if ($action === 'save_appearance') {
        $theme = (string)($_POST['theme'] ?? 'system');
        $density = (string)($_POST['display_density'] ?? 'comfortable');
        if (!in_array($theme, ['system', 'light', 'dark'], true)) settingsRespond('error', 'Choose a valid theme.');
        if (!in_array($density, ['comfortable', 'compact'], true)) settingsRespond('error', 'Choose a valid display density.');
        $saveSetting('theme', $theme);
        $saveSetting('display_density', $density);
        settingsRespond('success', 'Appearance preferences saved.');
    }

    if ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $row = $run('SELECT cPass FROM company WHERE cid = :cid', ['cid'=>$cid])->fetchArray(SQLITE3_ASSOC);
        if (!$row || !password_verify($current, (string)$row['cPass'])) settingsRespond('error', 'Current password is incorrect.');
        if (!apiValidPassword($new)) settingsRespond('error', 'New password must be between 8 and 72 characters.');
        if (!hash_equals($new, $confirm)) settingsRespond('error', 'New password and confirmation do not match.');
        $run('UPDATE company SET cPass = :pass WHERE cid = :cid', ['pass'=>password_hash($new, PASSWORD_DEFAULT), 'cid'=>$cid]);
        settingsRespond('success', 'Password changed successfully.');
    }

    if ($action === 'save_security') {
        $current = (string)($_POST['current_password'] ?? '');
        $question = trim((string)($_POST['security_question'] ?? ''));
        $answer = trim((string)($_POST['security_answer'] ?? ''));
        $row = $run('SELECT cPass FROM company WHERE cid = :cid', ['cid'=>$cid])->fetchArray(SQLITE3_ASSOC);
        if (!$row || !password_verify($current, (string)$row['cPass'])) settingsRespond('error', 'Confirm your current password to update account recovery details.');
        if ($question === '' || strlen($question) > 800 || strlen($answer) < 2 || strlen($answer) > 72) settingsRespond('error', 'Enter a recovery question and an answer of 2 to 72 characters.');
        $run('UPDATE company SET question = :question, answer = :answer WHERE cid = :cid', ['question'=>$question, 'answer'=>password_hash(strtolower($answer), PASSWORD_DEFAULT), 'cid'=>$cid]);
        settingsRespond('success', 'Account recovery details saved.');
    }

    if ($action === 'upload_logo') {
        if (empty($_FILES['logo']) || !is_uploaded_file($_FILES['logo']['tmp_name'])) settingsRespond('error', 'Choose an image to upload.');
        $file = $_FILES['logo'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)$file['size'] > 2 * 1024 * 1024) settingsRespond('error', 'Logo must be an image no larger than 2 MB.');
        $imageInfo = @getimagesize($file['tmp_name']);
        $mime = $imageInfo['mime'] ?? '';
        $extensions = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
        if (!isset($extensions[$mime])) settingsRespond('error', 'Use a JPG, PNG, or WebP image.');
        $directory = __DIR__ . '/Images/companyDP';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) settingsRespond('error', 'Logo storage is unavailable.', [], 500);
        $filename = 'company_' . $cid . '_' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) settingsRespond('error', 'Could not store the logo.', [], 500);
        $run('UPDATE company SET cLogo = :logo WHERE cid = :cid', ['logo'=>$filename, 'cid'=>$cid]);
        settingsRespond('success', 'Business logo updated.', ['logo'=>$filename]);
    }

    if ($action === 'diagnostics') {
        $check = $db->querySingle('PRAGMA integrity_check');
        $products = (int)$db->querySingle('SELECT COUNT(*) FROM products WHERE cid = ' . $cid);
        $ledger = (int)$db->querySingle('SELECT COUNT(*) FROM stock_ledger WHERE cid = ' . $cid);
        $partners = (int)$db->querySingle('SELECT COUNT(*) FROM partners WHERE cid = ' . $cid . ' AND deleted_at IS NULL');
        settingsRespond('success', 'Database health check completed.', ['integrity'=>$check, 'products'=>$products, 'movements'=>$ledger, 'partners'=>$partners]);
    }

    settingsRespond('error', 'Unknown settings action.', [], 400);
} catch (Throwable $e) {
    error_log('Settings API error: ' . $e->getMessage());
    if (($action ?? '') === 'install_catalogue') {
        settingsRespond('error', 'Catalogue could not be installed. No partial changes were saved.', [], 500);
    }
    settingsRespond('error', 'Settings could not be saved due to a server error.', [], 500);
}
