-- ============================================================================
-- Govt Polytechnic Institute (GPI) - Admissions & Management System
-- Initial Seed Data
-- ============================================================================

USE `gpi_admissions`;

-- ----------------------------------------------------------------------------
-- 1. Seed Programs
-- Mirrors existing 6 programs from programs.html without inventing information
-- ----------------------------------------------------------------------------
INSERT INTO `programs` (`id`, `code`, `name`, `duration`, `eligibility`, `description`, `active`) VALUES
(1, 'electrical', 'Computer Operator', '6 Months', 'Matric', 
 'Comprehensive training covering computer operations, office applications, programming fundamentals, database management, web development, networking, operating systems, and digital technologies.', 1),

(2, 'civil', 'Ecommerce Digital Marketing', '6 Months', 'Matric', 
 'Comprehensive training covering digital marketing, e-commerce management, social media marketing, search engine optimization (SEO), content creation, online advertising, and e-commerce platforms.', 1),

(3, 'mechanical', 'Web Development', '6 Months', 'Matric', 
 'Intensive practical training in HTML, CSS, JavaScript, responsive web design, frontend development, backend fundamentals, databases, and modern web development tools and technologies.', 1),

(4, 'computer', 'Automobile Mechanic', '6 Months', 'Matric', 
 'Intensive practical training in automobile engine systems, vehicle maintenance, automotive electrical systems, brake and suspension systems, fuel systems, diagnostics, and repair techniques.', 1),

(5, 'electronics', 'Fashion Design & Beautician', '6 Months', 'Matric', 
 'Intensive practical training in fashion designing, garment construction, pattern making, fabric selection, embroidery, makeup techniques, hairstyling, skincare, and professional beauty services.', 1),

(6, 'autodiesel', 'Hospitality', '6 Months', 'Matric', 
 'Intensive practical training in food preparation, cooking techniques, kitchen operations, food safety, hospitality services, restaurant operations, and professional customer service.', 1)
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`),
  `duration` = VALUES(`duration`),
  `description` = VALUES(`description`);

-- ----------------------------------------------------------------------------
-- 2. Seed Default Administrative Accounts
-- Passwords are encrypted with bcrypt (Default password: Admin@GPI2026!)
-- ----------------------------------------------------------------------------
INSERT INTO `admins` (`id`, `name`, `email`, `password`, `role`, `is_active`) VALUES
(1, 'GPI Super Admin', 'admin@gpi.edu.pk', '$2y$10$KcLKAmO3l1BVXMd1EqJF0emKB54cXaPdE6RAA3LhhtpaBoat45pEq', 'super_admin', 1),
(2, 'Admissions Officer', 'staff@gpi.edu.pk', '$2y$10$KcLKAmO3l1BVXMd1EqJF0emKB54cXaPdE6RAA3LhhtpaBoat45pEq', 'admissions_staff', 1)
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`),
  `role` = VALUES(`role`);

-- ----------------------------------------------------------------------------
-- 3. Seed Existing Announcements
-- Matches the existing news cards in index.html
-- ----------------------------------------------------------------------------
INSERT INTO `announcements` (`id`, `tag`, `tag_class`, `title`, `content`, `date_display`, `is_published`, `sort_order`) VALUES
(1, 'Admissions', 'tag-urgent', 'Admissions Open for DAE Academic Session 2026-27', 
 'Online applications are now invited for technical diploma programs for the academic session 2026-27. Candidates who have passed SSC (Matriculation) or equivalent are encouraged to apply online before the closing date. Verify all documents matching your NADRA B-Form.', 
 'Sept 10, 2026', 1, 1),

(2, 'Examination', '', 'PBTE Annual Examination Date Sheet Issued', 
 'The Punjab Board of Technical Education (PBTE) has officially released the date sheet for upcoming annual technical examinations. Enrolled students can collect their roll number slips from the institute academic cell.', 
 'Sept 05, 2026', 1, 2),

(3, 'Scholarships', '', 'Ehsaas & PEEF Need-Based Scholarship Applications', 
 'Deserving, meritorious, and need-based candidates may submit financial assistance and scholarship forms through the student affairs desk before the announced deadline with verified income credentials.', 
 'Aug 28, 2026', 1, 3)
ON DUPLICATE KEY UPDATE 
  `title` = VALUES(`title`),
  `content` = VALUES(`content`),
  `date_display` = VALUES(`date_display`);

-- ----------------------------------------------------------------------------
-- 4. Seed Institutional Settings
-- Controls admission status, session branding, contact details, and deadline
-- ----------------------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('admissions_status', 'open'),
('admissions_deadline', '2026-10-31 23:59:59'),
('session_name', 'Session 2026-27'),
('institute_name', 'Government Polytechnic Institute'),
('contact_email', 'gbdtesd@gmail.com'),
('contact_phone', '(058159) 41113'),
('campus_address', 'Main Shigar Road, Thorgu, Skardu, Gilgit-Baltistan')
ON DUPLICATE KEY UPDATE 
  `setting_value` = VALUES(`setting_value`);
