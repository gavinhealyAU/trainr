-- Trainr database schema
-- Run once via phpMyAdmin or cPanel MySQL

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS athletes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  age INT NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_user_athlete (user_id, name),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  session_date DATE NOT NULL,
  type VARCHAR(50) NOT NULL DEFAULT 'Sprint',
  condition_val VARCHAR(20) NOT NULL DEFAULT 'dry',
  notes TEXT,
  athletes_json TEXT NOT NULL,
  exercises_json TEXT NOT NULL,
  results_json TEXT NOT NULL,
  duration_secs INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_date (user_id, session_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS custom_exercises (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  cat VARCHAR(50) NOT NULL DEFAULT 'Custom',
  icon VARCHAR(10) NOT NULL DEFAULT '⭐',
  ex_type ENUM('reps','timed','sprint','none') NOT NULL DEFAULT 'reps',
  default_reps INT NOT NULL DEFAULT 10,
  default_sets INT NOT NULL DEFAULT 3,
  default_dur INT NOT NULL DEFAULT 30,
  dur_unit VARCHAR(10) NOT NULL DEFAULT 's',
  attempts INT NOT NULL DEFAULT 3,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per user, upserted during active session for crash recovery
CREATE TABLE IF NOT EXISTS runner_state (
  user_id INT NOT NULL PRIMARY KEY,
  state_json LONGTEXT NOT NULL,
  saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
