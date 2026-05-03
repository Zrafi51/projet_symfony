-- 24-hour ephemeral stories (Instagram/Facebook-style).
-- One media item per row (image OR video) + optional music from the playlist.
-- `expires_at` is indexed so the feed carousel's "active stories" query is fast.

CREATE TABLE IF NOT EXISTS stories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    auteur      VARCHAR(100) NOT NULL,
    media_type  VARCHAR(10)  NOT NULL,
    filename    VARCHAR(500) NOT NULL,
    music_id    INT          DEFAULT NULL,
    created_at  DATETIME     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    INDEX idx_stories_author   (auteur),
    INDEX idx_stories_expires  (expires_at),
    CONSTRAINT fk_stories_music FOREIGN KEY (music_id)
        REFERENCES music(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
