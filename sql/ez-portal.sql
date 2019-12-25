-- phpMyAdmin SQL Dump
-- version 4.6.6deb4
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 25, 2019 at 02:10 PM
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
-- Table structure for table `entities`
--

CREATE TABLE `entities` (
  `entity_id` int(11) NOT NULL,
  `entity_zid` int(11) DEFAULT NULL,
  `entity_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` enum('PERSOON','LOKAAL','LESGROEP','VAK','CATEGORIE','STAMKLAS') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_visible` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `holiday_id` int(11) NOT NULL,
  `holiday_zid` int(11) NOT NULL,
  `sisy_id` int(11) NOT NULL,
  `holiday_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `holiday_start` date NOT NULL,
  `holiday_end` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sisys`
--

CREATE TABLE `sisys` (
  `sisy_id` int(11) NOT NULL,
  `sisy_zid` int(11) NOT NULL,
  `sisy_year` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sisy_name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sisy_archived` tinyint(1) NOT NULL,
  `studentCanViewOwnSchedule` tinyint(1) NOT NULL,
  `studentCanViewProjectSchedules` tinyint(1) NOT NULL,
  `studentCanViewProjectNames` tinyint(1) NOT NULL,
  `employeeCanViewOwnSchedule` tinyint(1) NOT NULL,
  `employeeCanViewProjectSchedules` tinyint(1) NOT NULL
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `weeks`
--

CREATE TABLE `weeks` (
  `week_id` int(11) NOT NULL,
  `sisy_id` int(11) DEFAULT NULL,
  `year` smallint(6) NOT NULL,
  `week` tinyint(4) NOT NULL,
  `monday_unix_timestamp` int(11) NOT NULL,
  `ma` tinyint(1) NOT NULL DEFAULT 1,
  `di` tinyint(1) NOT NULL DEFAULT 1,
  `wo` tinyint(1) NOT NULL DEFAULT 1,
  `do` tinyint(1) NOT NULL DEFAULT 1,
  `vr` tinyint(1) NOT NULL DEFAULT 1
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
-- Indexes for table `entities`
--
ALTER TABLE `entities`
  ADD PRIMARY KEY (`entity_id`),
  ADD UNIQUE KEY `entity_name` (`entity_name`,`entity_type`),
  ADD KEY `entity_zid` (`entity_zid`,`entity_type`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`holiday_id`),
  ADD UNIQUE KEY `holiday_zid` (`holiday_zid`),
  ADD KEY `sisy_id` (`sisy_id`);

--
-- Indexes for table `sisys`
--
ALTER TABLE `sisys`
  ADD PRIMARY KEY (`sisy_id`),
  ADD UNIQUE KEY `sisys_zid` (`sisy_zid`);

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
  ADD UNIQUE KEY `year` (`year`,`week`),
  ADD KEY `sisy_id` (`sisy_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access`
--
ALTER TABLE `access`
  MODIFY `access_id` int(11) NOT NULL AUTO_INCREMENT;
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
-- AUTO_INCREMENT for table `entities`
--
ALTER TABLE `entities`
  MODIFY `entity_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `sisys`
--
ALTER TABLE `sisys`
  MODIFY `sisy_id` int(11) NOT NULL AUTO_INCREMENT;
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
-- Constraints for table `holidays`
--
ALTER TABLE `holidays`
  ADD CONSTRAINT `holidays_ibfk_1` FOREIGN KEY (`sisy_id`) REFERENCES `sisys` (`sisy_id`);

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
