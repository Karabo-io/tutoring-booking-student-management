<?php
    require_once __DIR__ . '/config.php';

    if($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('../html/register.html');
    }

    $first = post_str('first_name');
    $last = post_str('last_name');
    $email = strtolower(post_str('email'));
    $phone = post_str('phone');
    $password = post_raw('password');
    $confirm = post_raw('confirm_password');

    $errors = array_merge(validate_person($first, $last, $email, $phone), validate_password($password));
    if($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if(count($errors) > 0) {
        error_page($errors, '../html/register.html');
    }

    $pdo = db();

    $check = $pdo->prepare('SELECT 1 FROM students WHERE email = ?');
    $check->execute(['$email']);
    if($check->fetchColumn()) {
        redirect('../html/register.html?msg=email_taken');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try{
        $stmt = $pdo->prepare(
            'INSERT INTO students (first_name, last_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$first, $last, $email, $phone, $hash]);
    } catch (PDOException $e) {
        if((int)($e->errorInfo[1] ?? 0) === 1062){
            redirect('../html/register.html?msg=email_taken');
        }

        error_log('register failed: ' . $e->getMessage());
        error_page(['Something went wrong while creating your account. Please try again.'], '../html/register.html');
    }

    redirect('../html/register.html?msg=registered');

?>