-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2025 at 02:19 AM
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
  `booking_payment` int(11) DEFAULT NULL,
  `booking_paymentMethod` int(11) NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `booking_fileName` varchar(255) NOT NULL,
  `booking_checkin_dateandtime` datetime DEFAULT NULL,
  `booking_checkout_dateandtime` datetime DEFAULT NULL,
  `booking_created_at` datetime DEFAULT NULL,
  `booking_isArchive` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_booking_charges`
--

CREATE TABLE `tbl_booking_charges` (
  `booking_charges_id` int(11) NOT NULL,
  `charges_master_id` int(11) DEFAULT NULL,
  `booking_charges_notes_id` int(11) DEFAULT NULL,
  `booking_room_id` int(11) DEFAULT NULL,
  `booking_charges_price` int(11) DEFAULT NULL,
  `booking_charges_quantity` int(11) DEFAULT NULL,
  `booking_charges_total` int(11) DEFAULT NULL,
  `booking_charge_status` int(11) NOT NULL DEFAULT 1,
  `booking_charge_datetime` datetime NOT NULL,
  `booking_return_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_booking_charges_notes`
--

CREATE TABLE `tbl_booking_charges_notes` (
  `booking_c_notes_id` int(11) NOT NULL,
  `booking_c_notes_charges_id` int(11) DEFAULT NULL,
  `booking_c_notes` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(2, 'Confirmed'),
(3, 'Cancelled'),
(4, 'No-Show'),
(5, 'Checked-In'),
(6, 'Checked-Out');

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
  `charge_name_isRestricted` tinyint(1) NOT NULL,
  `charge_isDisabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_charges_master`
--

INSERT INTO `tbl_charges_master` (`charges_master_id`, `charges_category_id`, `charges_master_name`, `charges_master_price`, `charges_master_description`, `charge_name_isRestricted`, `charge_isDisabled`) VALUES
(1, 1, 'In-room Dining', 350, 'Order food directly to your room', 0, 0),
(2, 1, 'Beds', 420, 'Makes you take a good nap', 1, 1),
(3, 1, 'Extra Pillow & Blanket', 100, 'Request for additional bedding', 0, 0),
(5, 1, 'Room Cleaning on Request', 150, 'On-demand cleaning service', 0, 0),
(6, 2, 'Wash & Fold', 120, 'Standard laundry service per kilogram', 0, 0),
(7, 2, 'Dry Cleaning', 180, 'Professional dry cleaning per item', 0, 0),
(8, 2, 'Ironing Service', 80, 'Clothes pressed per piece', 0, 0),
(9, 2, 'Express Laundry', 250, 'Same-day laundry processing', 1, 0),
(10, 2, 'Clothing Pickup & Delivery', 50, 'Laundry collected and returned to your room', 0, 0),
(11, 3, 'Breakfast Buffet', 450, 'Morning buffet with various options', 0, 0),
(12, 4, 'Extra Guests', 420, 'Welcome Guests, guests are charged for every 6 hours', 0, 1),
(13, 3, 'Beverage Service', 150, 'Order soft drinks, coffee, or tea', 0, 0),
(14, 3, 'Food', 200, 'Orders from the customers during their stay (Only Lists the food)', 0, 0),
(15, 3, 'Special Dietary Request', 500, 'Customized meals upon request', 1, 0),
(16, 4, 'Luggage Assistance', 0, 'Bellboy service for carrying luggage', 0, 1);

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
(1, 1, 'Melky Wayne', 'Macario', '09539143839', 'xmelmacario@gmail.com', NULL, '2001-03-08', NULL, NULL, 3, '2025-10-25 20:11:03', NULL, 'pending'),
(2, 2, 'Xyber Bean Malachi', 'Macario', '09878765432', 'beyasabellach@gmail.com', NULL, '1999-10-25', NULL, NULL, 1, '2025-10-26 04:12:33', NULL, 'pending'),
(3, 3, 'Xyber Bean Malachi', 'Macario', '09878765432', 'xmoonlightboy@gmail.com', NULL, '1999-10-25', NULL, NULL, 1, '2025-10-26 07:54:02', NULL, 'pending'),
(4, 4, 'Adel', 'Lausa', '09877876567', 'sevenevener@gmail.com', NULL, '2003-07-25', NULL, NULL, 1, '2025-10-28 22:25:06', NULL, 'pending'),
(5, 5, 'Claire', 'Manolo', '09868585745', 'watermelonsugar540@gmail.com', NULL, '2000-01-02', NULL, NULL, 1, '2025-10-29 06:35:20', NULL, 'pending');

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

--
-- Dumping data for table `tbl_customersreviews`
--

INSERT INTO `tbl_customersreviews` (`customersreviews_id`, `customers_id`, `customersreviews_rating`, `customersreviews_comment`, `customersreviews_date`) VALUES
(1, 1, 5, 'so good ', '2025-10-27 01:37:20');

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
  `customers_online_status` int(11) DEFAULT NULL,
  `customers_online_profile_image` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_customers_online`
--

INSERT INTO `tbl_customers_online` (`customers_online_id`, `customers_online_username`, `customers_online_password`, `customers_online_email`, `customers_online_phone`, `customers_online_created_at`, `customers_online_updated_at`, `customers_online_status`, `customers_online_profile_image`) VALUES
(1, 'yang', 'yang', 'ivla.versoza.coc@phinmaed.com', '09539143839', '2025-10-25 20:11:03', NULL, 1, ''),
(2, 'Kai', '$2y$10$pPIntC.ip7ODLstrRLZ4yuGSvPr3YqQTbqgYlwYx6trFZojDLgkJ2', 'beyasabellach@gmail.com', '09878765432', '2025-10-26 04:12:33', NULL, 0, ''),
(3, 'Kai2', '$2y$10$9MYKARQd4y7nDnQE6W4RUes0cIYJ0iXyLdUF.BQ94oTPGZ6Zi6fCW', 'xmoonlightboy@gmail.com', '09878765432', '2025-10-26 07:54:02', NULL, 0, ''),
(4, 'aiai', '$2y$10$J9pqfW3dAcUjmR/SEw/18e0XxOKyC4oACbCtPGlO9SGS6WxUrhArK', 'sevenevener@gmail.com', '09877876567', '2025-10-28 22:25:06', NULL, 0, ''),
(5, 'Lili', '$2y$10$iD.oqKNP20Q5x8bbv/I9gexULPlnZUIFgc1a/yJCNx5/d2.HpXlwi', 'watermelonsugar540@gmail.com', '09868585745', '2025-10-29 06:35:20', NULL, 1, '');

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
(1, NULL, 'Melky Wayne', 'Macario', '09539143839', 'xmelmacario@gmail.com', NULL, NULL, NULL, '2025-10-25 16:41:09', NULL, 'Active'),
(2, NULL, 'Melky Wayne', 'Macario', '09539143839', 'xmelmacario@gmail.com', NULL, NULL, NULL, '2025-10-25 19:00:03', NULL, 'Active'),
(3, NULL, 'Melky Wayne', 'Macario', '09539143839', 'xmelmacario@gmail.com', NULL, NULL, NULL, '2025-10-25 19:23:14', NULL, 'Active'),
(4, NULL, 'Melky Wayne', 'Macario', '09539143839', 'xmelmacario@gmail.com', NULL, NULL, NULL, '2025-10-25 19:25:34', NULL, 'Active'),
(6, NULL, 'Melky Wayne', 'Macario', '09539143839', 'xmelmacario@gmail.com', NULL, NULL, NULL, '2025-10-25 19:31:43', NULL, 'Active'),
(7, NULL, 'Melky Wayne', 'Macario', '09539143839', 'xmelmacario@gmail.com', NULL, NULL, NULL, '2025-10-25 19:44:54', NULL, 'Active'),
(8, NULL, 'Melky Wayne', 'Macario', '09539143839', 'xmelmacario@gmail.com', NULL, NULL, NULL, '2025-10-25 19:48:35', NULL, 'Active'),
(9, NULL, 'Melky Wayne', 'Macario', '09539143839', 'xmelmacario@gmail.com', NULL, NULL, NULL, '2025-10-25 19:52:57', NULL, 'Active'),
(10, NULL, 'Melky Wayne', 'Macario', '09539143839', 'xmelmacario@gmail.com', NULL, NULL, NULL, '2025-10-25 19:54:24', NULL, 'Active'),
(11, NULL, 'Melky Wayne', 'Macario', '09539143839', 'xmelmacario@gmail.com', NULL, NULL, NULL, '2025-10-25 19:55:45', NULL, 'Active'),
(12, NULL, 'Melky Wayne', 'Macario', '09539143839', 'xmelmacario@gmail.com', NULL, NULL, NULL, '2025-10-25 19:59:59', NULL, 'Active'),
(13, NULL, 'Realan', 'Fabrea', '09878765432', 'fabreasawyer@gmail.com', NULL, NULL, NULL, '2025-10-26 04:00:04', NULL, 'Active'),
(14, NULL, 'C Jay', 'Macuse', '09539143839', 'xmelmacario@gmail.com', NULL, NULL, NULL, '2025-10-26 04:07:36', NULL, 'Active'),
(15, NULL, 'John', 'Jaso', '09878989098', 'macusecjay25@gmail.com', NULL, NULL, NULL, '2025-10-27 05:17:05', NULL, 'Active'),
(21, NULL, 'Ivan ', 'Versoza', '09878765432', 'ivla.versoza.coc@phinmaed.com', NULL, NULL, NULL, '2025-10-27 09:48:36', NULL, 'Active'),
(22, NULL, 'Grizon ', 'Sacay', '09074585789', 'chsa.noynay.coc@phinmaed.com', NULL, NULL, NULL, '2025-10-28 22:19:41', NULL, 'Active'),
(23, NULL, 'Grizon ', 'Sacay', '09074585789', 'chsa.noynay.coc@phinmaed.com', NULL, NULL, NULL, '2025-10-29 03:51:01', NULL, 'Active'),
(24, NULL, 'Yvhone', 'Estanda', '09875652536', 'tokbe1162@gmail.com', NULL, NULL, NULL, '2025-10-29 06:30:31', NULL, 'Active');

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
  `discounts_description` text DEFAULT NULL,
  `discount_start_in` date DEFAULT NULL,
  `discount_ends_in` date DEFAULT NULL,
  `discount_isDisabled` tinyint(1) NOT NULL
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
  `employee_status` tinyint(1) DEFAULT NULL,
  `employee_online_authentication_status` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_employee`
--

INSERT INTO `tbl_employee` (`employee_id`, `employee_user_level_id`, `employee_fname`, `employee_lname`, `employee_username`, `employee_phone`, `employee_email`, `employee_password`, `employee_address`, `employee_birthdate`, `employee_gender`, `employee_created_at`, `employee_updated_at`, `employee_status`, `employee_online_authentication_status`) VALUES
(1, 1, 'Ivan Ky', 'Versoza', 'admin', '0962 818 3282', 'ikversoza@gmail.com', 'admin', 'Misamis Oriental, Cagayan de Oro', '2003-01-17', 'Male', '2025-09-30 22:32:38', '2025-10-27 20:03:39', 1, 0);

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

--
-- Dumping data for table `tbl_invoice`
--

INSERT INTO `tbl_invoice` (`invoice_id`, `billing_id`, `employee_id`, `payment_method_id`, `invoice_date`, `invoice_time`, `invoice_total_amount`, `invoice_status_id`) VALUES
(1, 27, 1, 2, '2025-10-27', '02:55:55', 1760, 1),
(2, 27, 1, 2, '2025-10-27', '02:55:55', 1971, 1),
(3, 28, 1, 2, '2025-10-28', '10:14:57', 1600, 1),
(4, 28, 1, 2, '2025-10-28', '10:14:57', 1792, 1),
(5, 32, 1, 2, '2025-10-28', '13:21:15', 1300, 1),
(6, 32, 1, 2, '2025-10-28', '13:21:15', 1456, 1),
(7, 33, 1, 2, '2025-10-28', '13:49:44', 1840, 1),
(8, 33, 1, 2, '2025-10-28', '13:49:44', 2061, 1),
(9, 34, 1, 2, '2025-10-28', '14:12:49', 16000, 1),
(10, 34, 1, 2, '2025-10-28', '14:12:49', 17920, 1),
(11, 39, 1, 2, '2025-10-28', '18:53:22', 11300, 1),
(12, 39, 1, 2, '2025-10-28', '18:53:22', 12656, 1);

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
(2, 'Paypal'),
(3, 'Cash'),
(4, 'Check');

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
(1, 2, 1, 1),
(2, 3, 1, 3),
(3, 1, 1, 3),
(4, 4, 1, 1),
(5, 5, 2, 3),
(6, 6, 2, 1),
(7, 7, 2, 3),
(8, 2, 2, 3),
(9, 3, 3, 1),
(10, 1, 3, 3),
(11, 4, 3, 3),
(12, 8, 3, 3),
(13, 5, 4, 3),
(14, 6, 4, 3),
(15, 7, 4, 3),
(16, 2, 4, 1),
(17, 3, 5, 1),
(18, 1, 5, 3),
(19, 4, 5, 1),
(20, 8, 5, 3),
(21, 1, 6, 3),
(22, 2, 6, 3),
(23, 3, 6, 3);

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
  `status_name` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_status_types`
--

INSERT INTO `tbl_status_types` (`status_id`, `status_name`) VALUES
(1, 'Occupied'),
(2, 'Pending'),
(3, 'Vacant'),
(4, 'Under-Maintenance'),
(5, 'Needs Cleaning'),
(6, 'Disabled');

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
(2, 'Front-Desk');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_visitorapproval`
--

CREATE TABLE `tbl_visitorapproval` (
  `visitorapproval_id` int(11) NOT NULL,
  `visitorapproval_status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_visitorapproval`
--

INSERT INTO `tbl_visitorapproval` (`visitorapproval_id`, `visitorapproval_status`) VALUES
(1, 'Approved'),
(2, 'Declined'),
(3, 'Pending'),
(4, 'Left');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_visitorlogs`
--

CREATE TABLE `tbl_visitorlogs` (
  `visitorlogs_id` int(11) NOT NULL,
  `visitorapproval_id` int(11) DEFAULT NULL,
  `booking_room_id` int(11) DEFAULT NULL,
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
  ADD KEY `tbl_booking_ibfk_4` (`customers_walk_in_id`),
  ADD KEY `payment_method` (`booking_paymentMethod`);

--
-- Indexes for table `tbl_booking_charges`
--
ALTER TABLE `tbl_booking_charges`
  ADD PRIMARY KEY (`booking_charges_id`),
  ADD KEY `tbl_booking_charges_ibfk_1` (`charges_master_id`),
  ADD KEY `tbl_booking_charges_ibfk_2` (`booking_room_id`),
  ADD KEY `charge_status` (`booking_charge_status`),
  ADD KEY `booking_charges_notes_id` (`booking_charges_notes_id`);

--
-- Indexes for table `tbl_booking_charges_notes`
--
ALTER TABLE `tbl_booking_charges_notes`
  ADD PRIMARY KEY (`booking_c_notes_id`);

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
  ADD KEY `tbl_visitorlogs_ibfk_2` (`booking_room_id`),
  ADD KEY `tbl_visitorlogs_ibfk_3` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_activitylogs`
--
ALTER TABLE `tbl_activitylogs`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_additional_customer`
--
ALTER TABLE `tbl_additional_customer`
  MODIFY `additional_customer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_billing`
--
ALTER TABLE `tbl_billing`
  MODIFY `billing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `tbl_booking_charges`
--
ALTER TABLE `tbl_booking_charges`
  MODIFY `booking_charges_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `tbl_booking_charges_notes`
--
ALTER TABLE `tbl_booking_charges_notes`
  MODIFY `booking_c_notes_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tbl_booking_history`
--
ALTER TABLE `tbl_booking_history`
  MODIFY `booking_history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `tbl_booking_room`
--
ALTER TABLE `tbl_booking_room`
  MODIFY `booking_room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `tbl_booking_status`
--
ALTER TABLE `tbl_booking_status`
  MODIFY `booking_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_charges_category`
--
ALTER TABLE `tbl_charges_category`
  MODIFY `charges_category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_charges_master`
--
ALTER TABLE `tbl_charges_master`
  MODIFY `charges_master_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

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
  MODIFY `customers_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_customersreviews`
--
ALTER TABLE `tbl_customersreviews`
  MODIFY `customersreviews_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_customers_online`
--
ALTER TABLE `tbl_customers_online`
  MODIFY `customers_online_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_customers_walk_in`
--
ALTER TABLE `tbl_customers_walk_in`
  MODIFY `customers_walk_in_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

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
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_imagesroommaster`
--
ALTER TABLE `tbl_imagesroommaster`
  MODIFY `imagesroommaster_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tbl_invoice`
--
ALTER TABLE `tbl_invoice`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
  MODIFY `roomnumber_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

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
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_user_level`
--
ALTER TABLE `tbl_user_level`
  MODIFY `userlevel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_visitorapproval`
--
ALTER TABLE `tbl_visitorapproval`
  MODIFY `visitorapproval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- Constraints for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  ADD CONSTRAINT `tbl_booking_ibfk_1` FOREIGN KEY (`customers_id`) REFERENCES `tbl_customers` (`customers_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_booking_ibfk_4` FOREIGN KEY (`customers_walk_in_id`) REFERENCES `tbl_customers_walk_in` (`customers_walk_in_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_booking_ibfk_5` FOREIGN KEY (`booking_paymentMethod`) REFERENCES `tbl_payment_method` (`payment_method_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
