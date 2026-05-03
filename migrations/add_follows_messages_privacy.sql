-- =====================================================================
-- Social features migration: privacy, follows, messages
-- Run once against agence_voyage_db:
--   mysql -u root agence_voyage_db < migrations/add_follows_messages_privacy.sql
-- =====================================================================

-- 1) Add privacy flag to users
ALTER TABLE users
    ADD COLUMN is_private TINYINT(1) NOT NULL DEFAULT 0 AFTER roles;

-- 2) Follow relationships
--   follower_username = who's following
--   following_username = who's being followed
--   status = 'accepted' | 'pending' (pending only for private accounts)
CREATE TABLE IF NOT EXISTS follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_username VARCHAR(100) NOT NULL,
    following_username VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'accepted',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_follow (follower_username, following_username),
    KEY idx_follower (follower_username),
    KEY idx_following (following_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Direct messages between two users
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_username VARCHAR(100) NOT NULL,
    receiver_username VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_conv (sender_username, receiver_username, created_at),
    KEY idx_receiver (receiver_username, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
