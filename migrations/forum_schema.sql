-- =====================================================================
-- Forum Module Schema — for database: voyage
-- All tables prefixed with sf_ to avoid conflicts with existing tables.
-- Run in phpMyAdmin (SQL tab) on the 'voyage' database.
-- =====================================================================

USE voyage;

-- ========== 1. USERS ==========
CREATE TABLE IF NOT EXISTS sf_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL DEFAULT '',
    email VARCHAR(180) NULL,
    bio VARCHAR(30) NULL DEFAULT NULL,
    roles JSON NOT NULL,
    is_private TINYINT(1) NOT NULL DEFAULT 0,
    profile_photo_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 2. POSTS ==========
CREATE TABLE IF NOT EXISTS sf_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auteur VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    chemin_photo VARCHAR(500) NOT NULL DEFAULT '',
    likes INT NOT NULL DEFAULT 0,
    music_id INT DEFAULT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_posts_auteur (auteur),
    INDEX idx_posts_date (date_creation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 3. COMMENTS ==========
CREATE TABLE IF NOT EXISTS sf_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    auteur VARCHAR(100) NOT NULL,
    contenu TEXT NOT NULL,
    date_commentaire DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sf_comments_post FOREIGN KEY (post_id) REFERENCES sf_posts(id) ON DELETE CASCADE,
    INDEX idx_sf_comments_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 4. REACTIONS ==========
CREATE TABLE IF NOT EXISTS sf_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    username VARCHAR(100) NOT NULL,
    reaction_type VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sf_reactions_post FOREIGN KEY (post_id) REFERENCES sf_posts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_sf_user_post_reaction (post_id, username),
    INDEX idx_sf_reactions_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 5. POST LIKES ==========
CREATE TABLE IF NOT EXISTS sf_post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    username VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_sf_post_user (post_id, username),
    INDEX idx_sf_post_likes_username (username),
    CONSTRAINT fk_sf_post_like_post FOREIGN KEY (post_id) REFERENCES sf_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 6. IMAGES ==========
CREATE TABLE IF NOT EXISTS sf_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    filename VARCHAR(500) NOT NULL,
    description VARCHAR(255) NULL,
    position INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_sf_images_post FOREIGN KEY (post_id) REFERENCES sf_posts(id) ON DELETE CASCADE,
    INDEX idx_sf_images_post (post_id),
    INDEX idx_sf_images_position (post_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 7. MUSIC ==========
CREATE TABLE IF NOT EXISTS sf_music (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    artist VARCHAR(150) DEFAULT NULL,
    filename VARCHAR(500) NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add music FK on posts
ALTER TABLE sf_posts
    ADD CONSTRAINT fk_sf_post_music FOREIGN KEY (music_id) REFERENCES sf_music(id) ON DELETE SET NULL;

-- ========== 8. VIDEOS ==========
CREATE TABLE IF NOT EXISTS sf_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    filename VARCHAR(500) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    position INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_sf_video_post FOREIGN KEY (post_id) REFERENCES sf_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 9. STORIES ==========
CREATE TABLE IF NOT EXISTS sf_stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auteur VARCHAR(100) NOT NULL,
    media_type VARCHAR(10) NOT NULL,
    filename VARCHAR(500) NOT NULL,
    music_id INT DEFAULT NULL,
    music_start DOUBLE DEFAULT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX idx_sf_stories_author (auteur),
    INDEX idx_sf_stories_expires (expires_at),
    CONSTRAINT fk_sf_stories_music FOREIGN KEY (music_id) REFERENCES sf_music(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 10. STORY VIEWS ==========
CREATE TABLE IF NOT EXISTS sf_story_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    story_id INT NOT NULL,
    viewer_username VARCHAR(100) NOT NULL,
    viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sf_story_viewer (story_id, viewer_username),
    KEY idx_sf_viewed_at (viewed_at),
    CONSTRAINT fk_sf_story_views_story FOREIGN KEY (story_id) REFERENCES sf_stories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 11. STORY LIKES ==========
CREATE TABLE IF NOT EXISTS sf_story_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    story_id INT NOT NULL,
    liker_username VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sf_story_liker (story_id, liker_username),
    KEY idx_sf_sl_created_at (created_at),
    CONSTRAINT fk_sf_story_likes_story FOREIGN KEY (story_id) REFERENCES sf_stories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 12. FOLLOWS ==========
CREATE TABLE IF NOT EXISTS sf_follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_username VARCHAR(100) NOT NULL,
    following_username VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'accepted',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_sf_follow (follower_username, following_username),
    KEY idx_sf_follower (follower_username),
    KEY idx_sf_following (following_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 13. MESSAGES ==========
CREATE TABLE IF NOT EXISTS sf_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_username VARCHAR(100) NOT NULL,
    receiver_username VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    story_id INT NULL DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sf_conv (sender_username, receiver_username, created_at),
    KEY idx_sf_receiver (receiver_username, is_read),
    CONSTRAINT fk_sf_messages_story FOREIGN KEY (story_id) REFERENCES sf_stories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 14. LOCATION PINS ==========
CREATE TABLE IF NOT EXISTS sf_location_pins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    label VARCHAR(120) NULL DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sf_location_user (username),
    INDEX idx_sf_location_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== CLEANUP OLD FORUM TABLES ==========
-- Drop old forum tables that are being replaced
DROP TABLE IF EXISTS forum_story_views;
DROP TABLE IF EXISTS forum_stories;
DROP TABLE IF EXISTS forum_reactions;
DROP TABLE IF EXISTS forum_comments;
DROP TABLE IF EXISTS forum_posts;

-- ========== SEED ADMIN USER ==========
-- Create a default admin user (password: "admin")
INSERT IGNORE INTO sf_users (username, password, email, roles, is_private, created_at)
VALUES (
    'admin',
    '$2y$13$KpEMXJDDa.O4/cTVVkMeYOfD2O6nZ6PfzEYK1lLi2LyY5xWQiTH/O',
    'admin@easytravel.com',
    '["ROLE_ADMIN"]',
    0,
    NOW()
);

SELECT 'Forum schema created successfully!' AS message;
