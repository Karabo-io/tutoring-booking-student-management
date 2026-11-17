<?php
    require_once __DIR__ . '/config.php';
    require_role(['student', 'tutor', 'admin']);

    $user = current_user();
    $role = $user['role'];
    $uid  = (int)$user['id'];

    $homes = [
        'student' => 'student_dashboard.php',
        'tutor' => 'tutor_dashboard.php',
        'admin' => 'admin_dashboard.php?section=bookings',
    ];
    $home = $homes[$role];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect($home);
    }
    check_csrf();

    $action = post_str('action');
    $bookingId = filter_var($_POST['booking_id'] ?? '', FILTER_VALIDATE_INT);

    if ($bookingId === false || $bookingId < 1 || !in_array($action, ['cancel', 'complete'], true)) {
        flash('error', 'Invalid request.');
        redirect($home);
    }

    $pdo = db();

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare(
            "SELECT b.booking_id, b.tutor_id, b.session_date, b.start_time, b.status,
                    e.student_id, p.programme_name
            FROM bookings b
            JOIN enrolments e ON e.enrolment_id = b.enrolment_id
            JOIN programmes p ON p.programme_id = e.programme_id
            WHERE b.booking_id = ? FOR UPDATE"
        );
        $st->execute([$bookingId]);
        $b = $st->fetch();

        if (!$b) {
            $pdo->rollBack();
            flash('error', 'That booking does not exist.');
            redirect($home);
        }

        $allowed = ($role === 'admin')
            || ($role === 'student' && (int)$b['student_id'] === $uid)
            || ($role === 'tutor' && (int)$b['tutor_id'] === $uid);
        if (!$allowed) {
            $pdo->rollBack();
            http_response_code(403);
            exit('Access denied. This booking does not belong to you.');
        }

        $when      = session_label($b['session_date'], $b['start_time']);
        $inFuture  = strtotime($b['session_date'] . ' ' . $b['start_time']) > time();
        $studentId = (int)$b['student_id'];
        $tutorId   = ($b['tutor_id'] === null) ? null : (int)$b['tutor_id'];
        $by        = $user['name'] . ' (' . $role . ')';

        if ($action === 'cancel') {
            if (!in_array($b['status'], ['Requested', 'Confirmed'], true)) {
                $pdo->rollBack();
                flash('error', 'Only requested or confirmed sessions can be cancelled.');
                redirect($home);
            }
            if (!$inFuture && $role !== 'admin') {
                $pdo->rollBack();
                flash('error', 'A session that has already started cannot be cancelled.');
                redirect($home);
            }

            $pdo->prepare("UPDATE bookings SET status = 'Cancelled' WHERE booking_id = ?")->execute([$bookingId]);

            notify($pdo, "Session on $when was cancelled by $by.", $bookingId, $studentId, null, null);
            if ($tutorId !== null) {
                notify($pdo, "Session on $when was cancelled by $by.", $bookingId, null, $tutorId, null);
            }
            notify_admins($pdo, "Booking #$bookingId ($when) was cancelled by $by.", $bookingId);
            $msg = 'The session has been cancelled.';
        } else {
            // complete
            if ($role === 'student') {
                $pdo->rollBack();
                http_response_code(403);
                exit('Access denied. Students cannot mark sessions as completed.');
            }
            if ($b['status'] !== 'Confirmed') {
                $pdo->rollBack();
                flash('error', 'Only confirmed sessions can be marked as completed.');
                redirect($home);
            }
            if ($b['session_date'] > date('Y-m-d')) {
                $pdo->rollBack();
                flash('error', 'A session can only be completed on or after its date.');
                redirect($home);
            }

            $pdo->prepare("UPDATE bookings SET status = 'Completed' WHERE booking_id = ?")->execute([$bookingId]);

            notify($pdo, "Your session on $when was marked as completed.", $bookingId, $studentId, null, null);
            notify_admins($pdo, "Booking #$bookingId ($when) was marked as completed by $by.", $bookingId);
            $msg = 'The session has been marked as completed.';
        }

        $pdo->commit();
        flash('success', $msg);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('booking action failed: ' . $ex->getMessage());
        flash('error', 'The booking could not be updated. Please try again.');
    }

    redirect($home);
