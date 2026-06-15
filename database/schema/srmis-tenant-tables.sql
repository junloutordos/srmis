CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` text COLLATE utf8mb4_unicode_ci,
  `position` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specialization` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `on_study_leave` tinyint(1) NOT NULL DEFAULT '0',
  `sst_position_id` bigint unsigned DEFAULT NULL COMMENT 'FK to sst_positions — determines salary grade for overload pay',
  `academic_unit_id` bigint unsigned DEFAULT NULL COMMENT 'FK to academic_units — which unit this user belongs to',
  `division_id` bigint unsigned DEFAULT NULL,
  `office_id` bigint unsigned DEFAULT NULL,
  `office` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sex` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `badge_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Biometric ID / Badge ID',
  `biometric_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Biometric device employee ID used for log matching',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `electronic_signature` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature_pin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `salary_grade` tinyint unsigned DEFAULT NULL,
  `salary_step` tinyint unsigned DEFAULT '1',
  `emp_category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_badge_id_unique` (`badge_id`),
  UNIQUE KEY `users_biometric_id_unique` (`biometric_id`),
  UNIQUE KEY `users_employee_no_unique` (`employee_no`),
  KEY `users_division_id_foreign` (`division_id`),
  KEY `users_office_id_foreign` (`office_id`),
  KEY `users_status_index` (`status`),
  KEY `users_sst_position_id_foreign` (`sst_position_id`),
  KEY `users_academic_unit_id_index` (`academic_unit_id`),
  CONSTRAINT `users_academic_unit_id_foreign` FOREIGN KEY (`academic_unit_id`) REFERENCES `academic_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_division_id_foreign` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_office_id_foreign` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_sst_position_id_foreign` FOREIGN KEY (`sst_position_id`) REFERENCES `sst_positions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `push_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `endpoint` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_key` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_encoding` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'aesgcm',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_endpoint_unique` (`endpoint`),
  KEY `push_subscriptions_user_id_foreign` (`user_id`),
  CONSTRAINT `push_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`),
  KEY `permissions_module_index` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_user` (
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `role_user_role_id_foreign` (`role_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permission_role` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `permission_role_role_id_foreign` (`role_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `divisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `division_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `acronym` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `division_chief_id` bigint unsigned DEFAULT NULL,
  `year` year DEFAULT NULL,
  `status` enum('active','not_active') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `signature_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `divisions_division_chief_id_foreign` (`division_chief_id`),
  CONSTRAINT `divisions_division_chief_id_foreign` FOREIGN KEY (`division_chief_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `offices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `division_id` bigint unsigned DEFAULT NULL,
  `unit_head` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `offices_name_unique` (`name`),
  KEY `offices_division_id_foreign` (`division_id`),
  KEY `offices_unit_head_index` (`unit_head`),
  CONSTRAINT `offices_division_id_foreign` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `offices_unit_head_foreign` FOREIGN KEY (`unit_head`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `campuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year_established` int DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `telephone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facebook` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `buildings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `building_use` json DEFAULT NULL,
  `number_of_floors` int DEFAULT NULL,
  `year_constructed` int DEFAULT NULL,
  `year_completed` int DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_of_rooms` int unsigned DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comfort_gender` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `building_id` bigint unsigned DEFAULT NULL,
  `floor` int DEFAULT NULL,
  `office_id` bigint unsigned DEFAULT NULL,
  `section_id` int DEFAULT NULL,
  `capacity` int DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rooms_building_id_foreign` (`building_id`),
  KEY `rooms_office_id_foreign` (`office_id`),
  CONSTRAINT `rooms_building_id_foreign` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rooms_office_id_foreign` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `organizational_units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Short machine-readable code, e.g. PSHS-CRC, ACAD-DIV',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full official unit name',
  `short_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Acronym or display abbreviation',
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` enum('institution','division','department','section','unit','office','committee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unit' COMMENT 'Organizational level classification',
  `parent_id` bigint unsigned DEFAULT NULL COMMENT 'NULL for root node; self-referencing adjacency list',
  `path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ancestor chain: /1/4/7/ — used for efficient subtree lookups',
  `depth` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Distance from root (0 = root node)',
  `order_index` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Sort order among siblings',
  `division_id` bigint unsigned DEFAULT NULL COMMENT 'FK to legacy divisions table if this unit maps to a division',
  `office_id` bigint unsigned DEFAULT NULL COMMENT 'FK to legacy offices table if this unit maps to an office',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `established_date` date DEFAULT NULL,
  `abolished_date` date DEFAULT NULL,
  `legal_basis` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'RA / EO / AO / CSC resolution that created this unit',
  `mandate` text COLLATE utf8mb4_unicode_ci COMMENT 'Official functions and responsibilities',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `deleted_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organizational_units_code_unique` (`code`),
  KEY `organizational_units_division_id_foreign` (`division_id`),
  KEY `organizational_units_office_id_foreign` (`office_id`),
  KEY `organizational_units_created_by_foreign` (`created_by`),
  KEY `organizational_units_updated_by_foreign` (`updated_by`),
  KEY `organizational_units_deleted_by_foreign` (`deleted_by`),
  KEY `organizational_units_parent_id_index` (`parent_id`),
  KEY `organizational_units_type_index` (`type`),
  KEY `organizational_units_is_active_index` (`is_active`),
  KEY `organizational_units_depth_index` (`depth`),
  KEY `idx_ou_parent_order` (`parent_id`,`order_index`),
  KEY `idx_ou_path` (`path`(191)),
  CONSTRAINT `organizational_units_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `organizational_units_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `organizational_units_division_id_foreign` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `organizational_units_office_id_foreign` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `organizational_units_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `organizational_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `organizational_units_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `organizational_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `version_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Semantic version string, e.g. v2026.1, v2026.2',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Human-readable version name, e.g. Q1 2026 Organizational Structure',
  `status` enum('draft','approved','active','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `effective_date` date NOT NULL COMMENT 'Date this version becomes the official structure',
  `end_date` date DEFAULT NULL COMMENT 'NULL = currently active; set when superseded',
  `snapshot` json DEFAULT NULL COMMENT 'Full JSON snapshot of the org tree at this version — enables historical replay',
  `approved_by` bigint unsigned DEFAULT NULL COMMENT 'HR/Admin user who approved this version',
  `approved_at` timestamp NULL DEFAULT NULL,
  `change_summary` text COLLATE utf8mb4_unicode_ci COMMENT 'Summary of structural changes from the previous version',
  `basis_document` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Resolution / Order that authorizes this structure',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `organizational_versions_approved_by_foreign` (`approved_by`),
  KEY `organizational_versions_created_by_foreign` (`created_by`),
  KEY `organizational_versions_updated_by_foreign` (`updated_by`),
  KEY `organizational_versions_status_index` (`status`),
  KEY `organizational_versions_effective_date_index` (`effective_date`),
  KEY `idx_ov_status_date` (`status`,`effective_date`),
  CONSTRAINT `organizational_versions_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `organizational_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `organizational_versions_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employee_unit_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `organizational_unit_id` bigint unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'True = primary/home unit; False = concurrent/secondary assignment',
  `assignment_type` enum('organic','seconded','designated','concurrent','detailed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'organic',
  `appointment_type` enum('permanent','temporary','casual','cos','job_order','secondment') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nature of appointment in this unit',
  `position_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Official position title within the unit',
  `item_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Plantilla item number for permanent positions',
  `effective_date` date NOT NULL COMMENT 'Date the assignment takes effect',
  `end_date` date DEFAULT NULL COMMENT 'NULL = currently active assignment',
  `order_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Office Order / Designation Order number',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_unit_assignments_created_by_foreign` (`created_by`),
  KEY `employee_unit_assignments_updated_by_foreign` (`updated_by`),
  KEY `employee_unit_assignments_user_id_index` (`user_id`),
  KEY `employee_unit_assignments_organizational_unit_id_index` (`organizational_unit_id`),
  KEY `employee_unit_assignments_is_primary_index` (`is_primary`),
  KEY `employee_unit_assignments_effective_date_index` (`effective_date`),
  KEY `employee_unit_assignments_end_date_index` (`end_date`),
  KEY `idx_eua_user_primary` (`user_id`,`is_primary`),
  KEY `idx_eua_unit_active` (`organizational_unit_id`,`end_date`),
  KEY `idx_eua_lookup` (`user_id`,`organizational_unit_id`,`end_date`),
  CONSTRAINT `employee_unit_assignments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_unit_assignments_organizational_unit_id_foreign` FOREIGN KEY (`organizational_unit_id`) REFERENCES `organizational_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_unit_assignments_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_unit_assignments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unit_heads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `organizational_unit_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `designation_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Official designation title, e.g. Division Chief, Head, Director',
  `is_acting` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'True = Officer-in-Charge / Acting; False = regular designation',
  `is_current` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether this is the currently active head designation',
  `effective_date` date NOT NULL COMMENT 'Date designation takes effect',
  `end_date` date DEFAULT NULL COMMENT 'NULL = currently serving; set when superseded or ended',
  `designation_order` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Office Order / Administrative Order number for the designation',
  `designation_order_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Date of the designation order document',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `unit_heads_created_by_foreign` (`created_by`),
  KEY `unit_heads_updated_by_foreign` (`updated_by`),
  KEY `unit_heads_organizational_unit_id_index` (`organizational_unit_id`),
  KEY `unit_heads_user_id_index` (`user_id`),
  KEY `unit_heads_is_current_index` (`is_current`),
  KEY `unit_heads_effective_date_index` (`effective_date`),
  KEY `idx_uh_unit_current` (`organizational_unit_id`,`is_current`),
  KEY `idx_uh_unit_active` (`organizational_unit_id`,`end_date`),
  CONSTRAINT `unit_heads_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `unit_heads_organizational_unit_id_foreign` FOREIGN KEY (`organizational_unit_id`) REFERENCES `organizational_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `unit_heads_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `unit_heads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vehicles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plate_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `capacity` int DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Good Working',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vehicle_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `requestor_id` bigint unsigned DEFAULT NULL,
  `purpose` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_needed_multiple` json DEFAULT NULL,
  `time_of_departure` time DEFAULT NULL,
  `eta` time DEFAULT NULL,
  `date_needed` date DEFAULT NULL,
  `vehicle_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `passengers` int NOT NULL DEFAULT '1',
  `division_chief_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `decline_reason` text COLLATE utf8mb4_unicode_ci,
  `declined_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicle_requests_division_chief_id_foreign` (`division_chief_id`),
  KEY `vehicle_requests_requestor_id_foreign` (`requestor_id`),
  CONSTRAINT `vehicle_requests_division_chief_id_foreign` FOREIGN KEY (`division_chief_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vehicle_requests_requestor_id_foreign` FOREIGN KEY (`requestor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `facilities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` smallint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `facility_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `requestor_id` bigint unsigned DEFAULT NULL,
  `activity` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purpose` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nature` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `time_start` time DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `male` smallint unsigned DEFAULT NULL,
  `female` smallint unsigned DEFAULT NULL,
  `venue` text COLLATE utf8mb4_unicode_ci,
  `equipment` text COLLATE utf8mb4_unicode_ci,
  `equipment_quantities` text COLLATE utf8mb4_unicode_ci,
  `chairs` smallint unsigned DEFAULT NULL,
  `tables` smallint unsigned DEFAULT NULL,
  `mic` smallint unsigned DEFAULT NULL,
  `whiteboard` smallint unsigned DEFAULT NULL,
  `projector` smallint unsigned DEFAULT NULL,
  `elecfans` smallint unsigned DEFAULT NULL,
  `aircon` smallint unsigned DEFAULT NULL,
  `trashbins` smallint unsigned DEFAULT NULL,
  `others` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `decline_reason` text COLLATE utf8mb4_unicode_ci,
  `declined_at` timestamp NULL DEFAULT NULL,
  `date_filed` timestamp NULL DEFAULT NULL,
  `participants` text COLLATE utf8mb4_unicode_ci,
  `reference_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `facility_requests_requestor_id_foreign` (`requestor_id`),
  CONSTRAINT `facility_requests_requestor_id_foreign` FOREIGN KEY (`requestor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `work_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `issue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Normal',
  `location_division_id` bigint unsigned DEFAULT NULL,
  `location_office_id` bigint unsigned DEFAULT NULL,
  `assigned_user_id` bigint unsigned DEFAULT NULL,
  `acted_by_id` bigint unsigned DEFAULT NULL,
  `requester_id` bigint unsigned DEFAULT NULL,
  `division_chief_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `decline_reason` text COLLATE utf8mb4_unicode_ci,
  `declined_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `work_requests_assigned_user_id_foreign` (`assigned_user_id`),
  KEY `work_requests_requester_id_foreign` (`requester_id`),
  KEY `work_requests_location_office_id_foreign` (`location_office_id`),
  KEY `work_requests_location_division_id_foreign` (`location_division_id`),
  KEY `work_requests_division_chief_id_foreign` (`division_chief_id`),
  KEY `work_requests_acted_by_id_foreign` (`acted_by_id`),
  CONSTRAINT `work_requests_acted_by_id_foreign` FOREIGN KEY (`acted_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_requests_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_requests_division_chief_id_foreign` FOREIGN KEY (`division_chief_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_requests_location_division_id_foreign` FOREIGN KEY (`location_division_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_requests_location_office_id_foreign` FOREIGN KEY (`location_office_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_requests_requester_id_foreign` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `service_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `copies` int DEFAULT NULL,
  `sheets_per_set` int DEFAULT NULL,
  `date_needed` date NOT NULL,
  `time_needed` time DEFAULT NULL,
  `purposes` text COLLATE utf8mb4_unicode_ci,
  `details` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `requestor_id` bigint unsigned DEFAULT NULL,
  `decline_reason` text COLLATE utf8mb4_unicode_ci,
  `declined_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `it_job_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `itjr_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `facility_request_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_date` date DEFAULT NULL,
  `posting_type` enum('financial','general') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'For Approval of DC',
  `divisionchief_id` bigint unsigned NOT NULL,
  `assignedto` bigint unsigned DEFAULT NULL,
  `dc_approval_date` date DEFAULT NULL,
  `ocd_approval_date` date DEFAULT NULL,
  `mis_assessment` text COLLATE utf8mb4_unicode_ci,
  `recommendation` text COLLATE utf8mb4_unicode_ci,
  `pdf_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_completion_date` date DEFAULT NULL,
  `action_taken` text COLLATE utf8mb4_unicode_ci,
  `completed_at` date DEFAULT NULL,
  `attendedby` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `feedback` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `rating` tinyint DEFAULT NULL,
  `rating_remarks` text COLLATE utf8mb4_unicode_ci,
  `rated_at` timestamp NULL DEFAULT NULL,
  `priority` enum('urgent','high','normal','low') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `queued_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_job_requests_facility_request_id_unique` (`facility_request_id`),
  KEY `it_job_requests_user_id_foreign` (`user_id`),
  KEY `it_job_requests_divisionchief_id_foreign` (`divisionchief_id`),
  KEY `it_job_requests_assignedto_foreign` (`assignedto`),
  KEY `itjr_status_idx` (`status`),
  KEY `itjr_title_idx` (`title`),
  KEY `itjr_category_idx` (`category`),
  CONSTRAINT `it_job_requests_assignedto_foreign` FOREIGN KEY (`assignedto`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `it_job_requests_divisionchief_id_foreign` FOREIGN KEY (`divisionchief_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `it_job_requests_facility_request_id_foreign` FOREIGN KEY (`facility_request_id`) REFERENCES `facility_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `it_job_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `it_job_requests_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `it_job_requests_categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `itjr_tracking_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `it_job_request_id` bigint unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `updated_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `itjr_tracking_logs_it_job_request_id_foreign` (`it_job_request_id`),
  KEY `itjr_tracking_logs_updated_by_foreign` (`updated_by`),
  CONSTRAINT `itjr_tracking_logs_it_job_request_id_foreign` FOREIGN KEY (`it_job_request_id`) REFERENCES `it_job_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `itjr_tracking_logs_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ict_pms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frequency` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by` bigint unsigned DEFAULT NULL,
  `status` enum('Pending','Ongoing','Completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `school_year` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_area` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ict_pms_performed_by_foreign` (`performed_by`),
  CONSTRAINT `ict_pms_performed_by_foreign` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ict_pms_dates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ict_pms_id` bigint unsigned NOT NULL,
  `schedule_date` date NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ict_pms_dates_ict_pms_id_foreign` (`ict_pms_id`),
  CONSTRAINT `ict_pms_dates_ict_pms_id_foreign` FOREIGN KEY (`ict_pms_id`) REFERENCES `ict_pms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ict_pms_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ict_pms_id` bigint unsigned DEFAULT NULL,
  `equipment_id` bigint unsigned DEFAULT NULL,
  `pms_date` date DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PMS',
  `cost_of_repair` decimal(12,2) NOT NULL DEFAULT '0.00',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ict_pms_history_ict_pms_id_index` (`ict_pms_id`),
  KEY `ict_pms_history_equipment_id_index` (`equipment_id`),
  KEY `ict_pms_history_created_by_index` (`created_by`),
  CONSTRAINT `ict_pms_history_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ict_pms_history_ict_pms_id_foreign` FOREIGN KEY (`ict_pms_id`) REFERENCES `ict_pms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `csm_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `respondable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `respondable_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `client_type` enum('citizen','business','government') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'government',
  `sex` enum('male','female') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `age` tinyint unsigned DEFAULT NULL,
  `region_of_residence` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Caraga',
  `date_of_transaction` date NOT NULL,
  `office_availed` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_availed` json NOT NULL,
  `service_availed_other` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cc1` tinyint unsigned NOT NULL,
  `cc2` tinyint unsigned DEFAULT NULL,
  `cc3` tinyint unsigned DEFAULT NULL,
  `sqd0` tinyint unsigned NOT NULL,
  `sqd1` tinyint unsigned NOT NULL,
  `sqd2` tinyint unsigned NOT NULL,
  `sqd3` tinyint unsigned NOT NULL,
  `sqd4` tinyint unsigned NOT NULL,
  `sqd5` tinyint unsigned NOT NULL,
  `sqd6` tinyint unsigned NOT NULL,
  `sqd7` tinyint unsigned NOT NULL,
  `sqd8` tinyint unsigned NOT NULL,
  `suggestions` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `csm_responses_respondable_type_respondable_id_index` (`respondable_type`,`respondable_id`),
  KEY `csm_responses_user_id_foreign` (`user_id`),
  CONSTRAINT `csm_responses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('direct','group') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'direct',
  `name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversations_type_index` (`type`),
  KEY `conversations_created_by_index` (`created_by`),
  CONSTRAINT `conversations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conversation_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL,
  `left_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversation_user_conversation_id_user_id_unique` (`conversation_id`,`user_id`),
  KEY `conversation_user_conversation_id_index` (`conversation_id`),
  KEY `conversation_user_user_id_index` (`user_id`),
  CONSTRAINT `conversation_user_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `sender_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_conversation_id_created_at_index` (`conversation_id`,`created_at`),
  KEY `messages_conversation_id_index` (`conversation_id`),
  KEY `messages_sender_id_index` (`sender_id`),
  CONSTRAINT `messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `digital_signatures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `signable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `signable_id` bigint unsigned NOT NULL,
  `signer_id` bigint unsigned NOT NULL,
  `document_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `signature` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `verification_token` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `signed_at` timestamp NOT NULL,
  `signature_type` enum('hmac','kms') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hmac',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `digital_signatures_verification_token_unique` (`verification_token`),
  KEY `digital_signatures_signer_id_foreign` (`signer_id`),
  KEY `digital_signatures_signable_type_signable_id_index` (`signable_type`,`signable_id`),
  CONSTRAINT `digital_signatures_signer_id_foreign` FOREIGN KEY (`signer_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `signatory_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `signable_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `signable_id` bigint unsigned NOT NULL,
  `role_label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `name_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `division_snapshot` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_snapshot` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `captured_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_signatory_signable` (`signable_type`,`signable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `approvable_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `approvable_id` bigint unsigned NOT NULL,
  `step` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sequence` tinyint unsigned NOT NULL DEFAULT '0',
  `action` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `user_id` bigint unsigned DEFAULT NULL,
  `name_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `division_snapshot` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_snapshot` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `signed_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_approval_approvable` (`approvable_type`,`approvable_id`),
  KEY `idx_approval_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auditable_id` bigint unsigned DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audit_logs_user_id_index` (`user_id`),
  KEY `audit_logs_auditable_id_index` (`auditable_id`),
  KEY `audit_logs_created_at_index` (`created_at`),
  KEY `audit_logs_action_index` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `app_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_versions_version_unique` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ict_equipments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_id` bigint unsigned DEFAULT NULL,
  `property_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `date_acquired` date DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_id` bigint unsigned DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `qr_code_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ict_equipments_owner_id_foreign` (`owner_id`),
  KEY `ict_equipments_room_id_foreign` (`room_id`),
  CONSTRAINT `ict_equipments_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ict_equipments_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ict_pms_equipment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ict_pms_id` bigint unsigned NOT NULL,
  `equipment_id` bigint unsigned NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ict_pms_equipment_ict_pms_id_foreign` (`ict_pms_id`),
  KEY `ict_pms_equipment_equipment_id_foreign` (`equipment_id`),
  CONSTRAINT `ict_pms_equipment_ict_pms_id_foreign` FOREIGN KEY (`ict_pms_id`) REFERENCES `ict_pms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ict_pms_equipment_equipment_id_foreign` FOREIGN KEY (`equipment_id`) REFERENCES `ict_equipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
