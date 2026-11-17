<?php
    require_once __DIR__ . '/config.php';
    require_role(['tutor']);

    if($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('tutor_dashboard.php');
    }
    check_csrf();

    $pdo = db();
    $tutorId = (int)['user']['id'];
    $action = post_str('action');

    try{
        if($action === 'add') {
            $day = post_str('day_of_week');
            $start = post_str('start_time');
            $end = post_str('end_time');
            
            $errors = [];
            if (!in_array($day, weekdays(), true)) {
                $errors[] = 'Choose a day from Monday to Friday.';
            }
            
            if (!preg_match('/^(0[89]|1[0-6]):00$/', $start)) {
                $errors[] = 'Start time must be a whole hour between 08:00 and 16:00.';
            }

            if (!preg_match('/^(09|1[0-7]):00$/', $end)) {
                $errors[] = 'End time must be a whole hour between 09:00 and 17:00.';
            }

            if (count($errors) === 0 && $end <= $start) {
                $errors[] = 'End time must be after the start time.';
            }

            if (count($errors) > 0) {
                flash('error', implode(' ', $errors));
                redirect('tutor_dashboard.php');
            }

            
            $chk = $pdo->prepare(
                'SELECT COUNT(*) FROM tutor_availability
                WHERE tutor_id = ? AND day_of_week = ? AND start_time < ? AND end_time > ?'
            );
            $chk->execute([$tutorId, $day, $end . ':00', $start . ':00']);
            if ((int)$chk->fetchColumn() > 0) {
                flash('error', 'That time overlaps with availability you already added for ' . $day . '.');
                redirect('tutor_dashboard.php');
            }

            $ins = $pdo->prepare('INSERT INTO tutor_availability (tutor_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)');
            $ins->execute([$tutorId, $day, $start . ':00', $end . ':00']);
            flash('success', "Availability added: $day $start - $end.");

        } elseif ($action === 'delete') {
            $id = filter_var($_POST['availability_id'] ?? '', FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                flash('error', 'Invalid request.');
                redirect('tutor_dashboard.php');
            }

            // the window must belong to this tutor
            $own = $pdo->prepare('SELECT COUNT(*) FROM tutor_availability WHERE availability_id = ? AND tutor_id = ?');
            $own->execute([$id, $tutorId]);
            if ((int)$own->fetchColumn() === 0) {
                http_response_code(403);
                exit('Access denied. That availability does not belong to you.');
            }

            // cannot remove a window that still has upcoming sessions inside it
            $busy = $pdo->prepare(
                "SELECT COUNT(*) FROM bookings b
                JOIN tutor_availability a ON a.availability_id = ? AND a.tutor_id = b.tutor_id
                WHERE b.status IN ('Requested', 'Confirmed')
                AND b.session_date >= CURDATE()
                AND DAYNAME(b.session_date) = a.day_of_week
                AND b.start_time >= a.start_time
                AND ADDTIME(b.start_time, '01:00:00') <= a.end_time"
            );
            $busy->execute([$id]);
            if ((int)$busy->fetchColumn() > 0) {
                flash('error', 'You still have upcoming sessions in that time window. Cancel or complete them first.');
                redirect('tutor_dashboard.php');
            }

            $del = $pdo->prepare('DELETE FROM tutor_availability WHERE availability_id = ? AND tutor_id = ?');
            $del->execute([$id, $tutorId]);
            flash('success', 'Availability removed.');

        } else {
            flash('error', 'Invalid request.');
        }
    } catch (Throwable $ex) {
        error_log('availability failed: ' . $ex->getMessage());
        flash('error', 'Your availability could not be updated. Please try again.');
    }

    redirect('tutor_dashboard.php');
?>