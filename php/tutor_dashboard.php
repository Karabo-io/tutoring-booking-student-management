<?php
    require_once __DIR__ . '/config.php';
    require_role(['tutor']);

    $pdo = db();
    $tutorId = (int)$_SESSION['user']['id'];

    $st = $pdo->prepare('SELECT s.subject_name FROM tutor_subjects ts JOIN subjects s ON s.subject_id = ts.subject_id WHERE ts.tutor_id = ? ORDER BY s.subject_name');
    $st->execute([$tutorId]);
    $subjects = $st->fetchAll(PDO::FETCH_COLUMN);

    $st = $pdo->prepare(
        "SELECT availability_id, day_of_week, start_time, end_time
        FROM tutor_availability WHERE tutor_id = ?
        ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), start_time"
    );
    $st->execute([$tutorId]);
    $availability = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT b.booking_id, b.session_date, b.start_time, b.status, b.topic, p.programme_name,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name
        FROM bookings b
        JOIN enrolments e ON e.enrolment_id = b.enrolment_id
        JOIN programmes p ON p.programme_id = e.programme_id
        JOIN students s ON s.student_id = e.student_id
        WHERE b.tutor_id = ?
        ORDER BY b.session_date, b.start_time"
    );
    $st->execute([$tutorId]);
    $rows = $st->fetchAll();

    $bookings = [];
    $updatable = [];   // confirmed sessions the tutor can complete or cancel
    foreach ($rows as $r) {
        $time = substr($r['start_time'], 0, 5);
        $bookings[] = [
            'id' => (int)$r['booking_id'],
            'date' => $r['session_date'],
            'day' => date('l', strtotime($r['session_date'])),
            'time' => $time,
            'programme' => $r['programme_name'],
            'person' => $r['student_name'],
            'status' => $r['status'],
        ];
        if ($r['status'] === 'Confirmed') {
            $updatable[] = [
                'id'    => (int)$r['booking_id'],
                'label' => date('D d M Y', strtotime($r['session_date'])) . ' ' . $time . ' - ' . $r['student_name'],
            ];
        }
    }

    $st = $pdo->prepare('SELECT message, is_read, created_at FROM notifications WHERE tutor_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT 8');
    $st->execute([$tutorId]);
    $notes = $st->fetchAll();
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE tutor_id = ?')->execute([$tutorId]);

    $pageTitle = 'Tutor Dashboard';
    require __DIR__ . '/header.php';
?>
<h2>Welcome, <?= e($_SESSION['user']['name']) ?></h2>
<p>Subjects you teach: <strong><?= e(implode(', ', $subjects)) ?></strong></p>

<section class="card">
    <h3>My availability (Monday to Friday)</h3>
    <?php if (count($availability) === 0): ?>
        <p>You have not added any availability yet. Students cannot be assigned to you until you do.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Day</th><th>From</th><th>To</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($availability as $a): ?>
                <tr>
                    <td><?= e($a['day_of_week']) ?></td>
                    <td><?= e(substr($a['start_time'], 0, 5)) ?></td>
                    <td><?= e(substr($a['end_time'], 0, 5)) ?></td>
                    <td>
                        <form action="tutor_availability.php" method="post" onsubmit="return confirm('Remove this availability?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="availability_id" value="<?= e($a['availability_id']) ?>">
                            <button type="submit" class="danger small">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h4>Add availability</h4>
    <form id="availabilityForm" action="tutor_availability.php" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">

        <label for="day_of_week">Day</label>
        <select id="day_of_week" name="day_of_week" required>
            <option value="">-- choose a day --</option>
            <?php foreach (weekdays() as $day): ?>
                <option value="<?= e($day) ?>"><?= e($day) ?></option>
            <?php endforeach; ?>
        </select>

        <div class="row">
            <div>
                <label for="start_time">From</label>
                <input type="time" id="start_time" name="start_time" required min="08:00" max="16:00" step="3600">
            </div>
            <div>
                <label for="end_time">To</label>
                <input type="time" id="end_time" name="end_time" required min="09:00" max="17:00" step="3600">
            </div>
        </div>
        <button type="submit">Add availability</button>
    </form>
</section>

<section class="card">
    <h3>My scheduled sessions</h3>
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
        <thead><tr><th>Date</th><th>Day</th><th>Time</th><th>Programme</th><th>Student</th><th>Status</th></tr></thead>
        <tbody></tbody>
    </table>
    <script id="bookingData" type="application/json"><?= json_encode($bookings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</section>

<?php if (count($updatable) > 0): ?>
<section class="card">
    <h3>Update a session</h3>
    <form action="booking_actions.php" method="post">
        <?= csrf_field() ?>
        <label for="booking_id">Session</label>
        <select id="booking_id" name="booking_id" required>
            <option value="">-- choose a session --</option>
            <?php foreach ($updatable as $u): ?>
                <option value="<?= e($u['id']) ?>"><?= e($u['label']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="action">Action</label>
        <select id="action" name="action" required>
            <option value="complete">Mark as completed</option>
            <option value="cancel">Cancel session</option>
        </select>
        <button type="submit">Update session</button>
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

<script src="../js/validation.js"></script>
<script src="../js/bookings.js"></script>
<?php require __DIR__ . '/footer.php'; ?>