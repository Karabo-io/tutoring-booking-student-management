<?php
    require_once __DIR__ . '/config.php';

    $_SESSION = [];
    if(ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 4200, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();
    redirect('../html/login.html?msg=logged_out');
