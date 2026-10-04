-- RGreen Technologies Attendance Portal: MySQL / MariaDB schema.
-- The app creates these tables automatically on first run. You only need this
-- file if you prefer to import it yourself (phpMyAdmin -> Import) into an
-- empty database named rgreen_attendance.

CREATE TABLE IF NOT EXISTS admin_users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  email         VARCHAR(190) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Normal (employee) accounts. Each one is tied to exactly one employee ID and signs in with
-- their email + password (password_hash). Rows are created automatically when the admin saves an
-- employee with an email and password. username is an unused leftover and stays nullable.
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  emp_id        VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(190) NULL,
  username      VARCHAR(100) NULL UNIQUE,
  password_hash VARCHAR(255) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employees (
  emp_id     VARCHAR(50)  NOT NULL PRIMARY KEY,
  name       VARCHAR(150) NOT NULL DEFAULT '',
  dept       VARCHAR(100) NOT NULL DEFAULT '',
  email      VARCHAR(190) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reports (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  week_label       VARCHAR(100) NOT NULL,
  period_start     DATE NULL,
  period_end       DATE NULL,
  file_name        VARCHAR(255) NOT NULL DEFAULT '',
  required_minutes INT NOT NULL,
  daily_minutes    INT NOT NULL DEFAULT 0,
  period_days      INT NOT NULL DEFAULT 0,
  grace_minutes    INT NOT NULL DEFAULT 0,
  warnings         MEDIUMTEXT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS report_entries (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  report_id   INT UNSIGNED NOT NULL,
  emp_id      VARCHAR(50)  NOT NULL,
  name        VARCHAR(150) NOT NULL DEFAULT '',
  dept        VARCHAR(100) NOT NULL DEFAULT '',
  days        INT NOT NULL DEFAULT 0,
  leave_days  INT NOT NULL DEFAULT 0,
  minutes     INT NOT NULL DEFAULT 0,
  required    INT NOT NULL DEFAULT 0,
  shortfall   INT NOT NULL DEFAULT 0,
  low         TINYINT(1) NOT NULL DEFAULT 0,
  email       VARCHAR(190) NOT NULL DEFAULT '',
  mail_status VARCHAR(20)  NOT NULL DEFAULT 'pending',
  mail_detail VARCHAR(500) NOT NULL DEFAULT '',
  daily       MEDIUMTEXT NULL,
  KEY idx_report (report_id),
  CONSTRAINT fk_entry_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mail_settings (
  id         INT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  host       VARCHAR(190) NOT NULL DEFAULT 'smtp.gmail.com',
  port       INT NOT NULL DEFAULT 587,
  encryption VARCHAR(10)  NOT NULL DEFAULT 'tls',
  username   VARCHAR(190) NOT NULL DEFAULT '',
  password   VARCHAR(255) NOT NULL DEFAULT '',
  from_email VARCHAR(190) NOT NULL DEFAULT '',
  from_name  VARCHAR(190) NOT NULL DEFAULT '',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS meta (
  k VARCHAR(50)  NOT NULL PRIMARY KEY,
  v VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
