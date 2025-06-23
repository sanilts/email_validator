-- Email Validator Database Backup
-- Generated on: 2025-06-23 16:52:59

SET FOREIGN_KEY_CHECKS=0;

-- Table structure for table `activity_logs`
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `activity_logs`
INSERT INTO `activity_logs` VALUES ('1', '1', 'user_logout', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-23 18:07:28');
INSERT INTO `activity_logs` VALUES ('2', '1', 'user_login', 'Successful login', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-23 18:08:07');

-- Table structure for table `email_lists`
DROP TABLE IF EXISTS `email_lists`;
CREATE TABLE `email_lists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `email_validations`
DROP TABLE IF EXISTS `email_validations`;
CREATE TABLE `email_validations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `status` enum('valid','invalid','risky','unknown') NOT NULL,
  `validation_type` enum('single','bulk') DEFAULT 'single',
  `format_valid` tinyint(1) DEFAULT 0,
  `dns_valid` tinyint(1) DEFAULT 0,
  `smtp_valid` tinyint(1) DEFAULT 0,
  `is_disposable` tinyint(1) DEFAULT 0,
  `is_role_based` tinyint(1) DEFAULT 0,
  `is_catch_all` tinyint(1) DEFAULT 0,
  `risk_score` int(11) DEFAULT 0,
  `validation_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 3 month),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `system_settings`
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `system_settings`
INSERT INTO `system_settings` VALUES ('1', 'cache_duration_months', '3', 'Email validation cache duration in months', '2025-06-23 01:40:32');
INSERT INTO `system_settings` VALUES ('2', 'max_bulk_emails', '10000', 'Maximum emails allowed in bulk validation', '2025-06-23 01:40:32');
INSERT INTO `system_settings` VALUES ('3', 'rate_limit_per_hour', '1000', 'Rate limit for validations per hour per user', '2025-06-23 01:40:32');
INSERT INTO `system_settings` VALUES ('4', 'company_name', 'Email Validator', 'Company name for branding', '2025-06-23 01:40:32');
INSERT INTO `system_settings` VALUES ('5', 'company_logo', '', 'Company logo file path', '2025-06-23 01:40:32');

-- Table structure for table `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `users`
INSERT INTO `users` VALUES ('1', 'admin', 'sanilts@gmail.com', '$2y$10$zmZMeRkVMgMykpUVnshkeOv6W9EMgSgedfP1UD7RKvUwhBRV0SQwe', 'admin', '2025-06-23 01:40:55', '2025-06-23 18:08:20', '1', '2025-06-23 18:08:20');

SET FOREIGN_KEY_CHECKS=1;
