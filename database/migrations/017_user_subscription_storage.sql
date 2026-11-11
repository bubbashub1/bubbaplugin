-- Bubba Hub migration 017: user subscription storage.
-- Creates the table on new installations and adds Stripe reference columns
-- to older existing tables only when they are missing.

CREATE TABLE IF NOT EXISTS bh_user_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    plan VARCHAR(40) NOT NULL DEFAULT 'family_pro',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    source VARCHAR(40) NOT NULL DEFAULT 'event',
    stripe_customer_id VARCHAR(255) NULL,
    stripe_subscription_id VARCHAR(255) NULL,
    stripe_checkout_session_id VARCHAR(255) NULL,
    started_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bh_user_subscription (user_id),
    UNIQUE KEY uq_bh_stripe_subscription (stripe_subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @bh_add_sub_customer = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_subscriptions' AND COLUMN_NAME='stripe_customer_id') = 0,
    'ALTER TABLE bh_user_subscriptions ADD COLUMN stripe_customer_id VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE bh_stmt FROM @bh_add_sub_customer; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;

SET @bh_add_sub_subscription = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_subscriptions' AND COLUMN_NAME='stripe_subscription_id') = 0,
    'ALTER TABLE bh_user_subscriptions ADD COLUMN stripe_subscription_id VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE bh_stmt FROM @bh_add_sub_subscription; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;

SET @bh_add_sub_checkout = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_subscriptions' AND COLUMN_NAME='stripe_checkout_session_id') = 0,
    'ALTER TABLE bh_user_subscriptions ADD COLUMN stripe_checkout_session_id VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE bh_stmt FROM @bh_add_sub_checkout; EXECUTE bh_stmt; DEALLOCATE PREPARE bh_stmt;
