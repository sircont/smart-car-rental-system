-- =============================================================================
-- SMART CAR RENTAL SYSTEM - COMPLETE DATABASE SCHEMA
-- =============================================================================
-- Author: Abubakari Zeidu (20220412010)
-- Project: Smart Car Rental System
-- MySQL Version: 8.0+
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- CREATE DATABASE
-- =============================================================================

CREATE DATABASE IF NOT EXISTS carrental 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE carrental;

-- =============================================================================
-- TABLE 1: ADMINISTRATORS
-- =============================================================================

CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('super_admin', 'admin') DEFAULT 'admin',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================================================
-- TABLE 2: STAFF MEMBERS
-- =============================================================================

CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('manager', 'mechanic', 'attendant') DEFAULT 'attendant',
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================================================
-- TABLE 3: CUSTOMERS (USERS)
-- =============================================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    region VARCHAR(100),
    digital_address VARCHAR(50),
    profile_image VARCHAR(255) DEFAULT 'default.png',
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(100),
    reset_token VARCHAR(100),
    reset_expires DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_phone (phone)
);

-- =============================================================================
-- TABLE 4: VEHICLE BRANDS
-- =============================================================================

CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    logo VARCHAR(255),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================================================
-- TABLE 5: BRANCHES (LOCATIONS)
-- =============================================================================

CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(40),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_branch_name (name),
    INDEX idx_branches_active (is_active),
    INDEX idx_branches_sort (sort_order)
);

-- =============================================================================
-- TABLE 6: VEHICLES (FLEET)
-- =============================================================================

CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    model VARCHAR(100) NOT NULL,
    year INT,
    registration_number VARCHAR(20) UNIQUE,
    vin VARCHAR(50) UNIQUE,
    color VARCHAR(30),
    fuel_type ENUM('Petrol', 'Diesel', 'Electric', 'Hybrid') DEFAULT 'Petrol',
    transmission ENUM('Manual', 'Automatic') DEFAULT 'Manual',
    vehicle_type VARCHAR(20) NOT NULL DEFAULT 'economy',
    seats INT DEFAULT 5,
    price_per_day DECIMAL(10,2) NOT NULL,
    price_per_week DECIMAL(10,2),
    price_per_month DECIMAL(10,2),
    security_deposit DECIMAL(10,2) DEFAULT 0,
    mileage INT DEFAULT 0,
    mileage_limit_per_day INT DEFAULT 100,
    excess_mileage_charge DECIMAL(5,2) DEFAULT 0.50,
    description TEXT,
    features JSON,
    images JSON,
    primary_image VARCHAR(255),
    location_lat DECIMAL(10,8),
    location_lng DECIMAL(11,8),
    location_address TEXT,
    is_available BOOLEAN DEFAULT TRUE,
    status ENUM('available', 'rented', 'maintenance', 'retired') DEFAULT 'available',
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_brand (brand_id),
    INDEX idx_fuel_type (fuel_type),
    INDEX idx_status (status),
    INDEX idx_price (price_per_day),
    FULLTEXT INDEX idx_search (model, description)
);

-- =============================================================================
-- TABLE 7: BOOKINGS (RESERVATIONS)
-- =============================================================================

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_number VARCHAR(20) UNIQUE,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    pickup_location TEXT NOT NULL,
    return_location TEXT,
    pickup_date DATETIME NOT NULL,
    return_date DATETIME NOT NULL,
    days INT GENERATED ALWAYS AS (DATEDIFF(return_date, pickup_date)) STORED,
    price_per_day DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) GENERATED ALWAYS AS (days * price_per_day) STORED,
    insurance_amount DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) GENERATED ALWAYS AS (subtotal + insurance_amount + tax_amount) STORED,
    security_deposit DECIMAL(10,2) DEFAULT 0,
    payment_status ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
    booking_status ENUM('pending', 'confirmed', 'active', 'returned', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    special_requests TEXT,
    driver_name VARCHAR(100),
    driver_license VARCHAR(50),
    driver_phone VARCHAR(20),
    is_extended BOOLEAN DEFAULT FALSE,
    extension_details JSON,
    cancellation_reason TEXT,
    cancelled_by VARCHAR(50),
    cancelled_at DATETIME,
    return_requested_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_dates (pickup_date, return_date),
    INDEX idx_status (booking_status),
    INDEX idx_payment (payment_status),
    UNIQUE INDEX idx_booking_number (booking_number)
);

-- =============================================================================
-- TABLE 8: PAYMENTS
-- =============================================================================

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    transaction_id VARCHAR(100) UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('mobile_money', 'card', 'bank_transfer', 'cash') NOT NULL,
    mobile_money_number VARCHAR(20),
    mobile_money_provider ENUM('mtn', 'vodafone', 'airteltigo'),
    card_last4 VARCHAR(4),
    payment_status ENUM('pending', 'processing', 'success', 'failed', 'refunded') DEFAULT 'pending',
    payment_details JSON,
    receipt_number VARCHAR(50),
    paid_at DATETIME,
    refunded_at DATETIME,
    refund_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_booking (booking_id),
    INDEX idx_transaction (transaction_id)
);

-- =============================================================================
-- TABLE 9: MAINTENANCE RECORDS
-- =============================================================================

CREATE TABLE IF NOT EXISTS maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    staff_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    maintenance_type ENUM('routine', 'repair', 'accident', 'inspection') DEFAULT 'routine',
    priority ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'medium',
    cost DECIMAL(10,2),
    scheduled_date DATE,
    completed_date DATE,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT,
    images JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_status (status)
);

-- =============================================================================
-- TABLE 10: CUSTOMER TESTIMONIALS
-- =============================================================================

CREATE TABLE IF NOT EXISTS testimonials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    booking_id INT,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(200),
    content TEXT NOT NULL,
    pros TEXT,
    cons TEXT,
    images JSON,
    is_approved BOOLEAN DEFAULT FALSE,
    approved_by INT,
    approved_at DATETIME,
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_approved (is_approved),
    INDEX idx_rating (rating)
);

-- =============================================================================
-- TABLE 11: CONTACT QUERIES
-- =============================================================================

CREATE TABLE IF NOT EXISTS contact_queries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new',
    assigned_to INT,
    reply TEXT,
    replied_by INT,
    replied_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_status (status)
);

-- =============================================================================
-- TABLE 12: PASSWORD RESETS
-- =============================================================================

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_token (token),
    INDEX idx_email (email)
);

-- =============================================================================
-- TABLE 13: NOTIFICATIONS
-- =============================================================================

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    staff_id INT,
    admin_id INT,
    type ENUM('booking', 'payment', 'reminder', 'promotion', 'alert') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_read (is_read)
);

-- =============================================================================
-- TABLE 14: ACTIVITY LOGS (AUDIT TRAIL)
-- =============================================================================

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    staff_id INT,
    admin_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
);

-- =============================================================================
-- TABLE 15: INSURANCE OPTIONS
-- =============================================================================

CREATE TABLE IF NOT EXISTS insurance_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price_per_day DECIMAL(10,2) NOT NULL,
    coverage_amount DECIMAL(10,2),
    excess_amount DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- TABLE 16: DISCOUNT CODES
-- =============================================================================

CREATE TABLE IF NOT EXISTS discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_booking_amount DECIMAL(10,2),
    max_discount_amount DECIMAL(10,2),
    valid_from DATETIME,
    valid_to DATETIME,
    usage_limit INT,
    used_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- TABLE 17: STAFF DAILY CHECKLIST
-- =============================================================================

CREATE TABLE IF NOT EXISTS daily_checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'staff.id',
    checklist_date DATE NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    is_done BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_staff_date (user_id, checklist_date)
);

-- =============================================================================
-- FOREIGN KEY CONSTRAINTS
-- =============================================================================

-- Vehicles to Brands
ALTER TABLE vehicles ADD CONSTRAINT fk_vehicles_brand 
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE;

-- Bookings to Users
ALTER TABLE bookings ADD CONSTRAINT fk_bookings_user 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Bookings to Vehicles
ALTER TABLE bookings ADD CONSTRAINT fk_bookings_vehicle 
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE;

-- Payments to Bookings
ALTER TABLE payments ADD CONSTRAINT fk_payments_booking 
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE;

-- Maintenance to Vehicles
ALTER TABLE maintenance ADD CONSTRAINT fk_maintenance_vehicle 
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE;

-- Maintenance to Staff
ALTER TABLE maintenance ADD CONSTRAINT fk_maintenance_staff 
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL;

-- Testimonials to Users
ALTER TABLE testimonials ADD CONSTRAINT fk_testimonials_user 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Testimonials to Bookings
ALTER TABLE testimonials ADD CONSTRAINT fk_testimonials_booking 
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL;

-- Testimonials to Admin (Approver)
ALTER TABLE testimonials ADD CONSTRAINT fk_testimonials_approved_by 
    FOREIGN KEY (approved_by) REFERENCES admin(id) ON DELETE SET NULL;

-- Daily Checklists to Staff
ALTER TABLE daily_checklists ADD CONSTRAINT fk_checklist_staff 
    FOREIGN KEY (user_id) REFERENCES staff(id) ON DELETE CASCADE;

-- =============================================================================
-- VIEWS FOR REPORTING
-- =============================================================================

-- Monthly Revenue Report
CREATE OR REPLACE VIEW monthly_revenue AS
SELECT
    DATE_FORMAT(p.paid_at, '%Y-%m') AS month,
    COUNT(DISTINCT b.id) AS total_bookings,
    SUM(p.amount) AS total_revenue,
    AVG(p.amount) AS avg_booking_value,
    SUM(CASE WHEN p.payment_method = 'mobile_money' THEN p.amount ELSE 0 END) AS mobile_money_revenue,
    SUM(CASE WHEN p.payment_method = 'card' THEN p.amount ELSE 0 END) AS card_revenue
FROM payments p
JOIN bookings b ON b.id = p.booking_id
WHERE p.payment_status = 'success'
GROUP BY DATE_FORMAT(p.paid_at, '%Y-%m');

-- Vehicle Popularity Report
CREATE OR REPLACE VIEW vehicle_popularity AS
SELECT
    v.id,
    v.model,
    b.name AS brand_name,
    COUNT(bk.id) AS total_bookings,
    SUM(bk.days) AS total_days_rented,
    AVG(bk.total_amount) AS avg_revenue_per_booking,
    SUM(bk.total_amount) AS total_revenue,
    RANK() OVER (ORDER BY COUNT(bk.id) DESC) AS popularity_rank
FROM vehicles v
JOIN brands b ON b.id = v.brand_id
LEFT JOIN bookings bk ON bk.vehicle_id = v.id AND bk.booking_status = 'completed'
GROUP BY v.id, v.model, b.name;

-- =============================================================================
-- INITIAL SEED DATA
-- =============================================================================

-- Default Admin User (password: password)
INSERT IGNORE INTO admin (username, password, email, full_name, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
        'admin@carrental.com', 'System Administrator', 'super_admin');

-- Default Staff User (password: password)
INSERT IGNORE INTO staff (username, password, email, full_name, phone, role) 
VALUES ('staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
        'staff@carrental.com', 'Staff User', '0201234567', 'attendant');

-- Default Branches
INSERT IGNORE INTO branches (name, address, phone, sort_order) VALUES
('Main Office', '12 Independence Avenue, Accra Central', '+233 30 123 4567', 1),
('Airport (Kotoka)', 'Kotoka International Airport – Terminal 3 area', '+233 30 987 6543', 2),
('East Legon', 'Near A&C Mall, East Legon', '+233 24 000 0000', 3);

-- Default Vehicle Brands
INSERT IGNORE INTO brands (name) VALUES
('Toyota'), ('Honda'), ('Hyundai'), ('Kia'), ('Nissan'),
('Mercedes-Benz'), ('BMW'), ('Audi'), ('Volkswagen'), ('Ford');

-- Default Insurance Options
INSERT IGNORE INTO insurance_options (name, description, price_per_day, coverage_amount, excess_amount) VALUES
('Basic Coverage', 'Third party liability only', 20.00, 50000.00, 1000.00),
('Standard Coverage', 'Third party + theft protection', 35.00, 100000.00, 500.00),
('Premium Coverage', 'Full comprehensive coverage', 50.00, 200000.00, 250.00);

-- =============================================================================
-- END OF SCHEMA
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 1;
