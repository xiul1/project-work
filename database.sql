-- Active: 1769184600561@@127.0.0.1@3306@KeyManager
CREATE DATABASE IF NOT EXISTS KeyManager;
CREATE TABLE users(
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash_master VARCHAR(255) NOT NULL,
    salt VARCHAR(255) NOT NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE credenziali (
    credential_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    service_name VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    password_encrypted TEXT NOT NULL,
    url VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP        
);
CREATE Table EncryptionKeys (
    key_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT(11),
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    encryption_key VARBINARY(512) NOT NULL,
    algorithm VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE Table devices (
    device_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT(11),
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    device_name VARCHAR(255) NOT NULL,
    device_type VARCHAR(100),
    last_used TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE synclogs (
    sync_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT(11),
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    device_id INT(11),
    FOREIGN KEY (device_id) REFERENCES devices(device_id)
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    sync_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) NOT NULL
);



