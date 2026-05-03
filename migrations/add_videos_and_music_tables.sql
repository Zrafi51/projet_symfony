-- Videos attached to a post.
CREATE TABLE IF NOT EXISTS videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    filename VARCHAR(500) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    position INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_video_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin-curated music playlist.
CREATE TABLE IF NOT EXISTS music (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    artist VARCHAR(150) DEFAULT NULL,
    filename VARCHAR(500) NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add music FK on posts (nullable — not every post has music).
ALTER TABLE posts
    ADD COLUMN IF NOT EXISTS music_id INT DEFAULT NULL,
    ADD CONSTRAINT fk_post_music FOREIGN KEY (music_id) REFERENCES music(id) ON DELETE SET NULL;
