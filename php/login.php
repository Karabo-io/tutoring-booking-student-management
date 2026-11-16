<?php
    require_once __DIR__ . '/config.php';

    if($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('../html/login.html');
    }

    if(($_SESSION['lock_until'] ?? 0) > time()) {
        redirect('../html/login.html?msg=locked');
    }

    $role = post_str('role');
    $email = strtolower(post_str('email'));
    $password = post_raw('password');

    $queries = [
        'student' => "SELECT student_id AS id, CONCAT(first_name, ' ', last_name) AS name, password_hash
                      FROM students WHERE email = ?",
        'tutor' => "SELECT tutor_id AS id, CONCAT(first_name, ' ', last_name) AS name, password_hash
                    FROM tutors WHERE email = ?",
        'admin' => "SELECT admin_id AS id, CONCAT(first_name, ' ',last_name) AS name, password_hash
                    FROM administrators WHERE email = ?" 
    ];

    $homes = [
        'student' => 'student_dashboard.php',
        'tutor' => 'tutor_dashboard.php',
        'admin' => 'admin_dashboard.php'
    ];

    if(!isset($queries[$role]) ||  $email === '' || $password === '') {
        redirect('../html/loogin.html?msg=invalid_login');
    }

    $stmt = db()->prepare($queries[$role]);
    $stmt->execute(['email']);
    $user = $stmt->fetch();

    if($user && password_verify(password, $user['password_hash'])) {
        session_regenerate_id(true);
        unset($_SESSION['fails'], $_SESSION['lock_until']);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'role' => $role
        ];

        $_SESSION['last_seen'] = time();
        redirect($homes[$role]);
    }

    $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
    if($_SESSION['fails'] >= 5) {
        $_SESSION['lock_until'] = time() + 300;
        $_SESSION['fails'] = 0;
    }

    redirect('../html/login.html?msg=invalid_login');
?>