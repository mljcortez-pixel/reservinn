-- ============================================================
--  ReservInn — Enhanced Database Schema
--  Compatible with XAMPP / phpMyAdmin (MySQL 5.7+)
-- ============================================================

SET SQL_MODE   = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone  = "+00:00";

CREATE DATABASE IF NOT EXISTS `reservinn`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `reservinn`;

-- ── Roles ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_roles` (
  `role_id`   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(20)      NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uk_role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `user_roles` (`role_id`,`role_name`) VALUES
  (1,'admin'),(2,'owner'),(3,'customer');

-- ── Users (with profile fields) ──────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`       INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(160)     NOT NULL,
  `password_hash` VARCHAR(255)     NOT NULL,
  `full_name`     VARCHAR(120)     NOT NULL,
  `phone`         VARCHAR(30)      DEFAULT NULL,
  `role_id`       TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `is_active`     TINYINT(1)       NOT NULL DEFAULT 1,
  -- Profile extras
  `bio`           TEXT             DEFAULT NULL,
  `address`       VARCHAR(255)     DEFAULT NULL,
  `profile_photo` VARCHAR(255)     DEFAULT NULL,
  `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `fk_user_role` (`role_id`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed demo users (password = "password")
INSERT IGNORE INTO `users` (`user_id`,`email`,`password_hash`,`full_name`,`phone`,`role_id`) VALUES
(1,'admin@reservinn.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Admin User',   '+63 900 000 0001', 1),
(2,'owner@reservinn.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Maria Santos',  '+63 917 123 4567', 2),
(3,'customer@reservinn.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Juan dela Cruz','+63 912 987 6543', 3);

-- ── Resorts ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `resorts` (
  `resort_id`        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `owner_id`         INT UNSIGNED    NOT NULL,
  `name`             VARCHAR(160)    NOT NULL,
  `description`      TEXT            DEFAULT NULL,
  `location_city`    VARCHAR(80)     DEFAULT NULL,
  `location_address` VARCHAR(255)    DEFAULT NULL,
  `price_per_night`  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `max_guests`       SMALLINT        NOT NULL DEFAULT 1,
  `is_available`     TINYINT(1)      NOT NULL DEFAULT 1,
  `image_path`       VARCHAR(255)    DEFAULT NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`resort_id`),
  KEY `fk_resort_owner` (`owner_id`),
  CONSTRAINT `fk_resort_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `resorts` (`resort_id`,`owner_id`,`name`,`description`,`location_city`,`location_address`,`price_per_night`,`max_guests`,`is_available`) VALUES
(1,2,'Villa Natividad','A stunning hilltop private villa with infinity pool and panoramic ocean views. Perfect for family gatherings and special occasions.','Batangas','Barangay Anilao, Mabini, Batangas',8500.00,20,1),
(2,2,'Azure Cove Resort','Nestled beside a pristine beach, Azure Cove offers a full-service experience with private waterfront access and modern amenities.','Palawan','El Nido, Palawan',12000.00,15,1);

-- ── Booking Status ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `booking_status` (
  `status_id`   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `status_name` VARCHAR(30)      NOT NULL,
  PRIMARY KEY (`status_id`),
  UNIQUE KEY `uk_status_name` (`status_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `booking_status` (`status_id`,`status_name`) VALUES
  (1,'pending'),
  (2,'confirmed'),
  (3,'cancelled'),
  (4,'completed'),
  (5,'rejected'),
  (6,'paid');

-- ── Bookings (with arrival/departure time) ────────────────────
CREATE TABLE IF NOT EXISTS `bookings` (
  `booking_id`       INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `resort_id`        INT UNSIGNED     NOT NULL,
  `customer_id`      INT UNSIGNED     NOT NULL,
  `check_in_date`    DATE             NOT NULL,
  `check_out_date`   DATE             NOT NULL,
  `arrival_time`     TIME             DEFAULT '14:00:00'  COMMENT 'Expected check-in time',
  `departure_time`   TIME             DEFAULT '12:00:00'  COMMENT 'Expected check-out time',
  `guest_count`      SMALLINT         NOT NULL DEFAULT 1,
  `total_price`      DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
  `special_requests` TEXT             DEFAULT NULL,
  `status_id`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  KEY `fk_booking_resort`   (`resort_id`),
  KEY `fk_booking_customer` (`customer_id`),
  KEY `fk_booking_status`   (`status_id`),
  CONSTRAINT `fk_booking_resort`   FOREIGN KEY (`resort_id`)   REFERENCES `resorts` (`resort_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_booking_customer` FOREIGN KEY (`customer_id`) REFERENCES `users`   (`user_id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_booking_status`   FOREIGN KEY (`status_id`)   REFERENCES `booking_status` (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample bookings
INSERT IGNORE INTO `bookings` (`booking_id`,`resort_id`,`customer_id`,`check_in_date`,`check_out_date`,`arrival_time`,`departure_time`,`guest_count`,`total_price`,`status_id`) VALUES
(1,1,3,'2025-05-10','2025-05-13','14:00:00','12:00:00',6,25500.00,4),
(2,2,3,'2025-06-01','2025-06-05','15:00:00','11:00:00',4,48000.00,1);

-- ── Payments ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `booking_id`            INT UNSIGNED  NOT NULL,
  `amount`                DECIMAL(10,2) NOT NULL,
  `payment_method`        VARCHAR(30)   DEFAULT NULL,
  `transaction_reference` VARCHAR(100)  DEFAULT NULL,
  `payment_status`        VARCHAR(20)   NOT NULL DEFAULT 'pending',
  `payment_type`          VARCHAR(20)   NOT NULL DEFAULT 'full_payment' COMMENT 'full_payment | reservation_fee | balance',
  `remaining_balance`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `paid_on_arrival`       TINYINT(1)    NOT NULL DEFAULT 0,
  `paid_at`               DATETIME      DEFAULT NULL,
  `created_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `fk_payment_booking` (`booking_id`),
  CONSTRAINT `fk_payment_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Reviews ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `reviews` (
  `review_id`   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `resort_id`   INT UNSIGNED    NOT NULL,
  `customer_id` INT UNSIGNED    NOT NULL,
  `booking_id`  INT UNSIGNED    NOT NULL,
  `rating`      TINYINT         NOT NULL DEFAULT 5,
  `comment`     TEXT            DEFAULT NULL,
  `is_approved` TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  KEY `fk_review_resort`   (`resort_id`),
  KEY `fk_review_customer` (`customer_id`),
  KEY `fk_review_booking`  (`booking_id`),
  CONSTRAINT `fk_review_resort`   FOREIGN KEY (`resort_id`)   REFERENCES `resorts`  (`resort_id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_review_customer` FOREIGN KEY (`customer_id`) REFERENCES `users`    (`user_id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_review_booking`  FOREIGN KEY (`booking_id`)  REFERENCES `bookings` (`booking_id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Resort Availability Blocks ────────────────────────────────
CREATE TABLE IF NOT EXISTS `resort_availability` (
  `block_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resort_id`  INT UNSIGNED NOT NULL,
  `start_date` DATE         NOT NULL,
  `end_date`   DATE         NOT NULL,
  `reason`     VARCHAR(50)  DEFAULT 'maintenance',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`block_id`),
  KEY `fk_avail_resort` (`resort_id`),
  CONSTRAINT `fk_avail_resort` FOREIGN KEY (`resort_id`) REFERENCES `resorts` (`resort_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Notifications ─────────────────────────────────────────────
-- type: 'booking_new' | 'booking_confirmed' | 'booking_rejected' |
--       'booking_cancelled' | 'review_new' | 'system'
CREATE TABLE IF NOT EXISTS `notifications` (
  `notif_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL  COMMENT 'Recipient',
  `type`        VARCHAR(30)  NOT NULL  DEFAULT 'system',
  `title`       VARCHAR(160) NOT NULL,
  `message`     TEXT         NOT NULL,
  `link`        VARCHAR(255) DEFAULT NULL COMMENT 'Optional action URL',
  `is_read`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notif_id`),
  KEY `fk_notif_user` (`user_id`),
  KEY `idx_notif_read` (`user_id`,`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Profile Photos upload dir (reminder comment) ──────────────
-- Create folder: reservinn/uploads/profiles/  (chmod 755)

-- ── If upgrading an existing database, run these ALTER statements ──
-- ALTER TABLE `payments`
--   ADD COLUMN IF NOT EXISTS `payment_type`      VARCHAR(20)   NOT NULL DEFAULT 'full_payment' AFTER `payment_status`,
--   ADD COLUMN IF NOT EXISTS `remaining_balance`  DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `payment_type`,
--   ADD COLUMN IF NOT EXISTS `paid_on_arrival`    TINYINT(1)    NOT NULL DEFAULT 0 AFTER `remaining_balance`;

-- ── If upgrading: add the 'paid' status ──────────────────────
-- INSERT IGNORE INTO `booking_status` (`status_id`,`status_name`) VALUES (6,'paid');

-- ── v3 Logic Update: Auto-confirm flow ──────────────────────
-- Run these if upgrading from previous version:
-- The 'rejected' status (5) is no longer used in the new flow
-- but kept for backward compatibility with existing records.
-- New flow: pending(1) → confirmed(2)[down payment] or paid(6)[full] → completed(4)
-- INSERT IGNORE INTO booking_status (status_id,status_name) VALUES (6,'paid');
