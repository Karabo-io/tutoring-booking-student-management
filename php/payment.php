<?php
    require_once __DIR__ . '/config.php';
    require_role(['student']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('programmes.php');
    }
    check_csrf();

    $pdo = db();
    $studentId = (int)$_SESSION['user']['id'];

    $programmeId = filter_var($_POST['programme_id'] ?? '', FILTER_VALIDATE_INT);
    $cardName    = post_str('card_name');
    $cardNumber  = str_replace(' ', '', post_str('card_number'));
    $expiry      = post_str('expiry');
    $cvv         = post_str('cvv');

    $errors = [];
    if ($programmeId === false || $programmeId < 1) {
        $errors[] = 'Please select a programme.';
    }

    if (!preg_match('/^[\p{L}\' .-]{2,60}$/u', $cardName)) {
        $errors[] = 'Enter the name on the card.';
    }

    if (!preg_match('/^[0-9]{16}$/', $cardNumber)) {
        $errors[] = 'Card number must be exactly 16 digits.';
    }

    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry, $m)) {
        $errors[] = 'Expiry date must be in the format MM/YY.';
    } else {
        $expiryValue = (2000 + (int)$m[2]) * 12 + (int)$m[1];
        $nowValue    = (int)date('Y') * 12 + (int)date('n');
        if ($expiryValue < $nowValue) {
            $errors[] = 'This card has expired.';
        }
    }

    if (!preg_match('/^[0-9]{3}$/', $cvv)) {
        $errors[] = 'CVV must be 3 digits.';
    }

    if (count($errors) > 0) {
        flash('error', implode(' ', $errors));
        redirect('programmes.php');
    }

    try {
        $pdo->beginTransaction();

        $p = $pdo->prepare('SELECT programme_id, programme_name, fee FROM programmes WHERE programme_id = ?');
        $p->execute([$programmeId]);
        $programme = $p->fetch();
        if (!$programme) {
            $pdo->rollBack();
            flash('error', 'That programme does not exist.');
            redirect('programmes.php');
        }

        $en = $pdo->prepare('SELECT enrolment_id, status FROM enrolments WHERE student_id = ? AND programme_id = ? FOR UPDATE');
        $en->execute([$studentId, $programmeId]);
        $enrolment = $en->fetch();

        if ($enrolment && $enrolment['status'] === 'Active') {
            $pdo->rollBack();
            flash('error', 'You are already enrolled in that programme.');
            redirect('programmes.php');
        }

        if ($enrolment) {
            $enrolmentId = (int)$enrolment['enrolment_id'];
            $pdo->prepare("UPDATE enrolments SET status = 'Pending' WHERE enrolment_id = ?")->execute([$enrolmentId]);
        } else {
            $pdo->prepare("INSERT INTO enrolments (student_id, programme_id, status) VALUES (?, ?, 'Pending')")
                ->execute([$studentId, $programmeId]);
            $enrolmentId = (int)$pdo->lastInsertId();
        }

        $last4  = substr($cardNumber, -4);
        $status = ($last4 === '0000') ? 'Failed' : 'Completed';
        $ref    = 'TXN' . date('YmdHis') . strtoupper(bin2hex(random_bytes(2)));

        $pay = $pdo->prepare(
            'INSERT INTO payments (enrolment_id, amount_paid, payment_status, transaction_ref, card_last4)
            VALUES (?, ?, ?, ?, ?)'
        );
        $pay->execute([$enrolmentId, $programme['fee'], $status, $ref, $last4]);

        if ($status === 'Completed') {
            // payment confirmed -> enrolment becomes Active automatically
            $pdo->prepare("UPDATE enrolments SET status = 'Active', enrolled_at = NOW() WHERE enrolment_id = ?")
                ->execute([$enrolmentId]);
            notify($pdo, 'Payment received (ref ' . $ref . '). You are now enrolled in ' . $programme['programme_name'] . '.',
                null, $studentId, null, null);
        }

        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('payment failed: ' . $ex->getMessage());
        flash('error', 'The payment could not be processed. Please try again.');
        redirect('programmes.php');
    }

    if ($status === 'Completed') {
        flash('success', 'Payment successful (reference ' . $ref . '). You are now enrolled in ' . $programme['programme_name'] . '. You can book your sessions.');
        redirect('student_dashboard.php');
    }

    flash('error', 'Your card was declined. You have not been enrolled. Please try a different card.');
    redirect('programmes.php');

?>