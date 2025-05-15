-- Create the database
DROP DATABASE IF EXISTS CovidSystem;
CREATE DATABASE CovidSystem;
USE CovidSystem;

-- Users table - stores all users across roles
CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    prs_id VARCHAR(10) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    national_id VARCHAR(50) UNIQUE,
    dob DATE,
    role ENUM('Admin', 'Official', 'Merchant', 'Citizen', 'Pending') NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    is_visitor TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Initial Users Data
-- Add Admin user to the system (default password: admin123)
INSERT INTO Users (prs_id, full_name, role, username, password, email, created_at)
VALUES ('PRS000001', 'System Administrator', 'Admin', 'admin', 'admin123', 'admin@pandemic-system.org', NOW());

-- Sample citizen user
INSERT INTO Users (prs_id, full_name, national_id, dob, role, username, password, email, phone, is_visitor)
VALUES ('PRS123456', 'John Smith', 'ID123456789', '1985-05-15', 'Citizen', 'jsmith', 'password123', 'john@example.com', '555-123-4567', 0);

-- Sample merchant user
INSERT INTO Users (prs_id, full_name, national_id, dob, role, username, password, email, phone, is_visitor)
VALUES ('PRS234567', 'Jane Doe', 'ID987654321', '1978-11-22', 'Merchant', 'jdoe', 'password123', 'jane@example.com', '555-987-6543', 0);

-- Sample official user
INSERT INTO Users (prs_id, full_name, national_id, dob, role, username, password, email, phone, is_visitor)
VALUES ('PRS345678', 'Alex Johnson', 'ID456789123', '1982-07-10', 'Official', 'ajohnson', 'password123', 'alex@gov.org', '555-456-7890', 0);

-- Additional Citizens (5 more)
INSERT INTO Users (prs_id, full_name, national_id, dob, role, username, password, email, phone, address, is_visitor)
VALUES 
('PRS456789', 'Maria Rodriguez', 'ID567891234', '1990-03-25', 'Citizen', 'mrodriguez', 'password123', 'maria@example.com', '555-234-5678', '456 Oak Avenue, Anytown', 0),
('PRS567890', 'David Lee', 'ID678912345', '1975-12-10', 'Citizen', 'dlee', 'password123', 'david@example.com', '555-345-6789', '789 Pine Street, Anytown', 0),
('PRS678901', 'Sarah Kim', 'ID789123456', '1988-07-15', 'Citizen', 'skim', 'password123', 'sarah@example.com', '555-456-7890', '101 Elm Road, Anytown', 0),
('PRS789012', 'Michael Chen', 'ID891234567', '1992-09-30', 'Citizen', 'mchen', 'password123', 'michael@example.com', '555-567-8901', '202 Maple Drive, Anytown', 0),
('PRS890123', 'Emily Wilson', 'ID912345678', '1980-05-18', 'Citizen', 'ewilson', 'password123', 'emily@example.com', '555-678-9012', '303 Cedar Lane, Anytown', 0);

-- Additional Merchants (3 more)
INSERT INTO Users (prs_id, full_name, national_id, dob, role, username, password, email, phone, address, is_visitor)
VALUES
('PRS901234', 'Robert Brown', 'ID123789456', '1973-08-12', 'Merchant', 'rbrown', 'password123', 'robert@example.com', '555-789-0123', '404 Birch Blvd, Anytown', 0),
('PRS012345', 'Lisa Wong', 'ID234890567', '1985-11-05', 'Merchant', 'lwong', 'password123', 'lisa@example.com', '555-890-1234', '505 Willow Court, Anytown', 0),
('PRS123456X', 'Kevin Patel', 'ID345901678', '1979-02-22', 'Merchant', 'kpatel', 'password123', 'kevin@example.com', '555-901-2345', '606 Aspen Way, Anytown', 0);

-- Additional Officials (2 more)
INSERT INTO Users (prs_id, full_name, national_id, dob, role, username, password, email, phone, address, is_visitor)
VALUES
('PRS234567X', 'Jennifer Martinez', 'ID456012789', '1983-06-15', 'Official', 'jmartinez', 'password123', 'jennifer@gov.org', '555-012-3456', '707 Redwood Street, Anytown', 0),
('PRS345678X', 'Thomas Jackson', 'ID567123890', '1977-09-08', 'Official', 'tjackson', 'password123', 'thomas@gov.org', '555-123-4567', '808 Sycamore Avenue, Anytown', 0);

-- Visitor
INSERT INTO Users (prs_id, full_name, national_id, dob, role, username, password, email, phone, address, is_visitor)
VALUES
('PRS456789V', 'Anna Schmidt', 'VSTRE12345', '1986-04-20', 'Citizen', 'aschmidt', 'password123', 'anna@example.com', '555-234-5678', 'Visitor from Germany', 1);

-- Officials (government officials) table
CREATE TABLE government_officials (
    official_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    department VARCHAR(100) NOT NULL,
    role VARCHAR(100) NOT NULL,
    badge_number VARCHAR(50) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Initial Official Data
-- Create a government_official entry for the admin
INSERT INTO government_officials (user_id, department, role, badge_number)
VALUES (1, 'System Administration', 'System Administrator', 'ADMIN001');

-- Sample official record
INSERT INTO government_officials (user_id, department, role, badge_number)
VALUES (4, 'Health Department', 'Public Health Officer', 'HB789123');

-- Additional Government Official Records
INSERT INTO government_officials (user_id, department, role, badge_number)
VALUES
(9, 'Emergency Management', 'Emergency Coordinator', 'EM789456'),
(10, 'Public Safety', 'Safety Inspector', 'PS890567');

-- Official approval requests table
CREATE TABLE official_approvals (
    approval_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department VARCHAR(100) NOT NULL,
    role VARCHAR(100) NOT NULL,
    badge_number VARCHAR(50) NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_by INT NULL,
    processed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES Users(user_id) ON DELETE SET NULL
);

-- Official Approval Requests Data
INSERT INTO official_approvals (user_id, department, role, badge_number, status, created_at, processed_by, processed_at)
VALUES
(9, 'Emergency Management', 'Emergency Coordinator', 'EM789456', 'Approved', DATE_SUB(NOW(), INTERVAL 30 DAY), 1, DATE_SUB(NOW(), INTERVAL 28 DAY)),
(10, 'Public Safety', 'Safety Inspector', 'PS890567', 'Approved', DATE_SUB(NOW(), INTERVAL 25 DAY), 1, DATE_SUB(NOW(), INTERVAL 23 DAY)),
(11, 'Transportation Department', 'Logistics Officer', 'TD901678', 'Approved', DATE_SUB(NOW(), INTERVAL 20 DAY), 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(12, 'Health Department', 'Vaccine Coordinator', 'HC123987', 'Pending', DATE_SUB(NOW(), INTERVAL 5 DAY), NULL, NULL),
(13, 'Supply Chain Management', 'Resource Manager', 'SC456321', 'Rejected', DATE_SUB(NOW(), INTERVAL 15 DAY), 1, DATE_SUB(NOW(), INTERVAL 12 DAY));

-- Merchants table
CREATE TABLE merchants (
    merchant_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    business_name VARCHAR(100) NOT NULL,
    business_type ENUM('Pharmacy', 'Grocery', 'Supermarket', 'Medical Supply') NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(100),
    postal_code VARCHAR(20),
    business_license_number VARCHAR(50) NOT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    is_active TINYINT(1) DEFAULT 1,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Initial Merchant Data
-- Sample merchant record
INSERT INTO merchants (user_id, business_name, business_type, address, city, postal_code, business_license_number, is_active)
VALUES (3, 'Jane\'s Pharmacy', 'Pharmacy', '123 Main Street', 'Anytown', '12345', 'BL12345678', 1);

-- Additional Merchant Records
INSERT INTO merchants (user_id, business_name, business_type, address, city, postal_code, business_license_number, latitude, longitude, is_active)
VALUES
(6, 'City Grocery', 'Grocery', '404 Birch Blvd', 'Anytown', '12345', 'BL34567890', 34.0522, -118.2437, 1),
(7, 'MediMart', 'Medical Supply', '505 Willow Court', 'Anytown', '12345', 'BL45678901', 34.0531, -118.2428, 1),
(8, 'SuperSave', 'Supermarket', '606 Aspen Way', 'Anytown', '12345', 'BL56789012', 34.0540, -118.2419, 1);

-- Critical items table
CREATE TABLE critical_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    item_description TEXT,
    item_category ENUM('Medical', 'Grocery', 'Hygiene', 'Protective') NOT NULL,
    unit_of_measure VARCHAR(50) NOT NULL,
    is_restricted TINYINT(1) DEFAULT 1,
    max_quantity_per_day INT DEFAULT 2,
    max_quantity_per_week INT DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES government_officials(official_id) ON DELETE SET NULL
);

-- Initial Critical Items Data
-- Insert sample critical items
INSERT INTO critical_items (item_name, item_description, item_category, unit_of_measure, is_restricted, max_quantity_per_day, max_quantity_per_week) VALUES
('Face Mask (N95)', 'High-quality N95 respirator mask for medical personnel', 'Medical', 'piece', 1, 2, 6),
('Hand Sanitizer', 'Alcohol-based hand sanitizer (70% alcohol)', 'Medical', 'bottle', 1, 2, 4),
('Disinfectant Wipes', 'Antibacterial disinfectant wipes', 'Medical', 'pack', 1, 1, 3),
('Toilet Paper', 'Toilet paper rolls', 'Hygiene', 'pack', 1, 1, 2),
('Rice', '5kg bag of rice', 'Grocery', 'bag', 1, 1, 1),
('Canned Beans', 'Canned beans in tomato sauce', 'Grocery', 'can', 1, 5, 15),
('Bottled Water', '1L bottles of drinking water', 'Grocery', 'bottle', 1, 6, 24),
('Paracetamol', 'Pain reliever tablets', 'Medical', 'box', 1, 1, 2),
('Disposable Gloves', 'Medical-grade disposable gloves', 'Medical', 'box', 1, 1, 2),
('Face Shield', 'Protective face shield', 'Protective', 'piece', 1, 2, 5);

-- Additional Critical Items
INSERT INTO critical_items (item_name, item_description, item_category, unit_of_measure, is_restricted, max_quantity_per_day, max_quantity_per_week)
VALUES
('Antiseptic Solution', 'Liquid antiseptic for wound cleaning', 'Medical', 'bottle', 1, 1, 2),
('Vitamin C Tablets', 'Immune system support supplements', 'Medical', 'bottle', 0, 2, 5),
('Flour', '2kg bag of all-purpose flour', 'Grocery', 'bag', 1, 1, 2),
('Infant Formula', 'Baby formula for 0-6 months', 'Grocery', 'can', 1, 3, 12),
('Thermometer', 'Digital thermometer for fever detection', 'Medical', 'piece', 1, 1, 1);

-- Stock table (inventory for merchants)
CREATE TABLE stock (
    stock_id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    item_id INT NOT NULL,
    current_quantity INT NOT NULL DEFAULT 0,
    last_restock_date TIMESTAMP NULL,
    last_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (merchant_id) REFERENCES merchants(merchant_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES critical_items(item_id) ON DELETE CASCADE,
    UNIQUE KEY merchant_item (merchant_id, item_id)
);

-- Initial Stock Data
-- Add some stock for the sample merchant
INSERT INTO stock (merchant_id, item_id, current_quantity, last_restock_date) VALUES
(1, 1, 100, NOW()),   -- Face Masks
(1, 2, 50, NOW()),    -- Hand Sanitizer
(1, 8, 200, NOW()),   -- Paracetamol
(1, 9, 150, NOW());   -- Disposable Gloves

-- Additional Stock for Merchants
-- For Jane's Pharmacy (merchant_id = 1)
INSERT INTO stock (merchant_id, item_id, current_quantity, last_restock_date)
VALUES
(1, 3, 75, NOW()),    -- Disinfectant Wipes
(1, 4, 40, NOW()),    -- Toilet Paper
(1, 11, 60, NOW()),   -- Antiseptic Solution
(1, 12, 120, NOW()),  -- Vitamin C Tablets
(1, 15, 30, NOW());   -- Thermometer

-- City Grocery (merchant_id = 2)
INSERT INTO stock (merchant_id, item_id, current_quantity, last_restock_date)
VALUES
(2, 4, 150, NOW()),   -- Toilet Paper
(2, 5, 80, NOW()),    -- Rice
(2, 6, 200, NOW()),   -- Canned Beans
(2, 7, 300, NOW()),   -- Bottled Water
(2, 13, 100, NOW()),  -- Flour
(2, 14, 50, NOW());   -- Infant Formula

-- MediMart (merchant_id = 3)
INSERT INTO stock (merchant_id, item_id, current_quantity, last_restock_date)
VALUES
(3, 1, 200, NOW()),   -- Face Masks
(3, 2, 150, NOW()),   -- Hand Sanitizer
(3, 8, 100, NOW()),   -- Paracetamol
(3, 9, 120, NOW()),   -- Disposable Gloves
(3, 10, 80, NOW()),   -- Face Shield
(3, 15, 50, NOW());   -- Thermometer

-- SuperSave (merchant_id = 4)
INSERT INTO stock (merchant_id, item_id, current_quantity, last_restock_date)
VALUES
(4, 4, 200, NOW()),   -- Toilet Paper
(4, 5, 150, NOW()),   -- Rice
(4, 6, 250, NOW()),   -- Canned Beans
(4, 7, 500, NOW()),   -- Bottled Water
(4, 13, 120, NOW());  -- Flour

-- Purchase schedule for restricted items
CREATE TABLE purchase_schedule (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '1=Monday, 7=Sunday',
    dob_year_ending VARCHAR(20) NOT NULL COMMENT 'Comma-separated last digits',
    effective_from DATE NOT NULL,
    effective_to DATE NULL COMMENT 'NULL means indefinite',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (item_id) REFERENCES critical_items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES government_officials(official_id) ON DELETE CASCADE,
    UNIQUE KEY item_day_dob (item_id, day_of_week, dob_year_ending, effective_from)
);

-- Initial Purchase Schedule Data
-- Example purchase schedules (for demonstration)
-- Monday: Birth years ending in 0 and 1
-- Tuesday: Birth years ending in 2 and 3
-- Wednesday: Birth years ending in 4 and 5
-- Thursday: Birth years ending in 6 and 7
-- Friday: Birth years ending in 8 and 9
-- Everyone can purchase on weekends

-- Face masks schedule (using admin official_id = 1)
INSERT INTO purchase_schedule (item_id, day_of_week, dob_year_ending, effective_from, created_by) VALUES
(1, 1, '0,1', '2023-01-01', 1),
(1, 2, '2,3', '2023-01-01', 1),
(1, 3, '4,5', '2023-01-01', 1),
(1, 4, '6,7', '2023-01-01', 1),
(1, 5, '8,9', '2023-01-01', 1),
(1, 6, '0,1,2,3,4,5,6,7,8,9', '2023-01-01', 1),
(1, 7, '0,1,2,3,4,5,6,7,8,9', '2023-01-01', 1);

-- Hand Sanitizer schedule
INSERT INTO purchase_schedule (item_id, day_of_week, dob_year_ending, effective_from, created_by) VALUES
(2, 1, '0,1', '2023-01-01', 1),
(2, 2, '2,3', '2023-01-01', 1),
(2, 3, '4,5', '2023-01-01', 1),
(2, 4, '6,7', '2023-01-01', 1),
(2, 5, '8,9', '2023-01-01', 1),
(2, 6, '0,1,2,3,4,5,6,7,8,9', '2023-01-01', 1),
(2, 7, '0,1,2,3,4,5,6,7,8,9', '2023-01-01', 1);

-- Rice schedule
INSERT INTO purchase_schedule (item_id, day_of_week, dob_year_ending, effective_from, created_by) VALUES
(5, 1, '0,1', '2023-01-01', 1),
(5, 2, '2,3', '2023-01-01', 1),
(5, 3, '4,5', '2023-01-01', 1),
(5, 4, '6,7', '2023-01-01', 1),
(5, 5, '8,9', '2023-01-01', 1),
(5, 6, '0,1,2,3,4,5,6,7,8,9', '2023-01-01', 1),
(5, 7, '0,1,2,3,4,5,6,7,8,9', '2023-01-01', 1);

-- Additional Purchase Schedules
-- Paracetamol schedule
INSERT INTO purchase_schedule (item_id, day_of_week, dob_year_ending, effective_from, created_by)
VALUES
(8, 1, '0,1', '2023-01-01', 1),
(8, 2, '2,3', '2023-01-01', 1),
(8, 3, '4,5', '2023-01-01', 1),
(8, 4, '6,7', '2023-01-01', 1),
(8, 5, '8,9', '2023-01-01', 1),
(8, 6, '0,1,2,3,4,5,6,7,8,9', '2023-01-01', 1),
(8, 7, '0,1,2,3,4,5,6,7,8,9', '2023-01-01', 1);

-- Infant Formula schedule
INSERT INTO purchase_schedule (item_id, day_of_week, dob_year_ending, effective_from, created_by)
VALUES
(14, 1, '0,1', '2023-01-01', 1),
(14, 2, '2,3', '2023-01-01', 1),
(14, 3, '4,5', '2023-01-01', 1),
(14, 4, '6,7', '2023-01-01', 1),
(14, 5, '8,9', '2023-01-01', 1),
(14, 6, '0,1,2,3,4,5,6,7,8,9', '2023-01-01', 1),
(14, 7, '0,1,2,3,4,5,6,7,8,9', '2023-01-01', 1);

-- Purchases table
CREATE TABLE purchases (
    purchase_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    merchant_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_by VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (merchant_id) REFERENCES merchants(merchant_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES critical_items(item_id) ON DELETE CASCADE
);

-- Purchase Records Data
INSERT INTO purchases (user_id, merchant_id, item_id, quantity, purchase_date, verified_by)
VALUES
-- John Smith purchases
(2, 1, 1, 2, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Jane Doe'),
(2, 1, 2, 1, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Jane Doe'),
(2, 2, 4, 1, DATE_SUB(NOW(), INTERVAL 8 DAY), 'Robert Brown'),
(2, 2, 5, 1, DATE_SUB(NOW(), INTERVAL 8 DAY), 'Robert Brown'),
(2, 2, 7, 6, DATE_SUB(NOW(), INTERVAL 8 DAY), 'Robert Brown'),

-- Maria Rodriguez purchases
(5, 3, 1, 2, DATE_SUB(NOW(), INTERVAL 9 DAY), 'Lisa Wong'),
(5, 3, 9, 1, DATE_SUB(NOW(), INTERVAL 9 DAY), 'Lisa Wong'),
(5, 4, 5, 1, DATE_SUB(NOW(), INTERVAL 7 DAY), 'Kevin Patel'),
(5, 4, 6, 4, DATE_SUB(NOW(), INTERVAL 7 DAY), 'Kevin Patel'),

-- David Lee purchases
(6, 1, 8, 1, DATE_SUB(NOW(), INTERVAL 6 DAY), 'Jane Doe'),
(6, 1, 12, 1, DATE_SUB(NOW(), INTERVAL 6 DAY), 'Jane Doe'),
(6, 2, 13, 1, DATE_SUB(NOW(), INTERVAL 4 DAY), 'Robert Brown'),

-- Sarah Kim purchases
(7, 3, 15, 1, DATE_SUB(NOW(), INTERVAL 5 DAY), 'Lisa Wong'),
(7, 4, 4, 1, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Kevin Patel'),
(7, 4, 7, 6, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Kevin Patel'),

-- Visitor purchase
(11, 1, 1, 2, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Jane Doe'),
(11, 3, 2, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Lisa Wong');

-- Vaccines table
CREATE TABLE vaccines (
    vaccine_id INT AUTO_INCREMENT PRIMARY KEY,
    vaccine_name VARCHAR(100) NOT NULL,
    manufacturer VARCHAR(100) NOT NULL,
    disease VARCHAR(100) NOT NULL,
    doses_required INT NOT NULL DEFAULT 1,
    min_days_between_doses INT NULL,
    approval_date DATE NOT NULL
);

-- Vaccines Data
INSERT INTO vaccines (vaccine_name, manufacturer, disease, doses_required, min_days_between_doses, approval_date) VALUES
('CoviShield', 'AstraZeneca', 'COVID-19', 2, 28, '2021-01-15'),
('mRNA-1273', 'Moderna', 'COVID-19', 2, 28, '2020-12-18'),
('BNT162b2', 'Pfizer-BioNTech', 'COVID-19', 2, 21, '2020-12-11'),
('Ad26.COV2.S', 'Johnson & Johnson', 'COVID-19', 1, NULL, '2021-02-27'),
('CoronaVac', 'Sinovac', 'COVID-19', 2, 14, '2021-02-06');

-- Vaccination records
CREATE TABLE vaccination_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vaccine_id INT NOT NULL,
    dose_number INT NOT NULL,
    vaccination_date DATE NOT NULL,
    healthcare_provider VARCHAR(100) NOT NULL,
    batch_number VARCHAR(50) NULL,
    location VARCHAR(100) NULL,
    verified TINYINT(1) DEFAULT 0,
    verification_date TIMESTAMP NULL,
    verified_by INT NULL,
    certificate_file VARCHAR(255) NULL,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (vaccine_id) REFERENCES vaccines(vaccine_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES government_officials(official_id) ON DELETE SET NULL,
    UNIQUE KEY user_vaccine_dose (user_id, vaccine_id, dose_number)
);

-- Vaccination Records Data
INSERT INTO vaccination_records (user_id, vaccine_id, dose_number, vaccination_date, healthcare_provider, batch_number, location, verified, verification_date, verified_by)
VALUES
-- John Smith vaccinations
(2, 1, 1, '2022-02-10', 'City Hospital', 'AZ1001', 'Main Clinic', 1, '2022-02-11', 1),
(2, 1, 2, '2022-03-15', 'City Hospital', 'AZ1056', 'Main Clinic', 1, '2022-03-16', 1),

-- Maria Rodriguez vaccinations
(5, 2, 1, '2022-01-20', 'County Medical Center', 'MD2034', 'North Branch', 1, '2022-01-21', 1),
(5, 2, 2, '2022-02-18', 'County Medical Center', 'MD2089', 'North Branch', 1, '2022-02-19', 1),

-- David Lee vaccinations
(6, 3, 1, '2022-02-05', 'State Vaccination Center', 'PZ3045', 'Downtown Hub', 1, '2022-02-06', 1),
(6, 3, 2, '2022-02-28', 'State Vaccination Center', 'PZ3112', 'Downtown Hub', 1, '2022-03-01', 1),

-- Sarah Kim vaccinations
(7, 4, 1, '2022-03-01', 'Community Health', 'JJ4023', 'South Clinic', 1, '2022-03-02', 1),

-- Michael Chen vaccinations
(8, 5, 1, '2022-01-15', 'University Hospital', 'SV5067', 'East Wing', 1, '2022-01-16', 1),
(8, 5, 2, '2022-02-02', 'University Hospital', 'SV5084', 'East Wing', 1, '2022-02-03', 1),

-- Emily Wilson vaccinations
(9, 3, 1, '2022-02-22', 'Memorial Health', 'PZ3078', 'West Branch', 1, '2022-02-23', 1),
(9, 3, 2, '2022-03-18', 'Memorial Health', 'PZ3156', 'West Branch', 0, NULL, NULL);

-- Access Logs
CREATE TABLE access_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_details TEXT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Access Logs Data
INSERT INTO access_logs (user_id, ip_address, action_type, action_details, entity_type, entity_id, timestamp)
VALUES
-- Admin logs
(1, '192.168.1.100', 'LOGIN', 'Admin user login', NULL, NULL, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(1, '192.168.1.100', 'CREATE', 'Created new critical item', 'critical_items', 11, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(1, '192.168.1.100', 'UPDATE', 'Updated purchase schedule', 'purchase_schedule', 8, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(1, '192.168.1.100', 'VERIFY', 'Verified vaccination record', 'vaccination_records', 1, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(1, '192.168.1.100', 'LOGOUT', 'Admin user logout', NULL, NULL, DATE_SUB(NOW(), INTERVAL 8 DAY)),

-- Official logs
(4, '192.168.1.101', 'LOGIN', 'Official user login', NULL, NULL, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(4, '192.168.1.101', 'VIEW', 'Viewed citizen profile', 'Users', 2, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(4, '192.168.1.101', 'VERIFY', 'Verified vaccination record', 'vaccination_records', 3, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(4, '192.168.1.101', 'REPORT', 'Generated vaccination report', NULL, NULL, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(4, '192.168.1.101', 'LOGOUT', 'Official user logout', NULL, NULL, DATE_SUB(NOW(), INTERVAL 6 DAY)),

-- Merchant logs
(3, '192.168.1.102', 'LOGIN', 'Merchant user login', NULL, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, '192.168.1.102', 'UPDATE', 'Updated inventory stock', 'stock', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, '192.168.1.102', 'RECORD', 'Recorded purchase transaction', 'purchases', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, '192.168.1.102', 'REPORT', 'Generated inventory report', NULL, NULL, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(3, '192.168.1.102', 'LOGOUT', 'Merchant user logout', NULL, NULL, DATE_SUB(NOW(), INTERVAL 4 DAY)),

-- Citizen logs
(2, '192.168.1.103', 'LOGIN', 'Citizen user login', NULL, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, '192.168.1.103', 'VIEW', 'Viewed purchase history', 'purchases', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, '192.168.1.103', 'VIEW', 'Viewed vaccination certificate', 'vaccination_records', 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, '192.168.1.103', 'DOWNLOAD', 'Downloaded vaccination certificate', 'vaccination_records', 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, '192.168.1.103', 'LOGOUT', 'Citizen user logout', NULL, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY));