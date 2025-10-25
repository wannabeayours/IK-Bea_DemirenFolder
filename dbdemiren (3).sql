-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 07, 2025 at 10:30 AM
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
-- Database: `dbdemiren`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_activitylogs`
--

CREATE TABLE `tbl_activitylogs` (
  `activity_id` int(11) NOT NULL,
  `user_type` enum('admin','customer','front_desk','system') NOT NULL COMMENT 'Type of user performing the action',
  `user_id` int(11) DEFAULT NULL COMMENT 'ID of the user (employee_id for admin/front_desk, customers_id for customer)',
  `user_name` varchar(100) DEFAULT NULL COMMENT 'Username or name of the user performing the action',
  `action_type` varchar(50) NOT NULL COMMENT 'Type of action performed (login, create, update, delete, approve, etc.)',
  `action_category` varchar(50) NOT NULL COMMENT 'Category of action (booking, room, user, master_file, etc.)',
  `action_description` text NOT NULL COMMENT 'Detailed description of the action performed',
  `target_table` varchar(50) DEFAULT NULL COMMENT 'Primary table affected by the action',
  `target_id` int(11) DEFAULT NULL COMMENT 'ID of the record affected (booking_id, room_id, etc.)',
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Previous values before update (for update operations)' CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'New values after update (for create/update operations)' CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of the user',
  `user_agent` text DEFAULT NULL COMMENT 'Browser/device information',
  `session_id` varchar(100) DEFAULT NULL COMMENT 'Session identifier',
  `status` enum('success','failed','pending') DEFAULT 'success' COMMENT 'Status of the action',
  `error_message` text DEFAULT NULL COMMENT 'Error message if action failed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp when the action was performed',
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional metadata related to the action' CHECK (json_valid(`additional_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Activity logs for tracking all user actions in the system';

--
-- Dumping data for table `tbl_activitylogs`
--

INSERT INTO `tbl_activitylogs` (`activity_id`, `user_type`, `user_id`, `user_name`, `action_type`, `action_category`, `action_description`, `target_table`, `target_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `status`, `error_message`, `created_at`, `additional_data`) VALUES
(1, 'admin', 1, 'Admin', 'login', 'authentication', 'Admin user logged into the system', 'tbl_employee', 1, NULL, '{\"login_time\": \"2024-01-15 10:30:00\"}', '192.168.1.100', NULL, NULL, 'success', NULL, '2025-09-30 13:19:19', NULL),
(2, 'customer', 1, 'john_doe', 'create', 'booking', 'Customer created a new booking', 'tbl_booking', 1, NULL, '{\"booking_id\": 1, \"check_in\": \"2024-01-20\", \"check_out\": \"2024-01-25\", \"total_amount\": 5000}', '192.168.1.101', NULL, NULL, 'success', NULL, '2025-09-30 13:19:19', NULL),
(3, 'admin', 1, 'Admin', 'update', 'booking', 'Admin approved customer booking', 'tbl_booking', 1, NULL, '{\"status\": \"approved\", \"approved_by\": 1}', '192.168.1.100', NULL, NULL, 'success', NULL, '2025-09-30 13:19:19', NULL),
(4, 'customer', 1, 'john_doe', 'update', 'profile', 'Customer updated profile information', 'tbl_customers', 1, NULL, '{\"phone\": \"+1234567890\", \"email\": \"john.doe@email.com\"}', '192.168.1.101', NULL, NULL, 'success', NULL, '2025-09-30 13:19:19', NULL),
(5, 'admin', 1, 'Admin', 'update', 'room', 'Admin changed room status to Under-Maintenance', 'tbl_rooms', 101, NULL, '{\"status\": \"Under-Maintenance\", \"previous_status\": \"Vacant\"}', '192.168.1.100', NULL, NULL, 'success', NULL, '2025-09-30 13:19:19', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_additional_customer`
--

CREATE TABLE `tbl_additional_customer` (
  `additional_customer_id` int(11) NOT NULL,
  `customers_id` int(11) DEFAULT NULL,
  `additional_customer_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_billing`
--

CREATE TABLE `tbl_billing` (
  `billing_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `booking_charges_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `discounts_id` int(11) DEFAULT NULL,
  `billing_dateandtime` datetime DEFAULT NULL,
  `billing_invoice_number` varchar(100) DEFAULT NULL,
  `billing_downpayment` int(11) DEFAULT NULL,
  `billing_vat` int(11) DEFAULT NULL,
  `billing_total_amount` int(11) DEFAULT NULL,
  `billing_balance` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_billing`
--

INSERT INTO `tbl_billing` (`billing_id`, `booking_id`, `booking_charges_id`, `employee_id`, `payment_method_id`, `discounts_id`, `billing_dateandtime`, `billing_invoice_number`, `billing_downpayment`, `billing_vat`, `billing_total_amount`, `billing_balance`) VALUES
(1, 1, NULL, NULL, 1, NULL, '2025-10-02 20:34:07', NULL, 590, 126, 1180, 590),
(2, 2, NULL, NULL, 1, NULL, '2025-10-04 00:05:36', NULL, 3160, 506, 6320, 6320),
(3, 3, NULL, NULL, 1, NULL, '2025-10-04 05:47:01', NULL, 590, 126, 1180, 380),
(5, 5, NULL, NULL, 1, NULL, '2025-10-04 06:39:58', NULL, 1030, 221, 2060, 560),
(6, 9, NULL, NULL, NULL, NULL, '2025-10-05 23:37:30', NULL, 590, 126, 1180, 1180),
(7, 10, NULL, NULL, 1, NULL, '2025-10-06 11:18:53', NULL, 590, 126, 1180, 1180),
(8, 11, NULL, NULL, 1, NULL, '2025-10-06 22:58:41', NULL, 440, 94, 880, 880),
(9, 12, NULL, NULL, 1, NULL, '2025-10-06 23:30:33', NULL, 1265, 271, 2530, 1065),
(10, 13, NULL, NULL, 1, NULL, '2025-10-07 02:46:23', NULL, 825, 177, 1650, 1650),
(11, 14, NULL, NULL, 1, NULL, '2025-10-07 02:59:49', NULL, 590, 126, 1180, 590),
(12, 15, NULL, NULL, 1, NULL, '2025-10-07 11:05:57', NULL, 440, 94, 880, 880),
(13, 21, NULL, NULL, 1, NULL, '2025-10-07 15:04:53', NULL, 440, 94, 880, 880),
(14, 22, NULL, NULL, 1, NULL, '2025-10-07 16:09:36', NULL, 1315, 282, 2630, 2630);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_booking`
--

CREATE TABLE `tbl_booking` (
  `booking_id` int(11) NOT NULL,
  `customers_id` int(11) DEFAULT NULL,
  `customers_walk_in_id` int(11) DEFAULT NULL,
  `guests_amnt` int(11) NOT NULL,
  `booking_totalAmount` int(11) NOT NULL,
  `booking_downpayment` int(11) DEFAULT NULL,
  `reference_no` varchar(50) NOT NULL,
  `booking_checkin_dateandtime` date DEFAULT NULL,
  `booking_checkout_dateandtime` date DEFAULT NULL,
  `booking_created_at` datetime DEFAULT NULL,
  `booking_isArchive` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_booking`
--

INSERT INTO `tbl_booking` (`booking_id`, `customers_id`, `customers_walk_in_id`, `guests_amnt`, `booking_totalAmount`, `booking_downpayment`, `reference_no`, `booking_checkin_dateandtime`, `booking_checkout_dateandtime`, `booking_created_at`, `booking_isArchive`) VALUES
(1, 1, NULL, 1, 2380, 590, '', '2025-10-01', '2025-10-05', '2025-10-02 20:34:07', 0),
(2, NULL, 1, 0, 6320, 3160, 'REF20251003180536691', '2025-10-18', '2025-10-22', '2025-10-04 00:05:36', 0),
(3, 1, NULL, 1, 1980, 590, '', '2025-10-04', '2025-10-05', '2025-10-04 05:47:01', 0),
(5, 1, NULL, 1, 2060, 1030, '', '2025-10-04', '2025-10-05', '2025-10-03 06:39:58', 0),
(9, NULL, 5, 1, 1180, 590, 'REF20251005173730680', '2025-10-06', '2025-10-07', '2025-10-05 23:37:30', 0),
(10, NULL, 6, 1, 1180, 590, 'REF20251006051853711', '2025-10-07', '2025-10-08', '2025-10-06 11:18:53', 0),
(11, NULL, 7, 1, 880, 440, 'REF20251006165841107', '2025-10-07', '2025-10-08', '2025-10-06 22:58:41', 0),
(12, 1, NULL, 3, 2530, 1265, '', '2025-10-07', '2025-10-08', '2025-10-06 23:30:33', 1),
(13, NULL, 8, 3, 1650, 825, 'REF20251006204623110', '2025-10-05', '2025-10-06', '2025-10-07 02:46:23', 0),
(14, 1, NULL, 1, 1180, 590, '', '2025-10-08', '2025-10-10', '2025-10-07 02:59:49', 0),
(15, NULL, 9, 1, 880, 440, 'REF20251007050557489', '2025-10-08', '2025-10-09', '2025-10-07 11:05:57', 0),
(21, NULL, 15, 1, 880, 440, 'REF20251007090453160', '2025-10-08', '2025-10-09', '2025-10-07 15:04:53', 0),
(22, NULL, 16, 1, 2630, 1315, 'REF20251007100936528', '2025-10-08', '2025-10-09', '2025-10-07 16:09:36', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_booking_charges`
--

CREATE TABLE `tbl_booking_charges` (
  `booking_charges_id` int(11) NOT NULL,
  `charges_master_id` int(11) DEFAULT NULL,
  `booking_room_id` int(11) DEFAULT NULL,
  `booking_charges_price` int(11) DEFAULT NULL,
  `booking_charges_quantity` int(11) DEFAULT NULL,
  `booking_charges_total` int(11) DEFAULT NULL,
  `booking_charge_status` int(11) NOT NULL DEFAULT 1,
  `booking_charge_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_booking_charges`
--

INSERT INTO `tbl_booking_charges` (`booking_charges_id`, `charges_master_id`, `booking_room_id`, `booking_charges_price`, `booking_charges_quantity`, `booking_charges_total`, `booking_charge_status`, `booking_charge_date`) VALUES
(7, 2, 2, 400, 1, 400, 1, '2025-10-05 00:00:00'),
(10, 2, 6, 400, 1, 400, 1, '2025-10-05 00:00:00'),
(11, 2, 7, 400, 1, 400, 1, '2025-10-05 00:00:00'),
(12, 2, 3, 800, 2, NULL, 3, '2025-10-05 00:00:00'),
(13, 1, 3, 0, 1, NULL, 1, '2025-10-05 00:00:00'),
(14, 1, 7, 0, 1, NULL, 1, '2025-10-05 00:00:00'),
(18, 2, 11, 400, 1, 400, 1, '2025-10-05 23:37:30'),
(19, 2, 12, 400, 1, 400, 1, '2025-10-06 11:18:53'),
(20, 2, 13, 400, 1, 400, 1, '2025-10-06 22:58:41'),
(21, 2, 14, 400, 1, 400, 1, '2025-10-06 23:30:33'),
(22, 2, 15, 400, 1, 400, 1, '2025-10-06 23:30:33'),
(23, 2, 16, 400, 1, 400, 1, '2025-10-07 02:46:23'),
(24, 2, 17, 400, 1, 400, 1, '2025-10-07 02:59:49'),
(25, 2, 18, 400, 1, 400, 1, '2025-10-07 11:05:57'),
(26, 1, 15, 0, 1, NULL, 3, '2025-10-07 11:29:33'),
(27, 1, 15, 0, 1, NULL, 1, '2025-10-07 11:30:09'),
(28, 2, 24, 400, 1, 400, 1, '2025-10-07 15:04:53'),
(29, 2, 25, 400, 1, 400, 1, '2025-10-07 16:09:36');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_booking_history`
--

CREATE TABLE `tbl_booking_history` (
  `booking_history_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_booking_history`
--

INSERT INTO `tbl_booking_history` (`booking_history_id`, `booking_id`, `employee_id`, `status_id`, `updated_at`) VALUES
(1, 2, NULL, 1, '2025-10-04 00:05:36'),
(2, 3, NULL, 3, '2025-10-04 08:25:55'),
(6, 9, NULL, 1, '2025-10-05 23:37:30'),
(7, 10, NULL, 1, '2025-10-06 11:18:53'),
(8, 11, NULL, 1, '2025-10-06 22:58:41'),
(9, 12, NULL, 3, '2025-10-06 23:34:48'),
(10, 13, NULL, 5, '2025-10-07 02:46:23'),
(11, 15, NULL, 1, '2025-10-07 11:05:57'),
(12, 21, NULL, 1, '2025-10-07 15:04:53'),
(13, 21, 1, 5, '2025-10-07 15:30:11'),
(14, 15, 1, 5, '2025-10-07 15:30:40'),
(15, 10, 1, 5, '2025-10-07 15:33:54'),
(16, 21, 1, 5, '2025-10-07 16:06:19'),
(17, 22, NULL, 1, '2025-10-07 16:09:36'),
(18, 22, 1, 5, '2025-10-07 16:17:33');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_booking_room`
--

CREATE TABLE `tbl_booking_room` (
  `booking_room_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `roomtype_id` int(11) DEFAULT NULL,
  `roomnumber_id` int(11) DEFAULT NULL,
  `bookingRoom_adult` int(11) NOT NULL,
  `bookingRoom_children` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_booking_room`
--

INSERT INTO `tbl_booking_room` (`booking_room_id`, `booking_id`, `roomtype_id`, `roomnumber_id`, `bookingRoom_adult`, `bookingRoom_children`) VALUES
(1, 1, 1, 3, 1, 0),
(2, 2, 1, 3, 1, 0),
(3, 3, 1, 18, 1, 0),
(6, 5, 2, 8, 1, 0),
(7, 5, 3, 2, 1, 0),
(11, 9, 3, 2, 1, 0),
(12, 10, 3, 2, 1, 0),
(13, 11, 2, 8, 1, 0),
(14, 12, 4, 4, 3, 0),
(15, 12, 2, 16, 1, 0),
(16, 13, 4, 4, 2, 1),
(17, 14, 1, 3, 1, 0),
(18, 15, 2, 8, 1, 0),
(24, 21, 2, 16, 1, 0),
(25, 22, 6, 6, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_booking_status`
--

CREATE TABLE `tbl_booking_status` (
  `booking_status_id` int(11) NOT NULL,
  `booking_status_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_booking_status`
--

INSERT INTO `tbl_booking_status` (`booking_status_id`, `booking_status_name`) VALUES
(1, 'Pending'),
(2, 'Approved'),
(3, 'Cancelled'),
(4, 'Checked-Out'),
(5, 'Checked-In');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_charges_category`
--

CREATE TABLE `tbl_charges_category` (
  `charges_category_id` int(11) NOT NULL,
  `charges_category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_charges_category`
--

INSERT INTO `tbl_charges_category` (`charges_category_id`, `charges_category_name`) VALUES
(1, 'Room Service'),
(2, 'Laundry'),
(3, 'Food & Beverage'),
(4, 'Additional Services');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_charges_master`
--

CREATE TABLE `tbl_charges_master` (
  `charges_master_id` int(11) NOT NULL,
  `charges_category_id` int(11) DEFAULT NULL,
  `charges_master_name` varchar(100) NOT NULL,
  `charges_master_price` int(11) NOT NULL,
  `charges_master_description` text DEFAULT NULL,
  `charges_master_status_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_charges_master`
--

INSERT INTO `tbl_charges_master` (`charges_master_id`, `charges_category_id`, `charges_master_name`, `charges_master_price`, `charges_master_description`, `charges_master_status_id`) VALUES
(1, 1, 'Towels', 0, 'ok', 1),
(2, 1, 'Bed', 400, 'apoy nang tagumpay', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_charges_status`
--

CREATE TABLE `tbl_charges_status` (
  `charges_status_id` int(11) NOT NULL,
  `charges_status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_charges_status`
--

INSERT INTO `tbl_charges_status` (`charges_status_id`, `charges_status_name`) VALUES
(1, 'Pending'),
(2, 'Delivered'),
(3, 'Cancelled');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_check_payment`
--

CREATE TABLE `tbl_check_payment` (
  `check_payment_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `check_payment_bank` varchar(100) DEFAULT NULL,
  `check_payment_number` varchar(100) DEFAULT NULL,
  `check_payment_date` date DEFAULT NULL,
  `check_payment_amount` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customers`
--

CREATE TABLE `tbl_customers` (
  `customers_id` int(11) NOT NULL,
  `customers_online_id` int(11) DEFAULT NULL,
  `customers_fname` varchar(100) NOT NULL,
  `customers_lname` varchar(100) NOT NULL,
  `customers_phone` varchar(20) NOT NULL,
  `customers_email` varchar(100) NOT NULL,
  `customers_address` text DEFAULT NULL,
  `customers_birthdate` date DEFAULT NULL,
  `customers_gender` varchar(10) DEFAULT NULL,
  `identification_id` int(11) DEFAULT NULL,
  `nationality_id` int(11) DEFAULT NULL,
  `customers_created_at` datetime DEFAULT NULL,
  `customers_updated_at` datetime DEFAULT NULL,
  `customers_status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_customers`
--

INSERT INTO `tbl_customers` (`customers_id`, `customers_online_id`, `customers_fname`, `customers_lname`, `customers_phone`, `customers_email`, `customers_address`, `customers_birthdate`, `customers_gender`, `identification_id`, `nationality_id`, `customers_created_at`, `customers_updated_at`, `customers_status`) VALUES
(1, 1, 'hello', 'luh', '09875647586', 'hello@gmail.com', 'cdo', '2025-04-07', NULL, NULL, 1, NULL, NULL, NULL),
(2, 2, 'Ivan Ky', 'Versoza', '09672959215', 'ikversoza@gmail.com', NULL, '2003-01-17', NULL, NULL, 1, '2025-10-07 11:55:43', NULL, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customersreviews`
--

CREATE TABLE `tbl_customersreviews` (
  `customersreviews_id` int(11) NOT NULL,
  `customers_id` int(11) DEFAULT NULL,
  `customersreviews_rating` int(11) DEFAULT NULL,
  `customersreviews_comment` text DEFAULT NULL,
  `customersreviews_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customers_online`
--

CREATE TABLE `tbl_customers_online` (
  `customers_online_id` int(11) NOT NULL,
  `customers_online_username` varchar(100) NOT NULL,
  `customers_online_password` varchar(255) NOT NULL,
  `customers_online_email` varchar(100) NOT NULL,
  `customers_online_phone` varchar(20) NOT NULL,
  `customers_online_created_at` datetime DEFAULT NULL,
  `customers_online_updated_at` datetime DEFAULT NULL,
  `customers_online_status` varchar(20) DEFAULT NULL,
  `customers_online_profile_image` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_customers_online`
--

INSERT INTO `tbl_customers_online` (`customers_online_id`, `customers_online_username`, `customers_online_password`, `customers_online_email`, `customers_online_phone`, `customers_online_created_at`, `customers_online_updated_at`, `customers_online_status`, `customers_online_profile_image`) VALUES
(1, 'hello', 'Hello123', 'hello@gmail.com', '091124234', '2025-09-30 00:59:21', NULL, NULL, ''),
(2, 'WarLordWolf2', '$2y$10$5bCa3heMpVE0QteOd7Y8f.kt3FfHEAi52PHT2Rhfdb/CjvCDImEGm', 'ikversoza@gmail.com', '09672959215', '2025-10-07 11:55:43', NULL, 'pending', '');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customers_walk_in`
--

CREATE TABLE `tbl_customers_walk_in` (
  `customers_walk_in_id` int(11) NOT NULL,
  `customers_id` int(11) DEFAULT NULL,
  `customers_walk_in_fname` varchar(100) NOT NULL,
  `customers_walk_in_lname` varchar(100) NOT NULL,
  `customers_walk_in_phone` varchar(20) NOT NULL,
  `customers_walk_in_email` varchar(100) NOT NULL,
  `customers_walk_in_address` text DEFAULT NULL,
  `customers_walk_in_birthdate` date DEFAULT NULL,
  `customers_walk_in_gender` varchar(10) DEFAULT NULL,
  `customers_walk_in_created_at` datetime DEFAULT NULL,
  `customers_walk_in_updated_at` datetime DEFAULT NULL,
  `customers_walk_in_status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_customers_walk_in`
--

INSERT INTO `tbl_customers_walk_in` (`customers_walk_in_id`, `customers_id`, `customers_walk_in_fname`, `customers_walk_in_lname`, `customers_walk_in_phone`, `customers_walk_in_email`, `customers_walk_in_address`, `customers_walk_in_birthdate`, `customers_walk_in_gender`, `customers_walk_in_created_at`, `customers_walk_in_updated_at`, `customers_walk_in_status`) VALUES
(1, NULL, 'mel', 'Macario', '09676887868', 'beyasabellach@gmail.com', NULL, NULL, NULL, '2025-10-04 00:05:36', NULL, 'Active'),
(5, NULL, 'Mel', 'Macario', '09676887868', 'beyasabellach@gmail.com', NULL, NULL, NULL, '2025-10-05 23:37:30', NULL, 'Active'),
(6, NULL, 'Bea', 'Lachica', '09676887868', 'beyasabellach@gmail.com', NULL, NULL, NULL, '2025-10-06 11:18:53', NULL, 'Active'),
(7, NULL, 'Mel', 'Macario', '09676887868', 'mel@gmail.com', NULL, NULL, NULL, '2025-10-06 22:58:41', NULL, 'Active'),
(8, NULL, 'Mel', 'Macario', '09676887868', 'beyasabellach@gmail.com', NULL, NULL, NULL, '2025-10-07 02:46:23', NULL, 'Active'),
(9, NULL, 'Mel', 'Macario', '09676887868', 'mel@gmail.com', NULL, NULL, NULL, '2025-10-07 11:05:57', NULL, 'Active'),
(15, NULL, 'Ivan Ky', 'Versoza', '0956 727 4632', 'ikversoza@gmail.com', NULL, NULL, NULL, '2025-10-07 15:04:53', NULL, 'Active'),
(16, NULL, 'Ivan Ky', 'Versoza', '0982 373 4748', 'ikversoza@gmail.com', NULL, NULL, NULL, '2025-10-07 16:09:36', NULL, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_customer_identification`
--

CREATE TABLE `tbl_customer_identification` (
  `identification_id` int(11) NOT NULL,
  `identification_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_customer_identification`
--

INSERT INTO `tbl_customer_identification` (`identification_id`, `identification_name`) VALUES
(1, 'Driver\'s License'),
(2, 'Passport'),
(3, 'National ID'),
(4, 'Student ID'),
(5, 'Company ID'),
(6, 'SSS ID'),
(7, 'PhilHealth ID'),
(8, 'TIN ID'),
(9, 'Postal ID'),
(10, 'Voter\'s ID'),
(11, 'Senior Citizen ID');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_discounts`
--

CREATE TABLE `tbl_discounts` (
  `discounts_id` int(11) NOT NULL,
  `discounts_name` varchar(100) NOT NULL,
  `discounts_percentage` decimal(5,2) DEFAULT NULL,
  `discounts_amount` int(11) DEFAULT NULL,
  `discounts_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_employee`
--

CREATE TABLE `tbl_employee` (
  `employee_id` int(11) NOT NULL,
  `employee_user_level_id` int(11) DEFAULT NULL,
  `employee_fname` varchar(100) NOT NULL,
  `employee_lname` varchar(100) NOT NULL,
  `employee_username` varchar(100) NOT NULL,
  `employee_phone` varchar(20) NOT NULL,
  `employee_email` varchar(100) NOT NULL,
  `employee_password` varchar(255) NOT NULL,
  `employee_address` text DEFAULT NULL,
  `employee_birthdate` date DEFAULT NULL,
  `employee_gender` varchar(10) DEFAULT NULL,
  `employee_created_at` datetime DEFAULT NULL,
  `employee_updated_at` datetime DEFAULT NULL,
  `employee_status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_employee`
--

INSERT INTO `tbl_employee` (`employee_id`, `employee_user_level_id`, `employee_fname`, `employee_lname`, `employee_username`, `employee_phone`, `employee_email`, `employee_password`, `employee_address`, `employee_birthdate`, `employee_gender`, `employee_created_at`, `employee_updated_at`, `employee_status`) VALUES
(1, 1, 'Admin', 'User', 'Admin_1', '09000000000', 'admin@demiren.com', 'Dr@g()nBlOod0317', 'Hotel Address', '1990-01-01', 'Male', '2025-01-01 00:00:00', '2025-10-07 16:10:32', 'Active'),
(2, 2, 'Front Desk', 'Staff', 'frontdesk', '09000000001', 'frontdesk@demiren.com', 'staff123', 'Hotel Address', '1990-01-01', 'Female', '2025-01-01 00:00:00', NULL, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_imagesroommaster`
--

CREATE TABLE `tbl_imagesroommaster` (
  `imagesroommaster_id` int(11) NOT NULL,
  `imagesroommaster_filename` varchar(255) DEFAULT NULL,
  `roomtype_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_imagesroommaster`
--

INSERT INTO `tbl_imagesroommaster` (`imagesroommaster_id`, `imagesroommaster_filename`, `roomtype_id`) VALUES
(1, 'standard1.jpg', 1),
(2, 'standard2.jpg', 1),
(3, 'standard3.jpg', 1),
(4, 'single1.jpg', 2),
(5, 'single2.jpg', 2),
(6, 'double1.jpg', 3),
(7, 'double2.jpg', 3),
(8, 'triple1.jpg', 4),
(9, 'triple2.jpg', 4),
(10, 'familya1.jpg', 6),
(11, 'familya2.jpg', 6),
(12, 'familyb1.jpg', 7),
(13, 'familyb2.jpg', 7),
(14, 'familyc1.jpg', 8),
(15, 'familyc2.jpg', 8);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_invoice`
--

CREATE TABLE `tbl_invoice` (
  `invoice_id` int(11) NOT NULL,
  `billing_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `invoice_time` time DEFAULT NULL,
  `invoice_total_amount` int(11) DEFAULT NULL,
  `invoice_status_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_invoice_status`
--

CREATE TABLE `tbl_invoice_status` (
  `invoice_status_id` int(11) NOT NULL,
  `invoice_status` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_invoice_status`
--

INSERT INTO `tbl_invoice_status` (`invoice_status_id`, `invoice_status`) VALUES
(1, 'Complete'),
(2, 'Incomplete');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_lost_found`
--

CREATE TABLE `tbl_lost_found` (
  `lost_found_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `lost_found_item_name` varchar(100) DEFAULT NULL,
  `lost_found_found_date` date DEFAULT NULL,
  `lost_found_status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_nationality`
--

CREATE TABLE `tbl_nationality` (
  `nationality_id` int(11) NOT NULL,
  `nationality_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_nationality`
--

INSERT INTO `tbl_nationality` (`nationality_id`, `nationality_name`) VALUES
(1, 'Filipino'),
(2, 'American'),
(3, 'Japanese'),
(4, 'Korean'),
(5, 'Chinese');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_online_payment`
--

CREATE TABLE `tbl_online_payment` (
  `online_payment_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `amount` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_payment_method`
--

CREATE TABLE `tbl_payment_method` (
  `payment_method_id` int(11) NOT NULL,
  `payment_method_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_payment_method`
--

INSERT INTO `tbl_payment_method` (`payment_method_id`, `payment_method_name`) VALUES
(1, 'GCash'),
(2, 'Paypal');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_rooms`
--

CREATE TABLE `tbl_rooms` (
  `roomnumber_id` int(11) NOT NULL,
  `roomtype_id` int(11) NOT NULL,
  `roomfloor` int(11) DEFAULT NULL,
  `room_status_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_rooms`
--

INSERT INTO `tbl_rooms` (`roomnumber_id`, `roomtype_id`, `roomfloor`, `room_status_id`) VALUES
(1, 2, 1, 4),
(2, 3, 1, 3),
(3, 1, 1, 3),
(4, 4, 1, 3),
(5, 5, 2, 3),
(6, 6, 2, 3),
(7, 7, 2, 3),
(8, 2, 2, 3),
(9, 3, 3, 3),
(10, 1, 3, 1),
(11, 4, 3, 3),
(12, 8, 3, 3),
(13, 5, 4, 3),
(14, 6, 4, 3),
(15, 7, 4, 3),
(16, 2, 4, 3),
(17, 3, 5, 3),
(18, 1, 5, 3),
(19, 4, 5, 3),
(20, 8, 5, 3);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_roomtype`
--

CREATE TABLE `tbl_roomtype` (
  `roomtype_id` int(11) NOT NULL,
  `roomtype_name` varchar(50) NOT NULL,
  `max_capacity` int(11) NOT NULL,
  `roomtype_description` text DEFAULT NULL,
  `roomtype_price` int(11) DEFAULT NULL,
  `roomtype_beds` int(11) NOT NULL,
  `roomtype_capacity` int(11) NOT NULL,
  `roomtype_sizes` varchar(50) NOT NULL,
  `roomtype_image` varchar(100) NOT NULL DEFAULT 'standardmain.jpg',
  `roomtype_maxbeds` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_roomtype`
--

INSERT INTO `tbl_roomtype` (`roomtype_id`, `roomtype_name`, `max_capacity`, `roomtype_description`, `roomtype_price`, `roomtype_beds`, `roomtype_capacity`, `roomtype_sizes`, `roomtype_image`, `roomtype_maxbeds`) VALUES
(1, 'Standard Twin Room', 3, 'Featuring free toiletries, this twin room includes a private bathroom with a shower, a bidet and a hairdryer. The air-conditioned twin room features a flat-screen TV, a safe deposit box, an electric kettle, a tiled floor as well as a quiet street view. The unit has 2 beds.', 1180, 2, 2, '21 m²', 'standardmain.jpg', 1),
(2, 'Single Room', 2, 'Providing free toiletries, this single room includes a private bathroom with a shower, a bidet and a hairdryer. The air-conditioned single room provides a flat-screen TV, a safe deposit box, an electric kettle, a tiled floor as well as a quiet street view. The unit offers 1 bed.', 880, 1, 1, '17 m²', 'singlemain.jpg', 1),
(3, 'Double Room', 3, 'Providing free toiletries, this double room includes a private bathroom with a shower, a bidet and a hairdryer. The air-conditioned double room provides a flat-screen TV, a safe deposit box, an electric kettle, a tiled floor as well as a quiet street view. The unit offers 1 bed.', 1180, 1, 2, '21 m²', 'doublemain.jpg', 1),
(4, 'Triple Room', 4, 'Featuring free toiletries, this triple room includes a private bathroom with a shower, a bidet and a hairdryer. The air-conditioned triple room features a flat-screen TV, a safe deposit box, an electric kettle, a tiled floor as well as a quiet street view. The unit has 3 beds.', 1650, 3, 3, '28 m²', 'triplemain.jpg', 1),
(5, 'Quadruple Room', 5, 'Providing free toiletries, this quadruple room includes a private bathroom with a shower, a bidet and a hairdryer. This quadruple room is air-conditioned and features a flat-screen TV, a safe deposit box, an electric kettle and a tiled floor. The unit offers 4 beds.', 2100, 4, 4, '30 m²', 'quadruplemain.jpg', 1),
(6, 'Family Room A', 6, 'Featuring free toiletries, this family room includes a private bathroom with a shower, a bidet and a hairdryer. The air-conditioned family room features a flat-screen TV, a safe deposit box, an electric kettle and a tiled floor. The unit has 5 beds.', 2630, 5, 5, '30 m²', 'familyamain.jpg', 1),
(7, 'Family Room B', 7, 'Providing free toiletries, this family room includes a private bathroom with a shower, a bidet and a hairdryer. The air-conditioned family room provides a flat-screen TV, a safe deposit box, an electric kettle and a tiled floor. The unit offers 6 beds.', 3130, 6, 6, '30 m²', 'familybmain.jpg', 1),
(8, 'Family Room C', 13, 'Featuring free toiletries, this family room includes a private bathroom with a shower, a bidet and a hairdryer. The family room features air conditioning, a safe deposit box, an electric kettle, a tiled floor, as well as a flat-screen TV. The unit has 12 beds.', 7300, 12, 12, '30 m²', 'familycmain.jpg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_room_amenities`
--

CREATE TABLE `tbl_room_amenities` (
  `amenities_id` int(11) NOT NULL,
  `roomnumber_id` int(11) NOT NULL,
  `room_amenities_master_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_room_amenities_master`
--

CREATE TABLE `tbl_room_amenities_master` (
  `room_amenities_master_id` int(11) NOT NULL,
  `room_amenities_master_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_room_amenities_master`
--

INSERT INTO `tbl_room_amenities_master` (`room_amenities_master_id`, `room_amenities_master_name`) VALUES
(1, 'Laundry Service'),
(2, 'Daily Housekeeping'),
(3, 'Complimentary Bottled Water'),
(4, 'Flat-Screen TV with Cable Channels'),
(5, 'Electric Kettle with Coffee & Tea Set');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_status_types`
--

CREATE TABLE `tbl_status_types` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_status_types`
--

INSERT INTO `tbl_status_types` (`status_id`, `status_name`) VALUES
(1, 'Occupied'),
(2, 'Pending'),
(3, 'Vacant'),
(4, 'Under-Maintenance'),
(5, 'Dirty');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user_level`
--

CREATE TABLE `tbl_user_level` (
  `userlevel_id` int(11) NOT NULL,
  `userlevel_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_user_level`
--

INSERT INTO `tbl_user_level` (`userlevel_id`, `userlevel_name`) VALUES
(1, 'Admin'),
(2, 'Employee');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_visitorapproval`
--

CREATE TABLE `tbl_visitorapproval` (
  `visitorapproval_id` int(11) NOT NULL,
  `visitorapproval_status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_visitorlogs`
--

CREATE TABLE `tbl_visitorlogs` (
  `visitorlogs_id` int(11) NOT NULL,
  `visitorapproval_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `visitorlogs_visitorname` varchar(100) DEFAULT NULL,
  `visitorlogs_purpose` varchar(255) DEFAULT NULL,
  `visitorlogs_checkin_time` datetime DEFAULT NULL,
  `visitorlogs_checkout_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_activitylogs`
--
ALTER TABLE `tbl_activitylogs`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `idx_user_type_id` (`user_type`,`user_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_action_category` (`action_category`),
  ADD KEY `idx_target_table_id` (`target_table`,`target_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user_action_date` (`user_type`,`user_id`,`created_at`),
  ADD KEY `idx_action_target` (`action_type`,`target_table`,`target_id`),
  ADD KEY `idx_date_range` (`created_at`,`user_type`);

--
-- Indexes for table `tbl_additional_customer`
--
ALTER TABLE `tbl_additional_customer`
  ADD PRIMARY KEY (`additional_customer_id`),
  ADD KEY `tbl_additional_customer_ibfk_1` (`customers_id`);

--
-- Indexes for table `tbl_billing`
--
ALTER TABLE `tbl_billing`
  ADD PRIMARY KEY (`billing_id`),
  ADD KEY `tbl_billing_ibfk_1` (`booking_id`),
  ADD KEY `tbl_billing_ibfk_2` (`booking_charges_id`),
  ADD KEY `tbl_billing_ibfk_3` (`employee_id`),
  ADD KEY `tbl_billing_ibfk_4` (`payment_method_id`),
  ADD KEY `tbl_billing_ibfk_5` (`discounts_id`);

--
-- Indexes for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `tbl_booking_ibfk_1` (`customers_id`),
  ADD KEY `tbl_booking_ibfk_4` (`customers_walk_in_id`);

--
-- Indexes for table `tbl_booking_charges`
--
ALTER TABLE `tbl_booking_charges`
  ADD PRIMARY KEY (`booking_charges_id`),
  ADD KEY `tbl_booking_charges_ibfk_1` (`charges_master_id`),
  ADD KEY `tbl_booking_charges_ibfk_2` (`booking_room_id`),
  ADD KEY `charge_status` (`booking_charge_status`);

--
-- Indexes for table `tbl_booking_history`
--
ALTER TABLE `tbl_booking_history`
  ADD PRIMARY KEY (`booking_history_id`),
  ADD KEY `tbl_booking_history_ibfk_1` (`employee_id`),
  ADD KEY `tbl_booking_history_ibfk_2` (`status_id`),
  ADD KEY `tbl_booking_history_ibfk_3` (`booking_id`);

--
-- Indexes for table `tbl_booking_room`
--
ALTER TABLE `tbl_booking_room`
  ADD PRIMARY KEY (`booking_room_id`),
  ADD KEY `tbl_booking_room_ibfk_1` (`booking_id`),
  ADD KEY `tbl_booking_room_ibfk_2` (`roomtype_id`),
  ADD KEY `tbl_booking_room_ibfk_3` (`roomnumber_id`);

--
-- Indexes for table `tbl_booking_status`
--
ALTER TABLE `tbl_booking_status`
  ADD PRIMARY KEY (`booking_status_id`);

--
-- Indexes for table `tbl_charges_category`
--
ALTER TABLE `tbl_charges_category`
  ADD PRIMARY KEY (`charges_category_id`);

--
-- Indexes for table `tbl_charges_master`
--
ALTER TABLE `tbl_charges_master`
  ADD PRIMARY KEY (`charges_master_id`),
  ADD KEY `tbl_charges_master_ibfk_1` (`charges_category_id`);

--
-- Indexes for table `tbl_charges_status`
--
ALTER TABLE `tbl_charges_status`
  ADD PRIMARY KEY (`charges_status_id`);

--
-- Indexes for table `tbl_check_payment`
--
ALTER TABLE `tbl_check_payment`
  ADD PRIMARY KEY (`check_payment_id`),
  ADD KEY `tbl_check_payment_ibfk_1` (`invoice_id`);

--
-- Indexes for table `tbl_customers`
--
ALTER TABLE `tbl_customers`
  ADD PRIMARY KEY (`customers_id`),
  ADD KEY `tbl_customers_ibfk_2` (`identification_id`),
  ADD KEY `tbl_customers_ibfk_3` (`customers_online_id`),
  ADD KEY `tbl_customers_ibfk_4` (`nationality_id`);

--
-- Indexes for table `tbl_customersreviews`
--
ALTER TABLE `tbl_customersreviews`
  ADD PRIMARY KEY (`customersreviews_id`),
  ADD KEY `tbl_customersreviews_ibfk_1` (`customers_id`);

--
-- Indexes for table `tbl_customers_online`
--
ALTER TABLE `tbl_customers_online`
  ADD PRIMARY KEY (`customers_online_id`);

--
-- Indexes for table `tbl_customers_walk_in`
--
ALTER TABLE `tbl_customers_walk_in`
  ADD PRIMARY KEY (`customers_walk_in_id`),
  ADD KEY `tbl_customers_walk_in_ibfk_1` (`customers_id`);

--
-- Indexes for table `tbl_customer_identification`
--
ALTER TABLE `tbl_customer_identification`
  ADD PRIMARY KEY (`identification_id`);

--
-- Indexes for table `tbl_discounts`
--
ALTER TABLE `tbl_discounts`
  ADD PRIMARY KEY (`discounts_id`);

--
-- Indexes for table `tbl_employee`
--
ALTER TABLE `tbl_employee`
  ADD PRIMARY KEY (`employee_id`),
  ADD KEY `tbl_employee_ibfk_1` (`employee_user_level_id`);

--
-- Indexes for table `tbl_imagesroommaster`
--
ALTER TABLE `tbl_imagesroommaster`
  ADD PRIMARY KEY (`imagesroommaster_id`),
  ADD KEY `tbl_imagesroommaster_ibfk_1` (`roomtype_id`);

--
-- Indexes for table `tbl_invoice`
--
ALTER TABLE `tbl_invoice`
  ADD PRIMARY KEY (`invoice_id`),
  ADD KEY `tbl_invoice_ibfk_1` (`billing_id`),
  ADD KEY `tbl_invoice_ibfk_2` (`employee_id`),
  ADD KEY `tbl_invoice_ibfk_3` (`payment_method_id`),
  ADD KEY `tbl_invoice_ibfk_4` (`invoice_status_id`);

--
-- Indexes for table `tbl_invoice_status`
--
ALTER TABLE `tbl_invoice_status`
  ADD PRIMARY KEY (`invoice_status_id`);

--
-- Indexes for table `tbl_lost_found`
--
ALTER TABLE `tbl_lost_found`
  ADD PRIMARY KEY (`lost_found_id`),
  ADD KEY `tbl_lost_found_ibfk_1` (`booking_id`);

--
-- Indexes for table `tbl_nationality`
--
ALTER TABLE `tbl_nationality`
  ADD PRIMARY KEY (`nationality_id`);

--
-- Indexes for table `tbl_online_payment`
--
ALTER TABLE `tbl_online_payment`
  ADD PRIMARY KEY (`online_payment_id`),
  ADD KEY `tbl_online_payment_ibfk_1` (`invoice_id`);

--
-- Indexes for table `tbl_payment_method`
--
ALTER TABLE `tbl_payment_method`
  ADD PRIMARY KEY (`payment_method_id`);

--
-- Indexes for table `tbl_rooms`
--
ALTER TABLE `tbl_rooms`
  ADD PRIMARY KEY (`roomnumber_id`),
  ADD KEY `tbl_rooms_ibfk_2` (`roomtype_id`),
  ADD KEY `tbl_rooms_ibfk_4` (`room_status_id`);

--
-- Indexes for table `tbl_roomtype`
--
ALTER TABLE `tbl_roomtype`
  ADD PRIMARY KEY (`roomtype_id`);

--
-- Indexes for table `tbl_room_amenities`
--
ALTER TABLE `tbl_room_amenities`
  ADD PRIMARY KEY (`amenities_id`),
  ADD KEY `tbl_room_amenities_ibfk_2` (`room_amenities_master_id`),
  ADD KEY `tbl_room_amenities_ibfk_3` (`roomnumber_id`);

--
-- Indexes for table `tbl_room_amenities_master`
--
ALTER TABLE `tbl_room_amenities_master`
  ADD PRIMARY KEY (`room_amenities_master_id`);

--
-- Indexes for table `tbl_status_types`
--
ALTER TABLE `tbl_status_types`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `tbl_user_level`
--
ALTER TABLE `tbl_user_level`
  ADD PRIMARY KEY (`userlevel_id`);

--
-- Indexes for table `tbl_visitorapproval`
--
ALTER TABLE `tbl_visitorapproval`
  ADD PRIMARY KEY (`visitorapproval_id`);

--
-- Indexes for table `tbl_visitorlogs`
--
ALTER TABLE `tbl_visitorlogs`
  ADD PRIMARY KEY (`visitorlogs_id`),
  ADD KEY `tbl_visitorlogs_ibfk_1` (`visitorapproval_id`),
  ADD KEY `tbl_visitorlogs_ibfk_2` (`booking_id`),
  ADD KEY `tbl_visitorlogs_ibfk_3` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_activitylogs`
--
ALTER TABLE `tbl_activitylogs`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_additional_customer`
--
ALTER TABLE `tbl_additional_customer`
  MODIFY `additional_customer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_billing`
--
ALTER TABLE `tbl_billing`
  MODIFY `billing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `tbl_booking_charges`
--
ALTER TABLE `tbl_booking_charges`
  MODIFY `booking_charges_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `tbl_booking_history`
--
ALTER TABLE `tbl_booking_history`
  MODIFY `booking_history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `tbl_booking_room`
--
ALTER TABLE `tbl_booking_room`
  MODIFY `booking_room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `tbl_booking_status`
--
ALTER TABLE `tbl_booking_status`
  MODIFY `booking_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_charges_category`
--
ALTER TABLE `tbl_charges_category`
  MODIFY `charges_category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_charges_master`
--
ALTER TABLE `tbl_charges_master`
  MODIFY `charges_master_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_charges_status`
--
ALTER TABLE `tbl_charges_status`
  MODIFY `charges_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_check_payment`
--
ALTER TABLE `tbl_check_payment`
  MODIFY `check_payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_customers`
--
ALTER TABLE `tbl_customers`
  MODIFY `customers_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_customersreviews`
--
ALTER TABLE `tbl_customersreviews`
  MODIFY `customersreviews_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_customers_online`
--
ALTER TABLE `tbl_customers_online`
  MODIFY `customers_online_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_customers_walk_in`
--
ALTER TABLE `tbl_customers_walk_in`
  MODIFY `customers_walk_in_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `tbl_customer_identification`
--
ALTER TABLE `tbl_customer_identification`
  MODIFY `identification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tbl_discounts`
--
ALTER TABLE `tbl_discounts`
  MODIFY `discounts_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_employee`
--
ALTER TABLE `tbl_employee`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_imagesroommaster`
--
ALTER TABLE `tbl_imagesroommaster`
  MODIFY `imagesroommaster_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tbl_invoice`
--
ALTER TABLE `tbl_invoice`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_invoice_status`
--
ALTER TABLE `tbl_invoice_status`
  MODIFY `invoice_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_lost_found`
--
ALTER TABLE `tbl_lost_found`
  MODIFY `lost_found_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_nationality`
--
ALTER TABLE `tbl_nationality`
  MODIFY `nationality_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_online_payment`
--
ALTER TABLE `tbl_online_payment`
  MODIFY `online_payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_payment_method`
--
ALTER TABLE `tbl_payment_method`
  MODIFY `payment_method_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_rooms`
--
ALTER TABLE `tbl_rooms`
  MODIFY `roomnumber_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `tbl_roomtype`
--
ALTER TABLE `tbl_roomtype`
  MODIFY `roomtype_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tbl_room_amenities`
--
ALTER TABLE `tbl_room_amenities`
  MODIFY `amenities_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_room_amenities_master`
--
ALTER TABLE `tbl_room_amenities_master`
  MODIFY `room_amenities_master_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_status_types`
--
ALTER TABLE `tbl_status_types`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_user_level`
--
ALTER TABLE `tbl_user_level`
  MODIFY `userlevel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_visitorapproval`
--
ALTER TABLE `tbl_visitorapproval`
  MODIFY `visitorapproval_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_visitorlogs`
--
ALTER TABLE `tbl_visitorlogs`
  MODIFY `visitorlogs_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_additional_customer`
--
ALTER TABLE `tbl_additional_customer`
  ADD CONSTRAINT `tbl_additional_customer_ibfk_1` FOREIGN KEY (`customers_id`) REFERENCES `tbl_customers` (`customers_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_billing`
--
ALTER TABLE `tbl_billing`
  ADD CONSTRAINT `tbl_billing_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_billing_ibfk_2` FOREIGN KEY (`booking_charges_id`) REFERENCES `tbl_booking_charges` (`booking_charges_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_billing_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `tbl_employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_billing_ibfk_4` FOREIGN KEY (`payment_method_id`) REFERENCES `tbl_payment_method` (`payment_method_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_billing_ibfk_5` FOREIGN KEY (`discounts_id`) REFERENCES `tbl_discounts` (`discounts_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  ADD CONSTRAINT `tbl_booking_ibfk_1` FOREIGN KEY (`customers_id`) REFERENCES `tbl_customers` (`customers_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_booking_ibfk_4` FOREIGN KEY (`customers_walk_in_id`) REFERENCES `tbl_customers_walk_in` (`customers_walk_in_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_booking_charges`
--
ALTER TABLE `tbl_booking_charges`
  ADD CONSTRAINT `tbl_booking_charges_ibfk_1` FOREIGN KEY (`charges_master_id`) REFERENCES `tbl_charges_master` (`charges_master_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_booking_charges_ibfk_2` FOREIGN KEY (`booking_room_id`) REFERENCES `tbl_booking_room` (`booking_room_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_booking_charges_ibfk_3` FOREIGN KEY (`booking_charge_status`) REFERENCES `tbl_charges_status` (`charges_status_id`);

--
-- Constraints for table `tbl_booking_history`
--
ALTER TABLE `tbl_booking_history`
  ADD CONSTRAINT `tbl_booking_history_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `tbl_employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_booking_history_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `tbl_booking_status` (`booking_status_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_booking_history_ibfk_3` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_booking_room`
--
ALTER TABLE `tbl_booking_room`
  ADD CONSTRAINT `tbl_booking_room_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_booking_room_ibfk_2` FOREIGN KEY (`roomtype_id`) REFERENCES `tbl_roomtype` (`roomtype_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_booking_room_ibfk_3` FOREIGN KEY (`roomnumber_id`) REFERENCES `tbl_rooms` (`roomnumber_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_charges_master`
--
ALTER TABLE `tbl_charges_master`
  ADD CONSTRAINT `tbl_charges_master_ibfk_1` FOREIGN KEY (`charges_category_id`) REFERENCES `tbl_charges_category` (`charges_category_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_check_payment`
--
ALTER TABLE `tbl_check_payment`
  ADD CONSTRAINT `tbl_check_payment_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `tbl_invoice` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_customers`
--
ALTER TABLE `tbl_customers`
  ADD CONSTRAINT `tbl_customers_ibfk_2` FOREIGN KEY (`identification_id`) REFERENCES `tbl_customer_identification` (`identification_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_customers_ibfk_3` FOREIGN KEY (`customers_online_id`) REFERENCES `tbl_customers_online` (`customers_online_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_customers_ibfk_4` FOREIGN KEY (`nationality_id`) REFERENCES `tbl_nationality` (`nationality_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_customersreviews`
--
ALTER TABLE `tbl_customersreviews`
  ADD CONSTRAINT `tbl_customersreviews_ibfk_1` FOREIGN KEY (`customers_id`) REFERENCES `tbl_customers` (`customers_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_customers_walk_in`
--
ALTER TABLE `tbl_customers_walk_in`
  ADD CONSTRAINT `tbl_customers_walk_in_ibfk_1` FOREIGN KEY (`customers_id`) REFERENCES `tbl_customers` (`customers_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_employee`
--
ALTER TABLE `tbl_employee`
  ADD CONSTRAINT `tbl_employee_ibfk_1` FOREIGN KEY (`employee_user_level_id`) REFERENCES `tbl_user_level` (`userlevel_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_imagesroommaster`
--
ALTER TABLE `tbl_imagesroommaster`
  ADD CONSTRAINT `tbl_imagesroommaster_ibfk_1` FOREIGN KEY (`roomtype_id`) REFERENCES `tbl_roomtype` (`roomtype_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_invoice`
--
ALTER TABLE `tbl_invoice`
  ADD CONSTRAINT `tbl_invoice_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `tbl_billing` (`billing_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_invoice_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `tbl_employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_invoice_ibfk_3` FOREIGN KEY (`payment_method_id`) REFERENCES `tbl_payment_method` (`payment_method_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_invoice_ibfk_4` FOREIGN KEY (`invoice_status_id`) REFERENCES `tbl_invoice_status` (`invoice_status_id`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_lost_found`
--
ALTER TABLE `tbl_lost_found`
  ADD CONSTRAINT `tbl_lost_found_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_online_payment`
--
ALTER TABLE `tbl_online_payment`
  ADD CONSTRAINT `tbl_online_payment_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `tbl_invoice` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_rooms`
--
ALTER TABLE `tbl_rooms`
  ADD CONSTRAINT `tbl_rooms_ibfk_2` FOREIGN KEY (`roomtype_id`) REFERENCES `tbl_roomtype` (`roomtype_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_rooms_ibfk_4` FOREIGN KEY (`room_status_id`) REFERENCES `tbl_status_types` (`status_id`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_room_amenities`
--
ALTER TABLE `tbl_room_amenities`
  ADD CONSTRAINT `tbl_room_amenities_ibfk_2` FOREIGN KEY (`room_amenities_master_id`) REFERENCES `tbl_room_amenities_master` (`room_amenities_master_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_room_amenities_ibfk_3` FOREIGN KEY (`roomnumber_id`) REFERENCES `tbl_rooms` (`roomnumber_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_visitorlogs`
--
ALTER TABLE `tbl_visitorlogs`
  ADD CONSTRAINT `tbl_visitorlogs_ibfk_1` FOREIGN KEY (`visitorapproval_id`) REFERENCES `tbl_visitorapproval` (`visitorapproval_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_visitorlogs_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_visitorlogs_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `tbl_employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
