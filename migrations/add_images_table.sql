-- Multi-image support for posts
CREATE TABLE IF NOT EXISTS images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    filename VARCHAR(500) NOT NULL,
    description VARCHAR(255) NULL,
    position INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_images_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    INDEX idx_images_post (post_id),
    INDEX idx_images_position (post_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
