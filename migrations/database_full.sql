-- =====================================================================
-- Full database schema for the Symfony forum / travel-agency app
-- Database: agence_voyage_db
-- Engine:   MariaDB / MySQL 8 (utf8mb4)
--
-- Import via phpMyAdmin (SQL tab) on a *fresh* database, OR run as-is
-- to drop/recreate the schema (DROP DATABASE IF EXISTS clears it first).
--
-- Built from the Doctrine entities in src/Entity/* — covers:
--   users, posts, comments, reactions, post_likes, images, videos,
--   music, follows, messages, stories, story_views, story_likes,
--   location_pins.
-- =====================================================================

DROP DATABASE IF EXISTS agence_voyage_db;
CREATE DATABASE agence_voyage_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agence_voyage_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- USERS
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(100)  NOT NULL,
    profile_photo_path  VARCHAR(255)  DEFAULT NULL,
    password            VARCHAR(255)  NOT NULL DEFAULT '',
    email               VARCHAR(180)  DEFAULT NULL,
    bio                 VARCHAR(30)   DEFAULT NULL,
    roles               JSON          NOT NULL,
    is_private          TINYINT(1)    NOT NULL DEFAULT 0,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- MUSIC  (admin-curated playlist; referenced by posts and stories)
-- ---------------------------------------------------------------------
CREATE TABLE music (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(150) NOT NULL,
    artist       VARCHAR(150) DEFAULT NULL,
    filename     VARCHAR(500) NOT NULL,
    uploaded_at  DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- POSTS
-- ---------------------------------------------------------------------
CREATE TABLE posts (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    auteur         VARCHAR(100) NOT NULL,
    description    TEXT         NOT NULL,
    chemin_photo   VARCHAR(500) NOT NULL DEFAULT '',
    likes          INT          NOT NULL DEFAULT 0,
    date_creation  DATETIME     NOT NULL,
    music_id       INT          DEFAULT NULL,
    KEY idx_posts_auteur (auteur),
    KEY idx_posts_music  (music_id),
    CONSTRAINT fk_posts_music
        FOREIGN KEY (music_id) REFERENCES music (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- COMMENTS
-- ---------------------------------------------------------------------
CREATE TABLE comments (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    post_id           INT          NOT NULL,
    auteur            VARCHAR(100) NOT NULL,
    contenu           TEXT         NOT NULL,
    date_commentaire  DATETIME     NOT NULL,
    KEY idx_comments_post (post_id),
    KEY idx_comments_auteur (auteur),
    CONSTRAINT fk_comments_post
        FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- REACTIONS  (emoji reactions on posts; one per user per post)
-- ---------------------------------------------------------------------
CREATE TABLE reactions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    post_id        INT          NOT NULL,
    username       VARCHAR(100) NOT NULL,
    reaction_type  VARCHAR(20)  NOT NULL,
    created_at     DATETIME     NOT NULL,
    UNIQUE KEY unique_user_post_reaction (post_id, username),
    KEY idx_reactions_post (post_id),
    CONSTRAINT fk_reactions_post
        FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- POST_LIKES  (heart toggle on a post; one per user per post)
-- ---------------------------------------------------------------------
CREATE TABLE post_likes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT          NOT NULL,
    username    VARCHAR(100) NOT NULL,
    created_at  DATETIME     NOT NULL,
    UNIQUE KEY uniq_post_user (post_id, username),
    KEY idx_post_likes_post (post_id),
    CONSTRAINT fk_post_likes_post
        FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- IMAGES  (attached to a post, ordered)
-- ---------------------------------------------------------------------
CREATE TABLE images (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    post_id      INT          NOT NULL,
    filename     VARCHAR(500) NOT NULL,
    description  VARCHAR(255) DEFAULT NULL,
    position     INT          NOT NULL DEFAULT 0,
    KEY idx_images_post (post_id),
    CONSTRAINT fk_images_post
        FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- VIDEOS  (attached to a post, ordered)
-- ---------------------------------------------------------------------
CREATE TABLE videos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    post_id      INT          NOT NULL,
    filename     VARCHAR(500) NOT NULL,
    description  VARCHAR(255) DEFAULT NULL,
    position     INT          NOT NULL DEFAULT 0,
    KEY idx_videos_post (post_id),
    CONSTRAINT fk_videos_post
        FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- FOLLOWS  (follower -> following, status = pending|accepted)
-- ---------------------------------------------------------------------
CREATE TABLE follows (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    follower_username   VARCHAR(100) NOT NULL,
    following_username  VARCHAR(100) NOT NULL,
    status              VARCHAR(20)  NOT NULL DEFAULT 'accepted',
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_follow (follower_username, following_username),
    KEY idx_follows_following (following_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- STORIES  (24h ephemeral image/video posts)
-- ---------------------------------------------------------------------
CREATE TABLE stories (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    auteur       VARCHAR(100) NOT NULL,
    media_type   VARCHAR(10)  NOT NULL DEFAULT 'image',
    filename     VARCHAR(500) NOT NULL,
    music_id     INT          DEFAULT NULL,
    music_start  DOUBLE       DEFAULT NULL,
    created_at   DATETIME     NOT NULL,
    expires_at   DATETIME     NOT NULL,
    KEY idx_stories_auteur     (auteur),
    KEY idx_stories_music      (music_id),
    KEY idx_stories_expires_at (expires_at),
    CONSTRAINT fk_stories_music
        FOREIGN KEY (music_id) REFERENCES music (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- STORY_VIEWS  (one row per (story, viewer))
-- ---------------------------------------------------------------------
CREATE TABLE story_views (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    story_id         INT          NOT NULL,
    viewer_username  VARCHAR(100) NOT NULL,
    viewed_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_story_viewer (story_id, viewer_username),
    CONSTRAINT fk_story_views_story
        FOREIGN KEY (story_id) REFERENCES stories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- STORY_LIKES  (heart toggle on a story; one per (story, liker))
-- ---------------------------------------------------------------------
CREATE TABLE story_likes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    story_id        INT          NOT NULL,
    liker_username  VARCHAR(100) NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_story_liker (story_id, liker_username),
    CONSTRAINT fk_story_likes_story
        FOREIGN KEY (story_id) REFERENCES stories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- MESSAGES  (direct message between two users; can reference a story)
-- ---------------------------------------------------------------------
CREATE TABLE messages (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    sender_username    VARCHAR(100) NOT NULL,
    receiver_username  VARCHAR(100) NOT NULL,
    content            TEXT         NOT NULL,
    story_id           INT          DEFAULT NULL,
    is_read            TINYINT(1)   NOT NULL DEFAULT 0,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_messages_sender   (sender_username),
    KEY idx_messages_receiver (receiver_username),
    KEY idx_messages_pair     (sender_username, receiver_username),
    KEY idx_messages_story    (story_id),
    CONSTRAINT fk_messages_story
        FOREIGN KEY (story_id) REFERENCES stories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- LOCATION_PINS  (one active pin per user, expires after a delay)
-- ---------------------------------------------------------------------
CREATE TABLE location_pins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100)   NOT NULL,
    latitude    DECIMAL(10, 7) NOT NULL,
    longitude   DECIMAL(10, 7) NOT NULL,
    label       VARCHAR(120)   DEFAULT NULL,
    expires_at  DATETIME       NOT NULL,
    created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_location_user (username),
    KEY idx_location_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- SEED DATA  (optional — gives you a working admin account on first run)
-- Login: admin / admin     —   change the password after first login.
-- ---------------------------------------------------------------------
INSERT INTO users (username, password, email, roles, is_private, created_at) VALUES
('admin',
 '$2y$13$0LeeWjkLIt4v1TIwdS1A/OUTcQ/gEtq30VmDetQC5bJ5sv6DiYXVa',
 'admin@example.com',
 '["ROLE_ADMIN"]',
 0,
 CURRENT_TIMESTAMP);

SELECT 'Database agence_voyage_db created successfully.' AS message;
