-- Create database if it does not exists
CREATE DATABASE IF NOT EXISTS tutoring_db
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE tutoring_db;

-- Users
CREATE TABLE students (
    student_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50)  NOT NULL,
    last_name VARCHAR(50)  NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(15)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    registered_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_students_email (email)
) ENGINE=InnoDB;

CREATE TABLE tutors (
    tutor_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50)  NOT NULL,
    last_name VARCHAR(50)  NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(15)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tutors_email (email)
) ENGINE=InnoDB;


CREATE TABLE administrators (
    admin_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB;

-- Subjects and programmes
CREATE TABLE subjects (
    subject_id INT UNSIGNED NOT NULL AUTO_INCREMENT  PRIMARY KEY,
    subject_name   VARCHAR(50)  NOT NULL,
    UNIQUE KEY uq_subject_name (subject_name)
) ENGINE=InnoDB;

CREATE TABLE programmes (
    programme_id INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    subject_id INT UNSIGNED  NOT NULL,
    programme_name VARCHAR(100)  NOT NULL,
    description VARCHAR(255)  NULL,
    fee DECIMAL(10,2) NOT NULL,
    duration_weeks  TINYINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_programme_name (programme_name),
    CONSTRAINT fk_programmes_subject FOREIGN KEY (subject_id)
        REFERENCES subjects (subject_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_programme_fee CHECK (fee > 0)
) ENGINE=InnoDB;

-- which subjects a tutor can teach (many-to-many)
CREATE TABLE tutor_subjects (
    tutor_id INT UNSIGNED NOT NULL,
    subject_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (tutor_id, subject_id),
    CONSTRAINT fk_ts_tutor FOREIGN KEY (tutor_id)
        REFERENCES tutors (tutor_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ts_subject FOREIGN KEY (subject_id)
        REFERENCES subjects (subject_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- weekly availability windows, Monday to Friday only
CREATE TABLE tutor_availability (
    availability_id  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT UNSIGNED NOT NULL,
    day_of_week  ENUM('Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
    start_time TIME NOT NULL,
    end_time  TIME NOT NULL,
    UNIQUE KEY uq_availability (tutor_id, day_of_week, start_time),
    CONSTRAINT fk_av_tutor FOREIGN KEY (tutor_id)
        REFERENCES tutors (tutor_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_av_times CHECK (end_time > start_time)
) ENGINE=InnoDB;

-- Enrolments, payments and bookings
CREATE TABLE enrolments (
    enrolment_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    programme_id  INT UNSIGNED NOT NULL,
    status ENUM('Pending','Active','Cancelled') NOT NULL DEFAULT 'Pending',
    enrolled_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_programme (student_id, programme_id),
    CONSTRAINT fk_enrol_student FOREIGN KEY (student_id)
        REFERENCES students (student_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_enrol_programme FOREIGN KEY (programme_id)
        REFERENCES programmes (programme_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE payments (
    payment_id INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    enrolment_id INT UNSIGNED  NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_status ENUM('Pending','Completed','Failed') NOT NULL DEFAULT 'Pending',
    transaction_ref VARCHAR(30)   NOT NULL,
    card_last4 CHAR(4)       NOT NULL,
    paid_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_transaction_ref (transaction_ref),
    CONSTRAINT fk_pay_enrolment FOREIGN KEY (enrolment_id)
        REFERENCES enrolments (enrolment_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_pay_amount CHECK (amount_paid > 0)
) ENGINE=InnoDB;


CREATE TABLE bookings (
    booking_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    enrolment_id  INT UNSIGNED NOT NULL,
    tutor_id  INT UNSIGNED NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    status ENUM('Requested','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Requested',
    topic VARCHAR(150) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_booking_slot (tutor_id, session_date, start_time),
    CONSTRAINT fk_book_enrolment FOREIGN KEY (enrolment_id)
        REFERENCES enrolments (enrolment_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_book_tutor FOREIGN KEY (tutor_id)
        REFERENCES tutors (tutor_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_book_weekday CHECK (WEEKDAY(session_date) BETWEEN 0 AND 4)
) ENGINE=InnoDB;

-- exactly one of student_id / tutor_id / admin_id is filled in by the PHP code
CREATE TABLE notifications (
    notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT UNSIGNED NULL,
    student_id INT UNSIGNED NULL,
    tutor_id INT UNSIGNED NULL,
    admin_id INT UNSIGNED NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_note_booking FOREIGN KEY (booking_id)
        REFERENCES bookings (booking_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_note_student FOREIGN KEY (student_id)
        REFERENCES students (student_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_note_tutor FOREIGN KEY (tutor_id)
        REFERENCES tutors (tutor_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_note_admin FOREIGN KEY (admin_id)
        REFERENCES administrators (admin_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sample data admin = Admin@123 | tutors = Tutor@123 | students = Student@123
INSERT INTO administrators (admin_id, full_name, email, password_hash) VALUES
(1, 'System Administrator', 'admin@tutoring.co.za', '$2y$10$55ORwpGI2rK/jRxsR2tIs.kE1a6hw.Cl2cAhI4a/1Xh1NEMZH01Mm');

INSERT INTO tutors (tutor_id, first_name, last_name, email, phone, password_hash) VALUES
(1, 'Thabo',  'Mokoena', 'thabo.mokoena@tutoring.co.za', '0711234501', '$2y$10$HTuoApthZLVRoZG4gtKWvernUG9kUFNyH3ka1EKimoRsVmSrov/Y.'),
(2, 'Naledi', 'Dlamini', 'naledi.dlamini@tutoring.co.za', '0711234502', '$2y$10$DWseDrnNGpz/wH2n85HOAuVWO4gOfYNoOoqZhv7Ky7A.QQolQ0Nda'),
(3, 'Sipho',  'Nkosi',   'sipho.nkosi@tutoring.co.za',   '0711234503', '$2y$10$oLvkrKuk.q1I1Nx56Xpfi.ntmp7BqAzRTDwaWaV4oJD10YXblakIK');

INSERT INTO students (student_id, first_name, last_name, email, phone, password_hash) VALUES
(1, 'Lerato',  'Khumalo',  'lerato.khumalo@student.co.za',  '0821234501', '$2y$10$7TgAt7FNP6G10FNAVwXyTeDSz6Q1iUo4NuaC3/kYoW7QUMCj9Bte6'),
(2, 'Kabelo',  'Mahlangu', 'kabelo.mahlangu@student.co.za', '0821234502', '$2y$10$hLSfF0iBh.oCCa/KvD0wnOzK.WX52ZqaKGI05ZRrmzs0norAu2mbu'),
(3, 'Ayanda',  'Zulu',     'ayanda.zulu@student.co.za',     '0821234503', '$2y$10$teEHBlXRzC7yvs3koWAZ2.106tqFbcCK9vGAdWPhj7I7H7KmMPwHS');

INSERT INTO subjects (subject_id, subject_name) VALUES
(1, 'Mathematics'),
(2, 'Data Science');

INSERT INTO programmes (programme_id, subject_id, programme_name, description, fee, duration_weeks) VALUES
(1, 1, 'Mathematics Foundation',      'Algebra, functions and trigonometry support', 1200.00, 8),
(2, 1, 'Calculus and Linear Algebra', 'Differentiation, integration, vectors and matrices', 1500.00, 10),
(3, 2, 'Data Science Fundamentals',   'Python, data cleaning and basic statistics',  1800.00, 10),
(4, 2, 'SQL and Data Analysis',       'Querying, joins and reporting with MySQL',  1400.00, 8);

INSERT INTO tutor_subjects (tutor_id, subject_id) VALUES
(1, 1), (2, 2), (3, 1), (3, 2);

INSERT INTO tutor_availability (tutor_id, day_of_week, start_time, end_time) VALUES
(1, 'Monday',    '08:00:00', '12:00:00'),
(1, 'Wednesday', '13:00:00', '17:00:00'),
(2, 'Tuesday',   '09:00:00', '13:00:00'),
(2, 'Thursday',  '09:00:00', '13:00:00'),
(3, 'Monday',    '13:00:00', '17:00:00'),
(3, 'Wednesday', '08:00:00', '12:00:00'),
(3, 'Friday',    '08:00:00', '15:00:00');

INSERT INTO enrolments (enrolment_id, student_id, programme_id, status, enrolled_at) VALUES
(1, 1, 1, 'Active',  '2026-10-20 09:16:00'),
(2, 1, 3, 'Active',  '2026-10-22 14:05:00'),
(3, 2, 3, 'Active',  '2026-10-21 11:40:00'),
(4, 3, 2, 'Pending', NULL);

INSERT INTO payments (payment_id, enrolment_id, amount_paid, payment_status, transaction_ref, card_last4, paid_at) VALUES
(1, 1, 1200.00, 'Completed', 'TXN20261020091600A1B2', '4242', '2026-10-20 09:16:00'),
(2, 2, 1800.00, 'Completed', 'TXN20261022140500C3D4', '4242', '2026-10-22 14:05:00'),
(3, 3, 1800.00, 'Completed', 'TXN20261021114000E5F6', '1111', '2026-10-21 11:40:00'),
(4, 4, 1500.00, 'Failed',    'TXN20261023100000G7H8', '0000', '2026-10-23 10:00:00');

-- 2 Nov 2026 is a Monday; all dates below fall on Monday-Friday
INSERT INTO bookings (booking_id, enrolment_id, tutor_id, session_date, start_time, status, topic) VALUES
(1, 1, 1, '2026-11-02', '09:00:00', 'Completed', 'Quadratic equations'),
(2, 3, 2, '2026-11-03', '10:00:00', 'Completed', 'Cleaning data with pandas'),
(3, 1, 1, '2026-11-04', '14:00:00', 'Cancelled', 'Trigonometric identities'),
(4, 1, 1, '2026-11-16', '10:00:00', 'Confirmed', 'Functions and graphs'),
(5, 2, 2, '2026-11-17', '11:00:00', 'Confirmed', 'Mean, median and mode'),
(6, 3, 2, '2026-11-19', '09:00:00', 'Confirmed', 'Intro to regression');

INSERT INTO notifications (booking_id, student_id, tutor_id, admin_id, message, is_read) VALUES
(4, 1,    NULL, NULL, 'Your session on Mon, 16 Nov 2026 at 10:00 is confirmed with Thabo Mokoena.', 0),
(4, NULL, 1,    NULL, 'New session: Lerato Khumalo on Mon, 16 Nov 2026 at 10:00 (Mathematics Foundation).', 0),
(4, NULL, NULL, 1,    'Booking #4 confirmed: Lerato Khumalo with Thabo Mokoena on Mon, 16 Nov 2026 at 10:00.', 0);