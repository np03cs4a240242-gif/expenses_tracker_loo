-- Migration: Notifications & User Preferences
-- Run this once on your expense_tracker database.

-- 1. User notification preferences table
CREATE TABLE IF NOT EXISTS user_notification_prefs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  -- Budget alerts
  budget_exceeded TINYINT(1) NOT NULL DEFAULT 1,
  budget_warning_threshold DECIMAL(5,2) NOT NULL DEFAULT 80.00, -- Alert at 80% budget usage
  -- Unusual spending alerts
  unusual_spending TINYINT(1) NOT NULL DEFAULT 1,
  unusual_spending_multiplier DECIMAL(3,1) NOT NULL DEFAULT 2.0, -- Alert when spending > 2x average
  -- Reminders
  expense_reminder TINYINT(1) NOT NULL DEFAULT 1,
  reminder_frequency ENUM('daily', 'weekly', 'none') NOT NULL DEFAULT 'daily',
  reminder_time TIME NOT NULL DEFAULT '20:00:00',
  -- Weekly summary
  weekly_summary TINYINT(1) NOT NULL DEFAULT 1,
  weekly_summary_day ENUM('monday', 'sunday') NOT NULL DEFAULT 'monday',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_prefs_user (user_id),
  CONSTRAINT fk_prefs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Notifications table
CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL, -- 'budget_exceeded', 'budget_warning', 'unusual_spending', 'reminder', 'weekly_summary'
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  severity VARCHAR(20) NOT NULL DEFAULT 'info', -- 'info', 'warning', 'critical', 'success'
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  action_url VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_user (user_id),
  INDEX idx_notif_read (is_read),
  INDEX idx_notif_created (created_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Expense log reminders tracking table
CREATE TABLE IF NOT EXISTS expense_reminder_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  reminder_date DATE NOT NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reminder_user_date (user_id, reminder_date),
  CONSTRAINT fk_reminder_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Weekly summary tracking table
CREATE TABLE IF NOT EXISTS weekly_summary_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  week_start_date DATE NOT NULL,
  week_end_date DATE NOT NULL,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_weekly_user_week (user_id, week_start_date),
  CONSTRAINT fk_weekly_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
