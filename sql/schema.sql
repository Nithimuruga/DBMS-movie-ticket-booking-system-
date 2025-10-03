-- MySQL Schema for Movie Booking System

-- Drop database if exists
DROP DATABASE IF EXISTS movie_booking_system;

-- Create the database
CREATE DATABASE movie_booking_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE movie_booking_system;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role TINYINT DEFAULT 0 COMMENT '0: User, 1: Admin',
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    last_login DATETIME
);

-- Movies table
CREATE TABLE movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    genre VARCHAR(100),
    language VARCHAR(50),
    duration INT NOT NULL COMMENT 'Duration in minutes',
    release_date DATE NOT NULL,
    poster VARCHAR(255),
    trailer_url VARCHAR(255),
    status ENUM('active', 'inactive', 'coming_soon') DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME
);

CREATE TABLE theaters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    city VARCHAR(50) NOT NULL,
    `rows` INT NOT NULL COMMENT 'Number of rows (A-Z)',
    `columns` INT NOT NULL COMMENT 'Number of columns',
    status ENUM('active', 'inactive', 'under_maintenance') DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME
);


-- Showtimes table
CREATE TABLE showtimes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    theater_id INT NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (theater_id) REFERENCES theaters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_showtime (movie_id, theater_id, date, time)
);

-- Bookings table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    showtime_id INT NOT NULL,
    booking_reference VARCHAR(20) NOT NULL UNIQUE,
    total_amount DECIMAL(10, 2) NOT NULL,
    booking_date DATETIME NOT NULL,
    status ENUM('confirmed', 'cancelled', 'pending') DEFAULT 'confirmed',
    cancelled_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (showtime_id) REFERENCES showtimes(id) ON DELETE RESTRICT
);

-- Booking Seats table
CREATE TABLE booking_seats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    seat_row INT NOT NULL COMMENT 'Row number (1 = A, 2 = B, etc.)',
    seat_column INT NOT NULL COMMENT 'Column number',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_seat (booking_id, seat_row, seat_column)
);

-- User Ratings table
CREATE TABLE ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    movie_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rating (user_id, movie_id)
);

-- Create an admin user (password: admin123)
INSERT INTO users (name, email, phone, password, role, created_at)
VALUES ('Admin User', 'admin@cinematime.com', '9876543210', '$2y$10$9NR5reFkATJJvXKFBLZXAuIzXwqNHBIUMmAI95yGBpEPnmQVn9hAG', 1, NOW());

-- Create a regular user (password: user123)
INSERT INTO users (name, email, phone, password, role, created_at)
VALUES ('Test User', 'user@example.com', '9876543211', '$2y$10$5pOXI8NWPi6oNCCRm63scuaHDH7WtjPGc6AkRKCoY3F5iJxCFhH8y', 0, NOW());

