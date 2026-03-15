-- It appears you experienced an issue with "No database selected" during import in phpMyAdmin.
-- To fix this, please ensure you either select the database on the left sidebar before importing,
-- or run these two statements first before pasting the rest in the SQL tab:
CREATE DATABASE IF NOT EXISTS questlog_db;
USE questlog_db;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    address VARCHAR(255),
    country VARCHAR(100),
    state VARCHAR(100),
    mobile VARCHAR(20),
    role ENUM('Tourist', 'Admin') DEFAULT 'Tourist',
    status ENUM('Active', 'Blocked') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Hotels Table
CREATE TABLE IF NOT EXISTS hotels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    description TEXT,
    price_per_night DECIMAL(10, 2) NOT NULL,
    amenities JSON,
    image_url VARCHAR(255),
    rooms_available INT NOT NULL DEFAULT 0,
    status ENUM('Active', 'Disabled') DEFAULT 'Active'
);

-- Transports Table
CREATE TABLE IF NOT EXISTS transports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('Flight', 'Train') NOT NULL,
    source VARCHAR(255) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    departure_date DATE NOT NULL,
    departure_time TIME NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    total_seats INT NOT NULL,
    available_seats INT NOT NULL,
    status ENUM('Active', 'Disabled') DEFAULT 'Active'
);

-- Bookings Table
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('Hotel', 'Transport') NOT NULL,
    entity_id INT NOT NULL, -- Corresponds to either hotel_id or transport_id
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    start_date DATE, -- For Hotel Check-in
    end_date DATE, -- For Hotel Check-out
    guests_count INT NOT NULL DEFAULT 1,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('Pending', 'Completed', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending',
    booking_status ENUM('Pending Payment', 'Confirmed', 'Cancelled', 'Completed') NOT NULL DEFAULT 'Pending Payment',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    -- Note: entity_id is a programmatic foreign key because it can link to two different tables
);




--  Booking Passengers Table - Stores individual passenger details for each booking
-- Supports multiple passengers per booking (e.g., 1 user booking for 4 people)
CREATE TABLE IF NOT EXISTS booking_passengers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    passenger_name VARCHAR(100) NOT NULL,
    passenger_age INT,
    passenger_gender ENUM('Male', 'Female', 'Other') NOT NULL,
    id_type VARCHAR(30),          -- e.g. 'Aadhaar', 'Passport', 'Driving License'
    id_number VARCHAR(20),       -- document number
    seat_number VARCHAR(10),      -- optional, for transport bookings
    -- [ADDED] Per-passenger status for individual cancellation
    passenger_status ENUM('Confirmed', 'Cancelled') DEFAULT 'Confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (booking_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);



-- Itineraries Table
CREATE TABLE IF NOT EXISTS itineraries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    trip_name VARCHAR(255) NOT NULL,
    source VARCHAR(255) DEFAULT NULL,
    destination VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    budget DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('Upcoming', 'Ongoing', 'Completed') DEFAULT 'Upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Itinerary Days Table
CREATE TABLE IF NOT EXISTS itinerary_days (
    id INT AUTO_INCREMENT PRIMARY KEY,
    itinerary_id INT NOT NULL,
    day_number INT NOT NULL,
    date DATE NOT NULL,
    FOREIGN KEY (itinerary_id) REFERENCES itineraries(id) ON DELETE CASCADE
);

-- Itinerary Items Table (Timeline)
CREATE TABLE IF NOT EXISTS itinerary_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    itinerary_id INT NOT NULL,
    day_id INT NOT NULL,
    item_type ENUM('Transport', 'Hotel', 'Activity') NOT NULL,
    booking_id INT DEFAULT NULL, -- Links to bookings table if applicable
    title VARCHAR(255) NOT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    notes TEXT,
    cost DECIMAL(10, 2) DEFAULT 0.00,
    FOREIGN KEY (itinerary_id) REFERENCES itineraries(id) ON DELETE CASCADE,
    FOREIGN KEY (day_id) REFERENCES itinerary_days(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
);

-- Activities Table (Optional, can also use itinerary_items)
CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    itinerary_id INT NOT NULL,
    day_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    time TIME,
    cost DECIMAL(10, 2) DEFAULT 0.00,
    notes TEXT,
    FOREIGN KEY (itinerary_id) REFERENCES itineraries(id) ON DELETE CASCADE,
    FOREIGN KEY (day_id) REFERENCES itinerary_days(id) ON DELETE CASCADE
);

-- Reviews Table
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    booking_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

-- Payments Table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_status ENUM('Success', 'Failed') NOT NULL DEFAULT 'Success',
    transaction_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (transaction_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert Default Admin User
INSERT INTO users (name, email, password_hash, role)
VALUES ('Admin', 'admin@questlog.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin');
-- Note: Default password is 'password'
