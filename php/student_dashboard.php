<?php
    require_once __DIR__ . '/config.php';
    require_role(['student']);

    $pdo = db();
    $studentId = (int)$_SESSION['user']['id'];

    $st = $pdo->prepare(
        "SELECT e.status, e.enrolled_at, p.programme_name, p.fee, s.subject_name
        FROM enrolments e
        JOIN programmes p ON p.programme_id = e.programme_id
        JOIN subjects s ON s.subject_id = p.subject_id
        WHERE e.student_id = ?
        ORDER BY e.created_at DESC"
    );
    $st->execute([$studentId]);
    $enrolments = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT b.booking_id, b.session_date, b.start_time, b.status, p.programme_name,
                CONCAT(t.first_name, ' ', t.last_name) AS tutor_name
        FROM bookings b
        JOIN enrolments e ON e.enrolment_id = b.enrolment_id
        JOIN programmes p ON p.programme_id = e.programme_id
        LEFT JOIN tutors t ON t.tutor_id = b.tutor_id
        WHERE e.student_id = ?
        ORDER BY b.session_date, b.start_time"
    );
    $st->execute([$studentId]);
    $rows = $st->fetchAll();

    $bookings = [];     
    $cancellable = [];   
    foreach ($rows as $r) {
        $time = substr($r['start_time'], 0, 5);
        $bookings[] = [
            'id' => (int)$r['booking_id'],
            'date' => $r['session_date'],
            'day' => date('l', strtotime($r['session_date'])),
            'time' => $time,
            'programme' => $r['programme_name'],
            'person' => $r['tutor_name'] ?? 'Not assigned',
            'status' => $r['status'],
        ];
        $inFuture = strtotime($r['session_date'] . ' ' . $r['start_time']) > time();
        if (in_array($r['status'], ['Requested', 'Confirmed'], true) && $inFuture) {
            $cancellable[] = [
                'id'    => (int)$r['booking_id'],
                'label' => date('D d M Y', strtotime($r['session_date'])) . ' ' . $time . ' - ' . $r['programme_name'],
            ];
        }
    }

    $st = $pdo->prepare('SELECT message, is_read, created_at FROM notifications WHERE student_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT 8');
    $st->execute([$studentId]);
    $notes = $st->fetchAll();
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE student_id = ?')->execute([$studentId]);

    $pageTitle = 'Student Dashboard';
    require __DIR__ . '/header.php';
?>

<h2>Welcome, <?= e($_SESSION['user']['name']) ?></h2>
<section class="card">
    <h3>My programmes</h3>
    <?php if (count($enrolments) === 0): ?>
        <p>You are not enrolled yet. <a href="programmes.php">Choose a programme and pay</a> to get started.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Programme</th><th>Subject</th><th>Fee</th><th>Status</th><th>Enrolled on</th></tr></thead>
            <tbody>
            <?php foreach ($enrolments as $en): ?>
                <tr>
                    <td><?= e($en['programme_name']) ?></td>
                    <td><?= e($en['subject_name']) ?></td>
                    <td>R <?= e(number_format((float)$en['fee'], 2)) ?></td>
                    <td><span class="badge <?= e(strtolower($en['status'])) ?>"><?= e($en['status']) ?></span></td>
                    <td><?= e($en['enrolled_at'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card">
    <h3>My tutoring sessions</h3>
    <div class="filter-row">
        <label for="statusFilter">Show:</label>
        <select id="statusFilter">
            <option value="All">All</option>
            <option value="Confirmed">Confirmed</option>
            <option value="Completed">Completed</option>
            <option value="Cancelled">Cancelled</option>
            <option value="Requested">Requested</option>
        </select>
        <span id="bookingSummary" class="hint"></span>
    </div>
    <table id="bookingTable">
        <thead><tr><th>Date</th><th>Day</th><th>Time</th><th>Programme</th><th>Tutor</th><th>Status</th></tr></thead>
        <tbody></tbody>
    </table>
    <script id="bookingData" type="application/json"><?= json_encode($bookings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <p><a class="button" href="book_session.php">Book a new session</a></p>
</section>

<?php if (count($cancellable) > 0): ?>
<section class="card">
    <h3>Cancel a session</h3>
    <form action="booking_actions.php" method="post" onsubmit="return confirm('Cancel this session?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <label for="booking_id">Session</label>
        <select id="booking_id" name="booking_id" required>
            <option value="">-- choose a session --</option>
            <?php foreach ($cancellable as $c): ?>
                <option value="<?= e($c['id']) ?>"><?= e($c['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="danger">Cancel session</button>
    </form>
</section>
<?php endif; ?>

<section class="card">
    <h3>Notifications</h3>
    <?php if (count($notes) === 0): ?>
        <p>No notifications yet.</p>
    <?php else: ?>
        <ul class="notes">
        <?php foreach ($notes as $n): ?>
            <li class="<?= ((int)$n['is_read'] === 0) ? 'unread' : '' ?>">
                <?= e($n['message']) ?> <span class="hint"><?= e($n['created_at']) ?></span>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<script src="../js/bookings.js"></script>
<?php require __DIR__ . '/footer.php'; ?>
