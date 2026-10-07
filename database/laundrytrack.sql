-- LaundryTrack database (MySQL 5.7+ / MariaDB 10.3+)
-- Import in phpMyAdmin (Import tab) or: mysql -u root < database/laundrytrack.sql
-- Then open http://localhost/laundrytrack/setup.php to create the first admin.

CREATE DATABASE IF NOT EXISTS laundrytrack
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laundrytrack;

-- Admins and staff. Customers don't have accounts; they track with order no. + phone.
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  phone      VARCHAR(20)  NOT NULL,          -- digits only, e.g. 09171234567
  email      VARCHAR(190) NULL,
  address    VARCHAR(255) NULL,
  notes      VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_customers_phone (phone),
  KEY idx_customers_name (name),
  CONSTRAINT fk_customers_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(60) NOT NULL,
  price_per_kg DECIMAL(8,2) NOT NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  sort_order   INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no     VARCHAR(20) NULL,             -- LAU-1001, set in the same DB transaction as the insert
  customer_id  INT UNSIGNED NOT NULL,
  service_id   INT UNSIGNED NOT NULL,
  weight_kg    DECIMAL(6,2) NOT NULL,
  price_per_kg DECIMAL(8,2) NOT NULL,        -- price at the time of the order
  amount_due   DECIMAL(10,2) NOT NULL,
  notes        VARCHAR(255) NULL,
  status       ENUM('Received','Washing','Drying','Folding','Ready for Pickup','Completed') NOT NULL DEFAULT 'Received',
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  UNIQUE KEY uq_orders_no (order_no),
  KEY idx_orders_status (status),
  KEY idx_orders_created (created_at),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers (id),
  CONSTRAINT fk_orders_service  FOREIGN KEY (service_id)  REFERENCES services (id),
  CONSTRAINT fk_orders_user     FOREIGN KEY (created_by)  REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per status change (the first is "Received" when the order is created).
CREATE TABLE IF NOT EXISTS order_status_history (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id   INT UNSIGNED NOT NULL,
  status     ENUM('Received','Washing','Drying','Folding','Ready for Pickup','Completed') NOT NULL,
  changed_by INT UNSIGNED NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_history_order (order_id, changed_at),
  KEY idx_history_time (changed_at),
  CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_history_user  FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments. An order is paid when SUM(amount) >= orders.amount_due.
CREATE TABLE IF NOT EXISTS transactions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  amount      DECIMAL(10,2) NOT NULL,
  method      ENUM('cash','gcash') NOT NULL DEFAULT 'cash',
  reference   VARCHAR(64) NULL,
  received_by INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tx_order (order_id),
  KEY idx_tx_created (created_at),
  CONSTRAINT fk_tx_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_tx_user  FOREIGN KEY (received_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key`   VARCHAR(64) PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed sign-ins and order lookups, for rate limiting.
CREATE TABLE IF NOT EXISTS attempts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind       ENUM('login','track') NOT NULL,
  ip         VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempts (kind, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO services (name, price_per_kg, sort_order)
SELECT * FROM (SELECT 'Wash & Fold', 45.00, 1 UNION ALL SELECT 'Wash Only', 35.00, 2 UNION ALL SELECT 'Dry Clean', 120.00, 3) s
WHERE NOT EXISTS (SELECT 1 FROM services);

INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('shop_name', 'LaundryTrack'),
  ('shop_phone', ''),
  ('shop_address', '');
