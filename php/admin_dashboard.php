<?php
    require_once __DIR__ . '/config.php';
    require_role(['admin']);

    $pdo = db();
    $adminId = (int)$_SESSION['user']['id'];

    $sections = [
        'overview' => 'Overview',
        'students' => 'Students',
        'tutors' => 'Tutors',
        'programmes' => 'Programmes',
        'bookings' => 'Bookings',
        'payments' => 'Payments',
    ];

    $section = $_GET['section'] ?? 'overview';
    if(!is_string($section) || !array_key_exists($section, $sections)) {
        $section = 'overview';
    }
    $editId = filter_var($_GET['edit'] ?? '', FILTER_VALIDATE_INT);   // false when not editing

    $subjectList = $pdo->query('SELECT subject_id, subject_name FROM subjects ORDER BY subject_name')->fetchAll();

    $pageTitle = 'Administrator Dashboard';
    require __DIR__ . '/header.php';
?>

<h2>Administrator Dashboard</h2>
<nav class="tabs">
<?php foreach ($sections as $key => $label): ?>
    <a href="admin_dashboard.php?section=<?= e($key) ?>" class="<?= ($key === $section) ? 'active' : '' ?>"><?= e($label) ?></a>
<?php endforeach; ?>
</nav>

<?php if ($section === 'overview'): ?>
<?php
    $stats = [
        'Registered students' => $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
        'Tutors' => $pdo->query('SELECT COUNT(*) FROM tutors')->fetchColumn(),
        'Active enrolments' => $pdo->query("SELECT COUNT(*) FROM enrolments WHERE status = 'Active'")->fetchColumn(),
        'Upcoming confirmed sessions' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Confirmed' AND session_date >= CURDATE()")->fetchColumn(),
        'Completed sessions' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'Completed'")->fetchColumn(),
        'Revenue (completed payments)' => 'R ' . number_format((float)$pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_status = 'Completed'")->fetchColumn(), 2),
    ];
    $st = $pdo->prepare('SELECT message, is_read, created_at FROM notifications WHERE admin_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT 10');
    $st->execute([$adminId]);
    $notes = $st->fetchAll();
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE admin_id = ?')->execute([$adminId]);
?>
    <div class="stats">
    <?php foreach ($stats as $label => $value): ?>
        <div class="stat"><span class="number"><?= e($value) ?></span><span><?= e($label) ?></span></div>
    <?php endforeach; ?>
    </div>

    <section class="card">
        <h3>Latest notifications</h3>
        <?php if (count($notes) === 0): ?>
            <p>No notifications yet.</p>
        <?php else: ?>
            <ul class="notes">
            <?php foreach ($notes as $n): ?>
                <li class="<?= ((int)$n['is_read'] === 0) ? 'unread' : '' ?>"><?= e($n['message']) ?> <span class="hint"><?= e($n['created_at']) ?></span></li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

<?php elseif ($section === 'students'): ?>
<?php
    $students = $pdo->query(
        "SELECT s.student_id, s.first_name, s.last_name, s.email, s.phone, s.registered_at,
                (SELECT COUNT(*) FROM enrolments e WHERE e.student_id = s.student_id AND e.status = 'Active') AS active_enrolments
         FROM students s ORDER BY s.student_id"
    )->fetchAll();
    $edit = null;
    if ($editId) {
        $q = $pdo->prepare('SELECT student_id, first_name, last_name, email, phone FROM students WHERE student_id = ?');
        $q->execute([$editId]);
        $edit = $q->fetch() ?: null;
    }
?>
    <section class="card">
        <h3><?= $edit ? 'Edit student' : 'Add student' ?></h3>
        <form id="adminPersonForm" action="admin_actions.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit ? 'update_student' : 'add_student' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= e($edit['student_id']) ?>"><?php endif; ?>

            <div class="row">
                <div><label for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" required maxlength="50" value="<?= e($edit['first_name'] ?? '') ?>"></div>
                <div><label for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" required maxlength="50" value="<?= e($edit['last_name'] ?? '') ?>"></div>
            </div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required maxlength="100" value="<?= e($edit['email'] ?? '') ?>">
            <label for="phone">Phone (10 digits)</label>
            <input type="tel" id="phone" name="phone" required pattern="0[0-9]{9}" maxlength="10" value="<?= e($edit['phone'] ?? '') ?>">
            <label for="password"><?= $edit ? 'New password (leave blank to keep the current one)' : 'Password' ?></label>
            <input type="password" id="password" name="password" <?= $edit ? '' : 'required' ?> minlength="8" maxlength="72"
                   pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}" title="At least 8 characters with an uppercase letter, a lowercase letter and a number">
            <button type="submit"><?= $edit ? 'Save changes' : 'Add student' ?></button>
            <?php if ($edit): ?><a class="button secondary" href="admin_dashboard.php?section=students">Cancel</a><?php endif; ?>
        </form>
    </section>

    <section class="card">
        <h3>All students</h3>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Active programmes</th><th>Registered</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><?= e($s['student_id']) ?></td>
                    <td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td><?= e($s['email']) ?></td>
                    <td><?= e($s['phone']) ?></td>
                    <td><?= e($s['active_enrolments']) ?></td>
                    <td><?= e($s['registered_at']) ?></td>
                    <td class="actions">
                        <a class="button small" href="admin_dashboard.php?section=students&amp;edit=<?= e($s['student_id']) ?>">Edit</a>
                        <form action="admin_actions.php" method="post" onsubmit="return confirm('Delete this student?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_student">
                            <input type="hidden" name="id" value="<?= e($s['student_id']) ?>">
                            <button type="submit" class="danger small">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

<?php elseif ($section === 'tutors'): ?>
<?php
    $tutors = $pdo->query(
        "SELECT t.tutor_id, t.first_name, t.last_name, t.email, t.phone,
                (SELECT GROUP_CONCAT(s.subject_name ORDER BY s.subject_name SEPARATOR ', ')
                 FROM tutor_subjects ts JOIN subjects s ON s.subject_id = ts.subject_id
                 WHERE ts.tutor_id = t.tutor_id) AS subjects
         FROM tutors t ORDER BY t.tutor_id"
    )->fetchAll();
    $edit = null;
    $editSubjects = [];
    if ($editId) {
        $q = $pdo->prepare('SELECT tutor_id, first_name, last_name, email, phone FROM tutors WHERE tutor_id = ?');
        $q->execute([$editId]);
        $edit = $q->fetch() ?: null;
        if ($edit) {
            $q = $pdo->prepare('SELECT subject_id FROM tutor_subjects WHERE tutor_id = ?');
            $q->execute([$editId]);
            $editSubjects = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
        }
    }
?>
    <section class="card">
        <h3><?= $edit ? 'Edit tutor' : 'Add tutor' ?></h3>
        <form id="adminPersonForm" action="admin_actions.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit ? 'update_tutor' : 'add_tutor' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= e($edit['tutor_id']) ?>"><?php endif; ?>

            <div class="row">
                <div><label for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" required maxlength="50" value="<?= e($edit['first_name'] ?? '') ?>"></div>
                <div><label for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" required maxlength="50" value="<?= e($edit['last_name'] ?? '') ?>"></div>
            </div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required maxlength="100" value="<?= e($edit['email'] ?? '') ?>">
            <label for="phone">Phone (10 digits)</label>
            <input type="tel" id="phone" name="phone" required pattern="0[0-9]{9}" maxlength="10" value="<?= e($edit['phone'] ?? '') ?>">
            <label for="password"><?= $edit ? 'New password (leave blank to keep the current one)' : 'Password' ?></label>
            <input type="password" id="password" name="password" <?= $edit ? '' : 'required' ?> minlength="8" maxlength="72"
                   pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}" title="At least 8 characters with an uppercase letter, a lowercase letter and a number">

            <fieldset>
                <legend>Subjects this tutor can teach</legend>
                <?php foreach ($subjectList as $sub): ?>
                    <label class="inline">
                        <input type="checkbox" name="subjects[]" value="<?= e($sub['subject_id']) ?>"
                            <?= in_array((int)$sub['subject_id'], $editSubjects, true) ? 'checked' : '' ?>>
                        <?= e($sub['subject_name']) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <button type="submit"><?= $edit ? 'Save changes' : 'Add tutor' ?></button>
            <?php if ($edit): ?><a class="button secondary" href="admin_dashboard.php?section=tutors">Cancel</a><?php endif; ?>
        </form>
    </section>

    <section class="card">
        <h3>All tutors</h3>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Subjects</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($tutors as $t): ?>
                <tr>
                    <td><?= e($t['tutor_id']) ?></td>
                    <td><?= e($t['first_name'] . ' ' . $t['last_name']) ?></td>
                    <td><?= e($t['email']) ?></td>
                    <td><?= e($t['phone']) ?></td>
                    <td><?= e($t['subjects']) ?></td>
                    <td class="actions">
                        <a class="button small" href="admin_dashboard.php?section=tutors&amp;edit=<?= e($t['tutor_id']) ?>">Edit</a>
                        <form action="admin_actions.php" method="post" onsubmit="return confirm('Delete this tutor?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_tutor">
                            <input type="hidden" name="id" value="<?= e($t['tutor_id']) ?>">
                            <button type="submit" class="danger small">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

<?php elseif ($section === 'programmes'): ?>
<?php
    $progs = $pdo->query(
        "SELECT p.programme_id, p.programme_name, p.description, p.fee, p.duration_weeks, p.subject_id, s.subject_name,
                (SELECT COUNT(*) FROM enrolments e WHERE e.programme_id = p.programme_id AND e.status = 'Active') AS active_students
         FROM programmes p JOIN subjects s ON s.subject_id = p.subject_id ORDER BY p.programme_id"
    )->fetchAll();
    $edit = null;
    if ($editId) {
        $q = $pdo->prepare('SELECT programme_id, subject_id, programme_name, description, fee, duration_weeks FROM programmes WHERE programme_id = ?');
        $q->execute([$editId]);
        $edit = $q->fetch() ?: null;
    }
?>
    <section class="card">
        <h3><?= $edit ? 'Edit programme' : 'Add programme' ?></h3>
        <form id="programmeForm" action="admin_actions.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit ? 'update_programme' : 'add_programme' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= e($edit['programme_id']) ?>"><?php endif; ?>

            <label for="programme_name">Programme name</label>
            <input type="text" id="programme_name" name="programme_name" required minlength="3" maxlength="100" value="<?= e($edit['programme_name'] ?? '') ?>">

            <label for="subject_id">Subject</label>
            <select id="subject_id" name="subject_id" required>
                <option value="">-- choose a subject --</option>
                <?php foreach ($subjectList as $sub): ?>
                    <option value="<?= e($sub['subject_id']) ?>" <?= ((int)($edit['subject_id'] ?? 0) === (int)$sub['subject_id']) ? 'selected' : '' ?>><?= e($sub['subject_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="description">Description</label>
            <input type="text" id="description" name="description" maxlength="255" value="<?= e($edit['description'] ?? '') ?>">

            <div class="row">
                <div><label for="fee">Fee (R)</label>
                    <input type="number" id="fee" name="fee" required min="1" max="99999" step="0.01" value="<?= e($edit['fee'] ?? '') ?>"></div>
                <div><label for="duration_weeks">Duration (weeks)</label>
                    <input type="number" id="duration_weeks" name="duration_weeks" required min="1" max="52" step="1" value="<?= e($edit['duration_weeks'] ?? '') ?>"></div>
            </div>
            <button type="submit"><?= $edit ? 'Save changes' : 'Add programme' ?></button>
            <?php if ($edit): ?><a class="button secondary" href="admin_dashboard.php?section=programmes">Cancel</a><?php endif; ?>
        </form>
    </section>

    <section class="card">
        <h3>All programmes</h3>
        <table>
            <thead><tr><th>ID</th><th>Programme</th><th>Subject</th><th>Fee</th><th>Weeks</th><th>Active students</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($progs as $p): ?>
                <tr>
                    <td><?= e($p['programme_id']) ?></td>
                    <td><?= e($p['programme_name']) ?></td>
                    <td><?= e($p['subject_name']) ?></td>
                    <td>R <?= e(number_format((float)$p['fee'], 2)) ?></td>
                    <td><?= e($p['duration_weeks']) ?></td>
                    <td><?= e($p['active_students']) ?></td>
                    <td class="actions">
                        <a class="button small" href="admin_dashboard.php?section=programmes&amp;edit=<?= e($p['programme_id']) ?>">Edit</a>
                        <form action="admin_actions.php" method="post" onsubmit="return confirm('Delete this programme?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_programme">
                            <input type="hidden" name="id" value="<?= e($p['programme_id']) ?>">
                            <button type="submit" class="danger small">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

<?php elseif ($section === 'bookings'): ?>
<?php
    $all = $pdo->query(
        "SELECT b.booking_id, b.session_date, b.start_time, b.status, b.tutor_id, p.programme_name,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                CONCAT(t.first_name, ' ', t.last_name) AS tutor_name
         FROM bookings b
         JOIN enrolments e ON e.enrolment_id = b.enrolment_id
         JOIN programmes p ON p.programme_id = e.programme_id
         JOIN students s ON s.student_id = e.student_id
         LEFT JOIN tutors t ON t.tutor_id = b.tutor_id
         ORDER BY b.session_date DESC, b.start_time DESC"
    )->fetchAll();
    $tutorList = $pdo->query("SELECT tutor_id, CONCAT(first_name, ' ', last_name) AS name FROM tutors ORDER BY first_name")->fetchAll();
?>
    <section class="card">
        <h3>All bookings</h3>
        <p class="hint">Reassigning checks the same rules as a normal booking: the tutor must teach the subject, be available that day and time, and not be booked already.</p>
        <table>
            <thead><tr><th>#</th><th>Date</th><th>Time</th><th>Programme</th><th>Student</th><th>Tutor</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($all as $b): ?>
                <?php
                    $active = in_array($b['status'], ['Requested', 'Confirmed'], true);
                    $upcoming = strtotime($b['session_date'] . ' ' . $b['start_time']) > time();
                ?>
                <tr>
                    <td><?= e($b['booking_id']) ?></td>
                    <td><?= e(date('D d M Y', strtotime($b['session_date']))) ?></td>
                    <td><?= e(substr($b['start_time'], 0, 5)) ?></td>
                    <td><?= e($b['programme_name']) ?></td>
                    <td><?= e($b['student_name']) ?></td>
                    <td><?= e($b['tutor_name'] ?? 'Not assigned') ?></td>
                    <td><span class="badge <?= e(strtolower($b['status'])) ?>"><?= e($b['status']) ?></span></td>
                    <td class="actions">
                        <?php if ($active && $upcoming): ?>
                        <form action="admin_actions.php" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reassign_booking">
                            <input type="hidden" name="id" value="<?= e($b['booking_id']) ?>">
                            <select name="tutor_id" required aria-label="Assign tutor">
                                <option value="">Assign tutor</option>
                                <?php foreach ($tutorList as $t): ?>
                                    <option value="<?= e($t['tutor_id']) ?>"><?= e($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="small">Assign</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($b['status'] === 'Confirmed' && $b['session_date'] <= date('Y-m-d')): ?>
                        <form action="booking_actions.php" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="complete">
                            <input type="hidden" name="booking_id" value="<?= e($b['booking_id']) ?>">
                            <button type="submit" class="small">Complete</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($active): ?>
                        <form action="booking_actions.php" method="post" onsubmit="return confirm('Cancel this booking?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="booking_id" value="<?= e($b['booking_id']) ?>">
                            <button type="submit" class="danger small">Cancel</button>
                        </form>
                        <?php endif; ?>
                        <form action="admin_actions.php" method="post" onsubmit="return confirm('Permanently delete this booking?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_booking">
                            <input type="hidden" name="id" value="<?= e($b['booking_id']) ?>">
                            <button type="submit" class="danger small">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

<?php elseif ($section === 'payments'): ?>
<?php
    $pays = $pdo->query(
        "SELECT pay.payment_id, pay.transaction_ref, pay.amount_paid, pay.payment_status, pay.card_last4, pay.paid_at,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name, p.programme_name, e.status AS enrolment_status
         FROM payments pay
         JOIN enrolments e ON e.enrolment_id = pay.enrolment_id
         JOIN students s ON s.student_id = e.student_id
         JOIN programmes p ON p.programme_id = e.programme_id
         ORDER BY pay.paid_at DESC, pay.payment_id DESC"
    )->fetchAll();
?>
    <section class="card">
        <h3>Payments</h3>
        <p class="hint">Use this list to verify that every Active enrolment is backed by a Completed payment.</p>
        <table>
            <thead><tr><th>Reference</th><th>Student</th><th>Programme</th><th>Amount</th><th>Card</th><th>Payment</th><th>Enrolment</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($pays as $p): ?>
                <tr>
                    <td><?= e($p['transaction_ref']) ?></td>
                    <td><?= e($p['student_name']) ?></td>
                    <td><?= e($p['programme_name']) ?></td>
                    <td>R <?= e(number_format((float)$p['amount_paid'], 2)) ?></td>
                    <td>**** <?= e($p['card_last4']) ?></td>
                    <td><span class="badge <?= e(strtolower($p['payment_status'])) ?>"><?= e($p['payment_status']) ?></span></td>
                    <td><span class="badge <?= e(strtolower($p['enrolment_status'])) ?>"><?= e($p['enrolment_status']) ?></span></td>
                    <td><?= e($p['paid_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<script src="../js/validation.js"></script>
<?php require __DIR__ . '/footer.php'; ?>