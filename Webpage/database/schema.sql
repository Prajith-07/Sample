-- ============================================================================
-- iTech Seed Grand Challenge 2027 - Complete Database Schema
-- PSG iTech Launchpad: Innozone & Startupzone
-- Compatible with MySQL / MariaDB (XAMPP default)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `itech_seed_2027` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `itech_seed_2027`;

-- ----------------------------------------------------------------------------
-- 1. Table: Early Registration Alerts & Notification Subscribers
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `registration_alerts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(20) DEFAULT NULL,
    `department` VARCHAR(100) NOT NULL,
    `year_of_study` ENUM('II', 'III', 'Final', 'Faculty', 'Other') NOT NULL,
    `interest_role` ENUM('Team Lead', 'Team Member', 'Faculty Mentor', 'Observer') DEFAULT 'Team Lead',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Table: Teams (Master Record for Multi-Step Challenge Application)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `teams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `application_no` VARCHAR(50) NOT NULL UNIQUE,
    `team_name` VARCHAR(150) NOT NULL UNIQUE,
    `leader_email` VARCHAR(150) DEFAULT NULL,
    `password_hash` VARCHAR(255) DEFAULT NULL,
    `team_size` TINYINT NOT NULL DEFAULT 2,
    `has_woman_member` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if at least one woman member exists',
    `current_step` TINYINT NOT NULL DEFAULT 1 COMMENT 'Current wizard step (1 to 7)',
    `status` ENUM('Draft', 'Submitted', 'Under Screening', 'Shortlisted', 'Pitch Day', 'Sanctioned', 'Rejected') DEFAULT 'Draft',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Table: Team Members (2 to 5 Students; Member 1 is designated Lead)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `team_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `is_lead` TINYINT(1) NOT NULL DEFAULT 0,
    `member_order` TINYINT NOT NULL DEFAULT 1 COMMENT '1 to 5',
    `full_name` VARCHAR(150) NOT NULL,
    `roll_number` VARCHAR(50) NOT NULL UNIQUE,
    `department` VARCHAR(100) NOT NULL,
    `year_of_study` ENUM('II', 'III', 'Final') NOT NULL,
    `college_email` VARCHAR(150) NOT NULL UNIQUE,
    `phone_number` VARCHAR(25) NOT NULL UNIQUE,
    `gender` ENUM('Female', 'Male', 'Prefer not to say') NOT NULL,
    `password_hash` VARCHAR(255) DEFAULT NULL,
    `is_dummy_password` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 if initial mobile number password, 0 once changed',
    `reset_token` VARCHAR(64) DEFAULT NULL,
    `reset_token_expires` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_team_id` (`team_id`),
    INDEX `idx_member_roll` (`roll_number`),
    INDEX `idx_member_email` (`college_email`),
    INDEX `idx_member_phone` (`phone_number`),
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Table: Team Mentors (Faculty, Additional Faculty, and Industry Mentor)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `team_mentors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `mentor_type` ENUM('faculty', 'additional_faculty', 'industry') NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `department` VARCHAR(100) DEFAULT NULL,
    `organisation` VARCHAR(150) DEFAULT NULL,
    `designation` VARCHAR(150) DEFAULT NULL,
    `email` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(25) NOT NULL,
    `linkedin_url` VARCHAR(255) DEFAULT NULL,
    `approval_status` ENUM('Pending', 'Approved', 'Declined') DEFAULT 'Pending',
    `approval_token` VARCHAR(64) DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `decline_reason` TEXT DEFAULT NULL,
    `mentor_ip_address` VARCHAR(45) DEFAULT NULL,
    `mentor_signature` VARCHAR(150) DEFAULT NULL,
    `consent_agree_guide` TINYINT(1) DEFAULT 0,
    `consent_certify_progress` TINYINT(1) DEFAULT 0,
    `consent_original_idea` TINYINT(1) DEFAULT 0,
    `password_hash` VARCHAR(255) DEFAULT NULL,
    `is_dummy_password` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 if initial mobile number password, 0 once changed',
    `reset_token` VARCHAR(64) DEFAULT NULL,
    `reset_token_expires` DATETIME DEFAULT NULL,
    `email_sent_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_mentor_team` (`team_id`),
    INDEX `idx_mentor_email` (`email`),
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Table: Proposals & Idea Details (Steps 2 to 6)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proposals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL UNIQUE,
    `project_title` VARCHAR(255) NOT NULL,
    `poc_type` ENUM('Hardware', 'Software', 'Both') NOT NULL DEFAULT 'Both',
    `trl_level` TINYINT DEFAULT 2 COMMENT 'Technology Readiness Level (1 to 9)',
    `problem_statement` TEXT DEFAULT NULL,
    `existing_solutions` TEXT DEFAULT NULL,
    `proposed_solution` TEXT DEFAULT NULL,
    `work_done_so_far` TEXT DEFAULT NULL,
    `prior_funding_has` ENUM('No', 'Yes') DEFAULT 'No',
    `prior_funding_agency` VARCHAR(255) DEFAULT NULL,
    `prior_funding_amount` VARCHAR(100) DEFAULT NULL,
    `prior_funding_overlap` TEXT DEFAULT NULL,
    `prior_funding` VARCHAR(255) DEFAULT 'None',
    `ip_status` VARCHAR(100) DEFAULT 'None',
    `deliverables` TEXT DEFAULT NULL,
    `test_method` TEXT DEFAULT NULL,
    `success_rate` TEXT DEFAULT NULL,
    `budget_requested` DECIMAL(10,2) NOT NULL DEFAULT 100000.00,
    `target_users` TEXT DEFAULT NULL,
    `market_need_size` TEXT DEFAULT NULL,
    `commercialisation_route` TEXT DEFAULT NULL,
    `societal_impact` TEXT DEFAULT NULL,
    `proposal_file_path` VARCHAR(255) DEFAULT NULL,
    `lead_signature_name` VARCHAR(150) DEFAULT NULL,
    `lead_signature_date` DATE DEFAULT NULL,
    `lead_declared_at` DATETIME DEFAULT NULL,
    `lead_ip_address` VARCHAR(45) DEFAULT NULL,
    `lead_declared_originality` TINYINT(1) DEFAULT 0,
    `lead_declared_no_funding_overlap` TINYINT(1) DEFAULT 0,
    `lead_declared_grant_agreement` TINYINT(1) DEFAULT 0,
    `lead_declared_ip_policy` TINYINT(1) DEFAULT 0,
    `lead_declared_mentor_guidance` TINYINT(1) DEFAULT 0,
    `submitted_at` DATETIME DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5.1 Table: Proposal Milestones (6-Month Implementation Roadmap)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proposal_milestones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `month_number` TINYINT NOT NULL COMMENT '1 to 6',
    `month_label` VARCHAR(100) NOT NULL,
    `milestone_desc` TEXT DEFAULT NULL,
    `output_deliverable` TEXT DEFAULT NULL,
    `verification_method` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_team_month` (`team_id`, `month_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5.2 Table: Proposal Budget Line Items (Item-wise Cost up to ₹1,00,000)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proposal_budget_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `item_order` INT NOT NULL DEFAULT 1,
    `item_name` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `purpose` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Table: Evaluations & Scoring Matrix (50 Marks)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `evaluations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `evaluator_name` VARCHAR(150) NOT NULL,
    `evaluator_role` ENUM('Screening Committee', 'Jury Member', 'Principal') NOT NULL,
    `innovation_score` TINYINT NOT NULL DEFAULT 0 COMMENT 'Max 10 marks',
    `technical_score` TINYINT NOT NULL DEFAULT 0 COMMENT 'Max 10 marks',
    `business_score` TINYINT NOT NULL DEFAULT 0 COMMENT 'Max 10 marks',
    `impact_score` TINYINT NOT NULL DEFAULT 0 COMMENT 'Max 10 marks',
    `presentation_score` TINYINT NOT NULL DEFAULT 0 COMMENT 'Max 10 marks',
    `total_score` TINYINT GENERATED ALWAYS AS (
        `innovation_score` + `technical_score` + `business_score` + `impact_score` + `presentation_score`
    ) STORED,
    `qualified_for_grant` TINYINT(1) GENERATED ALWAYS AS (`total_score` >= 30) STORED COMMENT 'Min 30/50 cutoff',
    `remarks` TEXT DEFAULT NULL,
    `evaluated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

