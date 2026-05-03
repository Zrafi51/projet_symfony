-- Adds a short "bio" field to the users table — displayed under the stats row
-- on both the own-profile and the public-profile views (Instagram-style).
-- Length is capped to 30 characters; same banned-words validation as post
-- descriptions applies at the form level.
ALTER TABLE users
    ADD COLUMN bio VARCHAR(30) NULL DEFAULT NULL AFTER email;
