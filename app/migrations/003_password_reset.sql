-- contextkeeper Sprint 4 Migration
-- Run in phpMyAdmin at contextkeeper.org:2083
-- Database: ckmatt_contextkeeper

-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_token (token_hash),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add Google OAuth columns if not present
ALTER TABLE users ADD COLUMN IF NOT EXISTS google_avatar VARCHAR(512) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_provider ENUM('local','google') DEFAULT 'local';
