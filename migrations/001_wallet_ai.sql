-- Migration: E-Wallet + AI Recommendations
-- Run this once on your expense_tracker database.

-- 1. Wallets table (one per user)
CREATE TABLE IF NOT EXISTS wallets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  currency VARCHAR(3) NOT NULL DEFAULT 'NPR',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wallet_user (user_id),
  CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Wallet transactions table (audit trail for all wallet movements)
CREATE TABLE IF NOT EXISTS wallet_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type ENUM('topup','withdrawal','expense','refund') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  balance_after DECIMAL(12,2) NOT NULL,
  reference_type VARCHAR(50) DEFAULT NULL,
  reference_id INT UNSIGNED DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_wallet_tx_user (user_id),
  INDEX idx_wallet_tx_type (type),
  INDEX idx_wallet_tx_created (created_at),
  CONSTRAINT fk_wallet_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Alter expenses table to support wallet linkage
ALTER TABLE expenses
  ADD COLUMN wallet_id INT UNSIGNED DEFAULT NULL AFTER user_id,
  ADD COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT 'cash' AFTER wallet_id,
  ADD INDEX idx_expense_wallet (wallet_id),
  ADD CONSTRAINT fk_expense_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE SET NULL;

-- 4. AI insights cache table (optional, for storing generated insights)
CREATE TABLE IF NOT EXISTS ai_insights (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  insight_type VARCHAR(50) NOT NULL,
  severity VARCHAR(20) NOT NULL DEFAULT 'info',
  payload JSON NOT NULL,
  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_insights_user (user_id),
  INDEX idx_ai_insights_type (insight_type),
  INDEX idx_ai_insights_generated (generated_at),
  CONSTRAINT fk_ai_insights_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
