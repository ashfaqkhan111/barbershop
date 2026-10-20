-- Booking / reservation schema (MySQL 8+ / MariaDB 10.5+, InnoDB, utf8mb4).
--
-- The booking pages (booking-service, booking-date-and-time, booking-confirmation,
-- dashboard, reservation-history, reservations-list, schadule) query these tables
-- through getDatabaseConnection() (PDO MySQL). They are kept separate from
-- sql/schema.sql, which is the PostgreSQL users/services starter schema.
--
--   mysql -u <user> -p <database> < sql/booking_schema.sql
--
-- Payment data lives in `payments`, linked to `reservations` by reservation_id.
-- `reservations.total_price` holds the price of the reservation itself.

CREATE TABLE IF NOT EXISTS customers (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(120) NOT NULL,
  whatsapp      VARCHAR(20)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_email (email),
  UNIQUE KEY uq_customers_whatsapp (whatsapp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Server-side price catalog. The confirmation endpoint reads prices from here,
-- never from the browser.
CREATE TABLE IF NOT EXISTS booking_catalog_items (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category         ENUM('service','hairstyle','addon') NOT NULL,
  name             VARCHAR(100) NOT NULL,
  description      TEXT NULL,
  price            INT UNSIGNED NOT NULL DEFAULT 0,
  duration_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  is_default       TINYINT(1) NOT NULL DEFAULT 0,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  sort_order       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_catalog_category (category, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per bookable start time. `reserved_count` is incremented when a
-- reservation is confirmed; a slot is full once it reaches `capacity`.
CREATE TABLE IF NOT EXISTS booking_time_slots (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slot_date      DATE NOT NULL,
  start_time     TIME NOT NULL,
  capacity       INT UNSIGNED NOT NULL DEFAULT 1,
  reserved_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slot (slot_date, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reservation details only. Payment method/status are NOT stored here any more;
-- see `payments`. `amount` was renamed to `total_price`.
CREATE TABLE IF NOT EXISTS reservations (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id      INT UNSIGNED NOT NULL,
  reservation_code VARCHAR(40)  NOT NULL,
  request_token    CHAR(36)     NOT NULL,
  status           VARCHAR(24)  NOT NULL DEFAULT 'pending_confirmation',
  service_name     VARCHAR(100) NOT NULL,
  hairstyle_name   VARCHAR(100) NULL,
  additional_service VARCHAR(255) NULL,
  barber_name      VARCHAR(100) NULL,
  barber_level     VARCHAR(50)  NULL,
  appointment_date DATE NOT NULL,
  start_time       TIME NOT NULL,
  end_time         TIME NOT NULL,
  total_price      INT UNSIGNED NOT NULL,
  booked_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reservations_code (reservation_code),
  -- Duplicate-submit guard: the same confirmation request can only save once.
  UNIQUE KEY uq_reservations_request_token (request_token),
  KEY idx_reservations_customer (customer_id, appointment_date, start_time),
  KEY idx_reservations_slot (appointment_date, start_time, status),
  CONSTRAINT fk_reservations_customer FOREIGN KEY (customer_id) REFERENCES customers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add-ons chosen for a reservation (name/price are a snapshot at booking time).
CREATE TABLE IF NOT EXISTS reservation_additional_services (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id  INT UNSIGNED NOT NULL,
  catalog_item_id INT UNSIGNED NULL,
  name            VARCHAR(100) NOT NULL,
  price           INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_ras_reservation (reservation_id),
  CONSTRAINT fk_ras_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE CASCADE,
  CONSTRAINT fk_ras_catalog FOREIGN KEY (catalog_item_id) REFERENCES booking_catalog_items (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment records, one or more per reservation, so attempts and refunds can be
-- tracked independently of the reservation itself.
CREATE TABLE IF NOT EXISTS payments (
  payment_id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id        INT UNSIGNED NOT NULL,
  payment_date          DATETIME NULL COMMENT 'set when the payment is actually received',
  amount                INT UNSIGNED NOT NULL,
  payment_method        VARCHAR(40)  NOT NULL,
  payment_status        ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  transaction_reference VARCHAR(80)  NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (payment_id),
  UNIQUE KEY uq_payments_reference (transaction_reference),
  KEY idx_payments_reservation (reservation_id),
  CONSTRAINT fk_payments_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Upgrading an existing database that still has the old reservations columns:
--
--   CREATE TABLE payments ...                       -- (as above)
--   INSERT INTO payments (reservation_id, amount, payment_method, payment_status, transaction_reference)
--     SELECT id, amount, COALESCE(payment_method, 'Bayar di Barbershop'),
--            CASE WHEN payment_status IN ('Lunas', 'paid') THEN 'paid'
--                 WHEN payment_status = 'Dikembalikan' THEN 'refunded'
--                 ELSE 'pending' END,
--            CONCAT('PAY-', reservation_code)
--     FROM reservations;
--   ALTER TABLE reservations
--     CHANGE COLUMN amount total_price INT UNSIGNED NOT NULL,
--     DROP COLUMN payment_method,
--     DROP COLUMN payment_status;
-- ---------------------------------------------------------------------------
