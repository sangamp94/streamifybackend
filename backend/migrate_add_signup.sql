-- Run this ONCE in phpMyAdmin (SQL tab -> paste -> Go) on your EXISTING
-- AppGuard database, to add support for the self-service "New user?
-- Generate a token" feature. Safe to run even if you're not sure whether
-- you've already run it — both tables use IF NOT EXISTS.
--
-- This does NOT touch or delete any existing data (users, devices,
-- tokens, logs, etc). It only adds two new tables.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS device_registrations (
  device_id VARCHAR(64) PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  user_id VARCHAR(20) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (ip),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS signup_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  device_id VARCHAR(64) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  sig CHAR(64) NOT NULL,
  redeemed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
