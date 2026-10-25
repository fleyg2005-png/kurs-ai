-- Начальная схема базы курсов. {p} заменяется на префикс таблиц (DB_PREFIX в .env).
-- MySQL 5.7+ / MariaDB 10.3+, кодировка utf8mb4. Таблицы создаются, только если их ещё нет.

CREATE TABLE IF NOT EXISTS {p}users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(120) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','admin') NOT NULL DEFAULT 'student',
  consent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}courses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL,
  title VARCHAR(200) NOT NULL,
  brand VARCHAR(80) NOT NULL DEFAULT '',
  brand_sub VARCHAR(120) NOT NULL DEFAULT '',
  logo VARCHAR(8) NOT NULL DEFAULT '',
  emoji VARCHAR(16) NOT NULL DEFAULT '📘',
  category VARCHAR(80) NOT NULL DEFAULT '',
  description TEXT NOT NULL,
  access ENUM('open','login','closed') NOT NULL DEFAULT 'open',
  access_code VARCHAR(64) NULL,
  price_label VARCHAR(64) NOT NULL DEFAULT 'Бесплатно',
  home_html MEDIUMTEXT NULL,
  cert_title VARCHAR(200) NOT NULL DEFAULT '',
  cert_sub VARCHAR(255) NOT NULL DEFAULT '',
  common_skills TEXT NULL,
  storage_key VARCHAR(64) NOT NULL DEFAULT '',
  module_base TINYINT NOT NULL DEFAULT 1,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  position INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_slug (slug),
  KEY k_published (is_published, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}tracks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  slug VARCHAR(64) NOT NULL,
  name VARCHAR(80) NOT NULL,
  emoji VARCHAR(16) NOT NULL DEFAULT '',
  tagline VARCHAR(200) NOT NULL DEFAULT '',
  description TEXT NULL,
  skills TEXT NULL,
  position INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_track (course_id, slug),
  CONSTRAINT fk_track_course FOREIGN KEY (course_id) REFERENCES {p}courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}modules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  track_id INT UNSIGNED NULL,
  title VARCHAR(200) NOT NULL,
  position INT NOT NULL DEFAULT 0,
  KEY k_module (course_id, track_id, position),
  CONSTRAINT fk_module_course FOREIGN KEY (course_id) REFERENCES {p}courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_module_track FOREIGN KEY (track_id) REFERENCES {p}tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}lessons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  slug VARCHAR(96) NOT NULL,
  title VARCHAR(255) NOT NULL,
  minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  is_control TINYINT(1) NOT NULL DEFAULT 0,
  is_preview TINYINT(1) NOT NULL DEFAULT 0,
  body MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_lesson (course_id, slug),
  CONSTRAINT fk_lesson_course FOREIGN KEY (course_id) REFERENCES {p}courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}module_lessons (
  module_id INT UNSIGNED NOT NULL,
  lesson_id INT UNSIGNED NOT NULL,
  position INT NOT NULL DEFAULT 0,
  PRIMARY KEY (module_id, lesson_id),
  KEY k_lesson (lesson_id),
  CONSTRAINT fk_ml_module FOREIGN KEY (module_id) REFERENCES {p}modules(id) ON DELETE CASCADE,
  CONSTRAINT fk_ml_lesson FOREIGN KEY (lesson_id) REFERENCES {p}lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}enrollments (
  user_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  source VARCHAR(20) NOT NULL DEFAULT 'admin',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, course_id),
  CONSTRAINT fk_enr_user FOREIGN KEY (user_id) REFERENCES {p}users(id) ON DELETE CASCADE,
  CONSTRAINT fk_enr_course FOREIGN KEY (course_id) REFERENCES {p}courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}progress (
  user_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  data MEDIUMTEXT NOT NULL,
  ts BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, course_id),
  CONSTRAINT fk_prog_user FOREIGN KEY (user_id) REFERENCES {p}users(id) ON DELETE CASCADE,
  CONSTRAINT fk_prog_course FOREIGN KEY (course_id) REFERENCES {p}courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  KEY k_token (token_hash),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES {p}users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(190) NOT NULL DEFAULT '',
  at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_ip (ip, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}settings (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v MEDIUMTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
