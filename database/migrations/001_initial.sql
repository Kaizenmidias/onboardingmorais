CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(32) PRIMARY KEY,
  applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(120) PRIMARY KEY,
  setting_value LONGTEXT NOT NULL,
  value_type VARCHAR(20) NOT NULL DEFAULT 'string',
  updated_at DATETIME NOT NULL,
  updated_by BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  company VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  cnpj VARCHAR(20) NULL,
  phone VARCHAR(40) NULL,
  whatsapp VARCHAR(40) NULL,
  status ENUM('active','inactive','pending') NOT NULL DEFAULT 'pending',
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_clients_email (email),
  KEY idx_clients_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NULL,
  role ENUM('agency_admin','office_admin','client') NOT NULL,
  status ENUM('active','inactive','invited') NOT NULL DEFAULT 'invited',
  auth_version INT UNSIGNED NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_client (client_id),
  KEY idx_users_role (role),
  CONSTRAINT fk_users_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  purpose ENUM('invite','reset') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_auth_token_hash (token_hash),
  KEY idx_auth_tokens_user (user_id, purpose),
  CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  category VARCHAR(120) NOT NULL DEFAULT '',
  description TEXT NOT NULL,
  cover_image VARCHAR(255) NULL,
  landscape_image VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  sequence_required TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_courses_slug (slug),
  KEY idx_courses_status_order (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_modules_course_order (course_id, sort_order),
  CONSTRAINT fk_modules_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_assets (
  id CHAR(36) PRIMARY KEY,
  original_name VARCHAR(255) NOT NULL,
  storage_key VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  checksum CHAR(64) NULL,
  status ENUM('uploading','ready','failed','deleted') NOT NULL DEFAULT 'uploading',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_media_storage (storage_key),
  KEY idx_media_status (status),
  CONSTRAINT fk_media_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lessons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  body LONGTEXT NOT NULL,
  duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  media_id CHAR(36) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_lessons_module_order (module_id, sort_order),
  CONSTRAINT fk_lessons_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
  CONSTRAINT fk_lessons_media FOREIGN KEY (media_id) REFERENCES media_assets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_course_access (
  client_id BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  mode ENUM('allow','deny') NOT NULL DEFAULT 'allow',
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  PRIMARY KEY (client_id, course_id),
  CONSTRAINT fk_access_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_access_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enrollments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  last_lesson_id BIGINT UNSIGNED NULL,
  first_access_at DATETIME NULL,
  last_access_at DATETIME NULL,
  UNIQUE KEY uq_enrollment (client_id, course_id),
  CONSTRAINT fk_enroll_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_enroll_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_progress (
  enrollment_id BIGINT UNSIGNED NOT NULL,
  lesson_id BIGINT UNSIGNED NOT NULL,
  completed_at DATETIME NULL,
  position_seconds DECIMAL(12,3) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (enrollment_id, lesson_id),
  CONSTRAINT fk_progress_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
  CONSTRAINT fk_progress_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action_name VARCHAR(100) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id VARCHAR(64) NULL,
  result VARCHAR(20) NOT NULL DEFAULT 'success',
  created_at DATETIME NOT NULL,
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
