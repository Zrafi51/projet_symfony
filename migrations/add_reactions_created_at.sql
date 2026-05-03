-- Adds a created_at timestamp to the reactions table so they can participate
-- in the chronological notifications feed (like/comment/follow already have one).
-- Existing rows get the current time as a reasonable default.
ALTER TABLE reactions
    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
