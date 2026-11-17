<?php
    // booking_logic.php
    // Business rules for session booking and tutor allocation.
    // Rules: Monday-Friday only, one hour sessions, tutor must teach the subject,
    // tutor must be available in that window and may not be double booked.
    require_once __DIR__ . '/config.php';

    function find_available_tutor(PDO $pdo, int $subjectId, string $date, string $startTime, int $excludeBookingId = 0, ?int $onlyTutor = null): ?int
    {
        $dayName = date('l', strtotime($date));                       
        $endTime = date('H:i:s', strtotime($startTime) + 3600);      

        $sql = "SELECT t.tutor_id
                FROM tutors t
                JOIN tutor_subjects ts ON ts.tutor_id = t.tutor_id AND ts.subject_id = ?
                JOIN tutor_availability av ON av.tutor_id = t.tutor_id
                    AND av.day_of_week = ?
                    AND av.start_time <= ?
                    AND av.end_time >= ?
                WHERE NOT EXISTS (
                    SELECT 1 FROM bookings b
                    WHERE b.tutor_id = t.tutor_id
                    AND b.session_date = ?
                    AND b.start_time = ?
                    AND b.status IN ('Requested', 'Confirmed')
                    AND b.booking_id <> ?
                )";
        $params = [$subjectId, $dayName, $startTime, $endTime, $date, $startTime, $excludeBookingId];

        if ($onlyTutor !== null) {
            $sql .= ' AND t.tutor_id = ?';
            $params[] = $onlyTutor;
        }

        $sql .= " ORDER BY (SELECT COUNT(*) FROM bookings b2
                            WHERE b2.tutor_id = t.tutor_id AND b2.status IN ('Requested', 'Confirmed')) ASC,
                        t.tutor_id ASC
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $found = $stmt->fetchColumn();
        return ($found === false) ? null : (int)$found;
    }

    // Creates a booking. Lifecycle: Requested -> (tutor found) -> Confirmed.
    // $enrol is a row with enrolment_id, subject_id and programme_name.
    // Returns [true|false, message].
    function create_booking(PDO $pdo, int $studentId, array $enrol, string $date, string $time, string $topic): array
    {
        $start = $time . ':00';

        try {
            $pdo->beginTransaction();

            $pdo->query('SELECT tutor_id FROM tutors FOR UPDATE')->fetchAll();

            $dup = $pdo->prepare(
                "SELECT COUNT(*) FROM bookings b
                JOIN enrolments e ON e.enrolment_id = b.enrolment_id
                WHERE e.student_id = ? AND b.session_date = ? AND b.start_time = ?
                AND b.status IN ('Requested', 'Confirmed')"
            );
            $dup->execute([$studentId, $date, $start]);
            if ((int)$dup->fetchColumn() > 0) {
                $pdo->rollBack();
                return [false, 'You already have a session booked at that date and time.'];
            }

            $tutorId = find_available_tutor($pdo, (int)$enrol['subject_id'], $date, $start);
            if ($tutorId === null) {
                $pdo->rollBack();
                return [false, 'No tutor is available for that subject at that date and time. Please choose another slot.'];
            }

            $ins = $pdo->prepare(
                "INSERT INTO bookings (enrolment_id, session_date, start_time, status, topic)
                VALUES (?, ?, ?, 'Requested', ?)"
            );
            $ins->execute([(int)$enrol['enrolment_id'], $date, $start, ($topic === '') ? null : $topic]);
            $bookingId = (int)$pdo->lastInsertId();

            $upd = $pdo->prepare("UPDATE bookings SET tutor_id = ?, status = 'Confirmed' WHERE booking_id = ?");
            $upd->execute([$tutorId, $bookingId]);

            $s = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM students WHERE student_id = ?");
            $s->execute([$studentId]);
            $studentName = (string)$s->fetchColumn();

            $t = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM tutors WHERE tutor_id = ?");
            $t->execute([$tutorId]);
            $tutorName = (string)$t->fetchColumn();

            $when = session_label($date, $start);
            $prog = (string)$enrol['programme_name'];

            notify($pdo, "Your session on $when is confirmed with $tutorName ($prog).", $bookingId, $studentId, null, null);
            notify($pdo, "New session: $studentName on $when ($prog).", $bookingId, null, $tutorId, null);
            notify_admins($pdo, "Booking #$bookingId confirmed: $studentName with $tutorName on $when.", $bookingId);

            $pdo->commit();
            return [true, "Your session is confirmed for $when with $tutorName."];
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('create_booking failed: ' . $ex->getMessage());
            return [false, 'Something went wrong while saving the booking. Please try again.'];
        }
    }
