-- Story interactions: views, likes, reply-messages
-- ============================================================================
-- Adds three bits of state so we can render Instagram-style story UX:
--   * story_views  — who has opened which story (one row per (story, viewer))
--   * story_likes  — heart-toggle on each story
--   * messages.story_id — marks a DM as "reply to this story" so the thread
--                         can render the story thumbnail like the screenshots.
--
-- All three reference stories.id with ON DELETE CASCADE so a purged/expired
-- story cleans up its satellite rows automatically.

CREATE TABLE IF NOT EXISTS story_views (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    story_id   INT NOT NULL,
    viewer_username VARCHAR(100) NOT NULL,
    viewed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- One row per (story, viewer) — re-opens don't spam the notifications.
    UNIQUE KEY uk_story_viewer (story_id, viewer_username),
    KEY idx_viewed_at (viewed_at),
    CONSTRAINT fk_story_views_story FOREIGN KEY (story_id)
        REFERENCES stories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS story_likes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    story_id   INT NOT NULL,
    liker_username VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_story_liker (story_id, liker_username),
    KEY idx_created_at (created_at),
    CONSTRAINT fk_story_likes_story FOREIGN KEY (story_id)
        REFERENCES stories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A DM that was composed from the story-reply input carries the story id so
-- the thread can render a thumbnail above the text. NULL for regular DMs.
ALTER TABLE messages
    ADD COLUMN story_id INT NULL DEFAULT NULL AFTER content,
    ADD CONSTRAINT fk_messages_story FOREIGN KEY (story_id)
        REFERENCES stories(id) ON DELETE SET NULL;
