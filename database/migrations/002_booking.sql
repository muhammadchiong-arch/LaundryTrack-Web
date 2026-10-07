-- Upgrade a LaundryTrack database created before online booking (October 2026).
-- Run once in phpMyAdmin (select the laundrytrack database, then Import) or:
--   mysql -u root laundrytrack < database/migrations/002_booking.sql
-- Uses MariaDB's IF NOT EXISTS, so running it twice is harmless.
USE laundrytrack;

ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER email,
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash,
  ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER created_by;
-- Emails typed for walk-ins must be unique before the unique key can be added.
UPDATE customers c JOIN (SELECT email, MIN(id) keep_id FROM customers WHERE email IS NOT NULL GROUP BY email HAVING COUNT(*) > 1) d
  ON c.email = d.email AND c.id <> d.keep_id SET c.email = NULL;
ALTER TABLE customers ADD UNIQUE KEY IF NOT EXISTS uq_customers_email (email);

ALTER TABLE services ADD COLUMN IF NOT EXISTS description VARCHAR(160) NOT NULL DEFAULT '' AFTER name;

CREATE TABLE IF NOT EXISTS bookings (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_no    VARCHAR(20) NULL,
  customer_id   INT UNSIGNED NOT NULL,
  service_id    INT UNSIGNED NOT NULL,
  slot_date     DATE NOT NULL,
  slot_time     TIME NOT NULL,
  est_weight_kg DECIMAL(6,2) NULL,
  notes         VARCHAR(255) NULL,
  status        ENUM('Pending','Confirmed','Rejected','Cancelled','No-show','Expired','Dropped off') NOT NULL DEFAULT 'Pending',
  status_reason VARCHAR(255) NULL,
  handled_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bookings_no (booking_no),
  KEY idx_bookings_slot (slot_date, slot_time, status),
  KEY idx_bookings_customer (customer_id, status),
  KEY idx_bookings_status (status, slot_date),
  CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id) REFERENCES customers (id),
  CONSTRAINT fk_bookings_service  FOREIGN KEY (service_id)  REFERENCES services (id),
  CONSTRAINT fk_bookings_user     FOREIGN KEY (handled_by)  REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
  MODIFY status ENUM('Received','Washing','Drying','Folding','Ready for Pickup','Completed','Cancelled') NOT NULL DEFAULT 'Received',
  ADD COLUMN IF NOT EXISTS booking_id INT UNSIGNED NULL AFTER service_id,
  ADD COLUMN IF NOT EXISTS cancel_reason VARCHAR(255) NULL AFTER status;
ALTER TABLE orders ADD UNIQUE KEY IF NOT EXISTS uq_orders_booking (booking_id);
ALTER TABLE orders ADD CONSTRAINT fk_orders_booking FOREIGN KEY IF NOT EXISTS (booking_id) REFERENCES bookings (id);

ALTER TABLE order_status_history
  MODIFY status ENUM('Received','Washing','Drying','Folding','Ready for Pickup','Completed','Cancelled') NOT NULL,
  ADD COLUMN IF NOT EXISTS note VARCHAR(255) NULL AFTER status;

ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS kind ENUM('payment','refund') NOT NULL DEFAULT 'payment' AFTER order_id,
  ADD COLUMN IF NOT EXISTS voided_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS voided_by INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS void_reason VARCHAR(255) NULL;
ALTER TABLE transactions ADD CONSTRAINT fk_tx_void FOREIGN KEY IF NOT EXISTS (voided_by) REFERENCES users (id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS closed_dates (
  closed_on DATE PRIMARY KEY,
  note      VARCHAR(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  customer_id INT UNSIGNED NULL,
  action      VARCHAR(40) NOT NULL,
  detail      VARCHAR(255) NOT NULL DEFAULT '',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_time (created_at),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE attempts MODIFY kind ENUM('login','track','register') NOT NULL;

INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('open_days', '1,2,3,4,5,6'), ('open_time', '08:00'), ('close_time', '18:00'),
  ('slot_minutes', '60'), ('slot_capacity', '3'), ('booking_days', '14'), ('lead_minutes', '60');
