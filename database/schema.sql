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

-- System settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
('cache_duration_months', '3', 'Email validation cache duration in months'),
('max_bulk_emails', '10000', 'Maximum emails allowed in bulk validation'),
('rate_limit_per_hour', '1000', 'Rate limit for validations per hour per user'),
('company_name', 'Email Validator', 'Company name for branding'),
('company_logo', '', 'Company logo file path');