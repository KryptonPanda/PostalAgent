-- Postal Email Dashboard Database Installation Script
-- Compatible with MySQL 5.7+ and PHP 8.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Create database (uncomment if needed)
-- CREATE DATABASE IF NOT EXISTS `postal_dashboard` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `postal_dashboard`;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `recipients`
-- --------------------------------------------------------

CREATE TABLE `recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `email_templates`
-- --------------------------------------------------------

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `subject` text NOT NULL,
  `content` longtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `emails`
-- --------------------------------------------------------

CREATE TABLE `emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `postal_message_id` varchar(255) DEFAULT NULL,
  `from_email` varchar(255) NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `subject` text NOT NULL,
  `content` longtext NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `bounce_type` varchar(50) DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `unsubscribed_at` timestamp NULL DEFAULT NULL,
  `spam_reported_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `postal_message_id` (`postal_message_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `template_id` (`template_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `email_stats`
-- --------------------------------------------------------

CREATE TABLE `email_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `total_sent` int(11) NOT NULL DEFAULT 0,
  `total_delivered` int(11) NOT NULL DEFAULT 0,
  `total_bounced` int(11) NOT NULL DEFAULT 0,
  `total_spam` int(11) NOT NULL DEFAULT 0,
  `total_opened` int(11) NOT NULL DEFAULT 0,
  `total_clicked` int(11) NOT NULL DEFAULT 0,
  `total_unsubscribed` int(11) NOT NULL DEFAULT 0,
  `soft_bounces` int(11) NOT NULL DEFAULT 0,
  `hard_bounces` int(11) NOT NULL DEFAULT 0,
  `delivery_rate` decimal(5,2) DEFAULT NULL,
  `bounce_rate` decimal(5,2) DEFAULT NULL,
  `spam_rate` decimal(5,2) DEFAULT NULL,
  `open_rate` decimal(5,2) DEFAULT NULL,
  `click_rate` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `settings`
-- --------------------------------------------------------

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Add foreign key constraints
-- --------------------------------------------------------

ALTER TABLE `emails`
  ADD CONSTRAINT `emails_recipient_fk` FOREIGN KEY (`recipient_id`) REFERENCES `recipients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `emails_template_fk` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL;

-- --------------------------------------------------------
-- Insert default admin user
-- Password: Mbg$MeM7709123 (hashed with PHP password_hash)
-- --------------------------------------------------------

INSERT INTO `users` (`username`, `password`, `email`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@yourdomain.com');

-- --------------------------------------------------------
-- Insert default settings
-- --------------------------------------------------------

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('postal_hostname', 'postal3.clfaceverifiy.com'),
('postal_api_key', 'KFBcjBpjIZQbUq3AMyfhDw0c'),
('postal_domain', 'bmh3.clfaceverifiy.com'),
('default_from_email', 'hello@bmh3.clfaceverifiy.com'),
('webhook_enabled', '1'),
('tracking_enabled', '1'),
('app_name', 'Postal Email Dashboard'),
('app_version', '1.0.0');

-- --------------------------------------------------------
-- Insert sample email template
-- --------------------------------------------------------

INSERT INTO `email_templates` (`name`, `subject`, `content`) VALUES
('Welcome Email', 'Welcome to our platform!', '<h1>Welcome!</h1><p>Thank you for joining our platform. We''re excited to have you on board.</p><p>Best regards,<br>The Team</p>'),
('Newsletter', 'Monthly Newsletter', '<h1>Monthly Update</h1><p>Here''s what''s new this month...</p><p>Stay tuned for more updates!</p>');

COMMIT;

-- Installation completed successfully
-- Default login: admin / Mbg$MeM7709123