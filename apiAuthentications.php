<?php
define('API_AUTHENTICATION_ENDPOINT', true);
require_once __DIR__ . '/api_helpers.php';





// -------------------------
// Helper Functions
// -------------------------
function respond($status, $text = '', $extra = [], $code = 400) {
    $data = isset($extra['data']) && is_array($extra['data']) ? $extra['data'] : $extra;
    apiRespond($status, $text, $data, $status === 'success' ? 200 : $code);
}



function recovery_answer_matches(string $answer, string $stored): bool {
    $info = password_get_info($stored);
    if (!empty($info['algo'])) return strlen($answer) <= 72 && password_verify($answer, $stored);
    $normalized = strtolower(trim($answer));
    $legacyEncoded = htmlspecialchars($normalized, ENT_QUOTES, 'UTF-8');
    return hash_equals(strtolower($stored), $normalized)
        || hash_equals(strtolower($stored), strtolower($legacyEncoded));
}






// -------------------------
// Database Connection
// -------------------------
$db = stockKeeperOpenDatabase();

// -------------------------
// Timezone and Date Ranges
// -------------------------
date_default_timezone_set("Africa/Lagos");
$now        = time();
$today      = strtotime('today');
$lastMonth  = strtotime('-1 month');
$beginning  = strtotime("first day of this month");
$end        = strtotime("last day of this month");
$begin      = strtotime("first day of last month");
$ending     = strtotime("last day of last month");
$action     = $_POST['action'] ?? null;



// -------------------------
// Action Handler
// -------------------------
// === Ensure action exists ===
if (!$action) respond("error", "No action provided");




switch ($action) {

    // ---------------- LOGIN ----------------
    case 'login':
        $email = strtolower(apiTrim($_POST["email"] ?? ''));
        $pass  = (string)($_POST["password"] ?? '');
        if (!apiValidEmail($email) || $pass === '' || strlen($pass) > 200) {
            respond("error", "Invalid login credentials", [], 401);
        }

        $stmt = $db->prepare("SELECT * FROM company WHERE LOWER(cEmail) = :email");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $ret = $stmt->execute();
        $row = $ret->fetchArray(SQLITE3_ASSOC);

        if ($row && password_verify($pass, $row['cPass'])) {
            session_regenerate_id(true);
            unset($_SESSION['reset_email'], $_SESSION['can_reset'], $_SESSION['reset_expires'], $_SESSION['reset_attempts']);
            $_SESSION['isLogedin']     = true;
            $_SESSION['cid']           = $row['cid'];
            $_SESSION['last_activity'] = $now;
            $_SESSION['csrf_token']    = bin2hex(random_bytes(32));

            respond("success", "Login successful");
        } else {
            respond("error", "Invalid email or password", [], 401);
        }
        break;

    // ---------------- SIGNUP ----------------
    case 'signup':
        $cName = apiTrim($_POST["name"] ?? '');
        $email = strtolower(apiTrim($_POST["email"] ?? ''));
        $password = (string)($_POST["password"] ?? '');
        $question = apiTrim($_POST["question"] ?? '');
        $answer = strtolower(apiTrim($_POST["answer"] ?? ''));

        $allowedQuestions = [
            "what is your mothers maiden name",
            "what is your favourite colour",
            "what is your favourite game"
        ];
        $nameLength = apiTextLength($cName);
        if (preg_match('//u', $cName) !== 1 || $nameLength < 2 || $nameLength > 120 || strlen($cName) > 480 || preg_match('/[\x00-\x1F\x7F]/', $cName)) respond('error', 'Company name must be 2 to 120 characters.');
        if (!apiValidEmail($email)) respond('error', 'Enter a valid email address.');
        if (!apiValidPassword($password)) respond('error', 'Password must be between 8 and 72 characters.');
        if ((string)($_POST['agree'] ?? '') !== '1') respond('error', 'Please accept the terms and conditions to create an account.');
        if (!in_array($question, $allowedQuestions, true)) respond('error', 'Choose a valid account recovery question.');
        if (preg_match('//u', $answer) !== 1 || apiTextLength($answer) < 2 || strlen($answer) > 72) respond('error', 'Recovery answer must be between 2 and 72 characters.');
        $passHash = password_hash($password, PASSWORD_DEFAULT);


        // check if name exists
        $stmt = $db->prepare("SELECT 1 FROM company WHERE LOWER(cName) = LOWER(:cName)");
        $stmt->bindValue(':cName', $cName, SQLITE3_TEXT);
        if ($stmt->execute()->fetchArray()) {
            respond("error", "Name already exists");
        }

        // check if email exists
        $stmt = $db->prepare("SELECT 1 FROM company WHERE LOWER(cEmail) = :email");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        if ($stmt->execute()->fetchArray()) {
            respond("error", "Email already exists");
        }

        // insert new company
        $stmt = $db->prepare("INSERT INTO company (cName, cEmail, cPass, regDate, question, answer, cLogo) 
                              VALUES (:cName, :email, :pass, :regDate, :question, :answer, :cLogo)");
        $stmt->bindValue(':cName', $cName, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':pass', $passHash, SQLITE3_TEXT);
        $stmt->bindValue(':regDate', $now, SQLITE3_INTEGER);
        $stmt->bindValue(':question', $question, SQLITE3_TEXT);
        $stmt->bindValue(':answer', password_hash($answer, PASSWORD_DEFAULT), SQLITE3_TEXT);
        $stmt->bindValue(':cLogo', "logo.jpg", SQLITE3_TEXT);

        if ($stmt->execute()) {
            respond("success", "Account created successfully!");
        } else {
            respond("error", $db->lastErrorMsg());
        }
        break;

    // ---------------- LOGOUT ----------------
    case 'logout':
        session_unset();
        session_destroy();
        respond("success", "Logout successful");
        break;

    // ---------------- COMPANY LOGO ----------------
    case 'cLogo':
        $cid = $_SESSION['cid'] ?? 0;
        if (!$cid) {
            respond("success", "Not logged in", ["logo" => "logo.jpg"]);
        }

        $stmt = $db->prepare("SELECT cLogo FROM company WHERE cid = :cid");
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $ret = $stmt->execute();
        $row = $ret->fetchArray(SQLITE3_ASSOC);

        if ($row && !empty($row['cLogo'])) {
            respond("success", "Company logo loaded successfully!", ["logo" => $row["cLogo"]]);
        } else {
            respond("success", "Logo not found", ["data" => "logo.jpg"]);
        }
        break;

    // ---------------- FORGOT PASSWORD: STEP 1 ----------------
    case "requestPasswordReset":
        $email = strtolower(apiTrim($_POST["fEmail"] ?? ''));
        if (!apiValidEmail($email)) respond("error", "Enter a valid email address.");
        unset($_SESSION['reset_email'], $_SESSION['can_reset']);
        $resetExpiry = (int)($_SESSION['reset_expires'] ?? 0);
        if ($resetExpiry < $now) {
            $_SESSION['reset_attempts'] = 0;
            $_SESSION['reset_expires'] = $now + 900;
        }
        $_SESSION["reset_email"] = $email;
        respond("success", "If the account exists, enter the recovery answer you chose at sign-up.", [
            "question" => "Enter the recovery answer you chose at sign-up."
        ]);
        break;

    // ---------------- FORGOT PASSWORD: STEP 2 ----------------
    case "forgotQandA":
        if (empty($_SESSION["reset_email"]) || empty($_SESSION["reset_expires"]) || (int)$_SESSION["reset_expires"] < $now) {
            unset($_SESSION["reset_email"], $_SESSION["can_reset"], $_SESSION["reset_expires"], $_SESSION["reset_attempts"]);
            respond("error", "Session expired. Please restart reset process.");
        }
        $answer = strtolower(apiTrim($_POST["answer"] ?? ''));
        if (strlen($answer) < 2 || strlen($answer) > 800) respond("error", "Enter an answer between 2 and 200 characters.");
        if ((int)($_SESSION["reset_attempts"] ?? 0) >= 5) {
            unset($_SESSION["reset_email"], $_SESSION["can_reset"]);
            respond("error", "Too many attempts. Try password recovery again after 15 minutes.");
        }
        $_SESSION["reset_attempts"] = (int)($_SESSION["reset_attempts"] ?? 0) + 1;

        $email  = $_SESSION["reset_email"];

        $stmt = $db->prepare("SELECT cid, answer FROM company WHERE LOWER(cEmail) = :email");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $ret = $stmt->execute();
        $row = $ret->fetchArray(SQLITE3_ASSOC);

        if ($row && recovery_answer_matches($answer, (string)$row['answer'])) {
            $info = password_get_info((string)$row['answer']);
            if (empty($info['algo']) && strlen($answer) <= 72) {
                $upgrade = $db->prepare('UPDATE company SET answer = :answer WHERE cid = :cid');
                $upgrade->bindValue(':answer', password_hash($answer, PASSWORD_DEFAULT), SQLITE3_TEXT);
                $upgrade->bindValue(':cid', (int)$row['cid'], SQLITE3_INTEGER);
                $upgrade->execute();
            }
            $_SESSION["can_reset"] = true;
            respond("success", "Answer verified. You may now reset your password.");
        } else {
            respond("error", "Incorrect answer.");
        }
        break;

    // ---------------- FORGOT PASSWORD: STEP 3 ----------------
    case "resetPassword":
        if (empty($_SESSION["reset_email"]) || empty($_SESSION["can_reset"]) || empty($_SESSION["reset_expires"]) || (int)$_SESSION["reset_expires"] < $now) {
            unset($_SESSION["reset_email"], $_SESSION["can_reset"], $_SESSION["reset_expires"], $_SESSION["reset_attempts"]);
            respond("error", "Unauthorized request.");
        }

        $password = (string)($_POST["pwd"] ?? '');
        if (!apiValidPassword($password)) respond("error", "Password must be between 8 and 72 characters.");

        $email = $_SESSION["reset_email"];
        $pwd   = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("UPDATE company SET cPass = :pwd WHERE cEmail = :email");
        $stmt->bindValue(':pwd', $pwd, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);

        if ($stmt->execute()) {
            unset($_SESSION["reset_email"], $_SESSION["can_reset"], $_SESSION["reset_expires"], $_SESSION["reset_attempts"]);
            respond("success", "Password reset successful.");
        } else {
            respond("error", "Failed to reset password.");
        }
        break;

    // ---------------- DEFAULT ----------------
    default:
        respond("error", "Unknown action");
        break;
}
?>
