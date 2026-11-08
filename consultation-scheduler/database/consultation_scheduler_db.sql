-- =====================================================================
--  Student Consultation Scheduler  |  CPE 304L  |  Group 11
--  Database: consultation_scheduler_db  (MySQL / MariaDB via XAMPP)
--
--  Tables student, instructor, availability_slot and consultation follow
--  the group's Data Dictionary Matrix (Phase 3) column for column.
--  Columns/tables marked "ADDED" support login, B-02 (password reset),
--  B-03 (decline reason), B-04 (who cancelled) and FR-004 (notifications).
--
--  Import: phpMyAdmin > Import > this file   or   open setup.php
--  Demo accounts
--     Student    : john.doe@example.com        / student123
--     Instructor : juan.delacruz@example.com   / instructor123
-- =====================================================================

SET time_zone = '+08:00';
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS consultation_scheduler_db;
CREATE DATABASE consultation_scheduler_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE consultation_scheduler_db;

CREATE TABLE student (
  student_id     INT          NOT NULL AUTO_INCREMENT,
  student_name   VARCHAR(100) NOT NULL,
  student_email  VARCHAR(100) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  PRIMARY KEY (student_id),
  UNIQUE KEY uq_student_email (student_email)
) ENGINE=InnoDB;

CREATE TABLE instructor (
  instructor_id       INT          NOT NULL AUTO_INCREMENT,
  instructor_name     VARCHAR(100) NOT NULL,
  instructor_email    VARCHAR(100) NOT NULL,
  availability_status VARCHAR(20)  NOT NULL DEFAULT 'AVAILABLE',
  password_hash       VARCHAR(255) NOT NULL,
  PRIMARY KEY (instructor_id),
  UNIQUE KEY uq_instructor_email (instructor_email)
) ENGINE=InnoDB;

CREATE TABLE availability_slot (
  slot_id         INT         NOT NULL AUTO_INCREMENT,
  instructor_id   INT         NOT NULL,
  slot_date       DATE        NOT NULL,
  slot_start_time TIME        NOT NULL,
  slot_end_time   TIME        NOT NULL,
  slot_status     VARCHAR(20) NOT NULL DEFAULT 'OPEN',
  PRIMARY KEY (slot_id),
  KEY idx_slot_instructor (instructor_id, slot_date),
  CONSTRAINT fk_slot_instructor FOREIGN KEY (instructor_id) REFERENCES instructor (instructor_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE consultation (
  consultation_id     INT          NOT NULL AUTO_INCREMENT,
  student_id          INT          NOT NULL,
  instructor_id       INT          NOT NULL,
  slot_id             INT          NOT NULL,
  consultation_date   DATE         NOT NULL,
  start_time          TIME         NOT NULL,
  end_time            TIME         NOT NULL,
  consultation_status VARCHAR(20)  NOT NULL DEFAULT 'PENDING',
  decline_reason      VARCHAR(255) NULL,
  cancelled_by        VARCHAR(20)  NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (consultation_id),
  KEY idx_consult_slot (slot_id, consultation_status),
  CONSTRAINT fk_consult_student FOREIGN KEY (student_id) REFERENCES student (student_id) ON DELETE CASCADE,
  CONSTRAINT fk_consult_instructor FOREIGN KEY (instructor_id) REFERENCES instructor (instructor_id) ON DELETE CASCADE,
  CONSTRAINT fk_consult_slot FOREIGN KEY (slot_id) REFERENCES availability_slot (slot_id)
) ENGINE=InnoDB;

-- ADDED: B-02 password recovery (one-time 6-digit code, 15-minute expiry)
CREATE TABLE password_reset (
  reset_id    INT          NOT NULL AUTO_INCREMENT,
  user_role   VARCHAR(20)  NOT NULL,
  user_email  VARCHAR(100) NOT NULL,
  code_hash   VARCHAR(255) NOT NULL,
  expires_at  DATETIME     NOT NULL,
  used        TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (reset_id),
  KEY idx_reset_email (user_email)
) ENGINE=InnoDB;

-- ADDED: FR-004 real-time notifications (availability changes, request status)
CREATE TABLE notification (
  notification_id INT          NOT NULL AUTO_INCREMENT,
  recipient_role  VARCHAR(20)  NOT NULL,
  recipient_id    INT          NOT NULL,
  consultation_id INT          NULL,
  message         VARCHAR(255) NOT NULL,
  is_read         TINYINT(1)   NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notification_id),
  KEY idx_notif_recipient (recipient_role, recipient_id, is_read)
) ENGINE=InnoDB;

-- =====================================================================
--  Seed data (dates are relative to the day you import)
-- =====================================================================
INSERT INTO student (student_id, student_name, student_email, password_hash) VALUES
 (1, 'John Doe',     'john.doe@example.com',     '$2y$10$I2SGlYgGudmtLPgmZQA5..jWPx/3EBAE7yliSbDiQwoIy3CM71IfW'),
 (2, 'Ana Reyes',    'ana.reyes@example.com',    '$2y$10$I2SGlYgGudmtLPgmZQA5..jWPx/3EBAE7yliSbDiQwoIy3CM71IfW');

INSERT INTO instructor (instructor_id, instructor_name, instructor_email, availability_status, password_hash) VALUES
 (1, 'Juan dela Cruz',  'juan.delacruz@example.com',  'AVAILABLE',   '$2y$10$54yZ4jxooQ.GeJc673s9AuKMiq2TnkNb4eBBAIWct3nHccGzaks62'),
 (2, 'Maria dela Cruz', 'maria.delacruz@example.com', 'AVAILABLE',   '$2y$10$54yZ4jxooQ.GeJc673s9AuKMiq2TnkNb4eBBAIWct3nHccGzaks62'),
 (3, 'Mark Anthony',    'mark.anthony@example.com',   'AVAILABLE',   '$2y$10$54yZ4jxooQ.GeJc673s9AuKMiq2TnkNb4eBBAIWct3nHccGzaks62'),
 (4, 'Mark Zidane',     'mark.zidane@example.com',    'UNAVAILABLE', '$2y$10$54yZ4jxooQ.GeJc673s9AuKMiq2TnkNb4eBBAIWct3nHccGzaks62');

INSERT INTO availability_slot (slot_id, instructor_id, slot_date, slot_start_time, slot_end_time, slot_status) VALUES
 (1, 1, CURDATE() + INTERVAL 1 DAY, '07:00:00', '09:00:00', 'OPEN'),
 (2, 1, CURDATE() + INTERVAL 2 DAY, '13:00:00', '15:00:00', 'OPEN'),
 (3, 2, CURDATE() + INTERVAL 3 DAY, '14:00:00', '16:00:00', 'OPEN'),
 (4, 3, CURDATE() + INTERVAL 1 DAY, '09:00:00', '12:00:00', 'OPEN'),
 (5, 3, CURDATE() + INTERVAL 1 DAY, '13:00:00', '15:00:00', 'OPEN'),
 (6, 3, CURDATE() + INTERVAL 4 DAY, '08:00:00', '11:00:00', 'OPEN');

INSERT INTO consultation (consultation_id, student_id, instructor_id, slot_id, consultation_date, start_time, end_time, consultation_status, created_at, updated_at) VALUES
 (1, 1, 1, 1, CURDATE() + INTERVAL 1 DAY, '07:00:00', '08:30:00', 'PENDING',   NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 2 HOUR),
 (2, 2, 1, 2, CURDATE() + INTERVAL 2 DAY, '13:00:00', '14:00:00', 'CONFIRMED', NOW() - INTERVAL 1 DAY,  NOW() - INTERVAL 20 HOUR),
 (3, 1, 2, 3, CURDATE() + INTERVAL 3 DAY, '14:00:00', '16:00:00', 'CONFIRMED', NOW() - INTERVAL 1 DAY,  NOW() - INTERVAL 22 HOUR);

INSERT INTO notification (recipient_role, recipient_id, consultation_id, message, is_read, created_at) VALUES
 ('STUDENT', 1, 3, 'Maria dela Cruz confirmed your consultation.', 1, NOW() - INTERVAL 22 HOUR),
 ('INSTRUCTOR', 1, 1, 'John Doe requested a consultation.', 0, NOW() - INTERVAL 2 HOUR);
