-- ============================================================
-- ninjalens_db.sql
-- NinjaLens: Camera & Filming Equipment Rental System
-- Full Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS ninjalens_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ninjalens_db;

-- ============================================================
-- TABLE: administrator
-- Stores admin accounts (role-based access)
-- ============================================================
CREATE TABLE IF NOT EXISTS administrator (
    admin_id     INT AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(100)        NOT NULL,
    email        VARCHAR(150)        NOT NULL UNIQUE,
    password     VARCHAR(255)        NOT NULL,   -- Stores hashed password
    created_at   TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: customer
-- Stores customer/renter accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS customer (
    customer_id  INT AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(100)        NOT NULL,
    email        VARCHAR(150)        NOT NULL UNIQUE,
    password     VARCHAR(255)        NOT NULL,
    phone        VARCHAR(20)         DEFAULT NULL,
    address      TEXT                DEFAULT NULL,
    has_penalty  TINYINT(1)          NOT NULL DEFAULT 0,  -- 1 = has unpaid penalty
    created_at   TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: equipment
-- Stores all camera and filming equipment
-- ============================================================
CREATE TABLE IF NOT EXISTS equipment (
    equipment_id   INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(150)       NOT NULL,
    category       VARCHAR(100)       NOT NULL,
    description    TEXT               DEFAULT NULL,
    rental_rate    DECIMAL(10,2)      NOT NULL,            -- Rate per day
    status         ENUM('Available','Rented','Maintenance','Damaged')
                                      NOT NULL DEFAULT 'Available',
    image_url      VARCHAR(255)       DEFAULT NULL,
    created_at     TIMESTAMP          DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: rental
-- Tracks each rental transaction
-- ============================================================
CREATE TABLE IF NOT EXISTS rental (
    rental_id    INT AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT                 NOT NULL,
    equipment_id INT                 NOT NULL,
    rental_date  DATE                NOT NULL,
    return_date  DATE                NOT NULL,
    num_days     INT                 NOT NULL,             -- Computed: return_date - rental_date
    total_amount DECIMAL(10,2)       NOT NULL,             -- rental_rate × num_days
    status       ENUM('Pending','Active','Returned','Overdue')
                                     NOT NULL DEFAULT 'Pending',
    created_at   TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_customer   FOREIGN KEY (customer_id)  REFERENCES customer(customer_id)  ON DELETE CASCADE,
    CONSTRAINT fk_rental_equipment  FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: payment
-- Records payment for each rental
-- ============================================================
CREATE TABLE IF NOT EXISTS payment (
    payment_id     INT AUTO_INCREMENT PRIMARY KEY,
    rental_id      INT                NOT NULL UNIQUE,     -- One payment per rental
    customer_id    INT                NOT NULL,
    amount_paid    DECIMAL(10,2)      NOT NULL,
    payment_method ENUM('Cash','Card','Online')  NOT NULL DEFAULT 'Cash',
    payment_status ENUM('Paid','Pending','Failed') NOT NULL DEFAULT 'Pending',
    paid_at        TIMESTAMP          DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_rental   FOREIGN KEY (rental_id)   REFERENCES rental(rental_id)   ON DELETE CASCADE,
    CONSTRAINT fk_payment_customer FOREIGN KEY (customer_id) REFERENCES customer(customer_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA: Default admin account
-- Password: admin123 (bcrypt hashed)
-- ============================================================
INSERT INTO administrator (full_name, email, password) VALUES
('NinjaLens Admin', 'admin@ninjalens.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- ============================================================
-- SEED DATA: Sample customers
-- Password: password (bcrypt hashed)
-- ============================================================
INSERT INTO customer (full_name, email, password, phone) VALUES
('Juan dela Cruz', 'juan@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '09171234567'),
('Maria Santos',   'maria@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '09281234567');

-- ============================================================
-- SEED DATA: Sample equipment
-- ============================================================
INSERT INTO equipment (name, category, description, rental_rate, status) VALUES
('Sony A7 III',          'Camera Body',   'Full-frame mirrorless camera, 24.2 MP',              1500.00, 'Available'),
('Canon EOS R5',         'Camera Body',   'High-resolution mirrorless, 8K video capable',       2500.00, 'Available'),
('DJI Ronin-S',          'Stabilizer',    '3-axis gimbal for DSLR and mirrorless cameras',       800.00, 'Available'),
('Sigma 24-70mm f/2.8',  'Lens',          'Art series zoom lens, full-frame coverage',           600.00, 'Available'),
('Aputure 120D Mark II', 'Lighting',      'Daylight LED fresnel, 180W output',                  700.00, 'Maintenance'),
('Rode VideoMic Pro+',   'Audio',         'Directional on-camera microphone',                   350.00, 'Available'),
('DJI Mavic 3 Pro',      'Drone',         'Triple-camera aerial drone, 5.1K video',            2000.00, 'Available'),
('SmallRig Cage Kit',    'Accessories',   'Universal DSLR camera cage with rails',              300.00, 'Available'),
('Manfrotto 504X Tripod','Tripod',        'Professional fluid video head tripod',               400.00, 'Available'),
('Atomos Shogun 7',      'Monitor/Rec',   '7-inch HDR monitor-recorder, RAW recording',        1200.00, 'Rented');
