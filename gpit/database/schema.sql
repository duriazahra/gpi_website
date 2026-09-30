-- ============================================================================
-- Govt Polytechnic Institute (GPI) - Admissions & Management System
-- Database Schema Definition
-- Compatible with MySQL 5.7+, MySQL 8.0+, MariaDB 10.3+
-- Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `gpi_admissions` 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `gpi_admissions`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Table: admins
-- Stores administrative user credentials and role-based permissions
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin', 'admissions_staff') NOT NULL DEFAULT 'admissions_staff',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_admin_role_active` (`role`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Table: programs
-- Database-driven technical programs offered by the institute
-- Allows activating/deactivating, editing titles, descriptions, and durations
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `programs`;
CREATE TABLE `programs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Slug identifier e.g. electrical, civil, mechanical',
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `duration` VARCHAR(50) NOT NULL DEFAULT '6 Months',
  `eligibility` VARCHAR(100) NOT NULL DEFAULT 'Matric',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_program_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Table: applications
-- Core admission candidate records
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `applications`;
CREATE TABLE `applications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `application_no` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Official format e.g. GPI-2026-000001',
  `session_year` VARCHAR(20) NOT NULL DEFAULT '2026-27',
  
  -- Personal Information
  `full_name` VARCHAR(100) NOT NULL,
  `father_name` VARCHAR(100) NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `cnic_bform` VARCHAR(20) NOT NULL COMMENT 'Format: 00000-0000000-0',
  `mobile` VARCHAR(20) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `address` TEXT NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  
  -- Academic Information
  `qualification` VARCHAR(100) NOT NULL,
  `passing_year` INT NOT NULL,
  `board` VARCHAR(150) NULL,
  `total_marks` DECIMAL(6,2) NOT NULL,
  `obtained_marks` DECIMAL(6,2) NOT NULL,
  `percentage` DECIMAL(5,2) NOT NULL,
  `previous_institution` VARCHAR(200) NOT NULL,
  
  -- Program Preference
  `program_id` INT UNSIGNED NOT NULL,
  `second_preference_id` INT UNSIGNED NULL,
  
  -- Additional Fields
  `reason` TEXT NULL COMMENT 'Why applicant wants to join GPI',
  `declaration` TINYINT(1) NOT NULL DEFAULT 1,
  
  -- Application Lifecycle
  `status` ENUM(
    'Pending',
    'Under Review',
    'Documents Required',
    'Verified',
    'Accepted',
    'Rejected'
  ) NOT NULL DEFAULT 'Pending',
  
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Relationships
  CONSTRAINT `fk_app_program` 
    FOREIGN KEY (`program_id`) REFERENCES `programs`(`id`) 
    ON UPDATE CASCADE,
    
  CONSTRAINT `fk_app_second_program` 
    FOREIGN KEY (`second_preference_id`) REFERENCES `programs`(`id`) 
    ON UPDATE CASCADE ON DELETE SET NULL,
    
  -- Integrity Constraints
  CONSTRAINT `chk_app_marks` 
    CHECK (`obtained_marks` <= `total_marks` AND `total_marks` > 0),
    
  CONSTRAINT `chk_app_percentage` 
    CHECK (`percentage` >= 0 AND `percentage` <= 100),
    
  CONSTRAINT `uq_candidate_session` 
    UNIQUE (`cnic_bform`, `session_year`),
    
  -- Fast Search & Filter Indexes
  INDEX `idx_app_number` (`application_no`),
  INDEX `idx_app_cnic` (`cnic_bform`),
  INDEX `idx_app_mobile` (`mobile`),
  INDEX `idx_app_status` (`status`),
  INDEX `idx_app_city` (`city`),
  INDEX `idx_app_passing_year` (`passing_year`),
  INDEX `idx_app_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Table: application_documents
-- Stores metadata of sensitive documents (photo, result card, CNIC copy)
-- Files reside in protected non-public storage
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `application_documents`;
CREATE TABLE `application_documents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT UNSIGNED NOT NULL,
  `document_type` ENUM('photo', 'marksheet', 'cnic') NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL UNIQUE,
  `file_path` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL COMMENT 'Size in bytes',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  CONSTRAINT `fk_doc_application` 
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
    
  INDEX `idx_doc_app_type` (`application_id`, `document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Table: application_status_history
-- Complete audit trail for every status transition and admin note
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `application_status_history`;
CREATE TABLE `application_status_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT UNSIGNED NOT NULL,
  `admin_id` INT UNSIGNED NULL COMMENT 'NULL indicates initial system submission',
  `old_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `note` TEXT NULL COMMENT 'Internal administrative remark',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  CONSTRAINT `fk_hist_application` 
    FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
    
  CONSTRAINT `fk_hist_admin` 
    FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE,
    
  INDEX `idx_hist_app` (`application_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Table: contact_messages
-- Stores incoming inquiries submitted through the contact page form
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `subject` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('unread', 'read', 'archived') NOT NULL DEFAULT 'unread',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_contact_status` (`status`),
  INDEX `idx_contact_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Table: announcements
-- Dynamic announcements managed from the admin panel and shown on homepage
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tag` VARCHAR(50) NOT NULL DEFAULT 'Notice',
  `tag_class` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'e.g. tag-urgent',
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,
  `date_display` VARCHAR(50) NOT NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_ann_published_order` (`is_published`, `sort_order`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. Table: settings
-- Key-value institutional configuration (admission status, deadlines, etc.)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. Table: admin_audit_logs
-- Security logging for administrative actions (login, doc download, export, etc.)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_audit_logs`;
CREATE TABLE `admin_audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_id` VARCHAR(50) NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  CONSTRAINT `fk_audit_admin` 
    FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE,
    
  INDEX `idx_audit_admin` (`admin_id`),
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
