-- Database: airport_management
-- Users table to store all user accounts (admin, staff, travellers)
CREATE TABLE users (
id INT AUTO_INCREMENT PRIMARY KEY,
first_name VARCHAR(50) NOT NULL,
last_name VARCHAR(50) NOT NULL,
email VARCHAR(100) NOT NULL UNIQUE,
password VARCHAR(255) NOT NULL,
role ENUM('admin', 'staff', 'traveller') NOT NULL,
status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
created_at DATETIME NOT NULL,
updated_at DATETIME DEFAULT NULL,
last_login DATETIME DEFAULT NULL
);
-- Airports table to store airport information
CREATE TABLE airports (
code VARCHAR(10) PRIMARY KEY,
name VARCHAR(100) NOT NULL,
city VARCHAR(50) NOT NULL,
country VARCHAR(50) NOT NULL,
latitude DECIMAL(10,6),
longitude DECIMAL(10,6),
timezone VARCHAR(50),
status ENUM('active', 'inactive') DEFAULT 'active'
);
-- Airlines table to store airline information
CREATE TABLE airlines (
id INT AUTO_INCREMENT PRIMARY KEY,
code VARCHAR(10) NOT NULL UNIQUE,
name VARCHAR(100) NOT NULL,
logo VARCHAR(255),
status ENUM('active', 'inactive') DEFAULT 'active'
);
-- Aircraft table to store information about aircrafts
CREATE TABLE aircraft (
id INT AUTO_INCREMENT PRIMARY KEY,
airline_id INT,
name VARCHAR(100) NOT NULL,
model VARCHAR(50) NOT NULL,
capacity INT NOT NULL,
status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
FOREIGN KEY (airline_id) REFERENCES airlines(id)
);
-- Flights table to store flight information
CREATE TABLE flights (
id INT AUTO_INCREMENT PRIMARY KEY,
flight_number VARCHAR(20) NOT NULL,
airline_id INT NOT NULL,
aircraft_id INT NOT NULL,
departure_airport VARCHAR(10) NOT NULL,
arrival_airport VARCHAR(10) NOT NULL,
departure_time DATETIME NOT NULL,
arrival_time DATETIME NOT NULL,
status ENUM('scheduled', 'boarding', 'departed', 'in_air', 'landed', 'arrived', 'delayed', 'cancelled') DEFAULT 'scheduled',
terminal VARCHAR(10),
gate VARCHAR(10),
created_at DATETIME NOT NULL,
updated_at DATETIME DEFAULT NULL,
FOREIGN KEY (airline_id) REFERENCES airlines(id),
FOREIGN KEY (aircraft_id) REFERENCES aircraft(id),
FOREIGN KEY (departure_airport) REFERENCES airports(code),
FOREIGN KEY (arrival_airport) REFERENCES airports(code)
);
-- Flight Delays table to store information about delayed flights
CREATE TABLE flight_delays (
id INT AUTO_INCREMENT PRIMARY KEY,
flight_id INT NOT NULL,
status ENUM('delayed', 'cancelled') NOT NULL,
reason VARCHAR(255),
estimated_departure_time DATETIME,
created_at DATETIME NOT NULL,
FOREIGN KEY (flight_id) REFERENCES flights(id)
);
-- Prices table to store ticket prices for different flight classes
CREATE TABLE prices (
id INT AUTO_INCREMENT PRIMARY KEY,
flight_id INT NOT NULL,
class ENUM('economy', 'premium_economy', 'business', 'first') NOT NULL,
amount DECIMAL(10,2) NOT NULL,
currency VARCHAR(3) DEFAULT 'USD',
is_promotion BOOLEAN DEFAULT FALSE,
valid_from DATETIME NOT NULL,
valid_until DATETIME,
FOREIGN KEY (flight_id) REFERENCES flights(id),
UNIQUE KEY (flight_id, class)
);
-- Traveller Profiles to store additional information about travellers
CREATE TABLE traveller_profiles (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
passport_number VARCHAR(50),
nationality VARCHAR(50),
date_of_birth DATE,
gender ENUM('male', 'female', 'other'),
address TEXT,
phone_number VARCHAR(20),
emergency_contact_name VARCHAR(100),
emergency_contact_phone VARCHAR(20),
preferences TEXT,
created_at DATETIME NOT NULL,
updated_at DATETIME DEFAULT NULL,
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
-- Booking Transactions table for payment tracking
CREATE TABLE booking_transactions (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
amount DECIMAL(10,2) NOT NULL,
currency VARCHAR(3) DEFAULT 'USD',
payment_method VARCHAR(50),
payment_status VARCHAR(50),
transaction_reference VARCHAR(100),
status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
created_at DATETIME NOT NULL,
updated_at DATETIME DEFAULT NULL,
FOREIGN KEY (user_id) REFERENCES users(id)
);
-- Bookings table to store flight bookings
CREATE TABLE bookings (
id INT AUTO_INCREMENT PRIMARY KEY,
booking_reference VARCHAR(10) NOT NULL UNIQUE,
user_id INT NOT NULL,
flight_id INT NOT NULL,
transaction_id INT,
seat_number VARCHAR(10),
class ENUM('economy', 'premium_economy', 'business', 'first') DEFAULT 'economy',
price DECIMAL(10,2) NOT NULL,
status ENUM('pending', 'confirmed', 'checked_in', 'cancelled', 'refunded') DEFAULT 'pending',
booking_date DATETIME NOT NULL,
created_at DATETIME NOT NULL,
updated_at DATETIME DEFAULT NULL,
FOREIGN KEY (user_id) REFERENCES users(id),
FOREIGN KEY (flight_id) REFERENCES flights(id),
FOREIGN KEY (transaction_id) REFERENCES booking_transactions(id)
);
-- Check-ins table to track passenger check-ins
CREATE TABLE check_ins (
id INT AUTO_INCREMENT PRIMARY KEY,
booking_id INT NOT NULL,
boarding_pass_number VARCHAR(20) NOT NULL UNIQUE,
check_in_time DATETIME NOT NULL,
check_in_method ENUM('online', 'kiosk', 'counter') NOT NULL,
checked_in_by INT, -- staff ID if checked in by staff
created_at DATETIME NOT NULL,
FOREIGN KEY (booking_id) REFERENCES bookings(id),
FOREIGN KEY (checked_in_by) REFERENCES users(id)
);
-- Baggage table to track passenger baggage
CREATE TABLE baggage (
id INT AUTO_INCREMENT PRIMARY KEY,
booking_id INT NOT NULL,
tracking_number VARCHAR(20) NOT NULL UNIQUE,
weight DECIMAL(5,2) NOT NULL,
size VARCHAR(50) NOT NULL,
special_handling TEXT,
status ENUM('checked_in', 'security_screening', 'loading', 'in_transit', 'unloading', 'arrived', 'delivered', 'delayed', 'lost') DEFAULT 'checked_in',
last_updated DATETIME NOT NULL,
created_at DATETIME NOT NULL,
FOREIGN KEY (booking_id) REFERENCES bookings(id)
);
-- Baggage Status Logs to track baggage status changes
CREATE TABLE baggage_status_logs (
id INT AUTO_INCREMENT PRIMARY KEY,
baggage_id INT NOT NULL,
staff_id INT,
previous_status VARCHAR(50) NOT NULL,
new_status VARCHAR(50) NOT NULL,
location VARCHAR(100),
remarks TEXT,
created_at DATETIME NOT NULL,
FOREIGN KEY (baggage_id) REFERENCES baggage(id),
FOREIGN KEY (staff_id) REFERENCES users(id)
);
-- Flight Status Logs to track flight status changes
CREATE TABLE flight_status_logs (
id INT AUTO_INCREMENT PRIMARY KEY,
flight_id INT NOT NULL,
staff_id INT NOT NULL,
previous_status VARCHAR(50) NOT NULL,
new_status VARCHAR(50) NOT NULL,
remarks TEXT,
created_at DATETIME NOT NULL,
FOREIGN KEY (flight_id) REFERENCES flights(id),
FOREIGN KEY (staff_id) REFERENCES users(id)
);

-- Auth Tokens for "Remember Me" functionality
CREATE TABLE auth_tokens (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
selector VARCHAR(255) NOT NULL,
token VARCHAR(255) NOT NULL,
expires_at DATETIME NOT NULL,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE flight_crew (
id INT AUTO_INCREMENT PRIMARY KEY,
flight_id INT NOT NULL,
user_id INT NOT NULL, -- staff member
role ENUM('pilot', 'co_pilot', 'flight_attendant', 'engineer') NOT NULL,
is_lead BOOLEAN DEFAULT FALSE,
created_at DATETIME NOT NULL,
FOREIGN KEY (flight_id) REFERENCES flights(id),
FOREIGN KEY (user_id) REFERENCES users(id),
UNIQUE KEY (flight_id, user_id, role)
);

CREATE TABLE flight_schedules (
id INT AUTO_INCREMENT PRIMARY KEY,
flight_number VARCHAR(20) NOT NULL,
airline_id INT NOT NULL,
aircraft_id INT NOT NULL,
departure_airport VARCHAR(10) NOT NULL,
arrival_airport VARCHAR(10) NOT NULL,
departure_time TIME NOT NULL, -- Time of day
arrival_time TIME NOT NULL, -- Time of day
duration INT NOT NULL, -- In minutes
days_of_week VARCHAR(13) NOT NULL, -- '1,2,3,4,5' for Mon-Fri
terminal VARCHAR(10),
gate VARCHAR(10),
status ENUM('active', 'inactive') DEFAULT 'active',
created_at DATETIME NOT NULL,
updated_at DATETIME DEFAULT NULL,
FOREIGN KEY (airline_id) REFERENCES airlines(id),
FOREIGN KEY (aircraft_id) REFERENCES aircraft(id),
FOREIGN KEY (departure_airport) REFERENCES airports(code),
FOREIGN KEY (arrival_airport) REFERENCES airports(code)
);
CREATE TABLE flight_seats (
id INT AUTO_INCREMENT PRIMARY KEY,
flight_id INT NOT NULL,
seat_number VARCHAR(10) NOT NULL,
class ENUM('economy', 'premium_economy', 'business', 'first') NOT NULL,
status ENUM('available', 'booked', 'blocked') DEFAULT 'available',
passenger_id INT DEFAULT NULL,
booking_id INT DEFAULT NULL,
FOREIGN KEY (flight_id) REFERENCES flights(id),
FOREIGN KEY (passenger_id) REFERENCES users(id),
FOREIGN KEY (booking_id) REFERENCES bookings(id),
UNIQUE KEY (flight_id, seat_number)
);
ALTER TABLE flights ADD COLUMN duration INT AFTER arrival_time;
ALTER TABLE airports
ADD COLUMN timezone_offset VARCHAR(6),
ADD COLUMN dst_observed BOOLEAN DEFAULT FALSE;

CREATE TABLE IF NOT EXISTS payment_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    payment_id INT NOT NULL,
    card_number VARCHAR(255) NOT NULL,
    pin_number VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (payment_id) REFERENCES payments(id)
)";
-- Index for better performance
CREATE INDEX idx_flights_departure_time ON flights(departure_time);
CREATE INDEX idx_flights_arrival_time ON flights(arrival_time);
CREATE INDEX idx_flights_status ON flights(status);
CREATE INDEX idx_bookings_booking_reference ON bookings(booking_reference);
CREATE INDEX idx_bookings_status ON bookings(status);
CREATE INDEX idx_baggage_tracking_number ON baggage(tracking_number);
CREATE INDEX idx_baggage_status ON baggage(status);