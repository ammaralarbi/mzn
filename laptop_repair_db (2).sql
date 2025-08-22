-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 15 أغسطس 2025 الساعة 22:01
-- إصدار الخادم: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `laptop_repair_db`
--

-- --------------------------------------------------------

--
-- بنية الجدول `broken_laptops`
--

CREATE TABLE `broken_laptops` (
  `laptop_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `device_category_number` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) NOT NULL,
  `specs` text DEFAULT NULL,
  `with_charger` tinyint(1) DEFAULT 0,
  `problems_count` int(11) DEFAULT 0,
  `branch_name` varchar(100) DEFAULT NULL,
  `problem_details` text DEFAULT NULL,
  `entered_by_user_id` int(11) DEFAULT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `problem_type` varchar(100) DEFAULT NULL,
  `transfer_ref` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `repeat_problem_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `broken_laptops`
--

INSERT INTO `broken_laptops` (`laptop_id`, `category_id`, `device_category_number`, `serial_number`, `specs`, `with_charger`, `problems_count`, `branch_name`, `problem_details`, `entered_by_user_id`, `assigned_user_id`, `problem_type`, `transfer_ref`, `status`, `repeat_problem_count`) VALUES
(4, 1, '1', '354363', 'هعغهغ', 1, 4, 'المتجر', 'من زمان', 6, 4, 'كسر خارجي', '9897', 'review_pending', 5),
(5, 1, '88888', '34444444444444', 'Lenovo Legion 5 15ITH6H , Intel® Core™ Core i7-11800H , Ram 16GB 512GB', 1, 1, 'المازن', 'بايوس', 6, 4, 'هاردوير', '815', 'entered', 0);

-- --------------------------------------------------------

--
-- بنية الجدول `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`) VALUES
(1, 'لابتوبات');

-- --------------------------------------------------------

--
-- بنية الجدول `complaints`
--

CREATE TABLE `complaints` (
  `complaint_id` int(11) NOT NULL,
  `laptop_id` int(11) NOT NULL,
  `complaint_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `problem_title` varchar(200) DEFAULT NULL,
  `problem_details` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `complaints`
--

INSERT INTO `complaints` (`complaint_id`, `laptop_id`, `complaint_date`, `problem_title`, `problem_details`, `image_path`, `user_id`) VALUES
(1, 4, '2025-08-15 00:04:55', 'مشكلة أولية', 'من زمان', NULL, 6),
(2, 5, '2025-08-15 19:22:49', 'مشكلة أولية', 'بايوس', NULL, 6);

-- --------------------------------------------------------

--
-- بنية الجدول `laptop_discussions`
--

CREATE TABLE `laptop_discussions` (
  `discussion_id` int(11) NOT NULL,
  `laptop_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `laptop_discussions`
--

INSERT INTO `laptop_discussions` (`discussion_id`, `laptop_id`, `user_id`, `message`, `image_path`, `created_at`) VALUES
(1, 4, 6, 'الزبون يشكي من خلل في كرت الشاشه', NULL, '2025-08-15 00:33:22'),
(2, 4, 6, 'هل تم فحص الجهاز', NULL, '2025-08-15 19:26:38'),
(3, 4, 6, 'كيف', NULL, '2025-08-15 19:30:57'),
(4, 4, 6, 'فع', NULL, '2025-08-15 19:31:41'),
(5, 4, 6, 'منتظر', NULL, '2025-08-15 19:32:33'),
(6, 4, 4, 'اشتغل فيه', NULL, '2025-08-15 19:49:30');

-- --------------------------------------------------------

--
-- بنية الجدول `locks`
--

CREATE TABLE `locks` (
  `lock_id` int(11) NOT NULL,
  `laptop_id` int(11) NOT NULL,
  `lock_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `lock_type` varchar(100) DEFAULT NULL,
  `solution_percentage` decimal(5,2) DEFAULT NULL,
  `more_description` text DEFAULT NULL,
  `final_status` varchar(100) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `transfer_ref` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 4, 'تم تعيين جهاز جديد لك : 34444444444444', 'laptop_chat.php?laptop_id=5', 1, '2025-08-15 19:22:49'),
(2, 4, 'رسالة جديدة من admin بخصوص الجهاز 354363', 'laptop_chat.php?laptop_id=4', 1, '2025-08-15 19:26:38'),
(3, 4, 'رسالة جديدة من admin بخصوص الجهاز 354363', 'laptop_chat.php?laptop_id=4', 1, '2025-08-15 19:30:57'),
(4, 4, 'رسالة جديدة من admin بخصوص الجهاز 354363', 'laptop_chat.php?laptop_id=4', 1, '2025-08-15 19:31:41'),
(5, 4, 'رسالة جديدة من admin بخصوص الجهاز 354363', 'laptop_chat.php?laptop_id=4', 1, '2025-08-15 19:32:33'),
(6, 6, 'رسالة جديدة من manager بخصوص الجهاز 354363', 'laptop_chat.php?laptop_id=4', 1, '2025-08-15 19:49:30');

-- --------------------------------------------------------

--
-- بنية الجدول `operations`
--

CREATE TABLE `operations` (
  `operation_id` int(11) NOT NULL,
  `laptop_id` int(11) NOT NULL,
  `operation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL,
  `repair_result` varchar(255) DEFAULT NULL,
  `remaining_problems_count` int(11) DEFAULT 0,
  `details` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `operations`
--

INSERT INTO `operations` (`operation_id`, `laptop_id`, `operation_date`, `user_id`, `repair_result`, `remaining_problems_count`, `details`, `image_path`) VALUES
(1, 4, '2025-08-15 00:04:55', 6, 'تم إدخال الجهاز والمشكلة', 4, 'تم إدخال الجهاز بواسطة المستخدم', NULL),
(2, 4, '2025-08-15 00:56:05', 6, '100', 1, 'تم اصلاح منفذ الشاحن', NULL),
(3, 5, '2025-08-15 19:22:49', 6, 'تم إدخال الجهاز والمشكلة', 1, 'تم إدخال الجهاز بواسطة المستخدم', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `permissions` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `permissions`) VALUES
(4, 'manager', '4444', 'manager'),
(5, 'technician', '4444', 'technician'),
(6, 'admin', '4444', 'admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `broken_laptops`
--
ALTER TABLE `broken_laptops`
  ADD PRIMARY KEY (`laptop_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `entered_by_user_id` (`entered_by_user_id`),
  ADD KEY `assigned_user_id` (`assigned_user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`complaint_id`),
  ADD KEY `laptop_id` (`laptop_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `laptop_discussions`
--
ALTER TABLE `laptop_discussions`
  ADD PRIMARY KEY (`discussion_id`),
  ADD KEY `laptop_id` (`laptop_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `locks`
--
ALTER TABLE `locks`
  ADD PRIMARY KEY (`lock_id`),
  ADD KEY `laptop_id` (`laptop_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `operations`
--
ALTER TABLE `operations`
  ADD PRIMARY KEY (`operation_id`),
  ADD KEY `laptop_id` (`laptop_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `broken_laptops`
--
ALTER TABLE `broken_laptops`
  MODIFY `laptop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `complaint_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `laptop_discussions`
--
ALTER TABLE `laptop_discussions`
  MODIFY `discussion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `locks`
--
ALTER TABLE `locks`
  MODIFY `lock_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `operation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `broken_laptops`
--
ALTER TABLE `broken_laptops`
  ADD CONSTRAINT `broken_laptops_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `broken_laptops_ibfk_2` FOREIGN KEY (`entered_by_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `broken_laptops_ibfk_3` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`laptop_id`) REFERENCES `broken_laptops` (`laptop_id`),
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `laptop_discussions`
--
ALTER TABLE `laptop_discussions`
  ADD CONSTRAINT `laptop_discussions_ibfk_1` FOREIGN KEY (`laptop_id`) REFERENCES `broken_laptops` (`laptop_id`),
  ADD CONSTRAINT `laptop_discussions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `locks`
--
ALTER TABLE `locks`
  ADD CONSTRAINT `locks_ibfk_1` FOREIGN KEY (`laptop_id`) REFERENCES `broken_laptops` (`laptop_id`),
  ADD CONSTRAINT `locks_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `operations`
--
ALTER TABLE `operations`
  ADD CONSTRAINT `operations_ibfk_1` FOREIGN KEY (`laptop_id`) REFERENCES `broken_laptops` (`laptop_id`),
  ADD CONSTRAINT `operations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
