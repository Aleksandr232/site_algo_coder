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
  robot_slug VARCHAR(191) NULL,
  robot_title VARCHAR(500) NULL,
  robot_price VARCHAR(120) NULL,
  KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS strategies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(191) NOT NULL UNIQUE,
  venue VARCHAR(16) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'visible',
  sort_order INT NOT NULL DEFAULT 0,
  dot VARCHAR(120) NOT NULL,
  eyebrow VARCHAR(255) NULL,
  title VARCHAR(500) NOT NULL,
  lead TEXT NULL,
  notes TEXT NULL,
  entry VARCHAR(32) NULL,
  stop VARCHAR(32) NULL,
  target VARCHAR(32) NULL,
  comon_id VARCHAR(32) NULL,
  instrument VARCHAR(64) NULL,
  source_url VARCHAR(500) NULL,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO strategies
  (slug, venue, status, sort_order, dot, eyebrow, title, lead, notes, entry, stop, target, comon_id, instrument, source_url, is_test, created_at, updated_at)
VALUES
  (
    'comon-131208', 'comon', 'visible', 10,
    'Юань · Comon', 'Кейс · автообновление с Comon', 'Юань Тренд 2-5-15',
    'Автоматическая стратегия по фьючерсу на юань. Работает в сторону устойчивого движения, характер умеренно-агрессивный.',
    'Если сделка старше 5 дней и прибыль 7–10%, фиксация может быть досрочной.\nПри прибыли выше ~10% позиция обычно держится до цели 15%.\nС 01.06.2026 усилена логика тренда: меньше ложных входов в боковике.',
    '2%', '−5%', '+15%', '131208', 'CNYRUB', 'https://www.comon.ru/strategies/131208/', 0,
    NOW(), NOW()
  ),
  (
    'bybit-btc', 'bybit', 'visible', 20,
    'BTC · тест', 'Тестовый кейс · пока считаем доходность', 'BTC Trend · Bybit · тест',
    'Трендовый робот по бессрочному фьючерсу BTCUSDT на Bybit. Входит по направлению движения, режет риск и забирает профит по правилам системы.',
    'Доходность считается по изменению баланса за каждый день, не по витрине.\nВ кривую входят закрытый результат и актуальная оценка счёта.\nСервер каждый день пишет снимок эквити и подтягивает историю сделок BTC.',
    '', '', '', '', 'BTCUSDT', 'https://www.bybit.com/', 1,
    NOW(), NOW()
  );

CREATE TABLE IF NOT EXISTS ready_robots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(191) NOT NULL UNIQUE,
  status VARCHAR(16) NOT NULL DEFAULT 'visible',
  sort_order INT NOT NULL DEFAULT 0,
  title VARCHAR(500) NOT NULL,
  description TEXT NULL,
  price VARCHAR(120) NOT NULL,
  venue VARCHAR(64) NULL,
  image VARCHAR(500) NULL,
  keywords VARCHAR(1000) NULL,
  seo_title VARCHAR(500) NULL,
  seo_description VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
