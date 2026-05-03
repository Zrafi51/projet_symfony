-- Instagram-style "Carte" feature.
-- One active pin per user — unique on username. When a user re-pins we UPDATE
-- the existing row (lat/lng/expires_at) instead of inserting a duplicate.
-- Expired pins are filtered out at read time; a periodic purge can DELETE them.
CREATE TABLE IF NOT EXISTS location_pins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    label VARCHAR(120) NULL DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_location_user (username),
    INDEX idx_location_expires (expires_at)
);
