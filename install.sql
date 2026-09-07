-- Необязательно: сайт сам создаёт базу и таблицы при первом подключении.
-- Этот файл — запасной ручной импорт.

CREATE DATABASE IF NOT EXISTS amquantlab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE amquantlab;

CREATE TABLE IF NOT EXISTS posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(191) NOT NULL UNIQUE,
  title VARCHAR(500) NOT NULL,
  excerpt TEXT NULL,
  body MEDIUMTEXT NULL,
  image VARCHAR(500) NULL,
  keywords VARCHAR(1000) NULL,
  seo_title VARCHAR(500) NULL,
  seo_description VARCHAR(1000) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  published_at DATETIME NULL,
  KEY status_published (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS post_redirects (
  old_slug VARCHAR(191) NOT NULL PRIMARY KEY,
  new_slug VARCHAR(191) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  contact VARCHAR(255) NOT NULL,
  market VARCHAR(64) NOT NULL,
  message TEXT NOT NULL,
  ip VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
