-- AppGuard Admin Console — PostgreSQL schema (for Render + Neon)
-- Run this ONCE in the Neon SQL Editor (or `psql "$DATABASE_URL" -f schema-postgres.sql`)
-- after creating your Neon project. Run the WHOLE file in one go.

-- ---------------------------------------------------------------
-- Admin login (console operators, not app end-users)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
  id SERIAL PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_sessions (
  token CHAR(64) PRIMARY KEY,
  admin_id INT NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------
-- Core app data
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(20) PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  token VARCHAR(50) UNIQUE NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active','blocked')),
  expiry DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS devices (
  id VARCHAR(20) PRIMARY KEY,
  user_id VARCHAR(20) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  platform VARCHAR(150),
  ip VARCHAR(45),
  last_seen TIMESTAMP
);

CREATE TABLE IF NOT EXISTS logs (
  id SERIAL PRIMARY KEY,
  action VARCHAR(30) NOT NULL,
  text VARCHAR(255) NOT NULL,
  time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- NOTE: MySQL's reserved-ish `force` column is renamed force_update here
-- (and in update_push.php / update_history_list.php) to avoid any
-- identifier-quoting headaches in Postgres.
CREATE TABLE IF NOT EXISTS update_history (
  id SERIAL PRIMARY KEY,
  version VARCHAR(30) NOT NULL,
  notes TEXT,
  force_update SMALLINT NOT NULL DEFAULT 0,
  target VARCHAR(30) NOT NULL DEFAULT 'all',
  time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS extend_links (
  id SERIAL PRIMARY KEY,
  user_id VARCHAR(20) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  days INT NOT NULL,
  sig CHAR(64) NOT NULL,
  redeemed SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL
);

-- Self-service "New user? Generate a token" feature (frontend/extend.html)
CREATE TABLE IF NOT EXISTS device_registrations (
  device_id VARCHAR(64) PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  user_id VARCHAR(20) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_device_registrations_ip ON device_registrations(ip);

CREATE TABLE IF NOT EXISTS signup_requests (
  id SERIAL PRIMARY KEY,
  device_id VARCHAR(64) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  sig CHAR(64) NOT NULL,
  redeemed SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL
);

-- ---------------------------------------------------------------
-- Seed data — same demo records the original mock UI shipped with
-- ---------------------------------------------------------------
INSERT INTO users (id, name, email, token, status, expiry) VALUES
('u1','Rohan Mehta','rohan.mehta@example.com','TKN-7H2K-99XQ-3A1D','active','2026-08-06'),
('u2','Aisha Khan','aisha.khan@example.com','TKN-2M9P-11ZR-6C4F','active','2026-07-27'),
('u3','Vikram Studios','vikram.studio@example.com','TKN-5X0L-77YT-9B2E','blocked','2026-07-30'),
('u4','Neha Verma','neha.verma@example.com','TKN-8Q3N-44WS-1D7G','active','2026-08-12'),
('u5','Sameer Iqbal','sameer.iqbal@example.com','TKN-1Z6B-22VC-8E5H','active','2026-07-19'),
('u6','Priya Nair','priya.nair@example.com','TKN-3F8D-55XA-0G9J','blocked','2026-08-01'),
('u7','Karthik Rao','karthik.rao@example.com','TKN-9J4K-33BE-7F1M','active','2026-08-20')
ON CONFLICT (id) DO NOTHING;

INSERT INTO devices (id, user_id, platform, ip, last_seen) VALUES
('DEV-88F1','u1','Android 14 • Redmi Note 13','103.21.58.44','2026-07-24 21:10:00'),
('DEV-12AB','u2','iOS 17 • iPhone 13','182.74.12.9','2026-07-25 08:02:00'),
('DEV-99CD','u2','Windows 11 • Desktop','182.74.12.9','2026-07-24 19:40:00'),
('DEV-33EF','u3','Android 13 • Vivo V29','49.36.88.201','2026-07-20 11:15:00'),
('DEV-44GH','u3','Android 12 • Oppo A78','106.51.22.7','2026-07-20 12:03:00'),
('DEV-55IJ','u3','Windows 10 • Desktop','157.44.9.61','2026-07-20 12:40:00'),
('DEV-66KL','u3','Android 14 • OnePlus 11','49.36.88.201','2026-07-19 22:12:00'),
('DEV-77MN','u4','Android 14 • Samsung S23','115.98.4.19','2026-07-25 07:55:00'),
('DEV-78OP','u4','iOS 18 • iPad Air','115.98.4.19','2026-07-24 14:20:00'),
('DEV-79QR','u4','Android 13 • Realme 11','223.30.11.85','2026-07-23 09:05:00'),
('DEV-80ST','u5','Android 12 • Redmi 10','27.5.66.130','2026-07-18 20:30:00'),
('DEV-81UV','u6','iOS 17 • iPhone 15','49.207.44.3','2026-07-15 10:00:00'),
('DEV-82WX','u6','macOS • MacBook Air','49.207.44.3','2026-07-15 10:05:00'),
('DEV-83YZ','u7','Android 14 • Pixel 8','103.9.128.44','2026-07-25 06:40:00'),
('DEV-84AA','u7','Android 13 • Poco X6','171.79.3.18','2026-07-24 23:11:00'),
('DEV-85BB','u7','Windows 11 • Desktop','59.92.4.200','2026-07-24 18:02:00'),
('DEV-86CC','u7','Linux • Ubuntu','106.219.4.71','2026-07-23 21:19:00'),
('DEV-87DD','u7','Android 12 • Vivo Y100','49.36.201.9','2026-07-22 15:47:00')
ON CONFLICT (id) DO NOTHING;

INSERT INTO logs (action, text, time) VALUES
('block','Blocked Vikram Studios — token reused across 4 devices','2026-07-24 22:05:00'),
('extend','Extended Neha Verma token by 15 days','2026-07-23 09:12:00'),
('push-update','Pushed v2.4.0 (forced) to all users','2026-07-22 18:30:00'),
('block','Blocked Priya Nair — chargeback reported','2026-07-21 10:00:00');

INSERT INTO update_history (version, notes, force_update, target, time) VALUES
('2.4.0','Fixed login crash on Android 12; improved token sync speed.',1,'all','2026-07-22 18:30:00');

-- NOTE: no admin_users row is created here on purpose — visit
-- backend/setup.php once after deploying, then remove it from the repo.
