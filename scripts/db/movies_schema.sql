CREATE TABLE IF NOT EXISTS movies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tmdb_id INT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    year SMALLINT UNSIGNED NOT NULL,
    genre VARCHAR(100) NULL,
    poster_path_local VARCHAR(255) NULL,
    poster_path_remote VARCHAR(255) NULL,
    poster_cached_at DATETIME NULL,
    summary TEXT NULL,
    director VARCHAR(255) NULL,
    cast JSON NULL,
    duration SMALLINT UNSIGNED NULL,
    rating FLOAT NULL,
    rating_count INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_movies_year ON movies (year);
CREATE INDEX idx_movies_genre ON movies (genre);
CREATE INDEX idx_movies_tmdb ON movies (tmdb_id);
