-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 19, 2025 at 02:04 PM
-- Server version: 8.0.44
-- PHP Version: 8.4.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


CREATE TABLE `access_permissions` (
  `id` int NOT NULL,
  `identifier` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `item_id` int NOT NULL,
  `item_type` enum('file','quiz_set') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `access_times` int NOT NULL DEFAULT '0',
  `times_accessed` int NOT NULL DEFAULT '0',
  `granted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `access_to_all_users` (
  `id` int NOT NULL,
  `folder_id` int NOT NULL,
  `file_id` int DEFAULT NULL,
  `quiz_set_id` int DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `admin_users` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `assigned_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


INSERT INTO `admin_users` (`id`, `user_id`, `full_name`, `email`, `assigned_at`) VALUES
(6, 20, 'Admin User', 'admin@gmail.com', '2025-06-12 13:28:50'),
(21, 98, 'sandesh dhakal', 'sandeshdhakal75@gmail.com', '2025-10-13 10:55:29'),
(24, 102, 'EMA TAB', 'emakoreanlanguagecenter@gmail.com', '2025-11-01 16:37:18');

CREATE TABLE `files` (
  `id` int NOT NULL,
  `folder_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `icon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `access_type` enum('all','logged_in') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'logged_in'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `files` (`id`, `folder_id`, `name`, `file_path`, `icon_path`, `access_type`) VALUES
(100, 65, '40', 'Uploads/files/1762413061_690c4a0545dd9_40.wav', NULL, 'logged_in');


CREATE TABLE `folders` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `icon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `folders` (`id`, `name`, `icon_path`) VALUES
(65, 'Old Format Overall Sets', 'Uploads/folder_68bd026304c88_ubt logo.jpg'),
(101, 'NEW FORMAT NOVERMBER ', ''),
(104, 'test', '');


CREATE TABLE `give_access_to_all_users` (
  `id` int NOT NULL,
  `folder_id` int NOT NULL,
  `file_id` int DEFAULT NULL,
  `quiz_set_id` int DEFAULT NULL,
  `access_granted` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `give_access_to_login_users` (
  `id` int NOT NULL,
  `item_type` enum('file','quiz_set') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `item_id` int NOT NULL,
  `access_granted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `give_access_to_login_users` (`id`, `item_type`, `item_id`, `access_granted`, `created_at`) VALUES
(68, 'file', 28, 1, '2025-06-03 10:31:50'),
(69, 'file', 30, 1, '2025-06-03 10:31:51'),
(71, 'quiz_set', 34, 1, '2025-06-13 12:29:28'),
(72, 'quiz_set', 35, 1, '2025-06-13 14:43:17'),
(73, 'file', 44, 1, '2025-06-13 14:43:20'),
(74, 'quiz_set', 40, 1, '2025-06-17 07:08:16'),
(75, 'file', 75, 1, '2025-07-19 01:26:08'),
(78, 'quiz_set', 39, 1, '2025-07-19 01:26:29'),
(79, 'file', 77, 1, '2025-07-19 01:26:32');


CREATE TABLE `item_activation_status` (
  `id` int NOT NULL,
  `item_type` enum('file','quiz_set') NOT NULL,
  `item_id` int NOT NULL,
  `is_activated` tinyint(1) DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `notices` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `text_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `notices` (`id`, `title`, `text_content`, `file_name`, `file_path`, `created_at`) VALUES
('6906b799a5ba0', 'Please contact admin for full exam', '', NULL, NULL, '2025-11-02 01:44:57');


CREATE TABLE `password_reset_requests` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `request_status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `questions` (
  `id` int NOT NULL,
  `quiz_set_id` int NOT NULL,
  `question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `optional_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `question_file` mediumblob,
  `question_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `question_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correct_answer` enum('A','B','C','D') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `choice_A_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_A_file` mediumblob,
  `choice_A_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_A_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_B_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_B_file` mediumblob,
  `choice_B_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_B_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_C_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_C_file` mediumblob,
  `choice_C_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_C_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_D_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_D_file` mediumblob,
  `choice_D_file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_D_file_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `question_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `question_word_formatting` json NOT NULL,
  `optional_word_formatting` json NOT NULL,
  `choice_A_word_formatting` json NOT NULL,
  `choice_B_word_formatting` json NOT NULL,
  `choice_C_word_formatting` json NOT NULL,
  `choice_D_word_formatting` json NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `questions_backup` (
  `id` int NOT NULL DEFAULT '0',
  `quiz_set_id` int NOT NULL,
  `question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `optional_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `question_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correct_answer` enum('A','B','C','D') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `choice_A_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_A_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_B_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_B_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_C_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_C_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `choice_D_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `choice_D_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `question_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `question_bold` tinyint(1) DEFAULT '0',
  `question_underline` tinyint(1) DEFAULT '0',
  `optional_bold` tinyint(1) DEFAULT '0',
  `optional_underline` tinyint(1) DEFAULT '0',
  `choice_A_bold` tinyint(1) DEFAULT '0',
  `choice_A_underline` tinyint(1) DEFAULT '0',
  `choice_B_bold` tinyint(1) DEFAULT '0',
  `choice_B_underline` tinyint(1) DEFAULT '0',
  `choice_C_bold` tinyint(1) DEFAULT '0',
  `choice_C_underline` tinyint(1) DEFAULT '0',
  `choice_D_bold` tinyint(1) DEFAULT '0',
  `choice_D_underline` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `quiz_sets` (
  `id` int NOT NULL,
  `folder_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `icon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `access_type` enum('all','logged_in') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'logged_in'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `users` (
  `id` int NOT NULL,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'user',
  `is_logged_in` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `user_access`
--

CREATE TABLE `user_access` (
  `id` int NOT NULL,
  `identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_admin` tinyint(1) DEFAULT '0',
  `item_id` int NOT NULL,
  `item_type` enum('file','quiz_set') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `access_times` int DEFAULT '-1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `access_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `identifier` (`identifier`,`item_id`,`item_type`);

ALTER TABLE `access_to_all_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `folder_id` (`folder_id`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `quiz_set_id` (`quiz_set_id`);

ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id_unique` (`user_id`);

ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `folder_id` (`folder_id`);

ALTER TABLE `folders`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `give_access_to_all_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_access` (`folder_id`,`file_id`,`quiz_set_id`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `quiz_set_id` (`quiz_set_id`);

ALTER TABLE `give_access_to_login_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_access` (`item_type`,`item_id`);

ALTER TABLE `item_activation_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item` (`item_type`,`item_id`),
  ADD KEY `item_id` (`item_id`);

ALTER TABLE `notices`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `password_reset_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);
ALTER TABLE `password_reset_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_set_id` (`quiz_set_id`);

ALTER TABLE `quiz_sets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `folder_id` (`folder_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `user_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `identifier` (`identifier`,`item_id`,`item_type`);

ALTER TABLE `access_permissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3680;


ALTER TABLE `access_to_all_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `admin_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

ALTER TABLE `files`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;


ALTER TABLE `folders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;


ALTER TABLE `give_access_to_all_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

ALTER TABLE `give_access_to_login_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

ALTER TABLE `item_activation_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=665;


ALTER TABLE `password_reset_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;


ALTER TABLE `questions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2725;


ALTER TABLE `quiz_sets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=240;


ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;


ALTER TABLE `user_access`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

ALTER TABLE `access_to_all_users`
  ADD CONSTRAINT `access_to_all_users_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `access_to_all_users_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `access_to_all_users_ibfk_3` FOREIGN KEY (`quiz_set_id`) REFERENCES `quiz_sets` (`id`) ON DELETE CASCADE;


ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE;


ALTER TABLE `give_access_to_all_users`
  ADD CONSTRAINT `give_access_to_all_users_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `give_access_to_all_users_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `give_access_to_all_users_ibfk_3` FOREIGN KEY (`quiz_set_id`) REFERENCES `quiz_sets` (`id`) ON DELETE CASCADE;


ALTER TABLE `password_reset_requests`
  ADD CONSTRAINT `password_reset_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;


ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`quiz_set_id`) REFERENCES `quiz_sets` (`id`) ON DELETE CASCADE;

ALTER TABLE `quiz_sets`
  ADD CONSTRAINT `quiz_sets_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE;
COMMIT;