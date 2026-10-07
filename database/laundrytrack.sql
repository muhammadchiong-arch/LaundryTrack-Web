-- LaundryTrack database (MariaDB 10.3+ as shipped with XAMPP, or MySQL 8)
-- New install: import in phpMyAdmin (Import tab) or: mysql -u root < database/laundrytrack.sql
-- Then open http://localhost/laundrytrack/setup.php to create the first admin.
-- Upgrading an install made before online booking? Run database/migrations/002_booking.sql instead.

CREATE DATABASE IF NOT EXISTS laundrytrack
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laundrytrack;

-- Shop accounts: admins and staff.
CREATE TABLE IF NOT EXISTS users (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                 VARCHAR(100) NOT NULL,
  email                VARCHAR(190) NOT NULL,
  password_hash        VARCHAR(255) NOT NULL,
  role                 ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  is_active            TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,   -- set when an admin resets the password
  last_login_at        DATETIME NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every customer. Walk-ins created at the counter have no password;
-- customers who register online have an email + password and can book.
CREATE TABLE IF NOT EXISTS customers (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  phone         VARCHAR(20)  NOT NULL,          -- digits only, e.g. 09171234567
  email         VARCHAR(190) NULL,
  password_hash VARCHAR(255) NULL,              -- NULL = no online account
  is_active     TINYINT(1) NOT NULL DEFAULT 1,  -- admins can turn an online account off
  address       VARCHAR(255) NULL,
  notes         VARCHAR(255) NULL,
  created_by    INT UNSIGNED NULL,              -- staff member, NULL when self-registered
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customers_email (email),
  KEY idx_customers_phone (phone),
  KEY idx_customers_name (name),
  CONSTRAINT fk_customers_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(60) NOT NULL,
  description  VARCHAR(160) NOT NULL DEFAULT '',
  price_per_kg DECIMAL(8,2) NOT NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  sort_order   INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Online drop-off appointments. A booking reserves one place in a time slot;
-- when the customer arrives, staff check it in and it becomes an order.
CREATE TABLE IF NOT EXISTS bookings (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_no    VARCHAR(20) NULL,                 -- BK-1001, set in the same DB transaction as the insert
  customer_id   INT UNSIGNED NOT NULL,
  service_id    INT UNSIGNED NOT NULL,
  slot_date     DATE NOT NULL,
  slot_time     TIME NOT NULL,                    -- start of the drop-off slot
  est_weight_kg DECIMAL(6,2) NULL,                -- customer's estimate; the real weight is taken at drop-off
  notes         VARCHAR(255) NULL,
  status        ENUM('Pending','Confirmed','Rejected','Cancelled','No-show','Expired','Dropped off') NOT NULL DEFAULT 'Pending',
  status_reason VARCHAR(255) NULL,                -- why it was rejected or cancelled
  handled_by    INT UNSIGNED NULL,                -- staff who last changed the status
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

CREATE TABLE IF NOT EXISTS orders (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no      VARCHAR(20) NULL,             -- LAU-1001, set in the same DB transaction as the insert
  customer_id   INT UNSIGNED NOT NULL,
  service_id    INT UNSIGNED NOT NULL,
  booking_id    INT UNSIGNED NULL,            -- the online booking this order came from, if any
  weight_kg     DECIMAL(6,2) NOT NULL,        -- actual weight at drop-off
  price_per_kg  DECIMAL(8,2) NOT NULL,        -- price at the time of the order
  amount_due    DECIMAL(10,2) NOT NULL,
  notes         VARCHAR(255) NULL,
  status        ENUM('Received','Washing','Drying','Folding','Ready for Pickup','Completed','Cancelled') NOT NULL DEFAULT 'Received',
  cancel_reason VARCHAR(255) NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at  DATETIME NULL,
  UNIQUE KEY uq_orders_no (order_no),
  UNIQUE KEY uq_orders_booking (booking_id),
  KEY idx_orders_status (status),
  KEY idx_orders_created (created_at),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers (id),
  CONSTRAINT fk_orders_service  FOREIGN KEY (service_id)  REFERENCES services (id),
  CONSTRAINT fk_orders_booking  FOREIGN KEY (booking_id)  REFERENCES bookings (id),
  CONSTRAINT fk_orders_user     FOREIGN KEY (created_by)  REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per status change (the first is "Received" when the order is created).
CREATE TABLE IF NOT EXISTS order_status_history (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id   INT UNSIGNED NOT NULL,
  status     ENUM('Received','Washing','Drying','Folding','Ready for Pickup','Completed','Cancelled') NOT NULL,
  note       VARCHAR(255) NULL,               -- e.g. "Correction" or the cancel reason
  changed_by INT UNSIGNED NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_history_order (order_id, changed_at),
  KEY idx_history_time (changed_at),
  CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_history_user  FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Money in (payment) and out (refund). Records are never deleted: a mistake is voided
-- with a reason, and voided rows are left out of every total.
CREATE TABLE IF NOT EXISTS transactions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  kind        ENUM('payment','refund') NOT NULL DEFAULT 'payment',
  amount      DECIMAL(10,2) NOT NULL,          -- always positive; refunds are subtracted
  method      ENUM('cash','gcash') NOT NULL DEFAULT 'cash',
  reference   VARCHAR(64) NULL,
  received_by INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  voided_at   DATETIME NULL,
  voided_by   INT UNSIGNED NULL,
  void_reason VARCHAR(255) NULL,
  KEY idx_tx_order (order_id),
  KEY idx_tx_created (created_at),
  CONSTRAINT fk_tx_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_tx_user  FOREIGN KEY (received_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_tx_void  FOREIGN KEY (voided_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Days the shop is closed (holidays). Weekly closed days are in settings (open_days).
CREATE TABLE IF NOT EXISTS closed_dates (
  closed_on DATE PRIMARY KEY,
  note      VARCHAR(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who did what: booking decisions, cancellations, refunds, voids, account and settings changes.
CREATE TABLE IF NOT EXISTS activity_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,               -- staff/admin; NULL when the customer acted
  customer_id INT UNSIGNED NULL,
  action      VARCHAR(40) NOT NULL,
  detail      VARCHAR(255) NOT NULL DEFAULT '',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_time (created_at),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key`   VARCHAR(64) PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed sign-ins, order lookups and registrations, for rate limiting.
CREATE TABLE IF NOT EXISTS attempts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind       ENUM('login','track','register') NOT NULL,
  ip         VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempts (kind, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO services (name, description, price_per_kg, sort_order)
SELECT * FROM (
  SELECT 'Wash & Fold', 'Washed, dried and neatly folded.', 45.00, 1 UNION ALL
  SELECT 'Wash Only', 'Washed and dried, not folded.', 35.00, 2 UNION ALL
  SELECT 'Dry Clean', 'For delicate fabrics and formal wear.', 120.00, 3
) s
WHERE NOT EXISTS (SELECT 1 FROM services);

INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('shop_name', 'LaundryTrack'),
  ('shop_phone', ''),
  ('shop_address', ''),
  ('open_days', '1,2,3,4,5,6'),   -- ISO weekdays: 1 = Monday ... 7 = Sunday
  ('open_time', '08:00'),
  ('close_time', '18:00'),
  ('slot_minutes', '60'),
  ('slot_capacity', '3'),         -- drop-off bookings accepted per slot
  ('booking_days', '14'),         -- how far ahead customers can book
  ('lead_minutes', '60');         -- earliest a slot can be booked before it starts
