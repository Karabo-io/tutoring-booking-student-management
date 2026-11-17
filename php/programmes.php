<?php
    require_once __DIR__ . '/config.php';
    require_role(['student']);

    $pdo = db();
    $studentId = (int)$_SESSION['user']['id'];

    $programmes = $pdo->query(
        "SELECT p.programme_id, p.programme_name, p.description, p.fee, p.duration_weeks, s.subject_name
        FROM programmes p 
        JOIN subjects s ON s.subject_id = p.subject_id
        ORDER BY s.subject_name, p.programme_name"
    )->fetchAll();

    $st = $pdo->prepare('SELECT programme_id, status FROM enrolments WHERE student_id = ?');
    $st->execute([$studentId]);
    $myStatus = [];
    foreach ($st->fetchAll() as $row) {
        $myStatus[(int)$row['programme_id']] = $row['status'];
    }

    $pageTitle = 'Programmes & Payment';
    require __DIR__ . '/header.php';
?>
<h2>Programmes &amp; Payment</h2>

<table>
    <thead>
        <tr><th>Programme</th><th>Subject</th><th>Description</th><th>Weeks</th><th>Fee</th><th>Your status</th></tr>
    </thead>
    <tbody>
    <?php foreach ($programmes as $p): ?>
        <tr>
            <td><?= e($p['programme_name']) ?></td>
            <td><?= e($p['subject_name']) ?></td>
            <td><?= e($p['description']) ?></td>
            <td><?= e($p['duration_weeks']) ?></td>
            <td>R <?= e(number_format((float)$p['fee'], 2)) ?></td>
            <td><?= e($myStatus[(int)$p['programme_id']] ?? 'Not enrolled') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<section class="card">
    <h3>Select a programme and pay</h3>
    <p class="hint">This is a simulated payment. Use any 16 digit number. A card ending in 0000 is declined so the failed payment path can be tested. Card details are not saved, only the last 4 digits.</p>
    <form id="paymentForm" action="payment.php" method="post">
        <?= csrf_field() ?>

        <label for="programme_id">Programme</label>
        <select id="programme_id" name="programme_id" required>
            <option value="" data-fee="">-- choose a programme --</option>
            <?php foreach ($programmes as $p): ?>
                <?php if (($myStatus[(int)$p['programme_id']] ?? '') !== 'Active'): ?>
                    <option value="<?= e($p['programme_id']) ?>" data-fee="<?= e($p['fee']) ?>">
                        <?= e($p['programme_name']) ?> (R <?= e(number_format((float)$p['fee'], 2)) ?>)
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <p>Amount due: <strong id="amountDue">R 0.00</strong></p>

        <label for="card_name">Name on card</label>
        <input type="text" id="card_name" name="card_name" required minlength="2" maxlength="60" autocomplete="cc-name">

        <label for="card_number">Card number (16 digits)</label>
        <input type="text" id="card_number" name="card_number" required inputmode="numeric"
               pattern="[0-9]{16}" maxlength="16" title="Enter exactly 16 digits" autocomplete="cc-number">

        <div class="row">
            <div>
                <label for="expiry">Expiry (MM/YY)</label>
                <input type="text" id="expiry" name="expiry" required placeholder="MM/YY"
                       pattern="(0[1-9]|1[0-2])/[0-9]{2}" maxlength="5" title="Format MM/YY" autocomplete="cc-exp">
            </div>
            <div>
                <label for="cvv">CVV</label>
                <input type="password" id="cvv" name="cvv" required inputmode="numeric"
                       pattern="[0-9]{3}" maxlength="3" title="3 digits" autocomplete="cc-csc">
            </div>
        </div>

        <button type="submit">Pay and enrol</button>
    </form>
</section>

<script src="../js/validation.js"></script>
<?php require __DIR__ . '/footer.php'; ?>
