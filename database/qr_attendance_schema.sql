-- =============================================================
--  Secure Dynamic QR Attendance System — Full MySQL Schema
--  Laravel | Flutter | MySQL
--  Version 1.0 | May 2026
--
--  Run order matters — foreign keys must reference existing tables.
--  Tables: users, departments, students, faculty, system_settings,
--          security_policies, data_retention_policies,
--          admin_role_assignments, courses, class_groups, rooms,
--          timetables, enrollments, device_registrations,
--          attendance_sessions, qr_challenges, attendance_records,
--          proxy_flags, audit_logs, session_exports,
--          personal_access_tokens
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- =============================================================
-- 1. USERS
-- =============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`              VARCHAR(150)    NOT NULL,
    `email`             VARCHAR(191)    NOT NULL,
    `email_verified_at` TIMESTAMP       NULL,
    `password`          VARCHAR(255)    NOT NULL,
    `role`              ENUM('super_admin','admin','faculty','student') NOT NULL,
    `status`            ENUM('active','suspended','pending') NOT NULL DEFAULT 'pending',
    `last_login_at`     TIMESTAMP       NULL,
    `remember_token`    VARCHAR(100)    NULL,
    `created_at`        TIMESTAMP       NULL,
    `updated_at`        TIMESTAMP       NULL,
    `deleted_at`        TIMESTAMP       NULL,   -- soft delete
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`),
    INDEX `idx_users_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 2. DEPARTMENTS  (head_faculty_id FK added after faculty table)
-- =============================================================
CREATE TABLE IF NOT EXISTS `departments` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(150)    NOT NULL,
    `code`            VARCHAR(20)     NOT NULL,
    `head_faculty_id` BIGINT UNSIGNED NULL,     -- FK added below
    `is_active`       TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`      TIMESTAMP       NULL,
    `updated_at`      TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_departments_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 3. STUDENTS
-- =============================================================
CREATE TABLE IF NOT EXISTS `students` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `roll_no`       VARCHAR(30)     NOT NULL,
    `department_id` BIGINT UNSIGNED NOT NULL,
    `batch_year`    YEAR            NOT NULL,
    `section`       VARCHAR(10)     NOT NULL,
    `status`        ENUM('active','inactive','graduated') NOT NULL DEFAULT 'active',
    `created_at`    TIMESTAMP       NULL,
    `updated_at`    TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_students_user_id`  (`user_id`),
    UNIQUE KEY `uq_students_roll_no`  (`roll_no`),
    INDEX `idx_students_department`   (`department_id`),
    INDEX `idx_students_batch_status` (`batch_year`, `status`),
    CONSTRAINT `fk_students_user`       FOREIGN KEY (`user_id`)       REFERENCES `users`       (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_students_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 4. FACULTY
-- =============================================================
CREATE TABLE IF NOT EXISTS `faculty` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `employee_code` VARCHAR(30)     NOT NULL,
    `department_id` BIGINT UNSIGNED NOT NULL,
    `designation`   VARCHAR(100)    NULL,
    `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`    TIMESTAMP       NULL,
    `updated_at`    TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_faculty_user_id`       (`user_id`),
    UNIQUE KEY `uq_faculty_employee_code` (`employee_code`),
    INDEX `idx_faculty_department`        (`department_id`),
    CONSTRAINT `fk_faculty_user`       FOREIGN KEY (`user_id`)       REFERENCES `users`       (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_faculty_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add head_faculty_id FK on departments now that faculty table exists
ALTER TABLE `departments`
    ADD CONSTRAINT `fk_departments_head_faculty`
    FOREIGN KEY (`head_faculty_id`) REFERENCES `faculty` (`id`) ON DELETE SET NULL;

-- =============================================================
-- 5. SUPER ADMIN — system_settings
-- =============================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`         VARCHAR(100)    NOT NULL,
    `value`       TEXT            NOT NULL,
    `data_type`   ENUM('string','integer','boolean','json') NOT NULL DEFAULT 'string',
    `description` VARCHAR(255)    NULL,
    `is_public`   TINYINT(1)      NOT NULL DEFAULT 0,
    `updated_by`  BIGINT UNSIGNED NULL,
    `created_at`  TIMESTAMP       NULL,
    `updated_at`  TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_system_settings_key` (`key`),
    CONSTRAINT `fk_system_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 6. SUPER ADMIN — security_policies
-- =============================================================
CREATE TABLE IF NOT EXISTS `security_policies` (
    `id`                     BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `policy_name`            VARCHAR(100)     NOT NULL,
    `qr_expiry_seconds`      SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `risk_auto_reject`       TINYINT UNSIGNED NOT NULL DEFAULT 80,
    `risk_pending_review`    TINYINT UNSIGNED NOT NULL DEFAULT 50,
    `late_threshold_mins`    TINYINT UNSIGNED NOT NULL DEFAULT 10,
    `geofence_radius_m`      SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    `device_binding_required` TINYINT(1)      NOT NULL DEFAULT 1,
    `clock_skew_seconds`     TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `is_active`              TINYINT(1)       NOT NULL DEFAULT 1,
    `created_by`             BIGINT UNSIGNED  NULL,
    `created_at`             TIMESTAMP        NULL,
    `updated_at`             TIMESTAMP        NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_security_policies_name` (`policy_name`),
    CONSTRAINT `fk_security_policies_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 7. SUPER ADMIN — data_retention_policies
-- =============================================================
CREATE TABLE IF NOT EXISTS `data_retention_policies` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type`         VARCHAR(60)     NOT NULL,
    `retain_days`         SMALLINT UNSIGNED NOT NULL,
    `anonymize_on_expire` TINYINT(1)      NOT NULL DEFAULT 0,
    `delete_on_expire`    TINYINT(1)      NOT NULL DEFAULT 0,
    `updated_by`          BIGINT UNSIGNED NULL,
    `created_at`          TIMESTAMP       NULL,
    `updated_at`          TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_retention_entity_type` (`entity_type`),
    CONSTRAINT `fk_retention_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 8. SUPER ADMIN — admin_role_assignments
-- =============================================================
CREATE TABLE IF NOT EXISTS `admin_role_assignments` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `role`       ENUM('super_admin','admin','faculty','student') NOT NULL,
    `granted_by` BIGINT UNSIGNED NULL,
    `granted_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `revoked_at` TIMESTAMP       NULL,
    `notes`      TEXT            NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_role_assignments_user` (`user_id`),
    INDEX `idx_role_assignments_granted_by` (`granted_by`),
    CONSTRAINT `fk_role_assignments_user`       FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_role_assignments_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 9. ADMIN — courses
-- =============================================================
CREATE TABLE IF NOT EXISTS `courses` (
    `id`                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `code`                VARCHAR(30)      NOT NULL,
    `name`                VARCHAR(150)     NOT NULL,
    `department_id`       BIGINT UNSIGNED  NOT NULL,
    `semester`            TINYINT UNSIGNED NOT NULL,
    `credits`             TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `min_attendance_pct`  TINYINT UNSIGNED NOT NULL DEFAULT 75,
    `is_active`           TINYINT(1)       NOT NULL DEFAULT 1,
    `created_at`          TIMESTAMP        NULL,
    `updated_at`          TIMESTAMP        NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_courses_code` (`code`),
    INDEX `idx_courses_department_semester` (`department_id`, `semester`),
    CONSTRAINT `fk_courses_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 10. ADMIN — class_groups
-- =============================================================
CREATE TABLE IF NOT EXISTS `class_groups` (
    `id`            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(100)     NOT NULL,
    `department_id` BIGINT UNSIGNED  NOT NULL,
    `batch_year`    YEAR             NOT NULL,
    `section`       VARCHAR(10)      NOT NULL,
    `semester`      TINYINT UNSIGNED NOT NULL,
    `is_active`     TINYINT(1)       NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP        NULL,
    `updated_at`    TIMESTAMP        NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_class_groups_department_batch` (`department_id`, `batch_year`),
    CONSTRAINT `fk_class_groups_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 11. ADMIN — rooms
-- =============================================================
CREATE TABLE IF NOT EXISTS `rooms` (
    `id`                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `name`              VARCHAR(100)      NOT NULL,
    `building`          VARCHAR(100)      NOT NULL,
    `latitude`          DECIMAL(10, 8)    NULL,
    `longitude`         DECIMAL(11, 8)    NULL,
    `geofence_radius_m` SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    `beacon_id`         VARCHAR(100)      NULL,
    `wifi_ssid`         VARCHAR(100)      NULL,
    `capacity`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`         TINYINT(1)        NOT NULL DEFAULT 1,
    `created_at`        TIMESTAMP         NULL,
    `updated_at`        TIMESTAMP         NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 12. ADMIN — timetables
-- =============================================================
CREATE TABLE IF NOT EXISTS `timetables` (
    `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `course_id`      BIGINT UNSIGNED  NOT NULL,
    `class_group_id` BIGINT UNSIGNED  NOT NULL,
    `faculty_id`     BIGINT UNSIGNED  NOT NULL,
    `room_id`        BIGINT UNSIGNED  NOT NULL,
    `day_of_week`    TINYINT UNSIGNED NOT NULL COMMENT '0=Mon, 6=Sun',
    `start_time`     TIME             NOT NULL,
    `end_time`       TIME             NOT NULL,
    `effective_from` DATE             NOT NULL,
    `effective_until` DATE            NULL,
    `created_at`     TIMESTAMP        NULL,
    `updated_at`     TIMESTAMP        NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_timetables_course_day`   (`course_id`,  `day_of_week`),
    INDEX `idx_timetables_faculty_day`  (`faculty_id`, `day_of_week`),
    INDEX `idx_timetables_group`        (`class_group_id`),
    CONSTRAINT `fk_timetables_course`       FOREIGN KEY (`course_id`)      REFERENCES `courses`       (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_timetables_class_group`  FOREIGN KEY (`class_group_id`) REFERENCES `class_groups`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_timetables_faculty`      FOREIGN KEY (`faculty_id`)     REFERENCES `faculty`       (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_timetables_room`         FOREIGN KEY (`room_id`)        REFERENCES `rooms`         (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 13. ADMIN — enrollments
-- =============================================================
CREATE TABLE IF NOT EXISTS `enrollments` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`     BIGINT UNSIGNED NOT NULL,
    `course_id`      BIGINT UNSIGNED NOT NULL,
    `class_group_id` BIGINT UNSIGNED NOT NULL,
    `enrolled_at`    DATE            NOT NULL,
    `status`         ENUM('active','dropped','completed') NOT NULL DEFAULT 'active',
    `created_at`     TIMESTAMP       NULL,
    `updated_at`     TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_enrollments_student_course_group` (`student_id`, `course_id`, `class_group_id`),
    INDEX `idx_enrollments_student_status` (`student_id`,  `status`),
    INDEX `idx_enrollments_course_status`  (`course_id`,   `status`),
    INDEX `idx_enrollments_group`          (`class_group_id`),
    CONSTRAINT `fk_enrollments_student`     FOREIGN KEY (`student_id`)     REFERENCES `students`     (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_enrollments_course`      FOREIGN KEY (`course_id`)      REFERENCES `courses`      (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_enrollments_class_group` FOREIGN KEY (`class_group_id`) REFERENCES `class_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 14. DEVICE_REGISTRATIONS
-- =============================================================
CREATE TABLE IF NOT EXISTS `device_registrations` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`  BIGINT UNSIGNED NOT NULL,
    `device_hash` CHAR(64)        NOT NULL COMMENT 'SHA-256 of device identifiers',
    `public_key`  TEXT            NULL,
    `platform`    ENUM('android','ios') NOT NULL,
    `device_name` VARCHAR(150)    NULL,
    `status`      ENUM('pending','approved','revoked') NOT NULL DEFAULT 'pending',
    `approved_by` BIGINT UNSIGNED NULL,
    `approved_at` TIMESTAMP       NULL,
    `last_used_at` TIMESTAMP      NULL,
    `created_at`  TIMESTAMP       NULL,
    `updated_at`  TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_device_hash` (`device_hash`),
    INDEX `idx_device_student_status` (`student_id`, `status`),
    CONSTRAINT `fk_device_student`  FOREIGN KEY (`student_id`)  REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_device_approver` FOREIGN KEY (`approved_by`) REFERENCES `users`    (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 15. FACULTY — attendance_sessions
-- =============================================================
CREATE TABLE IF NOT EXISTS `attendance_sessions` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`            CHAR(36)        NOT NULL,
    `course_id`       BIGINT UNSIGNED NOT NULL,
    `class_group_id`  BIGINT UNSIGNED NOT NULL,
    `faculty_id`      BIGINT UNSIGNED NOT NULL,
    `room_id`         BIGINT UNSIGNED NOT NULL,
    `timetable_id`    BIGINT UNSIGNED NULL,
    `status`          ENUM('pending','active','paused','closed','finalized') NOT NULL DEFAULT 'pending',
    `started_at`      TIMESTAMP       NULL,
    `closed_at`       TIMESTAMP       NULL,
    `total_enrolled`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `total_present`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `close_reason`    VARCHAR(255)    NULL,
    `created_at`      TIMESTAMP       NULL,
    `updated_at`      TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sessions_uuid` (`uuid`),
    INDEX `idx_sessions_course_start`  (`course_id`,  `started_at`),
    INDEX `idx_sessions_faculty_start` (`faculty_id`, `started_at`),
    INDEX `idx_sessions_status_start`  (`status`,     `started_at`),
    CONSTRAINT `fk_sessions_course`       FOREIGN KEY (`course_id`)      REFERENCES `courses`      (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sessions_class_group`  FOREIGN KEY (`class_group_id`) REFERENCES `class_groups` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sessions_faculty`      FOREIGN KEY (`faculty_id`)     REFERENCES `faculty`      (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sessions_room`         FOREIGN KEY (`room_id`)        REFERENCES `rooms`        (`id`),
    CONSTRAINT `fk_sessions_timetable`    FOREIGN KEY (`timetable_id`)   REFERENCES `timetables`   (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 16. FACULTY — qr_challenges  (audit trail; active ones in Redis)
-- =============================================================
CREATE TABLE IF NOT EXISTS `qr_challenges` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`  BIGINT UNSIGNED NOT NULL,
    `jti_hash`    CHAR(64)        NOT NULL COMMENT 'SHA-256 of the random jti UUID',
    `slot_no`     BIGINT UNSIGNED NOT NULL COMMENT 'floor(unix_timestamp / 30)',
    `not_before`  TIMESTAMP       NOT NULL,
    `expires_at`  TIMESTAMP       NOT NULL,
    `room_id`     BIGINT UNSIGNED NOT NULL,
    `was_used`    TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_qr_jti_hash` (`jti_hash`),
    INDEX `idx_qr_session_slot`  (`session_id`, `slot_no`),
    INDEX `idx_qr_expires`       (`expires_at`),
    CONSTRAINT `fk_qr_session` FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_qr_room`    FOREIGN KEY (`room_id`)    REFERENCES `rooms`               (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 17. FACULTY — attendance_records  (CORE TABLE)
-- =============================================================
CREATE TABLE IF NOT EXISTS `attendance_records` (
    `id`                    BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `attendance_session_id` BIGINT UNSIGNED  NOT NULL,
    `student_id`            BIGINT UNSIGNED  NOT NULL,
    `status`                ENUM('present','late','pending_review','rejected','absent','manual_override')
                            NOT NULL,
    `marked_at`             TIMESTAMP        NULL,
    `risk_score`            TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `device_id`             BIGINT UNSIGNED  NULL,
    `latitude`              DECIMAL(10, 8)   NULL,
    `longitude`             DECIMAL(11, 8)   NULL,
    `gps_accuracy_m`        FLOAT            NULL,
    `evidence_json`         JSON             NULL COMMENT 'Full raw scan payload',
    `override_by`           BIGINT UNSIGNED  NULL,
    `override_reason`       TEXT             NULL,
    `created_at`            TIMESTAMP        NULL,
    `updated_at`            TIMESTAMP        NULL,
    PRIMARY KEY (`id`),
    -- Critical: prevent duplicate attendance for same student in same session
    UNIQUE KEY `uq_attendance_session_student` (`attendance_session_id`, `student_id`),
    INDEX `idx_attendance_student_time`   (`student_id`,            `marked_at`),
    INDEX `idx_attendance_session_status` (`attendance_session_id`, `status`),
    INDEX `idx_attendance_risk`           (`risk_score`),
    CONSTRAINT `fk_attendance_session`  FOREIGN KEY (`attendance_session_id`) REFERENCES `attendance_sessions`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendance_student`  FOREIGN KEY (`student_id`)            REFERENCES `students`             (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attendance_device`   FOREIGN KEY (`device_id`)             REFERENCES `device_registrations` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_attendance_override` FOREIGN KEY (`override_by`)           REFERENCES `users`                (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 18. ADMIN — proxy_flags
-- =============================================================
CREATE TABLE IF NOT EXISTS `proxy_flags` (
    `id`                    BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `attendance_record_id`  BIGINT UNSIGNED  NOT NULL,
    `severity`              ENUM('low','medium','high') NOT NULL,
    `reason_code`           VARCHAR(80)      NOT NULL,
    `risk_score`            TINYINT UNSIGNED NOT NULL,
    `review_status`         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `reviewed_by`           BIGINT UNSIGNED  NULL,
    `reviewed_at`           TIMESTAMP        NULL,
    `reviewer_notes`        TEXT             NULL,
    `created_at`            TIMESTAMP        NULL,
    `updated_at`            TIMESTAMP        NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_proxy_record`          (`attendance_record_id`),
    INDEX `idx_proxy_review_severity` (`review_status`, `severity`),
    CONSTRAINT `fk_proxy_attendance_record` FOREIGN KEY (`attendance_record_id`) REFERENCES `attendance_records` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_proxy_reviewer`          FOREIGN KEY (`reviewed_by`)          REFERENCES `users`              (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 19. AUDIT_LOGS  — APPEND-ONLY, never UPDATE or DELETE
-- =============================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_id`    BIGINT UNSIGNED NULL,
    `actor_role`  VARCHAR(30)     NULL,
    `action`      VARCHAR(100)    NOT NULL COMMENT 'e.g. attendance.override, session.close',
    `entity_type` VARCHAR(80)     NOT NULL COMMENT 'e.g. AttendanceRecord',
    `entity_id`   BIGINT UNSIGNED NULL,
    `old_values`  JSON            NULL,
    `new_values`  JSON            NULL,
    `ip_address`  VARCHAR(45)     NULL,
    `user_agent`  TEXT            NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_audit_actor`  (`actor_id`,    `created_at`),
    INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
    INDEX `idx_audit_action` (`action`),
    CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 20. SESSION_EXPORTS
-- =============================================================
CREATE TABLE IF NOT EXISTS `session_exports` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`   BIGINT UNSIGNED NOT NULL,
    `requested_by` BIGINT UNSIGNED NOT NULL,
    `format`       ENUM('pdf','csv','xlsx') NOT NULL,
    `status`       ENUM('queued','processing','ready','failed') NOT NULL DEFAULT 'queued',
    `file_path`    VARCHAR(255)    NULL,
    `expires_at`   TIMESTAMP       NULL,
    `created_at`   TIMESTAMP       NULL,
    `updated_at`   TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_exports_session` (`session_id`),
    CONSTRAINT `fk_exports_session`   FOREIGN KEY (`session_id`)   REFERENCES `attendance_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_exports_requester` FOREIGN KEY (`requested_by`) REFERENCES `users`               (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 21. PERSONAL_ACCESS_TOKENS  (Laravel Sanctum)
-- =============================================================
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tokenable_type` VARCHAR(255)    NOT NULL,
    `tokenable_id`   BIGINT UNSIGNED NOT NULL,
    `name`           VARCHAR(255)    NOT NULL,
    `token`          VARCHAR(64)     NOT NULL,
    `abilities`      TEXT            NULL,
    `last_used_at`   TIMESTAMP       NULL,
    `expires_at`     TIMESTAMP       NULL,
    `created_at`     TIMESTAMP       NULL,
    `updated_at`     TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token` (`token`),
    INDEX `idx_tokenable` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- SEED: default security policy
-- =============================================================
INSERT INTO `security_policies` (
    `policy_name`, `qr_expiry_seconds`, `risk_auto_reject`,
    `risk_pending_review`, `late_threshold_mins`, `geofence_radius_m`,
    `device_binding_required`, `clock_skew_seconds`, `is_active`,
    `created_at`, `updated_at`
) VALUES (
    'default', 30, 80, 50, 10, 50, 1, 5, 1,
    NOW(), NOW()
);

-- SEED: default system settings
INSERT INTO `system_settings` (`key`, `value`, `data_type`, `description`, `is_public`, `created_at`, `updated_at`) VALUES
('app_name',               'QR Attendance System',  'string',  'Application display name',              1, NOW(), NOW()),
('qr_rotation_seconds',    '30',                    'integer', 'QR challenge rotation interval',        0, NOW(), NOW()),
('max_devices_per_student','1',                      'integer', 'Maximum approved devices per student',  0, NOW(), NOW()),
('attendance_window_mins', '120',                    'integer', 'How long a session can stay active',    0, NOW(), NOW());

-- SEED: default retention policies
INSERT INTO `data_retention_policies` (`entity_type`, `retain_days`, `anonymize_on_expire`, `delete_on_expire`, `created_at`, `updated_at`) VALUES
('evidence_json', 365, 0, 0, NOW(), NOW()),
('proxy_flags',   730, 0, 0, NOW(), NOW()),
('audit_logs',    730, 0, 0, NOW(), NOW()),
('qr_challenges',  90, 0, 1, NOW(), NOW());
