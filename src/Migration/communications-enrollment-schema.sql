-- MariaDB 10.11 canonical communications and daily mail quota definitions for enrollment verification.
CREATE TABLE `communications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('BULK','CGU_REMINDER') NOT NULL DEFAULT 'BULK',
  `subject` varchar(255) NOT NULL,
  `body` mediumtext NOT NULL,
  `filters_json` text DEFAULT NULL,
  `status` enum('QUEUED','SENDING','PAUSED_LIMIT','COMPLETED','COMPLETED_WITH_ERRORS','CANCELLED') NOT NULL DEFAULT 'QUEUED',
  `total_recipients` int(11) NOT NULL DEFAULT 0,
  `sent_count` int(11) NOT NULL DEFAULT 0,
  `failed_count` int(11) NOT NULL DEFAULT 0,
  `cancelled_count` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_communications_type_created` (`type`,`created_at`),
  KEY `idx_communications_status` (`status`),
  KEY `idx_communications_created_by` (`created_by`),
  CONSTRAINT `communications_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `communication_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `communication_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `status` enum('PENDING','SENDING','SENT','FAILED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `next_attempt_at` datetime NOT NULL DEFAULT current_timestamp(),
  `claimed_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comm_recipients_dispatch` (`communication_id`,`status`,`next_attempt_at`),
  KEY `idx_comm_recipients_due` (`status`,`next_attempt_at`),
  KEY `idx_comm_recipients_student` (`student_id`),
  CONSTRAINT `comm_recipients_comm_fk` FOREIGN KEY (`communication_id`) REFERENCES `communications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comm_recipients_student_fk` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `mail_daily_usage` (
  `usage_date` date NOT NULL,
  `sent_count` int(11) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usage_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
