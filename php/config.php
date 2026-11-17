<?php
    date_default_timezone_set('Africa/Johannesburg');
    
    function load_env(string $path): array {
        $vars = [];
        if(!is_readable($path)) {
            return $vars;
        }

        foreach(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $vars[trim($key)] = trim(trim($value), "\"'");
        }
        return $vars;
    }

    $env = load_env(__DIR__ . '/../.env');

    define('DB_HOST', $env['DB_HOST']);
    define('DB_NAME', $env['DB_NAME']);
    define('DB_USER', $env['DB_USER']);
    define('DB_PASS', $env['DB_PASS']);

    // cookie settings have to be set before session_start()
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

   
    function db(): PDO {
        static $pdo = null;
        if ($pdo === null) {
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,   
                ]);
            } catch (PDOException $e) {
                error_log('DB connection failed' . $e->getMessage());
                http_response_code(500);
                exit('The system could not connect to the database.');
            }
            
        }
        return $pdo;
    }

    
    function e($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    // trimmed string from $_POST (returns '' if the field is missing or is an array)
    function post_str(string $key): string {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    // untrimmed string from $_POST, used for passwords
    function post_raw(string $key): string {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    function redirect(string $url): void {
        header('Location: ' . $url);
        exit;
    }

    // ---------- flash messages ----------
    function flash(string $type, string $message): void {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    function show_flash(): string {
        $html = '';
        foreach ($_SESSION['flash'] ?? [] as $f) {
            $class = ($f['type'] === 'success') ? 'success' : 'error';
            $html .= '<div class="alert ' . $class . '">' . e($f['message']) . '</div>';
        }
        unset($_SESSION['flash']);
        return $html;
    }

    // small standalone page used when a handler (e.g. register.php) finds invalid input
    function error_page(array $errors, string $backUrl): void {
        http_response_code(400);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Please check your details</title>';
        echo '<link rel="stylesheet" href="../css/style.css"></head><body><main class="container">';
        echo '<h2>Please fix the following</h2><ul class="alert error">';
        foreach ($errors as $err) {
            echo '<li>' . e($err) . '</li>';
        }
        echo '</ul><p><a href="' . e($backUrl) . '">Go back</a></p></main></body></html>';
        exit;
    }

    // ---------- CSRF protection ----------
    function csrf_token(): string {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
    }

    function check_csrf(): void {
        $sent  = $_POST['csrf'] ?? '';
        $token = $_SESSION['csrf'] ?? '';
        if ($token === '' || !is_string($sent) || !hash_equals($token, $sent)) {
            http_response_code(403);
            exit('Invalid or missing security token. Go back, refresh the page and try again.');
        }
    }

    // ---------- authentication / role based access control ----------
    function current_user(): ?array {
        return $_SESSION['user'] ?? null;
    }

    // call at the top of every protected page: require_role(['admin']);
    function require_role(array $roles): void {
        $user = current_user();
        if ($user === null) {
            redirect('../html/login.html?msg=login_required');
        }
        // log the user out after 30 minutes of doing nothing
        if (isset($_SESSION['last_seen']) && (time() - $_SESSION['last_seen']) > 1800) {
            $_SESSION = [];
            session_destroy();
            redirect('../html/login.html?msg=timeout');
        }
        $_SESSION['last_seen'] = time();

        if (!in_array($user['role'], $roles, true)) {
            http_response_code(403);
            exit('Access denied. Your account is not allowed to open this page.');
        }
    }

    // ---------- validation ----------
    function validate_person(string $first, string $last, string $email, string $phone): array {
        $errors = [];
        if (!preg_match('/^[\p{L}\' -]{2,50}$/u', $first)) {
            $errors[] = 'First name must be 2 to 50 letters.';
        }
        if (!preg_match('/^[\p{L}\' -]{2,50}$/u', $last)) {
            $errors[] = 'Last name must be 2 to 50 letters.';
        }
        if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!preg_match('/^0[0-9]{9}$/', $phone)) {
            $errors[] = 'Phone number must be 10 digits and start with 0.';
        }
        return $errors;
    }

    function validate_password(string $pw): array {
        $errors = [];
        if (strlen($pw) < 8 || !preg_match('/[A-Z]/', $pw) || !preg_match('/[a-z]/', $pw) || !preg_match('/[0-9]/', $pw)) {
            $errors[] = 'Password must be at least 8 characters and contain an uppercase letter, a lowercase letter and a number.';
        }
        if (strlen($pw) > 72) {   // bcrypt only uses the first 72 bytes
            $errors[] = 'Password may not be longer than 72 characters.';
        }
        return $errors;
    }

    // ---------- business constants ----------
    function weekdays(): array {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    }

    // one hour sessions, the last one starts at 16:00 and ends at 17:00
    function time_slots(): array {
        return ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];
    }

    function session_label(string $date, string $time): string {
        return date('D, d M Y', strtotime($date)) . ' at ' . substr($time, 0, 5);
    }

    // ---------- notifications ----------
    function notify(PDO $pdo, string $message, ?int $bookingId, ?int $studentId, ?int $tutorId, ?int $adminId): void {
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (booking_id, student_id, tutor_id, admin_id, message) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$bookingId, $studentId, $tutorId, $adminId, $message]);
    }

    function notify_admins(PDO $pdo, string $message, ?int $bookingId): void {
        $ids = $pdo->query('SELECT admin_id FROM administrators')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            notify($pdo, $message, $bookingId, null, null, (int)$id);
        }
    }

?>