CREATE DATABASE IF NOT EXISTS `gym_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `gym_db`;
SET FOREIGN_KEY_CHECKS = 0;
-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 10, 2025 at 09:48 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gym_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_by` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiry_date` date DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `target_audience` enum('all','clients','trainers') DEFAULT 'all',
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `created_by`, `created_at`, `expiry_date`, `priority`, `target_audience`, `updated_by`, `updated_at`) VALUES
(11, 'asdasd', 'asdasd', 'admin', '2025-11-03 16:38:37', NULL, 'medium', 'all', NULL, '2025-11-03 16:38:37'),
(12, 'Test announcement ', 'Testing testing', 'admin', '2025-11-05 14:55:29', NULL, 'medium', 'all', NULL, '2025-11-05 14:55:29');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `check_in` datetime NOT NULL,
  `check_out` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--


-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `budget_type` enum('revenue','expense') NOT NULL,
  `budget_amount` decimal(10,2) NOT NULL,
  `month_year` date NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_role` enum('admin','trainer','client') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `receiver_role` enum('admin','trainer','client') NOT NULL,
  `message` text NOT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `attachment_type` enum('image','file','video','audio') DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--


-- --------------------------------------------------------

--
-- Table structure for table `client_progress`
--

CREATE TABLE `client_progress` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `body_fat` decimal(5,2) DEFAULT NULL,
  `chest_measurement` decimal(5,2) DEFAULT NULL,
  `waist_measurement` decimal(5,2) DEFAULT NULL,
  `arm_measurement` decimal(5,2) DEFAULT NULL,
  `thigh_measurement` decimal(5,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `progress_date` date NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_progress`
--


-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(100) NOT NULL,
  `location` varchar(100) NOT NULL,
  `status` enum('Good','Needs Maintenance','Under Repair','Broken') DEFAULT 'Good',
  `date_added` datetime DEFAULT current_timestamp(),
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `name`, `category`, `location`, `status`, `date_added`, `last_updated`, `created_by`, `notes`) VALUES
(1, 'Treadmill #1', 'Cardio Machine', 'Cardio Area', 'Good', '2025-10-28 02:08:39', '2025-10-28 02:08:39', NULL, 'Regular maintenance done last month'),
(2, 'Treadmill #2', 'Cardio Machine', 'Cardio Area', 'Needs Maintenance', '2025-10-28 02:08:39', '2025-10-28 02:08:39', NULL, 'Belt needs adjustment'),
(3, 'Stationary Bike #1', 'Cardio Machine', 'Cardio Area', 'Good', '2025-10-28 02:08:39', '2025-10-28 02:08:39', NULL, ''),
(4, 'Elliptical #1', 'Cardio Machine', 'Cardio Area', 'Good', '2025-10-28 02:08:39', '2025-10-28 02:08:39', NULL, ''),
(5, 'Bench Press #1', 'Weight Machine', 'Weight Room', 'Good', '2025-10-28 02:08:39', '2025-10-30 06:36:26', NULL, ''),
(6, 'Leg Press Machine', 'Weight Machine', 'Weight Room', 'Good', '2025-10-28 02:08:39', '2025-10-28 03:41:59', NULL, 'Hydraulic leak - parts ordered'),
(7, 'Dumbbell 10kg #1', 'Free Weight', 'Weight Room', 'Good', '2025-10-28 02:08:39', '2025-10-28 02:08:39', NULL, ''),
(8, 'Dumbbell 10kg #2', 'Free Weight', 'Weight Room', 'Good', '2025-10-28 02:08:39', '2025-10-28 02:08:39', NULL, ''),
(9, 'Dumbbell 20kg #1', 'Free Weight', 'Weight Room', 'Good', '2025-10-28 02:08:39', '2025-10-28 03:57:48', NULL, 'Handle cracked - needs replacement'),
(10, 'Barbell Set #1', 'Free Weight', 'Weight Room', 'Under Repair', '2025-10-28 02:08:39', '2025-11-03 16:38:47', NULL, ''),
(11, 'Yoga Mat #1', 'Accessory', 'Group Exercise Studio', 'Good', '2025-10-28 02:08:39', '2025-10-28 02:08:39', NULL, ''),
(12, 'Yoga Mat #2', 'Accessory', 'Group Exercise Studio', 'Good', '2025-10-28 02:08:39', '2025-10-28 02:08:39', NULL, ''),
(13, 'Resistance Band Set', 'Accessory', 'Group Exercise Studio', 'Good', '2025-10-28 02:08:39', '2025-10-28 02:08:39', NULL, ''),
(14, 'dumbbell 25kg', 'equipment', 'weight room', 'Good', '2025-10-28 03:47:56', '2025-10-28 03:58:06', 1, 'sira handle'),
(15, 'dumbbell 50kg', 'equipment', 'weight room', 'Good', '2025-10-28 05:00:15', '2025-10-28 23:43:53', 1, 'sira plates'),
(16, 'asdsad', 'sadasd', 'sadas', 'Good', '2025-11-03 16:38:53', '2025-11-05 14:55:54', 1, 'asdsad');

--
-- Triggers `equipment`
--
DELIMITER $$
CREATE TRIGGER `after_equipment_status_update` AFTER UPDATE ON `equipment` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        -- Log the status change
        INSERT INTO equipment_logs (equipment_id, old_status, new_status, updated_by, note)
        VALUES (NEW.id, OLD.status, NEW.status, NEW.created_by, 'Status updated via system');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `equipment_logs`
--

CREATE TABLE `equipment_logs` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `old_status` varchar(50) NOT NULL,
  `new_status` varchar(50) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `date_updated` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_logs`
--


-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `payment_method` enum('cash','gcash','bank_transfer','card','online') DEFAULT 'cash',
  `expense_date` date DEFAULT curdate(),
  `recorded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `currency` varchar(3) DEFAULT 'PHP'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--


-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#ef4444',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `name`, `description`, `color`, `is_active`, `created_at`) VALUES
(1, 'Salaries & Wages', 'Employee salaries and wages', '#ef4444', 1, '2025-10-24 17:46:00'),
(2, 'Rent & Utilities', 'Gym rent and utility bills', '#f59e0b', 1, '2025-10-24 17:46:00'),
(3, 'Equipment Maintenance', 'Gym equipment repair and maintenance', '#10b981', 1, '2025-10-24 17:46:00'),
(4, 'Supplements & Inventory', 'Product inventory and supplements', '#3b82f6', 1, '2025-10-24 17:46:00'),
(5, 'Marketing & Advertising', 'Promotional and advertising costs', '#8b5cf6', 1, '2025-10-24 17:46:00'),
(6, 'Software & Subscriptions', 'Software licenses and subscriptions', '#6b7280', 1, '2025-10-24 17:46:00'),
(7, 'Other Expenses', 'Miscellaneous expenses', '#64748b', 1, '2025-10-24 17:46:00');

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `facility_condition` enum('Good','Needs Maintenance','Under Repair','Closed') DEFAULT 'Good',
  `notes` text DEFAULT NULL,
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `name`, `facility_condition`, `notes`, `last_updated`, `updated_by`) VALUES
(1, 'Cardio Area', 'Good', 'Treadmills, ellipticals, and stationary bikes', '2025-10-28 02:08:39', NULL),
(2, 'Weight Room', 'Good', 'Free weights and weight machines', '2025-10-28 02:08:39', NULL),
(6, 'Comfort Room', 'Needs Maintenance', 'madumi', '2025-10-29 00:49:24', 2),
(7, 'Warm-up Area', 'Needs Maintenance', 'Designated area for warm-up exercises', '2025-10-29 00:49:49', 2);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_role` varchar(20) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `category` enum('workout','nutrition','trainer','facility','equipment','service','other') DEFAULT 'other',
  `message` text NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `urgent` tinyint(1) DEFAULT 0,
  `status` enum('pending','reviewed','resolved') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--


-- --------------------------------------------------------

--
-- Table structure for table `financial_goals`
--

CREATE TABLE `financial_goals` (
  `id` int(11) NOT NULL,
  `goal_name` varchar(255) NOT NULL,
  `target_type` enum('revenue','profit','members','products') NOT NULL,
  `target_amount` decimal(10,2) NOT NULL,
  `current_amount` decimal(10,2) DEFAULT 0.00,
  `start_date` date NOT NULL,
  `target_date` date NOT NULL,
  `created_by` int(11) NOT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `financial_summary`
-- (See below for the actual view)
--
CREATE TABLE `financial_summary` (
`type` varchar(7)
,`total_amount` decimal(32,2)
,`month` int(3)
,`year` int(5)
);

-- --------------------------------------------------------

--
-- Table structure for table `gcash_payments`
--

CREATE TABLE `gcash_payments` (
  `id` int(11) NOT NULL,
  `renewal_request_id` int(11) NOT NULL,
  `reference_number` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `screenshot_path` varchar(500) DEFAULT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meal_plans`
--

CREATE TABLE `meal_plans` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `meals` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`meals`)),
  `daily_calories` int(11) DEFAULT NULL,
  `protein_goal` decimal(5,2) DEFAULT NULL,
  `carbs_goal` decimal(5,2) DEFAULT NULL,
  `fat_goal` decimal(5,2) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meal_plans`
--


-- --------------------------------------------------------

--
-- Table structure for table `meal_templates`
--

CREATE TABLE `meal_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `meals` longtext NOT NULL,
  `daily_calories` int(11) DEFAULT NULL,
  `protein_goal` decimal(5,2) DEFAULT NULL,
  `carbs_goal` decimal(5,2) DEFAULT NULL,
  `fat_goal` decimal(5,2) DEFAULT NULL,
  `goal` enum('weight_loss','muscle_gain','maintenance','performance') DEFAULT 'maintenance',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meal_templates`
--

INSERT INTO `meal_templates` (`id`, `template_name`, `description`, `meals`, `daily_calories`, `protein_goal`, `carbs_goal`, `fat_goal`, `goal`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'weight loss template', 'follow this', '[{\"name\":\"breakfast\",\"time\":\"9am\",\"calories\":\"2559\",\"description\":\"masarap\"}]', 2000, 150.00, 209.00, 67.00, 'weight_loss', 2, '2025-10-27 11:09:43', '2025-10-27 11:09:43');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `member_type` enum('walk-in','client') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `age` int(11) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `fitness_goals` text DEFAULT NULL,
  `membership_plan` enum('daily','weekly','halfmonth','monthly') NOT NULL,
  `start_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('active','expired','expiring') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT 1,
  `user_id` int(11) DEFAULT NULL,
  `qr_code_path` varchar(500) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ;

--
-- Dumping data for table `members`
--


-- --------------------------------------------------------

--
-- Table structure for table `membership_payments`
--

CREATE TABLE `membership_payments` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `member_name` varchar(100) NOT NULL,
  `plan_type` enum('daily','weekly','halfmonth','monthly') NOT NULL,
  `plan_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date DEFAULT curdate(),
  `payment_method` varchar(50) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `revenue_entry_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_payments`
--


-- --------------------------------------------------------

--
-- Table structure for table `membership_renewal_requests`
--

CREATE TABLE `membership_renewal_requests` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `member_name` varchar(100) NOT NULL,
  `trainer_id` int(11) NOT NULL,
  `plan_type` enum('daily','weekly','halfmonth','monthly') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','gcash') NOT NULL,
  `status` enum('pending','approved','rejected','paid','completed') DEFAULT 'pending',
  `gcash_reference` varchar(100) DEFAULT NULL,
  `gcash_amount` decimal(10,2) DEFAULT NULL,
  `gcash_screenshot` varchar(500) DEFAULT NULL,
  `gcash_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `membership_renewal_requests`
--


-- --------------------------------------------------------

--
-- Stand-in structure for view `member_statistics`
-- (See below for the actual view)
--
CREATE TABLE `member_statistics` (
`status` enum('active','expired','expiring')
,`count` bigint(21)
,`membership_plan` enum('daily','weekly','halfmonth','monthly')
,`avg_days_remaining` decimal(11,4)
);

-- --------------------------------------------------------

--
-- Table structure for table `message_reactions`
--

CREATE TABLE `message_reactions` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reaction` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `role` enum('admin','trainer','client') DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('announcement','membership','message','system') DEFAULT 'system',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `read_status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--


-- --------------------------------------------------------

--
-- Table structure for table `nutrition_sessions`
--

CREATE TABLE `nutrition_sessions` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `meal_plan_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `completed_meals` longtext NOT NULL CHECK (json_valid(`completed_meals`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nutrition_sessions`
--


-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `category` varchar(100) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `stock_quantity`, `category`, `image_url`, `created_at`, `deleted_at`, `created_by`, `updated_by`) VALUES
(11, 'Protein Powder', NULL, 40.00, 0, NULL, NULL, '2025-09-27 12:03:38', NULL, 1, NULL),
(12, 'Egg(per piece)', NULL, 10.00, 15, NULL, NULL, '2025-09-28 16:39:00', NULL, 1, NULL),
(16, 'creatine', NULL, 40.00, 43, NULL, NULL, '2025-10-21 04:32:37', NULL, 1, NULL),
(17, 'pre workout', NULL, 30.00, 41, NULL, NULL, '2025-10-21 07:51:33', NULL, 1, NULL),
(18, 'sting', NULL, 20.00, 20, NULL, NULL, '2025-10-29 03:00:07', NULL, NULL, NULL),
(19, 'test', NULL, 66.00, 75, NULL, NULL, '2025-11-03 16:38:29', NULL, NULL, NULL),
(20, 'Newproduct', NULL, 35.00, 55, NULL, NULL, '2025-11-05 14:55:07', NULL, NULL, NULL);

--
-- Triggers `products`
--
DELIMITER $$
CREATE TRIGGER `check_low_stock` AFTER UPDATE ON `products` FOR EACH ROW BEGIN
    IF NEW.stock_quantity <= 5 AND NEW.stock_quantity > 0 THEN
        INSERT INTO `notifications` (
            `user_id`, `role`, `title`, `message`, `type`, `priority`, `read_status`
        ) VALUES (
            NULL, 'admin', 'Low Stock Alert', 
            CONCAT('Product ''', NEW.name, ''' is running low (only ', NEW.stock_quantity, ' left)'),
            'system', 'medium', 0
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `revenue_categories`
--

CREATE TABLE `revenue_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#3b82f6',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `revenue_categories`
--

INSERT INTO `revenue_categories` (`id`, `name`, `description`, `color`, `is_active`, `created_at`) VALUES
(1, 'Product Sales', 'Revenue from product and supplement sales', '#10b981', 1, '2025-10-24 17:04:52'),
(2, 'Membership Fees', 'Revenue from membership subscriptions', '#3b82f6', 0, '2025-10-24 17:04:52'),
(3, 'Personal Training', 'Revenue from personal training sessions', '#f59e0b', 0, '2025-10-24 17:04:52'),
(4, 'Service (Treadmill)', 'Revenue from treadmill services', '#f59e0b', 1, '2025-10-24 17:04:52'),
(5, 'Services', 'Other services like locker rentals, consultations', '#8b5cf6', 0, '2025-10-24 17:04:52'),
(6, 'Other Income', 'Miscellaneous revenue sources', '#6b7280', 0, '2025-10-24 17:04:52'),
(7, 'Service (Treadmill)', 'Revenue from treadmill services', '#f59e0b', 0, '2025-10-28 16:02:18');

-- --------------------------------------------------------

--
-- Table structure for table `revenue_entries`
--

CREATE TABLE `revenue_entries` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `payment_method` enum('cash','gcash','bank_transfer','card','online') DEFAULT 'cash',
  `reference_id` int(11) DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `reference_name` varchar(255) DEFAULT NULL,
  `revenue_date` date DEFAULT curdate(),
  `recorded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reconciled` tinyint(1) DEFAULT 0,
  `reconciled_at` timestamp NULL DEFAULT NULL,
  `reconciled_by` int(11) DEFAULT NULL,
  `expense_id` int(11) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'PHP'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `revenue_entries`
--


-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `items` text NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `sold_by` int(11) DEFAULT NULL,
  `sold_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `member_id` int(11) DEFAULT NULL,
  `revenue_entry_id` int(11) DEFAULT NULL,
  `created_by_id` int(11) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'PHP'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--


-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trainer_client_assignments`
--

CREATE TABLE `trainer_client_assignments` (
  `id` int(11) NOT NULL,
  `trainer_user_id` int(11) NOT NULL,
  `client_user_id` int(11) NOT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trainer_client_assignments`
--


-- --------------------------------------------------------

--
-- Table structure for table `typing_indicators`
--

CREATE TABLE `typing_indicators` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `is_typing` tinyint(1) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','trainer','client') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `client_type` enum('walk-in','full-time') DEFAULT 'walk-in',
  `verification_token` varchar(64) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `profile_picture` varchar(500) DEFAULT 'https://i.pravatar.cc/120',
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--


-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `push_notifications` tinyint(1) DEFAULT 1,
  `newsletter` tinyint(1) DEFAULT 0,
  `theme` varchar(20) DEFAULT 'dark',
  `language` varchar(20) DEFAULT 'english',
  `timezone` varchar(50) DEFAULT 'UTC',
  `privacy_level` varchar(20) DEFAULT 'public',
  `activity_visibility` varchar(20) DEFAULT 'all',
  `auto_logout` int(11) DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workout_plans`
--

CREATE TABLE `workout_plans` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `exercises` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`exercises`)),
  `schedule` enum('daily','weekly','custom') DEFAULT 'weekly',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workout_plans`
--


-- --------------------------------------------------------

--
-- Table structure for table `workout_sessions`
--

CREATE TABLE `workout_sessions` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `workout_plan_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `completed_exercises` longtext DEFAULT NULL CHECK (json_valid(`completed_exercises`)),
  `notes` text DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `exercise_weights` longtext DEFAULT NULL CHECK (json_valid(`exercise_weights`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workout_sessions`
--


-- --------------------------------------------------------

--
-- Table structure for table `workout_templates`
--

CREATE TABLE `workout_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `exercises` longtext NOT NULL,
  `schedule` enum('daily','weekly','custom') DEFAULT 'weekly',
  `difficulty` enum('beginner','intermediate','advanced') DEFAULT 'beginner',
  `goal` enum('weight_loss','muscle_gain','strength','endurance','general_fitness') DEFAULT 'general_fitness',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workout_templates`
--

INSERT INTO `workout_templates` (`id`, `template_name`, `description`, `exercises`, `schedule`, `difficulty`, `goal`, `created_by`, `created_at`, `updated_at`) VALUES
(4, 'push day', 'first template for kurt', '[{\"name\":\"incline press\",\"sets\":\"3\",\"reps\":\"6\",\"rest\":\"2\",\"notes\":\"heavy weight with proper form\"}]', 'daily', 'beginner', 'weight_loss', 2, '2025-10-27 11:06:35', '2025-10-27 11:06:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcements_updated_by` (`updated_by`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attendance_member_id` (`member_id`),
  ADD KEY `idx_attendance_check_in` (`check_in`),
  ADD KEY `idx_attendance_date` (`check_in`);

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_sender` (`sender_id`,`receiver_id`),
  ADD KEY `idx_chat_receiver` (`receiver_id`,`sender_id`),
  ADD KEY `idx_chat_created` (`created_at`);

--
-- Indexes for table `client_progress`
--
ALTER TABLE `client_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_progress_member_id` (`member_id`),
  ADD KEY `idx_client_progress_date` (`progress_date`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_equipment_created_by` (`created_by`),
  ADD KEY `idx_equipment_status` (`status`),
  ADD KEY `idx_equipment_category` (`category`);

--
-- Indexes for table `equipment_logs`
--
ALTER TABLE `equipment_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_equipment_logs_equipment` (`equipment_id`),
  ADD KEY `fk_equipment_logs_user` (`updated_by`),
  ADD KEY `idx_equipment_logs_date` (`date_updated`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_expenses_date` (`expense_date`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_facilities_updated_by` (`updated_by`),
  ADD KEY `idx_facilities_condition` (`facility_condition`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_feedback_user_id` (`user_id`),
  ADD KEY `idx_feedback_status` (`status`);

--
-- Indexes for table `financial_goals`
--
ALTER TABLE `financial_goals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `gcash_payments`
--
ALTER TABLE `gcash_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `renewal_request_id` (`renewal_request_id`);

--
-- Indexes for table `meal_plans`
--
ALTER TABLE `meal_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_meal_plans_member_id` (`member_id`);

--
-- Indexes for table `meal_templates`
--
ALTER TABLE `meal_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_members_user_id` (`user_id`),
  ADD KEY `idx_members_status` (`status`),
  ADD KEY `idx_members_expiry` (`expiry_date`);

--
-- Indexes for table `membership_payments`
--
ALTER TABLE `membership_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `membership_renewal_requests`
--
ALTER TABLE `membership_renewal_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `trainer_id` (`trainer_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reaction` (`message_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `read_status` (`read_status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `nutrition_sessions`
--
ALTER TABLE `nutrition_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `meal_plan_id` (`meal_plan_id`),
  ADD KEY `session_date` (`session_date`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_products_name` (`name`),
  ADD KEY `fk_products_updated_by` (`updated_by`),
  ADD KEY `idx_products_stock` (`stock_quantity`),
  ADD KEY `idx_products_created_by` (`created_by`);

--
-- Indexes for table `revenue_categories`
--
ALTER TABLE `revenue_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `revenue_entries`
--
ALTER TABLE `revenue_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `fk_revenue_entries_reconciled_by` (`reconciled_by`),
  ADD KEY `fk_revenue_entries_sale_id` (`sale_id`),
  ADD KEY `idx_revenue_date` (`revenue_date`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sales_member_id` (`member_id`),
  ADD KEY `fk_sales_revenue_entry_id` (`revenue_entry_id`),
  ADD KEY `idx_sales_date` (`sale_date`),
  ADD KEY `idx_sales_created_by` (`created_by_id`),
  ADD KEY `idx_sales_sold_by` (`sold_by`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `trainer_client_assignments`
--
ALTER TABLE `trainer_client_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`trainer_user_id`,`client_user_id`),
  ADD KEY `fk_trainer_client_client` (`client_user_id`);

--
-- Indexes for table `typing_indicators`
--
ALTER TABLE `typing_indicators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_typing` (`user_id`,`receiver_id`),
  ADD KEY `idx_typing_receiver` (`receiver_id`),
  ADD KEY `idx_typing_updated` (`last_updated`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `idx_users_email` (`email`),
  ADD KEY `idx_reset_token` (`reset_token`),
  ADD KEY `idx_reset_expiry` (`reset_expiry`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Indexes for table `workout_plans`
--
ALTER TABLE `workout_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_workout_plans_member_id` (`member_id`);

--
-- Indexes for table `workout_sessions`
--
ALTER TABLE `workout_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `workout_sessions_ibfk_1` (`member_id`),
  ADD KEY `workout_sessions_ibfk_2` (`workout_plan_id`);

--
-- Indexes for table `workout_templates`
--
ALTER TABLE `workout_templates`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=182;

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `client_progress`
--
ALTER TABLE `client_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `equipment_logs`
--
ALTER TABLE `equipment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `financial_goals`
--
ALTER TABLE `financial_goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gcash_payments`
--
ALTER TABLE `gcash_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meal_plans`
--
ALTER TABLE `meal_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `meal_templates`
--
ALTER TABLE `meal_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `membership_payments`
--
ALTER TABLE `membership_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `membership_renewal_requests`
--
ALTER TABLE `membership_renewal_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `message_reactions`
--
ALTER TABLE `message_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=244;

--
-- AUTO_INCREMENT for table `nutrition_sessions`
--
ALTER TABLE `nutrition_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `revenue_categories`
--
ALTER TABLE `revenue_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `revenue_entries`
--
ALTER TABLE `revenue_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trainer_client_assignments`
--
ALTER TABLE `trainer_client_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `typing_indicators`
--
ALTER TABLE `typing_indicators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=230;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workout_plans`
--
ALTER TABLE `workout_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `workout_sessions`
--
ALTER TABLE `workout_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `workout_templates`
--
ALTER TABLE `workout_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

-- --------------------------------------------------------

--
-- Structure for view `financial_summary`
--
DROP TABLE IF EXISTS `financial_summary`;

CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `financial_summary`  AS SELECT 'revenue' AS `type`, sum(`revenue_entries`.`amount`) AS `total_amount`, month(`revenue_entries`.`revenue_date`) AS `month`, year(`revenue_entries`.`revenue_date`) AS `year` FROM `revenue_entries` WHERE `revenue_entries`.`revenue_date` >= curdate() - interval 12 month GROUP BY year(`revenue_entries`.`revenue_date`), month(`revenue_entries`.`revenue_date`)union all select 'expense' AS `type`,sum(`expenses`.`amount`) AS `total_amount`,month(`expenses`.`expense_date`) AS `month`,year(`expenses`.`expense_date`) AS `year` from `expenses` where `expenses`.`expense_date` >= curdate() - interval 12 month group by year(`expenses`.`expense_date`),month(`expenses`.`expense_date`)  ;

-- --------------------------------------------------------

--
-- Structure for view `member_statistics`
--
DROP TABLE IF EXISTS `member_statistics`;

CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `member_statistics`  AS SELECT `m`.`status` AS `status`, count(0) AS `count`, `m`.`membership_plan` AS `membership_plan`, avg(to_days(`m`.`expiry_date`) - to_days(curdate())) AS `avg_days_remaining` FROM `members` AS `m` WHERE `m`.`deleted_at` is null GROUP BY `m`.`status`, `m`.`membership_plan` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_member_cascade` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `budgets`
--
ALTER TABLE `budgets`
  ADD CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `revenue_categories` (`id`),
  ADD CONSTRAINT `budgets_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_progress`
--
ALTER TABLE `client_progress`
  ADD CONSTRAINT `fk_client_progress_member_cascade` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `equipment`
--
ALTER TABLE `equipment`
  ADD CONSTRAINT `fk_equipment_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `equipment_logs`
--
ALTER TABLE `equipment_logs`
  ADD CONSTRAINT `fk_equipment_logs_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_equipment_logs_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `facilities`
--
ALTER TABLE `facilities`
  ADD CONSTRAINT `fk_facilities_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `financial_goals`
--
ALTER TABLE `financial_goals`
  ADD CONSTRAINT `financial_goals_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `gcash_payments`
--
ALTER TABLE `gcash_payments`
  ADD CONSTRAINT `gcash_payments_ibfk_1` FOREIGN KEY (`renewal_request_id`) REFERENCES `membership_renewal_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meal_plans`
--
ALTER TABLE `meal_plans`
  ADD CONSTRAINT `meal_plans_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `fk_members_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `membership_payments`
--
ALTER TABLE `membership_payments`
  ADD CONSTRAINT `membership_payments_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `membership_renewal_requests`
--
ALTER TABLE `membership_renewal_requests`
  ADD CONSTRAINT `membership_renewal_requests_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `membership_renewal_requests_ibfk_2` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD CONSTRAINT `message_reactions_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_reactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `nutrition_sessions`
--
ALTER TABLE `nutrition_sessions`
  ADD CONSTRAINT `nutrition_sessions_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `nutrition_sessions_ibfk_2` FOREIGN KEY (`meal_plan_id`) REFERENCES `meal_plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_products_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `revenue_entries`
--
ALTER TABLE `revenue_entries`
  ADD CONSTRAINT `fk_revenue_entries_reconciled_by` FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_revenue_entries_sale_id` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `revenue_entries_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `revenue_categories` (`id`),
  ADD CONSTRAINT `revenue_entries_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_created_by` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_sales_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sales_revenue_entry_id` FOREIGN KEY (`revenue_entry_id`) REFERENCES `revenue_entries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sales_sold_by` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `trainer_client_assignments`
--
ALTER TABLE `trainer_client_assignments`
  ADD CONSTRAINT `fk_trainer_client_client` FOREIGN KEY (`client_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trainer_client_assignments_ibfk_1` FOREIGN KEY (`trainer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trainer_client_assignments_ibfk_2` FOREIGN KEY (`client_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workout_plans`
--
ALTER TABLE `workout_plans`
  ADD CONSTRAINT `workout_plans_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workout_sessions`
--
ALTER TABLE `workout_sessions`
  ADD CONSTRAINT `workout_sessions_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `workout_sessions_ibfk_2` FOREIGN KEY (`workout_plan_id`) REFERENCES `workout_plans` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Re-enable Foreign Key Checks
SET FOREIGN_KEY_CHECKS = 1;

-- Clean default users
INSERT INTO `users` (`id`, `username`, `password`, `role`, `full_name`, `email`, `client_type`, `email_verified`) VALUES
(1, 'admin', '$2y$10$eSaEoyhN7TWu0QK8ayQL4ua/EFzZY3aFxS.YotEZ32uPt6A77Ll7W', 'admin', 'System Administrator', 'admin@gym.local', 'walk-in', 1),
(2, 'trainer', '$2y$10$eSaEoyhN7TWu0QK8ayQL4ua/EFzZY3aFxS.YotEZ32uPt6A77Ll7W', 'trainer', 'Head Trainer', 'trainer@gym.local', 'walk-in', 1),
(3, 'client', '$2y$10$eSaEoyhN7TWu0QK8ayQL4ua/EFzZY3aFxS.YotEZ32uPt6A77Ll7W', 'client', 'John Client', 'client@gym.local', 'full-time', 1);

INSERT INTO `members` (`id`, `user_id`, `full_name`, `contact_number`, `age`, `address`, `membership_plan`, `status`, `start_date`, `expiry_date`, `member_type`) VALUES
(1, 3, 'John Client', '09123456789', 25, 'Sample Address, City', 'monthly', 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'client');
