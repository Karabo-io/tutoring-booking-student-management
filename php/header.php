<?php

    $navUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> <?= e($pageTitle ?? 'Tutoring System') ?></title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header class="topbar">
        <h1>Math &amp; Data Science Tutoring</h1>
        <nav>
            <?php if ($navUser !==  null && $navUser['role'] === 'student'): ?>
                <a href="student_dashboard.php">My Dashboard</a>
                <a href="programmes.php">Programmes &amp; Payment</a>
                <a href="book_session.php">Book a Session</a>
            <?php elseif ($navUser !== null && $navUser['role'] === 'tutor'): ?>
                <a href="tutor_dashboard.php">Tutor Dashboard</a>
            <?php elseif ($navUser !== null && $navUser['role'] === 'admin'): ?>
                <a href="admin_dashboard.php">Admin Dashboard</a>
            <?php endif; ?>
            <?php if ($navUser !== null): ?>
                <a href="logout.php">Logout (<?= e($navUser['name']) ?>)</a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="container">
    <?= show_flash() ?>