-- phpMyAdmin SQL Dump
-- version 4.6.6deb4
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 30, 2019 at 11:06 AM
-- Server version: 10.3.17-MariaDB-0+deb10u1
-- PHP Version: 7.3.9-1~deb10u1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ez-portal_ovc`
--

-- --------------------------------------------------------

--
-- Table structure for table `access`
--

CREATE TABLE `access` (
  `access_id` int(11) NOT NULL,
  `access_token` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int(11) NOT NULL,
  `access_refreshed` timestamp NOT NULL DEFAULT current_timestamp(),
  `access_expires` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `appointment_day` tinyint(1) NOT NULL,
  `appointment_time` time NOT NULL,
  `appointment_timeSlot` tinyint(2) NOT NULL,
  `appointment_duration` int(11) NOT NULL,
  `bos_id` int(11) NOT NULL,
  `type_text_id` int(11) NOT NULL,
  `groups_egrp_id` int(11) NOT NULL,
  `subjects_egrp_id` int(11) NOT NULL,
  `teachers_egrp_id` int(11) NOT NULL,
  `locations_egrp_id` int(11) NOT NULL,
  `schedulerRemark_text_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `boss`
--

CREATE TABLE `boss` (
  `bos_id` int(11) NOT NULL,
  `bos_zid` int(11) NOT NULL,
  `sisy_id` int(11) NOT NULL,
  `bos_name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `config`
--

CREATE TABLE `config` (
  `config_id` int(11) NOT NULL,
  `config_key` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `egrps`
--

CREATE TABLE `egrps` (
  `egrp_id` int(11) NOT NULL,
  `egrp` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entities`
--

CREATE TABLE `entities` (
  `entity_id` int(11) NOT NULL,
  `entity_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` enum('PERSOON','LOKAAL','LESGROEP','VAK','CATEGORIE','STAMKLAS') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_visible` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Table structure for table `entities2egrps`
--

CREATE TABLE `entities2egrps` (
  `entity2egrp_id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `egrp_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entity_zids`
--

CREATE TABLE `entity_zids` (
  `entitiy_zid_id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `entity_zid` int(11) DEFAULT NULL,
  `parent_entity_id` int(11) DEFAULT NULL,
  `bos_id` int(11) DEFAULT NULL,
  `sisy_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `holiday_id` int(11) NOT NULL,
  `holiday_zid` int(11) NOT NULL,
  `sisy_id` int(11) DEFAULT NULL,
  `holiday_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `holiday_start` date NOT NULL,
  `holiday_end` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roosters`
--

CREATE TABLE `roosters` (
  `rooster_id` int(11) NOT NULL,
  `week_id` int(11) NOT NULL,
  `rooster_last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `rooster_type` enum('basis','week') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rooster_ok` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Table structure for table `sisys`
--

CREATE TABLE `sisys` (
  `sisy_id` int(11) NOT NULL,
  `sisy_zid` int(11) NOT NULL,
  `sisy_year` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sisy_school` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sisy_project` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sisy_archived` tinyint(1) NOT NULL,
  `studentCanViewOwnSchedule` tinyint(1) NOT NULL,
  `studentCanViewProjectSchedules` tinyint(1) NOT NULL,
  `studentCanViewProjectNames` tinyint(1) NOT NULL,
  `employeeCanViewOwnSchedule` tinyint(1) NOT NULL,
  `employeeCanViewProjectSchedules` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `texts`
--

CREATE TABLE `texts` (
  `text_id` int(11) NOT NULL,
  `text` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `firstName` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lastName` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isStudent` tinyint(1) NOT NULL,
  `isEmployee` tinyint(1) NOT NULL,
  `isFamilyMember` tinyint(1) NOT NULL,
  `isSchoolScheduler` tinyint(1) NOT NULL,
  `isSchoolLeader` tinyint(1) NOT NULL,
  `isStudentAdministrator` tinyint(1) NOT NULL,
  `isTeamLeader` tinyint(1) NOT NULL,
  `isSectionLeader` tinyint(1) NOT NULL,
  `isMentor` tinyint(1) NOT NULL,
  `isParentTeacherNightScheduler` tinyint(1) NOT NULL,
  `isDean` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Table structure for table `weeks`
--

CREATE TABLE `weeks` (
  `week_id` int(11) NOT NULL,
  `sisy_id` int(11) DEFAULT NULL,
  `year` smallint(6) NOT NULL,
  `week` tinyint(4) NOT NULL,
  `monday_timestamp` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ma` tinyint(1) NOT NULL DEFAULT 1,
  `di` tinyint(1) NOT NULL DEFAULT 1,
  `wo` tinyint(1) NOT NULL DEFAULT 1,
  `do` tinyint(1) NOT NULL DEFAULT 1,
  `vr` tinyint(1) NOT NULL DEFAULT 1,
  `week_lock` int(11) NOT NULL DEFAULT 0,
  `week_last_sync` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access`
--
ALTER TABLE `access`
  ADD PRIMARY KEY (`access_id`),
  ADD UNIQUE KEY `access_token` (`access_token`) USING BTREE,
  ADD KEY `entity_id` (`entity_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD UNIQUE KEY `appointment_day` (`appointment_day`,`appointment_time`,`appointment_timeSlot`,`appointment_duration`,`bos_id`,`type_text_id`,`groups_egrp_id`,`subjects_egrp_id`,`teachers_egrp_id`,`locations_egrp_id`,`schedulerRemark_text_id`),
  ADD KEY `bos_id` (`bos_id`),
  ADD KEY `type_text_id` (`type_text_id`),
  ADD KEY `groups_egrp_id` (`groups_egrp_id`),
  ADD KEY `teachers_egrp_id` (`teachers_egrp_id`),
  ADD KEY `locations_egrp_id` (`locations_egrp_id`),
  ADD KEY `schedulerRemark_text_id` (`schedulerRemark_text_id`),
  ADD KEY `subjects_egrp_id` (`subjects_egrp_id`) USING BTREE;

--
-- Indexes for table `boss`
--
ALTER TABLE `boss`
  ADD PRIMARY KEY (`bos_id`),
  ADD UNIQUE KEY `bos_zid` (`bos_zid`);

--
-- Indexes for table `config`
--
ALTER TABLE `config`
  ADD PRIMARY KEY (`config_id`),
  ADD UNIQUE KEY `config_key` (`config_key`);

--
-- Indexes for table `egrps`
--
ALTER TABLE `egrps`
  ADD PRIMARY KEY (`egrp_id`);

--
-- Indexes for table `entities`
--
ALTER TABLE `entities`
  ADD PRIMARY KEY (`entity_id`),
  ADD UNIQUE KEY `entity_name` (`entity_name`,`entity_type`),
  ADD KEY `entity_zid` (`entity_type`);

--
-- Indexes for table `entities2egrps`
--
ALTER TABLE `entities2egrps`
  ADD PRIMARY KEY (`entity2egrp_id`),
  ADD UNIQUE KEY `entity2egrp` (`entity_id`,`egrp_id`) USING BTREE,
  ADD KEY `egrp_id` (`egrp_id`),
  ADD KEY `entity_id` (`entity_id`);

--
-- Indexes for table `entity_zids`
--
ALTER TABLE `entity_zids`
  ADD PRIMARY KEY (`entitiy_zid_id`),
  ADD UNIQUE KEY `entity_id_2` (`entity_id`,`sisy_id`),
  ADD KEY `bos_id` (`bos_id`),
  ADD KEY `sisy_id` (`sisy_id`),
  ADD KEY `parent_entity_id` (`parent_entity_id`),
  ADD KEY `entity_id` (`entity_id`) USING BTREE;

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`holiday_id`),
  ADD UNIQUE KEY `holiday_zid` (`holiday_zid`),
  ADD KEY `sisy_id` (`sisy_id`);

--
-- Indexes for table `roosters`
--
ALTER TABLE `roosters`
  ADD PRIMARY KEY (`rooster_id`),
  ADD KEY `week_id` (`week_id`);

--
-- Indexes for table `sisys`
--
ALTER TABLE `sisys`
  ADD PRIMARY KEY (`sisy_id`),
  ADD UNIQUE KEY `sisys_zid` (`sisy_zid`);

--
-- Indexes for table `texts`
--
ALTER TABLE `texts`
  ADD PRIMARY KEY (`text_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `entity_id` (`entity_id`);

--
-- Indexes for table `weeks`
--
ALTER TABLE `weeks`
  ADD PRIMARY KEY (`week_id`),
  ADD UNIQUE KEY `sisy_id` (`sisy_id`,`week`),
  ADD KEY `year` (`year`,`week`),
  ADD KEY `sisy_id_2` (`sisy_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access`
--
ALTER TABLE `access`
  MODIFY `access_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `boss`
--
ALTER TABLE `boss`
  MODIFY `bos_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `config`
--
ALTER TABLE `config`
  MODIFY `config_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `egrps`
--
ALTER TABLE `egrps`
  MODIFY `egrp_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `entities`
--
ALTER TABLE `entities`
  MODIFY `entity_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `entities2egrps`
--
ALTER TABLE `entities2egrps`
  MODIFY `entity2egrp_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `entity_zids`
--
ALTER TABLE `entity_zids`
  MODIFY `entitiy_zid_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `roosters`
--
ALTER TABLE `roosters`
  MODIFY `rooster_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `sisys`
--
ALTER TABLE `sisys`
  MODIFY `sisy_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `texts`
--
ALTER TABLE `texts`
  MODIFY `text_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `weeks`
--
ALTER TABLE `weeks`
  MODIFY `week_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `access`
--
ALTER TABLE `access`
  ADD CONSTRAINT `access_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`entity_id`);

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`bos_id`) REFERENCES `boss` (`bos_id`),
  ADD CONSTRAINT `appointments_ibfk_16` FOREIGN KEY (`schedulerRemark_text_id`) REFERENCES `texts` (`text_id`),
  ADD CONSTRAINT `appointments_ibfk_4` FOREIGN KEY (`type_text_id`) REFERENCES `texts` (`text_id`),
  ADD CONSTRAINT `appointments_ibfk_5` FOREIGN KEY (`groups_egrp_id`) REFERENCES `egrps` (`egrp_id`),
  ADD CONSTRAINT `appointments_ibfk_7` FOREIGN KEY (`teachers_egrp_id`) REFERENCES `egrps` (`egrp_id`),
  ADD CONSTRAINT `appointments_ibfk_8` FOREIGN KEY (`locations_egrp_id`) REFERENCES `egrps` (`egrp_id`),
  ADD CONSTRAINT `appointments_ibfk_9` FOREIGN KEY (`subjects_egrp_id`) REFERENCES `egrps` (`egrp_id`);

--
-- Constraints for table `entities2egrps`
--
ALTER TABLE `entities2egrps`
  ADD CONSTRAINT `entities2egrps_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`entity_id`),
  ADD CONSTRAINT `entities2egrps_ibfk_2` FOREIGN KEY (`egrp_id`) REFERENCES `egrps` (`egrp_id`);

--
-- Constraints for table `entity_zids`
--
ALTER TABLE `entity_zids`
  ADD CONSTRAINT `entity_zids_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`entity_id`),
  ADD CONSTRAINT `entity_zids_ibfk_2` FOREIGN KEY (`parent_entity_id`) REFERENCES `entities` (`entity_id`),
  ADD CONSTRAINT `entity_zids_ibfk_3` FOREIGN KEY (`bos_id`) REFERENCES `boss` (`bos_id`),
  ADD CONSTRAINT `entity_zids_ibfk_4` FOREIGN KEY (`sisy_id`) REFERENCES `sisys` (`sisy_id`);

--
-- Constraints for table `holidays`
--
ALTER TABLE `holidays`
  ADD CONSTRAINT `holidays_ibfk_1` FOREIGN KEY (`sisy_id`) REFERENCES `sisys` (`sisy_id`);

--
-- Constraints for table `roosters`
--
ALTER TABLE `roosters`
  ADD CONSTRAINT `roosters_ibfk_1` FOREIGN KEY (`week_id`) REFERENCES `weeks` (`week_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`entity_id`);

--
-- Constraints for table `weeks`
--
ALTER TABLE `weeks`
  ADD CONSTRAINT `weeks_ibfk_1` FOREIGN KEY (`sisy_id`) REFERENCES `sisys` (`sisy_id`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
