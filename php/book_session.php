<?php
    require_once __DIR__ . '/booking_logic.php';
    require_role(['student']);

    $pdo = db();
    $studentId = (int)$_SESSION['user']['id'];

    $st = $pdo->prepare(
        "SELECT e.enrolment_id, p.programme_name, p.subject_id, s.subject_name
        FROM enrolments e
        JOIN programmes p ON p.programme_id = e.programme_id
        JOIN subjects s ON s.subject_id = p.subject_id
        WHERE e.student_id = ? AND e.status = 'Active'
        ORDER BY p.programme_name"
    );
    $st->execute([$studentId]);
    $enrolments = $st->fetchAll();

    $errors = [];
    $old = ['enrolment_id' => '', 'session_date' => '', 'start_time' => '', 'topic' => ''];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();

        $old['enrolment_id'] = post_str('enrolment_id');
        $old['session_date'] = post_str('session_date');
        $old['start_time'] = post_str('start_time');
        $old['topic'] = post_str('topic');

        $chosen = null;
        foreach ($enrolments as $en) {
            if ((string)$en['enrolment_id'] === $old['enrolment_id']) {
                $chosen = $en;
            }
        }
        if ($chosen === null) {
            $errors[] = 'Please choose one of your active programmes.';
        }
        
        $d = DateTime::createFromFormat('Y-m-d', $old['session_date']);
        $dateOk = ($d !== false && $d->format('Y-m-d') === $old['session_date']);
        if (!$dateOk) {
            $errors[] = 'Please enter a valid date.';
        } else {
            if ((int)$d->format('N') > 5) {
                $errors[] = 'Sessions can only be booked from Monday to Friday.';
            }
            if ($old['session_date'] < date('Y-m-d')) {
                $errors[] = 'You cannot book a session in the past.';
            }
        }

        if (!in_array($old['start_time'], time_slots(), true)) {
            $errors[] = 'Please choose a valid time slot.';
        } elseif ($dateOk && $old['session_date'] === date('Y-m-d') && $old['start_time'] <= date('H:i')) {
            $errors[] = 'That time has already passed today.';
        }

        if (strlen($old['topic']) > 150) {
            $errors[] = 'The topic may not be longer than 150 characters.';
        }

        if (count($errors) === 0) {
            [$ok, $message] = create_booking($pdo, $studentId, $chosen, $old['session_date'], $old['start_time'], $old['topic']);
            if ($ok) {
                flash('success', $message);
                redirect('student_dashboard.php');
            }
            $errors[] = $message;
        }
    }

    $pageTitle = 'Book a Session';
    require __DIR__ . '/header.php';
?>
<h2>Book a tutoring session</h2>

<?php if (count($enrolments) === 0): ?>
    <div class="alert error">You need an active programme before you can book a session.
        <a href="programmes.php">Choose a programme and pay</a> first.</div>
<?php else: ?>
    <?php if (count($errors) > 0): ?>
        <ul class="alert error">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <section class="card">
        <p class="hint">Sessions are one hour long and run Monday to Friday. A tutor who teaches the subject and is free at that time is assigned to you automatically.</p>
        <form id="bookingForm" action="book_session.php" method="post">
            <?= csrf_field() ?>

            <label for="enrolment_id">Programme</label>
            <select id="enrolment_id" name="enrolment_id" required>
                <option value="">-- choose a programme --</option>
                <?php foreach ($enrolments as $en): ?>
                    <option value="<?= e($en['enrolment_id']) ?>" <?= ((string)$en['enrolment_id'] === $old['enrolment_id']) ? 'selected' : '' ?>>
                        <?= e($en['programme_name']) ?> (<?= e($en['subject_name']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="session_date">Date (Monday to Friday)</label>
            <input type="date" id="session_date" name="session_date" required
                   min="<?= e(date('Y-m-d')) ?>" value="<?= e($old['session_date']) ?>">

            <label for="start_time">Start time</label>
            <select id="start_time" name="start_time" required>
                <option value="">-- choose a time --</option>
                <?php foreach (time_slots() as $slot): ?>
                    <option value="<?= e($slot) ?>" <?= ($slot === $old['start_time']) ? 'selected' : '' ?>><?= e($slot) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="topic">Topic (optional)</label>
            <input type="text" id="topic" name="topic" maxlength="150" value="<?= e($old['topic']) ?>">

            <button type="submit">Book session</button>
        </form>
    </section>
<?php endif; ?>

<script src="../js/validation.js"></script>
<?php require __DIR__ . '/footer.php'; ?>