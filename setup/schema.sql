-- examis · Database Schema
-- MariaDB 10+ / MySQL 8+  |  charset: utf8mb4_unicode_ci
-- Run via install.php or: mariadb -u root -p < setup/schema.sql

CREATE DATABASE IF NOT EXISTS examis
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE examis;

-- ─────────────────────────────────────────────────────────────────
--  Users  (all roles share one table)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)     NOT NULL UNIQUE,
  password_hash VARCHAR(255)    NOT NULL,
  full_name     VARCHAR(120)    NOT NULL,
  role          ENUM('admin','academic_deputy','exam_manager',
                     'teacher','exam_supervisor','student') NOT NULL,
  student_code  VARCHAR(20)     DEFAULT NULL COMMENT 'รหัสนักเรียน',
  is_active     TINYINT(1)      NOT NULL DEFAULT 1,
  created_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_role         (role),
  INDEX idx_student_code (student_code)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────
--  Semesters
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS semesters (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(80) NOT NULL COMMENT 'e.g. ภาคเรียนที่ 2/2568',
  start_date DATE        NOT NULL,
  end_date   DATE        NOT NULL,
  is_active  TINYINT(1)  NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────
--  Exam papers  (question banks created by teachers)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS exam_papers (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(200) NOT NULL,
  subject      VARCHAR(100) NOT NULL,
  level        VARCHAR(50)  NOT NULL COMMENT 'e.g. ม.3',
  paper_type   ENUM('digital','scanned_pdf') NOT NULL DEFAULT 'digital',
  pdf_filename VARCHAR(255) DEFAULT NULL,
  created_by   INT UNSIGNED NOT NULL,
  status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY fk_paper_creator (created_by) REFERENCES users(id),
  INDEX idx_status     (status),
  INDEX idx_created_by (created_by)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────
--  Questions
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS questions (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  paper_id        INT UNSIGNED NOT NULL,
  question_type   ENUM('multiple_choice','fill_blank','matching',
                       'drag_drop','true_false') NOT NULL DEFAULT 'multiple_choice',
  question_text   TEXT NOT NULL,
  order_num       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  points          TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  shuffle_choices TINYINT(1)        NOT NULL DEFAULT 1,
  FOREIGN KEY fk_q_paper (paper_id) REFERENCES exam_papers(id) ON DELETE CASCADE,
  INDEX idx_paper_order (paper_id, order_num)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────
--  Choices  (answer options for multiple_choice questions)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS choices (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  label       VARCHAR(4)   NOT NULL COMMENT 'ก ข ค ง or A B C D',
  choice_text TEXT         NOT NULL,
  is_correct  TINYINT(1)   NOT NULL DEFAULT 0,
  order_num   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  FOREIGN KEY fk_choice_q (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  INDEX idx_question (question_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────
--  Exam sessions  (one row = one scheduled exam sitting)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS exam_sessions (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  semester_id      INT UNSIGNED NOT NULL,
  paper_id         INT UNSIGNED NOT NULL,
  room_code        VARCHAR(20)  NOT NULL,
  supervisor_id    INT UNSIGNED NOT NULL,
  scheduled_date   DATE         NOT NULL,
  start_time       TIME         NOT NULL,
  end_time         TIME         NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  pin_code         CHAR(6)      NOT NULL,
  status           ENUM('draft','ready','active','ended') NOT NULL DEFAULT 'draft',
  created_by       INT UNSIGNED NOT NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY fk_ses_semester   (semester_id)   REFERENCES semesters(id),
  FOREIGN KEY fk_ses_paper      (paper_id)      REFERENCES exam_papers(id),
  FOREIGN KEY fk_ses_supervisor (supervisor_id) REFERENCES users(id),
  FOREIGN KEY fk_ses_creator    (created_by)    REFERENCES users(id),
  INDEX idx_ses_status     (status),
  INDEX idx_ses_supervisor (supervisor_id),
  INDEX idx_ses_date       (scheduled_date)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────
--  Session enrollments  (which students sit which session)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS session_enrollments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id    INT UNSIGNED NOT NULL,
  student_id    INT UNSIGNED NOT NULL,
  seat_number   SMALLINT UNSIGNED DEFAULT NULL,
  checked_in_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_enrollment (session_id, student_id),
  FOREIGN KEY fk_enroll_session (session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY fk_enroll_student (student_id) REFERENCES users(id),
  INDEX idx_enroll_session (session_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────
--  Student answers  (autosaved during exam)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS student_answers (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id   INT UNSIGNED NOT NULL,
  student_id   INT UNSIGNED NOT NULL,
  question_id  INT UNSIGNED NOT NULL,
  answer_label VARCHAR(4)   NOT NULL,
  answered_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_answer (session_id, student_id, question_id),
  FOREIGN KEY fk_ans_session  (session_id)  REFERENCES exam_sessions(id),
  FOREIGN KEY fk_ans_student  (student_id)  REFERENCES users(id),
  FOREIGN KEY fk_ans_question (question_id) REFERENCES questions(id),
  INDEX idx_ans_student_session (session_id, student_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────
--  Exam submissions  (final submission record)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS exam_submissions (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id     INT UNSIGNED NOT NULL,
  student_id     INT UNSIGNED NOT NULL,
  answered_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  submitted_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_submission (session_id, student_id),
  FOREIGN KEY fk_sub_session (session_id) REFERENCES exam_sessions(id),
  FOREIGN KEY fk_sub_student (student_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────
--  Late exam permission requests
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS late_requests (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id  INT UNSIGNED NOT NULL,
  student_id  INT UNSIGNED NOT NULL,
  reason      TEXT         NOT NULL,
  status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by INT UNSIGNED DEFAULT NULL,
  reviewed_at TIMESTAMP    NULL DEFAULT NULL,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY fk_lr_session  (session_id)  REFERENCES exam_sessions(id),
  FOREIGN KEY fk_lr_student  (student_id)  REFERENCES users(id),
  FOREIGN KEY fk_lr_reviewer (reviewed_by) REFERENCES users(id),
  INDEX idx_lr_status (status)
) ENGINE=InnoDB;
