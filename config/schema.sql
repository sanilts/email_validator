-- Email Validator Database Schema
CREATE DATABASE IF NOT EXISTS email_validator;
USE email_validator;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email)
);

-- Email validations table
CREATE TABLE IF NOT EXISTS email_validations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    status ENUM('valid', 'invalid', 'risky', 'unknown') NOT NULL,
    validation_type ENUM('single', 'bulk') DEFAULT 'single',
    format_valid BOOLEAN DEFAULT FALSE,
    dns_valid BOOLEAN DEFAULT FALSE,
    smtp_valid BOOLEAN DEFAULT FALSE,
    is_disposable BOOLEAN DEFAULT FALSE,
    is_role_based BOOLEAN DEFAULT FALSE,
    is_catch_all BOOLEAN DEFAULT FALSE,
    risk_score INT DEFAULT 0,
    validation_details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 3 MONTH),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Email lists table
CREATE TABLE IF NOT EXISTS email_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Email list items table
CREATE TABLE IF NOT EXISTS email_list_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    list_id INT NOT NULL,
    validation_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (list_id) REFERENCES email_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (validation_id) REFERENCES email_validations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_list_validation (list_id, validation_id)
);

-- Email templates table
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    template_type ENUM('verification', 'notification') DEFAULT 'verification',
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (template_type)
);

-- SMTP configurations table
CREATE TABLE IF NOT EXISTS smtp_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT NOT NULL,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    encryption ENUM('none', 'ssl', 'tls') DEFAULT 'tls',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Activity logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- System settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Bulk validation jobs table
CREATE TABLE IF NOT EXISTS bulk_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    total_emails INT DEFAULT 0,
    processed_emails INT DEFAULT 0,
    valid_emails INT DEFAULT 0,
    invalid_emails INT DEFAULT 0,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- Blocked IPs table for security
CREATE TABLE IF NOT EXISTS blocked_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) UNIQUE NOT NULL,
    reason VARCHAR(255),
    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_address (ip_address),
    INDEX idx_blocked_at (blocked_at)
);

-- Insert default system settings (only if they don't exist)
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
('cache_duration_months', '3', 'Email validation cache duration in months'),
('max_bulk_emails', '10000', 'Maximum emails allowed in bulk validation'),
('rate_limit_per_hour', '1000', 'Rate limit for validations per hour per user'),
('company_name', 'Email Validator', 'Company name for branding'),
('company_logo', '', 'Company logo file path');

-- Email Validator Database Schema - COMPLETE VERSION
-- Generated for Email Validator Application

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Create database
CREATE DATABASE IF NOT EXISTS `email_validator` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `email_validator`;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `role` enum('admin','user') COLLATE utf8mb4_unicode_ci DEFAULT 'user',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `is_active` tinyint(1) DEFAULT 1,
    `last_login` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    KEY `idx_username` (`username`),
    KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email validations table
CREATE TABLE IF NOT EXISTS `email_validations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `status` enum('valid','invalid','risky','unknown') COLLATE utf8mb4_unicode_ci NOT NULL,
    `validation_type` enum('single','bulk') COLLATE utf8mb4_unicode_ci DEFAULT 'single',
    `format_valid` tinyint(1) DEFAULT 0,
    `dns_valid` tinyint(1) DEFAULT 0,
    `smtp_valid` tinyint(1) DEFAULT 0,
    `is_disposable` tinyint(1) DEFAULT 0,
    `is_role_based` tinyint(1) DEFAULT 0,
    `is_catch_all` tinyint(1) DEFAULT 0,
    `risk_score` int(11) DEFAULT 0,
    `validation_details` json DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 3 month),
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_expires_at` (`expires_at`),
    CONSTRAINT `fk_validations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email lists table
CREATE TABLE IF NOT EXISTS `email_lists` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    CONSTRAINT `fk_lists_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email list items table
CREATE TABLE IF NOT EXISTS `email_list_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `list_id` int(11) NOT NULL,
    `validation_id` int(11) NOT NULL,
    `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_list_validation` (`list_id`,`validation_id`),
    KEY `fk_list_items_validation` (`validation_id`),
    CONSTRAINT `fk_list_items_list` FOREIGN KEY (`list_id`) REFERENCES `email_lists` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_list_items_validation` FOREIGN KEY (`validation_id`) REFERENCES `email_validations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email templates table
CREATE TABLE IF NOT EXISTS `email_templates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
    `template_type` enum('verification','notification') COLLATE utf8mb4_unicode_ci DEFAULT 'verification',
    `is_default` tinyint(1) DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_type` (`template_type`),
    CONSTRAINT `fk_templates_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SMTP configurations table
CREATE TABLE IF NOT EXISTS `smtp_configs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `provider` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    `host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `port` int(11) NOT NULL,
    `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `encryption` enum('none','ssl','tls') COLLATE utf8mb4_unicode_ci DEFAULT 'tls',
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    CONSTRAINT `fk_smtp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs table
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `details` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_action` (`action`),
    CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings table
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `setting_value` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bulk jobs table
CREATE TABLE IF NOT EXISTS `bulk_jobs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `total_emails` int(11) DEFAULT 0,
    `processed_emails` int(11) DEFAULT 0,
    `valid_emails` int(11) DEFAULT 0,
    `invalid_emails` int(11) DEFAULT 0,
    `status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
    `started_at` timestamp NULL DEFAULT NULL,
    `completed_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_jobs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blocked IPs table
CREATE TABLE IF NOT EXISTS `blocked_ips` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
    `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `blocked_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `ip_address` (`ip_address`),
    KEY `idx_ip_address` (`ip_address`),
    KEY `idx_blocked_at` (`blocked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email verification codes table (for verification feature)
CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `verification_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
    `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 10 minute),
    `verified_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_email` (`user_id`,`email`),
    KEY `idx_code` (`verification_code`),
    KEY `idx_expires` (`expires_at`),
    CONSTRAINT `fk_verifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email campaigns table (for campaign feature)
CREATE TABLE IF NOT EXISTS `email_campaigns` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
    `list_id` int(11) NOT NULL,
    `status` enum('draft','sending','sent','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
    `total_recipients` int(11) DEFAULT 0,
    `sent_count` int(11) DEFAULT 0,
    `sent_at` timestamp NULL DEFAULT NULL,
    `completed_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_list_id` (`list_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_campaigns_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_campaigns_list` FOREIGN KEY (`list_id`) REFERENCES `email_lists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system settings
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('cache_duration_months', '3', 'Email validation cache duration in months'),
('max_bulk_emails', '10000', 'Maximum emails allowed in bulk validation'),
('rate_limit_per_hour', '1000', 'Rate limit for validations per hour per user'),
('company_name', 'Email Validator', 'Company name for branding'),
('company_logo', '', 'Company logo file path'),
('smtp_enabled', '1', 'Enable SMTP email sending'),
('maintenance_mode', '0', 'System maintenance mode'),
('app_version', '1.0.0', 'Application version'),
('backup_retention_days', '30', 'Number of days to keep backups');

-- Insert default admin user (password: admin123)
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `role`, `is_active`) VALUES
('admin', 'admin@emailvalidator.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

SET FOREIGN_KEY_CHECKS=1;
COMMIT;