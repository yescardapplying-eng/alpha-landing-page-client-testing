-- ============================================================================
-- STOCK MARKET — QUALIFICATION & ASSESSMENT PORTAL
-- Database Engine: MySQL / TiDB Cloud Compatible
-- Character Set: utf8mb4 / Collation: utf8mb4_unicode_ci
-- All Data Stored as Flexible Strings (VARCHAR / TEXT) to Prevent Type Errors
-- ============================================================================

-- Table 1: Primary Leads Record (All data stored safely as Strings)
CREATE TABLE IF NOT EXISTS `test_leads` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(100) NOT NULL UNIQUE,
    `lead_reference` VARCHAR(100) NOT NULL UNIQUE,
    `status` VARCHAR(50) NOT NULL DEFAULT 'partial',
    `current_step` VARCHAR(20) NOT NULL DEFAULT '1',
    
    -- Step 1: Challenge (Stored as TEXT String)
    `challenge` TEXT NULL,
    
    -- Step 2: Contact Details (Stored as Strings)
    `full_name` VARCHAR(255) NULL,
    `mobile_number` VARCHAR(100) NULL,
    
    -- Step 3: Segment (Stored as String)
    `trading_segment` VARCHAR(255) NULL,
    
    -- Step 4: Capital (Stored as String)
    `trading_capital` VARCHAR(255) NULL,
    
    -- Step 5: Final Communication Details (Stored as Strings)
    `whatsapp_number` VARCHAR(100) NULL,
    `state` VARCHAR(255) NULL,
    
    -- Marketing Attribution (Stored as TEXT Strings)
    `utm_source` TEXT NULL,
    `utm_medium` TEXT NULL,
    `utm_campaign` TEXT NULL,
    `utm_content` TEXT NULL,
    `utm_term` TEXT NULL,
    `fbclid` TEXT NULL,
    
    -- Network Metadata
    `ip_address` VARCHAR(100) NULL,
    `user_agent` TEXT NULL,
    
    -- Timestamps
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,

    INDEX `idx_test_leads_session_id` (`session_id`),
    INDEX `idx_test_leads_status` (`status`),
    INDEX `idx_test_leads_current_step` (`current_step`),
    INDEX `idx_test_leads_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: Question & Answer Interaction Logs (All Questions & Answers Stored as Strings)
CREATE TABLE IF NOT EXISTS `test_assessment_interactions` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(100) NOT NULL,
    `step_number` VARCHAR(20) NOT NULL DEFAULT '1',
    `question` TEXT NOT NULL,
    `selected_answer` TEXT NOT NULL,
    `field_name` VARCHAR(100) NOT NULL,
    `action_type` VARCHAR(100) NOT NULL, -- 'option_click', 'continue_click', 'submit_click'
    `ip_address` VARCHAR(100) NULL,
    `user_agent` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_test_interactions_session_id` (`session_id`),
    INDEX `idx_test_interactions_step` (`step_number`),
    INDEX `idx_test_interactions_action` (`action_type`),
    INDEX `idx_test_interactions_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
