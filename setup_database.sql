-- JuniorCode Database Setup
-- Run this in phpMyAdmin or MySQL to recreate the database

CREATE DATABASE IF NOT EXISTS `juniorcode_db2` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `juniorcode_db2`;

-- --------------------------------------------------------
-- users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`                INT          NOT NULL AUTO_INCREMENT,
  `username`          VARCHAR(255) NOT NULL,
  `password`          VARCHAR(255) NOT NULL,
  `plain_password`    VARCHAR(255) NOT NULL DEFAULT '',
  `role`              VARCHAR(50)  NOT NULL DEFAULT 'student',
  `email`             VARCHAR(255) NOT NULL DEFAULT '',
  `zoom_personal_link` TEXT        NOT NULL,
  `profile_picture`   VARCHAR(300) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- classes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `classes` (
  `id`           INT          NOT NULL AUTO_INCREMENT,
  `teacher_id`   INT                   DEFAULT NULL,
  `teacher_name` VARCHAR(255) NOT NULL DEFAULT '',
  `student_name` VARCHAR(255) NOT NULL DEFAULT '',
  `class_date`   DATE                  DEFAULT NULL,
  `class_time`   TIME                  DEFAULT NULL,
  `type`         VARCHAR(100) NOT NULL DEFAULT '',
  `details`      TEXT         NOT NULL,
  `zoom_link`    TEXT                  DEFAULT NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- teacher_earnings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `teacher_earnings` (
  `id`           INT            NOT NULL AUTO_INCREMENT,
  `teacher_id`   INT                     DEFAULT NULL,
  `teacher_name` VARCHAR(255)   NOT NULL DEFAULT '',
  `lesson_title` VARCHAR(255)   NOT NULL DEFAULT '',
  `amount`       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `lesson_date`  DATE                    DEFAULT NULL,
  `notes`        TEXT           NOT NULL,
  `class_id`     INT                     DEFAULT NULL,
  `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- teacher_availability
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `teacher_availability` (
  `id`             INT          NOT NULL AUTO_INCREMENT,
  `teacher_id`     INT          NOT NULL DEFAULT 0,
  `teacher_name`   VARCHAR(255) NOT NULL DEFAULT '',
  `available_date` DATE         NOT NULL,
  `available_time` TIME         NOT NULL,
  `status`         VARCHAR(50)  NOT NULL DEFAULT 'available',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- settings (admin_prefs)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT         NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- courses
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `courses` (
  `id`          INT            NOT NULL AUTO_INCREMENT,
  `course_name` VARCHAR(255)   NOT NULL DEFAULT '',
  `category`    VARCHAR(100)   NOT NULL DEFAULT '',
  `age_group`   VARCHAR(50)    NOT NULL DEFAULT '',
  `level`       VARCHAR(50)    NOT NULL DEFAULT '',
  `price`       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `course_type` VARCHAR(20)    NOT NULL DEFAULT 'demo',
  `status`      VARCHAR(20)    NOT NULL DEFAULT 'active',
  `duration`    VARCHAR(100)   NOT NULL DEFAULT '',
  `image`       TEXT                    DEFAULT NULL,
  `section`     VARCHAR(50)    NOT NULL DEFAULT 'kids',
  `sub_section` VARCHAR(50)    NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- course_projects
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `course_projects` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `section`    VARCHAR(50)  NOT NULL DEFAULT 'kids',
  `category`   VARCHAR(100) NOT NULL DEFAULT 'Game Development',
  `title`      VARCHAR(255) NOT NULL,
  `url`        TEXT         NOT NULL,
  `image`      TEXT         NOT NULL,
  `pdf_url`    TEXT         NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- certificates
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `certificates` (
  `id`           INT          NOT NULL AUTO_INCREMENT,
  `student_name` VARCHAR(255) NOT NULL DEFAULT '',
  `course_name`  VARCHAR(255) NOT NULL DEFAULT '',
  `teacher_name` VARCHAR(255) NOT NULL DEFAULT '',
  `issued_date`  DATE                  DEFAULT NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- assignments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assignments` (
  `id`           INT          NOT NULL AUTO_INCREMENT,
  `teacher_id`   INT          NOT NULL DEFAULT 0,
  `teacher_name` VARCHAR(255) NOT NULL DEFAULT '',
  `student_name` VARCHAR(255) NOT NULL DEFAULT '',
  `title`        VARCHAR(255) NOT NULL DEFAULT '',
  `description`  TEXT         NOT NULL,
  `due_date`     DATE                  DEFAULT NULL,
  `file_name`    VARCHAR(255) NOT NULL DEFAULT '',
  `link`         VARCHAR(500) NOT NULL DEFAULT '',
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- assignment_submissions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assignment_submissions` (
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `assignment_id` INT          NOT NULL,
  `student_name`  VARCHAR(255) NOT NULL DEFAULT '',
  `file_name`     VARCHAR(255) NOT NULL DEFAULT '',
  `link`          VARCHAR(500) NOT NULL DEFAULT '',
  `submitted_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- quizzes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quizzes` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(255) NOT NULL,
  `topic`      VARCHAR(255) NOT NULL,
  `section`    VARCHAR(50)  NOT NULL DEFAULT 'kids',
  `difficulty` VARCHAR(50)  NOT NULL DEFAULT 'beginner',
  `created_by` VARCHAR(255) NOT NULL DEFAULT '',
  `time_limit` INT                   DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- quiz_questions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quiz_questions` (
  `id`             INT    NOT NULL AUTO_INCREMENT,
  `quiz_id`        INT    NOT NULL,
  `question`       TEXT   NOT NULL,
  `choice_a`       TEXT   NOT NULL,
  `choice_b`       TEXT   NOT NULL,
  `choice_c`       TEXT   NOT NULL,
  `choice_d`       TEXT   NOT NULL,
  `correct_answer` CHAR(1) NOT NULL,
  `explanation`    TEXT   NOT NULL,
  PRIMARY KEY (`id`),
  KEY `quiz_id` (`quiz_id`),
  CONSTRAINT `fk_qq_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- quiz_assignments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quiz_assignments` (
  `id`               INT          NOT NULL AUTO_INCREMENT,
  `quiz_id`          INT          NOT NULL,
  `student_username` VARCHAR(255) NOT NULL,
  `assigned_by`      VARCHAR(255) NOT NULL DEFAULT '',
  `assigned_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_assign` (`quiz_id`, `student_username`),
  CONSTRAINT `fk_qa_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- quiz_results
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quiz_results` (
  `id`               INT          NOT NULL AUTO_INCREMENT,
  `quiz_id`          INT          NOT NULL,
  `student_username` VARCHAR(255) NOT NULL,
  `score`            INT          NOT NULL DEFAULT 0,
  `total`            INT          NOT NULL DEFAULT 0,
  `answers`          TEXT         NOT NULL,
  `completed_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_result` (`quiz_id`, `student_username`),
  CONSTRAINT `fk_qr_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- ai_chat_usage
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_chat_usage` (
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `student_name`  VARCHAR(255) NOT NULL,
  `usage_date`    DATE         NOT NULL,
  `message_count` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_student_date` (`student_name`, `usage_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Default admin account (password: admin123)
-- --------------------------------------------------------
INSERT IGNORE INTO `users` (`username`, `password`, `plain_password`, `role`, `email`, `zoom_personal_link`, `profile_picture`)
VALUES (
  'admin',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'admin123',
  'admin',
  '',
  '',
  ''
);
