-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 14, 2026 at 04:18 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sit_in_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `full_name`, `created_at`) VALUES
(3, 'admin', 'admin123', 'CCS Administrator', '2026-03-25 14:12:26');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `content`, `created_at`) VALUES
(2, 'no class', '2026-04-13 16:19:52'),
(3, 'no class for today, see you in ICT Congress', '2026-04-22 00:59:12'),
(4, 'SEE YOU LATER AT ICT CONGRESS 2026', '2026-04-22 01:07:04');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `sit_in_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `sit_in_id`, `user_id`, `rating`, `message`, `created_at`) VALUES
(1, 3, 3, 5, 'it was nice', '2026-04-12 01:19:11'),
(2, 4, 3, 5, 'the working name gian licardo smells like marijuana', '2026-04-14 00:18:55'),
(3, 11, 1, 4, 'the working was approachable and it was nice', '2026-04-22 09:10:19');

-- --------------------------------------------------------

--
-- Table structure for table `sit_in_records`
--

CREATE TABLE `sit_in_records` (
  `id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `purpose` varchar(100) DEFAULT NULL,
  `lab_number` varchar(10) DEFAULT NULL,
  `pc_number` int(11) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `reserved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `status` enum('active','completed') DEFAULT 'active',
  `reward_points` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sit_in_records`
--

INSERT INTO `sit_in_records` (`id`, `id_number`, `user_id`, `purpose`, `lab_number`, `pc_number`, `login_time`, `reserved_at`, `logout_time`, `status`, `reward_points`) VALUES
(1, '1234', NULL, 'C Programming', '524', NULL, '2026-04-07 02:48:14', '2026-04-15 19:44:42', '2026-04-07 03:06:01', 'completed', 0),
(2, '2005', NULL, 'ASP.NET', '526', NULL, '2026-04-07 03:04:49', '2026-04-15 19:44:42', '2026-04-07 03:05:10', 'completed', 0),
(3, '2000', NULL, 'Java', '542', NULL, '2026-04-11 16:34:26', '2026-04-15 19:44:42', '2026-04-11 16:34:47', 'completed', 0),
(4, '2000', NULL, 'PHP', '528', NULL, '2026-04-13 16:17:27', '2026-04-15 19:44:42', '2026-04-13 16:17:40', 'completed', 0),
(5, '2000', NULL, 'ASP.NET', '526', NULL, '2026-04-15 19:07:58', '2026-04-15 19:44:42', '2026-04-15 19:08:08', 'completed', 0),
(6, '2000', NULL, 'ASP.NET', '524', NULL, '2026-04-15 23:00:00', '2026-04-15 19:45:21', '2026-04-15 19:46:13', 'completed', 0),
(7, '23792088', NULL, 'ASP.NET', '528', NULL, '2026-04-16 23:30:00', '2026-04-15 19:53:04', '2026-04-15 20:51:30', 'completed', 0),
(10, '1234', NULL, 'C Programming', '530', NULL, '2026-04-22 01:00:15', '2026-04-22 01:00:15', '2026-04-22 01:00:37', 'completed', 0),
(11, '1234', NULL, 'Java', '530', NULL, '2026-04-22 01:08:13', '2026-04-22 01:08:13', '2026-04-22 01:08:40', 'completed', 0),
(13, '1234', NULL, 'C Programming', '524', NULL, '2026-05-10 13:51:27', '2026-05-10 13:51:27', '2026-05-10 13:53:46', 'completed', 0),
(14, '2005', NULL, 'Java', '526', NULL, '2026-05-13 15:12:45', '2026-05-13 15:12:45', '2026-05-13 15:13:32', 'completed', 0),
(15, '2005', NULL, 'Java', '526', NULL, '2026-05-13 15:13:20', '2026-05-13 15:13:20', '2026-05-13 15:13:28', 'completed', 0),
(16, '1234', NULL, 'C Programming', '526', NULL, '2026-05-14 01:55:53', '2026-05-14 01:55:53', '2026-05-14 01:56:17', 'completed', 1),
(17, '1234', NULL, 'C Programming', '524', NULL, '2026-05-14 02:05:29', '2026-05-14 02:05:29', '2026-05-14 02:06:12', 'completed', 0);

-- --------------------------------------------------------

--
-- Table structure for table `software_availability`
--

CREATE TABLE `software_availability` (
  `id` int(11) NOT NULL,
  `lab_number` varchar(10) NOT NULL,
  `software_name` varchar(100) NOT NULL,
  `version` varchar(50) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `software_availability`
--

INSERT INTO `software_availability` (`id`, `lab_number`, `software_name`, `version`, `category`, `description`, `created_at`) VALUES
(1, '524', 'Visual Studio Code', '1.85.1', 'Code Editor', 'Lightweight code editor with extensions', '2026-05-04 15:12:08'),
(2, '524', 'XAMPP', '8.2.12', 'Development Server', 'Apache, MySQL, PHP, Perl stack', '2026-05-04 15:12:08'),
(3, '524', 'Node.js', '20.10.0', 'Runtime', 'JavaScript runtime for server-side development', '2026-05-04 15:12:08'),
(4, '524', 'Git', '2.42.0', 'Version Control', 'Distributed version control system', '2026-05-04 15:12:08'),
(5, '524', 'MySQL Workbench', '8.0.34', 'Database Tool', 'Visual database design tool', '2026-05-04 15:12:08'),
(7, '524', 'Chrome', '119.0', 'Browser', 'Web browser for testing', '2026-05-04 15:12:08'),
(8, '524', 'Firefox', '119.0', 'Browser', 'Alternative web browser', '2026-05-04 15:12:08'),
(9, '526', 'Visual Studio Code', '1.85.1', 'Code Editor', 'Lightweight code editor with extensions', '2026-05-04 15:12:08'),
(10, '526', 'XAMPP', '8.2.12', 'Development Server', 'Apache, MySQL, PHP, Perl stack', '2026-05-04 15:12:08'),
(11, '526', 'Python', '3.11.6', 'Programming Language', 'High-level programming language', '2026-05-04 15:12:08'),
(12, '526', 'Java JDK', '21.0.1', 'Programming Language', 'Java Development Kit', '2026-05-04 15:12:08'),
(13, '526', 'Eclipse IDE', '2023-09', 'IDE', 'Java IDE with plugins', '2026-05-04 15:12:08'),
(14, '526', 'IntelliJ IDEA', '2023.2', 'IDE', 'Java IDE with advanced features', '2026-05-04 15:12:08'),
(15, '526', 'Android Studio', '2022.3', 'IDE', 'Android app development', '2026-05-04 15:12:08'),
(16, '526', 'Git', '2.42.0', 'Version Control', 'Distributed version control system', '2026-05-04 15:12:08'),
(17, '528', 'Visual Studio Code', '1.85.1', 'Code Editor', 'Lightweight code editor with extensions', '2026-05-04 15:12:08'),
(18, '528', 'XAMPP', '8.2.12', 'Development Server', 'Apache, MySQL, PHP, Perl stack', '2026-05-04 15:12:08'),
(19, '528', 'Node.js', '20.10.0', 'Runtime', 'JavaScript runtime for server-side development', '2026-05-04 15:12:08'),
(20, '528', 'React DevTools', '4.28.0', 'Development Tool', 'React component debugging', '2026-05-04 15:12:08'),
(21, '528', 'MongoDB Compass', '1.40.0', 'Database Tool', 'MongoDB GUI', '2026-05-04 15:12:08'),
(22, '528', 'Docker', '24.0.6', 'Containerization', 'Container platform', '2026-05-04 15:12:08'),
(23, '528', 'VS Code Extensions', 'Various', 'Extensions', 'React, Vue, Angular extensions', '2026-05-04 15:12:08'),
(24, '530', 'Visual Studio Code', '1.85.1', 'Code Editor', 'Lightweight code editor with extensions', '2026-05-04 15:12:08'),
(25, '530', 'XAMPP', '8.2.12', 'Development Server', 'Apache, MySQL, PHP, Perl stack', '2026-05-04 15:12:08'),
(26, '530', 'PHP', '8.2.12', 'Programming Language', 'Server-side scripting', '2026-05-04 15:12:08'),
(27, '530', 'Composer', '2.6.5', 'Package Manager', 'PHP dependency manager', '2026-05-04 15:12:08'),
(28, '530', 'Laravel', '10.30.1', 'Framework', 'PHP web framework', '2026-05-04 15:12:08'),
(29, '530', 'MySQL', '8.0.34', 'Database', 'Relational database', '2026-05-04 15:12:08'),
(30, '530', 'phpMyAdmin', '5.2.1', 'Database Tool', 'Web-based MySQL admin', '2026-05-04 15:12:08'),
(31, '542', 'Visual Studio Code', '1.85.1', 'Code Editor', 'Lightweight code editor with extensions', '2026-05-04 15:12:08'),
(32, '542', 'Python', '3.11.6', 'Programming Language', 'High-level programming language', '2026-05-04 15:12:08'),
(33, '542', 'Jupyter Notebook', '7.0.6', 'Data Science', 'Interactive computing', '2026-05-04 15:12:08'),
(34, '542', 'Anaconda', '23.10.0', 'Data Science', 'Python distribution for data science', '2026-05-04 15:12:08'),
(35, '542', 'TensorFlow', '2.14.0', 'ML Framework', 'Machine learning framework', '2026-05-04 15:12:08'),
(36, '542', 'Pandas', '2.1.3', 'Data Analysis', 'Data manipulation library', '2026-05-04 15:12:08'),
(37, '542', 'NumPy', '1.25.2', 'Scientific Computing', 'Numerical computing library', '2026-05-04 15:12:08'),
(38, '542', 'Matplotlib', '3.8.0', 'Visualization', 'Plotting library', '2026-05-04 15:12:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `course` varchar(50) NOT NULL,
  `course_level` int(2) NOT NULL,
  `email` varchar(150) NOT NULL,
  `address` text NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT 'Studentlogo.png',
  `remaining_sessions` int(11) NOT NULL DEFAULT 30,
  `points` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `id_number`, `last_name`, `first_name`, `middle_name`, `course`, `course_level`, `email`, `address`, `password`, `created_at`, `profile_picture`, `remaining_sessions`, `points`) VALUES
(1, '1234', 'licardo', 'Gian', 'jared', 'BS Crim', 4, 'gianlicardo@gmail.com', 'Lapu-Lapu City', '$2y$10$fgb0hSpVgy6Bs8x7NaFp..f2RDyMRMtF287QyT782sCQ5.pR4V1C6', '2026-03-18 13:52:00', 'profile_1_1774307927.jpg', 24, 1),
(2, '2005', 'Sermonia', 'John Carlo', 'Dominguez', 'BSIT', 3, 'Sermonia@gmail.com', 'Lapu-Lapu City', '$2y$10$.KKclluA4QlJ96Tw7d0fwu4q1f464t3oexWd2ibFcsMgZtN6VVUtG', '2026-03-25 14:44:51', 'profile_2_1774450635.png', 27, 0),
(3, '2000', 'Eniong', 'Jude', 'Emmanuel', 'BSIT', 3, 'jude@gmail.com', 'Guadalupe,Cebu City', '$2y$10$fddgHGCr1Lisn.R.NIkcrOLewnukgH.c/i77YKlxJg9s8GzYpi0O.', '2026-04-11 16:23:41', 'Studentlogo.png', 25, 0),
(4, '23792088', 'Sermonia', 'John', 'Dominguez', 'BSCpE', 3, 'john@gmail.com', 'Lapu - Lapu City', '$2y$10$oxzVSuOl6npCyDl.JTuvbOY/jBDdMO9fG5mGrDNPD45UgfsDDb5hy', '2026-04-15 19:52:05', 'Studentlogo.png', 28, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sit_in_records`
--
ALTER TABLE `sit_in_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `software_availability`
--
ALTER TABLE `software_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_lab_software` (`lab_number`,`software_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sit_in_records`
--
ALTER TABLE `sit_in_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `software_availability`
--
ALTER TABLE `software_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2967;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `sit_in_records`
--
ALTER TABLE `sit_in_records`
  ADD CONSTRAINT `sit_in_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
