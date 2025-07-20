-- Frontier Tower Captive Portal Database Schema

CREATE DATABASE IF NOT EXISTS frontier_portal;
USE frontier_portal;

-- Events table (admin managed)
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Floors table (admin managed)
CREATE TABLE floors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    floor_number VARCHAR(10) NOT NULL,
    floor_name VARCHAR(255) NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Members table
CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    floor_id INT,
    mac_address VARCHAR(17),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (floor_id) REFERENCES floors(id)
);

-- Guests table
CREATE TABLE guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    mac_address VARCHAR(17),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Event log table
CREATE TABLE event_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    attendee_email VARCHAR(255) NOT NULL,
    attendee_name VARCHAR(255) NOT NULL,
    mac_address VARCHAR(17),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id)
);

-- Admin users table
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default floors
INSERT INTO floors (floor_number, floor_name) VALUES
('1', 'Ground Floor'),
('2', 'Second Floor'),
('3', 'Third Floor'),
('4', 'Fourth Floor'),
('5', 'Fifth Floor');

-- Insert sample events
INSERT INTO events (name, description) VALUES
('Tech Meetup', 'Monthly technology meetup'),
('Business Conference', 'Quarterly business conference'),
('Workshop Series', 'Educational workshop series');

-- Create default admin user (password: admin123 - change this!)
INSERT INTO admin_users (username, password_hash, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@frontiertower.com');
