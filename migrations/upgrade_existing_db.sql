-- =====================================================================
-- Migration: upgrade existing agence_voyage_db for Symfony
-- Keeps all existing data. Adds only the columns Symfony needs.
-- Run this in phpMyAdmin (SQL tab) on database: agence_voyage_db
-- =====================================================================

USE agence_voyage_db;

-- ---------- USERS ----------
-- Add Symfony security columns (password, email, roles, created_at)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS password VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS email VARCHAR(180) NULL,
    ADD COLUMN IF NOT EXISTS roles JSON NOT NULL,
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Make sure username is the PRIMARY KEY (if not already)
-- If your users table has no PK, uncomment the next line:
-- ALTER TABLE users ADD PRIMARY KEY (username);

-- Give existing users a default role
UPDATE users SET roles = '["ROLE_USER"]' WHERE roles IS NULL OR roles = '';

-- Give the first user admin rights + a known password
-- (default password: "admin" — change it after login)
-- Hash for "admin": $2y$13$KpEMXJDDa.O4/cTVVkMeYOfD2O6nZ6PfzEYK1lLi2LyY5xWQiTH/O
UPDATE users
SET roles = '["ROLE_ADMIN"]',
    password = '$2y$13$KpEMXJDDa.O4/cTVVkMeYOfD2O6nZ6PfzEYK1lLi2LyY5xWQiTH/O'
WHERE username = (SELECT username FROM (SELECT username FROM users LIMIT 1) AS t);

-- Give other users a default password "password"
-- Hash for "password": $2y$13$1tPq2H9jDFxP2eVvV.GhQuIqX0NU3hO8lVZ6kGpXiIMm5HIjIXGV2
UPDATE users
SET password = '$2y$13$1tPq2H9jDFxP2eVvV.GhQuIqX0NU3hO8lVZ6kGpXiIMm5HIjIXGV2'
WHERE password = '';

-- ---------- COMMENTS ----------
-- Add an id column if it doesn't exist (Symfony needs a single PK)
ALTER TABLE comments
    ADD COLUMN IF NOT EXISTS id INT AUTO_INCREMENT PRIMARY KEY FIRST;

-- ---------- REACTIONS ----------
-- Add an id column if it doesn't exist
ALTER TABLE reactions
    ADD COLUMN IF NOT EXISTS id INT AUTO_INCREMENT PRIMARY KEY FIRST;

-- Ensure unique constraint on (post_id, username) so a user can only react once per post
-- (Drop old index if it exists, then recreate)
SET @idx := (SELECT COUNT(1) FROM information_schema.statistics
             WHERE table_schema = 'agence_voyage_db'
             AND table_name = 'reactions'
             AND index_name = 'unique_user_post_reaction');
SET @sql := IF(@idx = 0,
    'ALTER TABLE reactions ADD CONSTRAINT unique_user_post_reaction UNIQUE (post_id, username)',
    'SELECT "index already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Done!
SELECT 'Migration terminée avec succès !' AS message;
SELECT username, LEFT(password, 20) AS password_hash, roles FROM users;
