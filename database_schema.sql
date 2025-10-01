-- V2LX Authentication System Database Schema
-- Run this SQL to create the necessary tables for the authentication system

CREATE DATABASE IF NOT EXISTS auth_system;
USE auth_system;

-- Licenses table - stores license information
CREATE TABLE IF NOT EXISTS licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(64) UNIQUE NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_license_key (license_key),
    INDEX idx_user_email (user_email),
    INDEX idx_expires_at (expires_at)
);

-- HWID registrations table - stores hardware ID bindings
CREATE TABLE IF NOT EXISTS hwid_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(64) NOT NULL,
    hwid VARCHAR(64) NOT NULL,
    first_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_license_hwid (license_key, hwid),
    INDEX idx_license_key (license_key),
    INDEX idx_hwid (hwid),
    FOREIGN KEY (license_key) REFERENCES licenses(license_key) ON DELETE CASCADE
);

-- Authentication logs table - logs all authentication attempts
CREATE TABLE IF NOT EXISTS auth_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(64) NOT NULL,
    hwid VARCHAR(64) NOT NULL,
    version VARCHAR(50),
    client_type VARCHAR(50),
    ip_address VARCHAR(45),
    success BOOLEAN NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_hwid (hwid),
    INDEX idx_created_at (created_at),
    INDEX idx_success (success)
);

-- Admin users table - for admin authentication
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- Insert default admin user (password: admin123 - CHANGE THIS!)
-- Use a proper password hashing function in production
INSERT INTO admin_users (username, password_hash, email) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com')
ON DUPLICATE KEY UPDATE username = username;

-- Create indexes for better performance
CREATE INDEX idx_licenses_active ON licenses(is_active);
CREATE INDEX idx_auth_logs_license_success ON auth_logs(license_key, success);
CREATE INDEX idx_hwid_registrations_last_used ON hwid_registrations(last_used);

-- Create a view for license statistics
CREATE OR REPLACE VIEW license_stats AS
SELECT 
    COUNT(*) as total_licenses,
    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_licenses,
    COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as non_expired_licenses,
    COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_licenses
FROM licenses;

-- Create a view for recent authentication attempts
CREATE OR REPLACE VIEW recent_auth_attempts AS
SELECT 
    al.license_key,
    al.hwid,
    al.version,
    al.client_type,
    al.ip_address,
    al.success,
    al.created_at,
    l.user_name,
    l.user_email
FROM auth_logs al
LEFT JOIN licenses l ON al.license_key = l.license_key
ORDER BY al.created_at DESC
LIMIT 100;

-- Sample data for testing (optional)
-- INSERT INTO licenses (license_key, user_name, user_email, expires_at) VALUES
-- ('TEST-LICENSE-KEY-1234567890ABCDEF', 'Test User', 'test@example.com', DATE_ADD(NOW(), INTERVAL 30 DAY));

-- Create a stored procedure to clean up old logs
DELIMITER //
CREATE PROCEDURE CleanupOldLogs(IN days_to_keep INT)
BEGIN
    DELETE FROM auth_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
END //
DELIMITER ;

-- Create a stored procedure to get license usage statistics
DELIMITER //
CREATE PROCEDURE GetLicenseStats(IN license_key_param VARCHAR(64))
BEGIN
    SELECT 
        l.license_key,
        l.user_name,
        l.user_email,
        l.created_at,
        l.expires_at,
        l.is_active,
        h.hwid,
        h.first_used,
        h.last_used,
        COUNT(al.id) as total_auth_attempts,
        COUNT(CASE WHEN al.success = 1 THEN 1 END) as successful_auths,
        COUNT(CASE WHEN al.success = 0 THEN 1 END) as failed_auths,
        MAX(al.created_at) as last_auth_attempt
    FROM licenses l
    LEFT JOIN hwid_registrations h ON l.license_key = h.license_key
    LEFT JOIN auth_logs al ON l.license_key = al.license_key
    WHERE l.license_key = license_key_param
    GROUP BY l.license_key, h.hwid;
END //
DELIMITER ;
