-- =====================================================================
-- FORUM MODULE UPDATE — FOR DATABASE: voyage
-- This script will create the new sf_ tables and import the data.
-- It also cleans up the old forum_ tables.
-- Run this in phpMyAdmin (SQL tab) on your 'voyage' database.
-- =====================================================================

USE voyage;

-- ========== CLEANUP OLD FORUM TABLES ==========
DROP TABLE IF EXISTS forum_story_views;
DROP TABLE IF EXISTS forum_stories;
DROP TABLE IF EXISTS forum_reactions;
DROP TABLE IF EXISTS forum_comments;
DROP TABLE IF EXISTS forum_posts;

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

-- ========== 2. MUSIC ==========
CREATE TABLE IF NOT EXISTS sf_music (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    artist VARCHAR(150) DEFAULT NULL,
    filename VARCHAR(500) NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 3. POSTS ==========
CREATE TABLE IF NOT EXISTS sf_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auteur VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    chemin_photo VARCHAR(500) NOT NULL DEFAULT '',
    likes INT NOT NULL DEFAULT 0,
    music_id INT DEFAULT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_posts_auteur (auteur),
    INDEX idx_posts_date (date_creation),
    CONSTRAINT fk_sf_post_music FOREIGN KEY (music_id) REFERENCES sf_music(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 4. COMMENTS ==========
CREATE TABLE IF NOT EXISTS sf_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    auteur VARCHAR(100) NOT NULL,
    contenu TEXT NOT NULL,
    date_commentaire DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sf_comments_post FOREIGN KEY (post_id) REFERENCES sf_posts(id) ON DELETE CASCADE,
    INDEX idx_sf_comments_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 5. REACTIONS ==========
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

-- ========== 6. POST LIKES ==========
CREATE TABLE IF NOT EXISTS sf_post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    username VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_sf_post_user (post_id, username),
    INDEX idx_sf_post_likes_username (username),
    CONSTRAINT fk_sf_post_like_post FOREIGN KEY (post_id) REFERENCES sf_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== 7. IMAGES ==========
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

-- ========== DATA IMPORT ==========

-- 1. USERS
INSERT IGNORE INTO `sf_users` (`id`, `username`, `password`, `email`, `bio`, `roles`, `is_private`, `profile_photo_path`, `created_at`) VALUES
(1, 'admin', '$2y$13$6NFvvRDG0U09Bd4HkNtTrO/gG58.U8S6Qrb3mIP/4sI4Vcupu1F/S', 'admin@easytravel.com', NULL, '[\"ROLE_ADMIN\"]', 0, NULL, '2026-04-25 19:47:57'),
(2, 'behybehy', '$2y$13$R45O5725nvanPtTR3npLyOQ1/26LdHdFduGxBEvmHbYzNX35e0QgG', 'behybehy@gmail.com', NULL, '[\"ROLE_USER\"]', 0, '35b63108ae7e7be77c44ad1420dcb86d-69ed3080ae916.jpg', '2026-04-25 21:35:48'),
(3, 'mohamed', '$2y$13$uzN/Nh9MXkPB/XsAFVfYZe/IHbDE/k9PfBzToG9pyIm9AiGza6TpG', 'mouadh@gmail.com', NULL, '[\"ROLE_USER\"]', 0, '464411394-8275821155873823-6935755919950091175-n-69edab3fa2867.jpg', '2026-04-25 23:27:31'),
(4, 'shayma', '$2y$13$BM19jQZ1fGwnyTnFrr5lyeq/c5CgvEjSOta.2eiMgKEpesn6fA5e6', 'shayma@gmail.com', NULL, '[\"ROLE_USER\"]', 0, NULL, '2026-04-25 23:43:33'),
(5, 'nour', '$2y$13$hQOq/DbckBa6.EZ1LxJTuetrfw3IZOAzN7tb6xZpYvgbNsIuykGWu', 'nour@gmail.com', NULL, '[\"ROLE_USER\"]', 1, 'Makeup-aesthetic-69ef15c187cb0.jpg', '2026-04-27 09:49:42'),
(6, 'leyen', '$2y$13$pVrDbnJEhMTXwDLz7z5Ozu7LdedfIPWsFHmAhaTSFcuUJanMC.2uS', 'leyen@gmail.com', NULL, '[\"ROLE_USER\"]', 0, '-69ef17a838089.jpg', '2026-04-27 09:50:20'),
(7, 'fatou', '$2y$13$msFJ.CvspELzlUgYuHvjz.HHe8pZPk5hlcjSQTXlhA/hLxEBZv.jW', 'fatou@gmail.com', NULL, '[\"ROLE_USER\"]', 0, '-69ef1ca814aad.jpg', '2026-04-27 09:50:54'),
(8, 'amine', '$2y$13$72bPVBQbK.WhYkkD6HvHu.wBG6GsxSuW6mQ3sDXUsxYznANZbZ//W', 'amine@gmail.com', NULL, '[\"ROLE_USER\"]', 0, NULL, '2026-04-27 09:51:30');

-- 2. MUSIC
INSERT IGNORE INTO `sf_music` (`id`, `title`, `artist`, `filename`, `uploaded_at`) VALUES
(1, 'music1', 'artist1', 'Guliz-Ayla-Olmazsan-Olmaz-Guliz-Ayla-128k-69ed32de405e5.mp3', '2026-04-25 23:32:14'),
(2, 'morafrefo deleli', 'fairuz', '1955-69ef12a2d3baa.mp3', '2026-04-27 09:39:14'),
(3, 'lamericano', 'tu vuo', 'Tu-vuo-fa-lamericano-69ef12e2206bd.mp3', '2026-04-27 09:40:18'),
(4, 'un jour tu ris', 'erik', 'Un-jour-tu-ris-un-jour-tu-pleures-No-Soy-De-Aqui-69ef13489f1b4.mp3', '2026-04-27 09:42:00'),
(5, 'lama bada yata', 'nour', 'Lamma-Bada-Yatathana-69ef13ab46dc0.mp3', '2026-04-27 09:43:39'),
(6, 'bang bang', 'cinatra', 'Bang-Bang-69ef13c7f2ce6.mp3', '2026-04-27 09:44:07'),
(7, 'love in portofino', 'dalida', 'Love-In-Portofino-69ef13dee1459.mp3', '2026-04-27 09:44:30'),
(8, 'salma ya salama', 'dalida', 'Dalida-Salma-Ya-Salama-Clip-Officiel-69ef1407314cc.mp3', '2026-04-27 09:45:11');

-- 3. POSTS
INSERT IGNORE INTO `sf_posts` (`id`, `auteur`, `description`, `chemin_photo`, `likes`, `music_id`, `date_creation`) VALUES
(1, 'mohamed', 'ggggg', '067b9c3a7d77ee1822d2aea5370fbffe-69ed3222e09f5.jpg', 1, NULL, '2026-04-25 23:29:06'),
(2, 'nour', 'Les pyramides...', 'jpg-39-69ef160daff50.jpg', 0, 8, '2026-04-27 09:53:49'),
(3, 'nour', 'La riviere...', 'Last-time-I-caught-Plaza-de-Espana-at-sunrise-this-time-I-stayed-for-the-sunset-glow-69ef16cd6aee0.jpg', 0, 2, '2026-04-27 09:57:01'),
(4, 'leyen', 'Sous le soleil de Malaga...', 'Malaga-Spain-69ef1818e8f46.jpg', 0, 5, '2026-04-27 10:02:32'),
(5, 'leyen', 'Les gargouilles de la Sagrada Familia...', 'Madrid-l-essentiel-en-quelques-jours-69ef18969a04d.jpg', 0, 4, '2026-04-27 10:04:38'),
(6, 'leyen', '#paris', 'Le-Louvre-paris-art-museum-69ef19a68608b.jpg', 0, 2, '2026-04-27 10:09:10'),
(7, 'leyen', 'A la lisiere d une montagne...', '5a89ac9405a365e7356b3183768a62f6-69ef1bc2bb1b0.jpg', 0, 6, '2026-04-27 10:18:10');

-- 4. IMAGES
INSERT IGNORE INTO `sf_images` (`id`, `post_id`, `filename`, `description`, `position`) VALUES
(1, 1, '067b9c3a7d77ee1822d2aea5370fbffe-69ed3222e09f5.jpg', NULL, 0),
(2, 2, 'jpg-39-69ef160daff50.jpg', NULL, 0),
(3, 3, 'Last-time-I-caught-Plaza-de-Espana-at-sunrise-69ef16cd6aee0.jpg', NULL, 0),
(4, 4, 'Malaga-Spain-69ef1818e8f46.jpg', NULL, 0),
(17, 6, 'Fall-diary-in-Paris-69ef19a685280.jpg', NULL, 1),
(18, 6, 'France-Paris-Louvre-Portrait-69ef19a685a23.jpg', NULL, 2),
(19, 6, 'Le-Louvre-paris-art-museum-69ef19a68608b.jpg', NULL, 0),
(21, 7, '5a89ac9405a365e7356b3183768a62f6-69ef1bc2bb1b0.jpg', NULL, 0),
(22, 7, '4x4-69ef1bc2bba39.jpg', NULL, 1);

-- 5. COMMENTS
INSERT IGNORE INTO `sf_comments` (`id`, `post_id`, `auteur`, `contenu`, `date_commentaire`) VALUES
(1, 1, 'mohamed', 'hhhh', '2026-04-25 23:29:25'),
(2, 1, 'admin', 'nn', '2026-04-25 23:42:53'),
(3, 1, 'shayma', 'HI', '2026-04-25 23:44:45');

-- 6. FOLLOWS
INSERT IGNORE INTO `sf_follows` (`id`, `follower_username`, `following_username`, `status`, `created_at`) VALUES
(2, 'behybehy', 'mohamed', 'accepted', '2026-04-27 00:48:46'),
(3, 'fatou', 'leyen', 'accepted', '2026-04-27 10:22:21');

-- 7. MESSAGES
INSERT IGNORE INTO `sf_messages` (`id`, `sender_username`, `receiver_username`, `content`, `story_id`, `is_read`, `created_at`) VALUES
(1, 'behybehy', 'mohamed', 'HI', NULL, 1, '2026-04-27 00:48:59'),
(2, 'behybehy', 'mohamed', 'wow', NULL, 1, '2026-04-27 00:50:30'),
(3, 'fatou', 'leyen', 'hello', NULL, 0, '2026-04-27 10:22:34');

-- 8. STORIES
INSERT IGNORE INTO `sf_stories` (`id`, `auteur`, `media_type`, `filename`, `music_id`, `music_start`, `created_at`, `expires_at`) VALUES
(4, 'nour', 'image', 'Last-time-I-caught-Plaza-de-Espana-at-sunrise-69ef17595cac0.jpg', 3, 0, '2026-04-27 09:59:21', '2026-04-28 09:59:21'),
(5, 'leyen', 'image', 'being-a-tourist-today-69ef18f141352.jpg', 1, 0, '2026-04-27 10:06:09', '2026-04-28 10:06:09'),
(6, 'fatou', 'image', 'Cooking-with-Nonna-69ef1ceb27c87.jpg', 3, 0, '2026-04-27 10:23:07', '2026-04-28 10:23:07');

-- 9. VIDEOS
INSERT IGNORE INTO `sf_videos` (`id`, `post_id`, `filename`, `description`, `position`) VALUES
(1, 7, 'WhatsApp-Video-2026-04-18-at-21-18-50-69ef1bc2bbfca.mp4', NULL, 0);

-- 10. REACTIONS
INSERT IGNORE INTO `sf_reactions` (`id`, `post_id`, `username`, `reaction_type`, `created_at`) VALUES
(1, 1, 'shayma', '😂', '2026-04-26 00:04:25');

-- 11. POST LIKES
INSERT IGNORE INTO `sf_post_likes` (`id`, `post_id`, `username`, `created_at`) VALUES
(2, 1, 'shayma', '2026-04-26 00:04:32');

SELECT 'Forum module successfully updated!' AS status;
