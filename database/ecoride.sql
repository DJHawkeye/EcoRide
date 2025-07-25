-- DROP TABLES IF THEY EXIST (for fresh import)
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS problems;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS ride_validations;
DROP TABLE IF EXISTS rides;
DROP TABLE IF EXISTS vehicle_preferences;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS users;

-- USERS TABLE
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_driver TINYINT(1) DEFAULT 0,
    is_passenger TINYINT(1) DEFAULT 1,
    credits INT DEFAULT 0,
    role ENUM('user','employee','admin') DEFAULT 'user',
    is_suspended TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VEHICLES TABLE
CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    license_plate VARCHAR(20) NOT NULL,
    first_registration DATE NOT NULL,
    brand VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    fuel VARCHAR(50) NOT NULL,
    color VARCHAR(50) DEFAULT NULL,
    seats INT NOT NULL DEFAULT 4,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VEHICLE_PREFERENCES TABLE
CREATE TABLE vehicle_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    allow_smoking TINYINT(1) DEFAULT 0,
    allow_pets TINYINT(1) DEFAULT 0,
    allow_music TINYINT(1) DEFAULT 0,
    custom_preferences TEXT DEFAULT NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RIDES TABLE
CREATE TABLE rides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    departure VARCHAR(255) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    departure_time DATETIME NOT NULL,
    arrival_time DATETIME NOT NULL,
    seats_available INT NOT NULL DEFAULT 0,
    price DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('planned','started','ended','cancelled') DEFAULT 'planned',
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- BOOKINGS TABLE
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ride_id INT NOT NULL,
    passenger_id INT NOT NULL,
    seats_booked INT NOT NULL DEFAULT 1,
    booked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE,
    FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PROBLEMS TABLE
CREATE TABLE problems (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ride_id INT NOT NULL,
    passenger_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('open','resolved','closed') DEFAULT 'open',
    FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE,
    FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- REVIEWS TABLE
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ride_id INT NOT NULL,
    passenger_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE,
    FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- RIDE_VALIDATIONS TABLE
CREATE TABLE ride_validations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ride_id INT NOT NULL,
    passenger_id INT NOT NULL,
    validated TINYINT(1) DEFAULT 0,
    problem_reported TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE,
    FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

START TRANSACTION;

-- USERS
INSERT INTO users (id, username, email, password_hash, photo, created_at, is_driver, is_passenger, credits, role, is_suspended) VALUES
(1, 'alice', 'alice@example.com', '$2a$12$fBkHs3ERhjfCKtrBtKRBSOCugP2QUtE/Q.A1T97mb3O2ZNoiSj7PW', 'images/profile_default.jpg', NOW(), 1, 1, 100, 'user', 0),
(2, 'bob', 'bob@example.com', '$2a$12$fBkHs3ERhjfCKtrBtKRBSOCugP2QUtE/Q.A1T97mb3O2ZNoiSj7PW', 'images/profile_default.jpg', NOW(), 1, 0, 50, 'user', 0),
(3, 'charlie', 'charlie@example.com', '$2a$12$fBkHs3ERhjfCKtrBtKRBSOCugP2QUtE/Q.A1T97mb3O2ZNoiSj7PW', 'images/profile_default.jpg', NOW(), 0, 1, 20, 'user', 1),
(4, 'José', 'jose@example.com', '$2a$12$fBkHs3ERhjfCKtrBtKRBSOCugP2QUtE/Q.A1T97mb3O2ZNoiSj7PW', 'images/profile_default.jpg', NOW(), 0, 0, 2000, 'admin', 0),
(5, 'don', 'don@example.com', '$2a$12$fBkHs3ERhjfCKtrBtKRBSOCugP2QUtE/Q.A1T97mb3O2ZNoiSj7PW', 'images/profile_default.jpg', NOW(), 0, 0, 0, 'employee', 0);

-- VEHICLES
INSERT INTO vehicles (id, user_id, license_plate, first_registration, brand, model, fuel, color, seats, created_at) VALUES
(1, 1, 'ABC-1234', '2018-05-12', 'Toyota', 'Corolla', 'gasoline', 'blue', 5, NOW()),
(2, 1, 'XYZ-9876', '2020-11-01', 'Tesla', 'Model 3', 'electric', 'red', 4, NOW());

-- VEHICLE PREFERENCES
INSERT INTO vehicle_preferences (id, vehicle_id, allow_smoking, allow_pets, allow_music, custom_preferences) VALUES
(1, 1, 0, 1, 1, 'No loud music after 9pm'),
(2, 2, 1, 0, 1, NULL);

-- RIDES
INSERT INTO rides (id, driver_id, vehicle_id, departure, destination, departure_time, arrival_time, seats_available, price, created_at, status) VALUES
(1, 1, 1, 'Paris', 'Lyon', '2025-08-01 09:00:00', '2025-08-01 13:00:00', 3, 20.00, NOW(), 'planned'),
(2, 1, 2, 'Lyon', 'Marseille', '2025-08-02 14:00:00', '2025-08-02 18:00:00', 4, 15.00, NOW(), 'started'),
(3, 2, 1, 'Nantes', 'Bordeaux', '2025-07-15 07:30:00', '2025-07-15 12:00:00', 2, 40.00, NOW(), 'ended');

-- BOOKINGS
INSERT INTO bookings (id, ride_id, passenger_id, seats_booked, booked_at) VALUES
(1, 1, 2, 1, NOW()),
(2, 1, 4, 2, NOW()),
(3, 2, 2, 1, NOW()),
(4, 3, 2, 1, NOW());

-- PROBLEMS
INSERT INTO problems (id, ride_id, passenger_id, comment, created_at, status) VALUES
(1, 1, 2, 'Driver was late', NOW(), 'open'),
(2, 3, 2, 'Car was dirty', NOW(), 'resolved'),
(3, 2, 4, 'Seatbelt broken', NOW(), 'closed');

-- REVIEWS
INSERT INTO reviews (id, ride_id, passenger_id, rating, comment, created_at, status) VALUES
(1, 1, 2, 5, 'Great ride!', NOW(), 'approved'),
(2, 3, 2, 3, 'Okay but could be better.', NOW(), 'pending'),
(3, 2, 4, 1, 'Very bad experience.', NOW(), 'rejected');

-- RIDE VALIDATIONS
INSERT INTO ride_validations (id, ride_id, passenger_id, validated, problem_reported, created_at) VALUES
(1, 1, 2, 1, 0, NOW()),
(2, 3, 2, 0, 1, NOW()),
(3, 2, 4, 1, 0, NOW());

COMMIT;
