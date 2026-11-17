<?php
    require_once __DIR__ . '/booking_logic.php';
    require_role(['admin']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('admin_dashboard.php');
    }
    check_csrf();

    $pdo = db();
    $action = post_str('action');

    $sectionOf = [
        'add_student' => 'students', 'update_student' => 'students', 'delete_student' => 'students',
        'add_tutor' => 'tutors', 'update_tutor' => 'tutors', 'delete_tutor' => 'tutors',
        'add_programme' => 'programmes', 'update_programme' => 'programmes', 'delete_programme' => 'programmes',
        'reassign_booking' => 'bookings', 'delete_booking' => 'bookings',
    ];
    if (!isset($sectionOf[$action])) {
        flash('error', 'Unknown action.');
        redirect('admin_dashboard.php');
    }
    $back = 'admin_dashboard.php?section=' . $sectionOf[$action];

    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

    function picked_subjects(PDO $pdo): array
    {
        $valid = $pdo->query('SELECT subject_id FROM subjects')->fetchAll(PDO::FETCH_COLUMN);
        $picked = [];
        $raw = $_POST['subjects'] ?? [];
        if (is_array($raw)) {
            foreach ($raw as $s) {
                $n = filter_var($s, FILTER_VALIDATE_INT);
                if ($n !== false && in_array($n, array_map('intval', $valid), true)) {
                    $picked[$n] = $n;
                }
            }
        }
        return array_values($picked);
    }

    try {
        switch ($action) {

            case 'add_student':
                $first = post_str('first_name');
                $last = post_str('last_name');
                $email = strtolower(post_str('email'));
                $phone = post_str('phone');
                $pw = post_raw('password');
                $errors = array_merge(validate_person($first, $last, $email, $phone), validate_password($pw));
                if (count($errors) > 0) {
                    flash('error', implode(' ', $errors));
                    break;
                }
                $pdo->prepare('INSERT INTO students (first_name, last_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$first, $last, $email, $phone, password_hash($pw, PASSWORD_DEFAULT)]);
                flash('success', 'Student added.');
                break;

            case 'update_student':
                $first = post_str('first_name');
                $last  = post_str('last_name');
                $email = strtolower(post_str('email'));
                $phone = post_str('phone');
                $pw    = post_raw('password');           
                $errors = validate_person($first, $last, $email, $phone);
                if ($pw !== '') {
                    $errors = array_merge($errors, validate_password($pw));
                }
                if ($id === false || $id < 1) {
                    $errors[] = 'Invalid student.';
                }
                if (count($errors) > 0) {
                    flash('error', implode(' ', $errors));
                    break;
                }
                if ($pw !== '') {
                    $pdo->prepare('UPDATE students SET first_name = ?, last_name = ?, email = ?, phone = ?, password_hash = ? WHERE student_id = ?')
                        ->execute([$first, $last, $email, $phone, password_hash($pw, PASSWORD_DEFAULT), $id]);
                } else {
                    $pdo->prepare('UPDATE students SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE student_id = ?')
                        ->execute([$first, $last, $email, $phone, $id]);
                }
                flash('success', 'Student updated.');
                break;

            case 'delete_student':
                if ($id === false || $id < 1) {
                    flash('error', 'Invalid student.');
                    break;
                }
                $del = $pdo->prepare('DELETE FROM students WHERE student_id = ?');
                $del->execute([$id]);
                flash($del->rowCount() > 0 ? 'success' : 'error', $del->rowCount() > 0 ? 'Student deleted.' : 'Student not found.');
                break;

            case 'add_tutor':
                $first = post_str('first_name');
                $last = post_str('last_name');
                $email = strtolower(post_str('email'));
                $phone = post_str('phone');
                $pw = post_raw('password');
                $subjectIds = picked_subjects($pdo);
                $errors = array_merge(validate_person($first, $last, $email, $phone), validate_password($pw));
                if (count($subjectIds) === 0) {
                    $errors[] = 'Select at least one subject for the tutor.';
                }
                if (count($errors) > 0) {
                    flash('error', implode(' ', $errors));
                    break;
                }
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO tutors (first_name, last_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$first, $last, $email, $phone, password_hash($pw, PASSWORD_DEFAULT)]);
                $newId = (int)$pdo->lastInsertId();
                $link = $pdo->prepare('INSERT INTO tutor_subjects (tutor_id, subject_id) VALUES (?, ?)');
                foreach ($subjectIds as $sid) {
                    $link->execute([$newId, $sid]);
                }
                $pdo->commit();
                flash('success', 'Tutor added.');
                break;

            case 'update_tutor':
                $first = post_str('first_name');
                $last = post_str('last_name');
                $email = strtolower(post_str('email'));
                $phone = post_str('phone');
                $pw = post_raw('password');
                $subjectIds = picked_subjects($pdo);
                $errors = validate_person($first, $last, $email, $phone);
                if ($pw !== '') {
                    $errors = array_merge($errors, validate_password($pw));
                }
                if (count($subjectIds) === 0) {
                    $errors[] = 'Select at least one subject for the tutor.';
                }
                if ($id === false || $id < 1) {
                    $errors[] = 'Invalid tutor.';
                }
                if (count($errors) > 0) {
                    flash('error', implode(' ', $errors));
                    break;
                }
                $pdo->beginTransaction();
                if ($pw !== '') {
                    $pdo->prepare('UPDATE tutors SET first_name = ?, last_name = ?, email = ?, phone = ?, password_hash = ? WHERE tutor_id = ?')
                        ->execute([$first, $last, $email, $phone, password_hash($pw, PASSWORD_DEFAULT), $id]);
                } else {
                    $pdo->prepare('UPDATE tutors SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE tutor_id = ?')
                        ->execute([$first, $last, $email, $phone, $id]);
                }
                $pdo->prepare('DELETE FROM tutor_subjects WHERE tutor_id = ?')->execute([$id]);
                $link = $pdo->prepare('INSERT INTO tutor_subjects (tutor_id, subject_id) VALUES (?, ?)');
                foreach ($subjectIds as $sid) {
                    $link->execute([$id, $sid]);
                }
                $pdo->commit();
                flash('success', 'Tutor updated.');
                break;

            case 'delete_tutor':
                if ($id === false || $id < 1) {
                    flash('error', 'Invalid tutor.');
                    break;
                }
                $del = $pdo->prepare('DELETE FROM tutors WHERE tutor_id = ?');
                $del->execute([$id]);
                flash($del->rowCount() > 0 ? 'success' : 'error', $del->rowCount() > 0 ? 'Tutor deleted.' : 'Tutor not found.');
                break;

            case 'add_programme':
            case 'update_programme':
                $name  = post_str('programme_name');
                $subj  = filter_var($_POST['subject_id'] ?? '', FILTER_VALIDATE_INT);
                $desc  = post_str('description');
                $fee   = filter_var($_POST['fee'] ?? '', FILTER_VALIDATE_FLOAT);
                $weeks = filter_var($_POST['duration_weeks'] ?? '', FILTER_VALIDATE_INT);

                $errors = [];
                if (strlen($name) < 3 || strlen($name) > 100) {
                    $errors[] = 'Programme name must be 3 to 100 characters.';
                }
                $sc = $pdo->prepare('SELECT COUNT(*) FROM subjects WHERE subject_id = ?');
                $sc->execute([($subj === false) ? 0 : $subj]);
                if ((int)$sc->fetchColumn() === 0) {
                    $errors[] = 'Choose a valid subject.';
                }
                if (strlen($desc) > 255) {
                    $errors[] = 'Description may not be longer than 255 characters.';
                }
                if ($fee === false || $fee <= 0 || $fee > 99999) {
                    $errors[] = 'Fee must be greater than 0 and less than 100000.';
                }
                if ($weeks === false || $weeks < 1 || $weeks > 52) {
                    $errors[] = 'Duration must be between 1 and 52 weeks.';
                }
                if ($action === 'update_programme' && ($id === false || $id < 1)) {
                    $errors[] = 'Invalid programme.';
                }
                if (count($errors) > 0) {
                    flash('error', implode(' ', $errors));
                    break;
                }
                $feeText = number_format((float)$fee, 2, '.', '');
                $descVal = ($desc === '') ? null : $desc;
                if ($action === 'add_programme') {
                    $pdo->prepare('INSERT INTO programmes (subject_id, programme_name, description, fee, duration_weeks) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$subj, $name, $descVal, $feeText, $weeks]);
                    flash('success', 'Programme added.');
                } else {
                    $pdo->prepare('UPDATE programmes SET subject_id = ?, programme_name = ?, description = ?, fee = ?, duration_weeks = ? WHERE programme_id = ?')
                        ->execute([$subj, $name, $descVal, $feeText, $weeks, $id]);
                    flash('success', 'Programme updated.');
                }
                break;

            case 'delete_programme':
                if ($id === false || $id < 1) {
                    flash('error', 'Invalid programme.');
                    break;
                }
                $del = $pdo->prepare('DELETE FROM programmes WHERE programme_id = ?');
                $del->execute([$id]);
                flash($del->rowCount() > 0 ? 'success' : 'error', $del->rowCount() > 0 ? 'Programme deleted.' : 'Programme not found.');
                break;

            case 'reassign_booking':
                $newTutor = filter_var($_POST['tutor_id'] ?? '', FILTER_VALIDATE_INT);
                if ($id === false || $id < 1 || $newTutor === false || $newTutor < 1) {
                    flash('error', 'Choose a booking and a tutor.');
                    break;
                }
                $pdo->beginTransaction();
                $pdo->query('SELECT tutor_id FROM tutors FOR UPDATE')->fetchAll();

                $st = $pdo->prepare(
                    "SELECT b.booking_id, b.tutor_id, b.session_date, b.start_time, b.status,
                            e.student_id, p.subject_id
                    FROM bookings b
                    JOIN enrolments e ON e.enrolment_id = b.enrolment_id
                    JOIN programmes p ON p.programme_id = e.programme_id
                    WHERE b.booking_id = ?"
                );
                $st->execute([$id]);
                $b = $st->fetch();

                if (!$b) {
                    $pdo->rollBack();
                    flash('error', 'Booking not found.');
                    break;
                }
                if (!in_array($b['status'], ['Requested', 'Confirmed'], true) || strtotime($b['session_date'] . ' ' . $b['start_time']) <= time()) {
                    $pdo->rollBack();
                    flash('error', 'Only upcoming requested or confirmed sessions can be reassigned.');
                    break;
                }
                if ($b['tutor_id'] !== null && (int)$b['tutor_id'] === $newTutor) {
                    $pdo->rollBack();
                    flash('error', 'That tutor is already assigned to this session.');
                    break;
                }

                $ok = find_available_tutor($pdo, (int)$b['subject_id'], $b['session_date'], $b['start_time'], (int)$b['booking_id'], $newTutor);
                if ($ok === null) {
                    $pdo->rollBack();
                    flash('error', 'That tutor does not teach this subject, is not available at that time, or is already booked.');
                    break;
                }

                $pdo->prepare("UPDATE bookings SET tutor_id = ?, status = 'Confirmed' WHERE booking_id = ?")->execute([$newTutor, $id]);

                $nm = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM tutors WHERE tutor_id = ?");
                $nm->execute([$newTutor]);
                $newName = (string)$nm->fetchColumn();
                $when = session_label($b['session_date'], $b['start_time']);

                notify($pdo, "Your session on $when is now with $newName.", $id, (int)$b['student_id'], null, null);
                notify($pdo, "You have been assigned a session on $when.", $id, null, $newTutor, null);
                if ($b['tutor_id'] !== null) {
                    notify($pdo, "Your session on $when was reassigned to another tutor.", $id, null, (int)$b['tutor_id'], null);
                }
                notify_admins($pdo, "Booking #$id ($when) reassigned to $newName.", $id);

                $pdo->commit();
                flash('success', "Booking #$id is now assigned to $newName.");
                break;

            case 'delete_booking':
                if ($id === false || $id < 1) {
                    flash('error', 'Invalid booking.');
                    break;
                }
                $del = $pdo->prepare('DELETE FROM bookings WHERE booking_id = ?');
                $del->execute([$id]);
                flash($del->rowCount() > 0 ? 'success' : 'error', $del->rowCount() > 0 ? 'Booking deleted.' : 'Booking not found.');
                break;
        }
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $code = (int)($ex->errorInfo[1] ?? 0);
        if ($code === 1062) {
            flash('error', 'That email address or programme name already exists.');
        } elseif ($code === 1451) {
            flash('error', 'This record is linked to other records (enrolments, payments or bookings) so it cannot be deleted.');
        } else {
            error_log('admin action failed: ' . $ex->getMessage());
            flash('error', 'The action could not be completed. Please try again.');
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('admin action failed: ' . $ex->getMessage());
        flash('error', 'The action could not be completed. Please try again.');
    }

    redirect($back);
