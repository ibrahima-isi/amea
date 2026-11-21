-- MariaDB 10.11 canonical member-operation definitions for enrollment verification.
CREATE TABLE `member_file_lock` (
  `id` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `file_operations` (
  `operation_id` varchar(100) NOT NULL,
  `backend` varchar(64) NOT NULL,
  `reason` varchar(50) NOT NULL,
  `keys_json` text NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `expected_identity` varchar(255) DEFAULT NULL,
  `expected_cv` varchar(255) DEFAULT NULL,
  `expected_deleted_at` datetime DEFAULT NULL,
  `started` tinyint(4) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`operation_id`),
  KEY `file_operations_backend` (`backend`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

