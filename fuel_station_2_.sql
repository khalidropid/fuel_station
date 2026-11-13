-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 24 أغسطس 2026 الساعة 22:29
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
-- Database: `fuel_station(2)`
--

-- --------------------------------------------------------

--
-- بنية الجدول `customers`
--

CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `customer_no` varchar(50) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `credit_limit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `customers`
--

INSERT INTO `customers` (`id`, `customer_no`, `full_name`, `phone`, `address`, `credit_limit`, `balance`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'CUST-001', 'عصام', '7113123', 'ذمار', 1000.00, 500.00, 'active', NULL, '2026-08-19 04:00:45', '2026-08-19 04:36:56');

-- --------------------------------------------------------

--
-- بنية الجدول `customer_payments`
--

CREATE TABLE `customer_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payment_no` varchar(50) NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('cash','card','other') NOT NULL DEFAULT 'cash',
  `notes` varchar(500) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `customer_payments`
--

INSERT INTO `customer_payments` (`id`, `payment_no`, `customer_id`, `amount`, `payment_date`, `payment_method`, `notes`, `created_by`, `created_at`) VALUES
(1, 'PAY-20260819031837-735', 1, 10.00, '2026-08-19 03:18:00', 'cash', '', 1, '2026-08-19 04:18:37');

-- --------------------------------------------------------

--
-- بنية الجدول `customer_transactions`
--

CREATE TABLE `customer_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `transaction_type` enum('sale','payment','adjustment') NOT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp(),
  `description` varchar(500) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `customer_transactions`
--

INSERT INTO `customer_transactions` (`id`, `customer_id`, `transaction_type`, `reference_id`, `amount`, `transaction_date`, `description`, `created_by`, `created_at`) VALUES
(1, 1, 'sale', 5, 500.00, '2026-08-19 03:16:00', 'بيع آجل - الفاتورة INV-20260819031716-457', 1, '2026-08-19 04:17:16');

-- --------------------------------------------------------

--
-- بنية الجدول `employees`
--

CREATE TABLE `employees` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_no` varchar(50) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `salary_type` enum('monthly','daily','hourly','shift') NOT NULL DEFAULT 'monthly',
  `salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `hire_date` date DEFAULT NULL,
  `status` enum('active','inactive','vacation') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `employees`
--

INSERT INTO `employees` (`id`, `employee_no`, `full_name`, `phone`, `address`, `job_title`, `salary_type`, `salary`, `hire_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'EMP-001', 'خالد', '772452744', 'ذمار', 'عامل طرمبه', 'monthly', 300000.00, '2026-08-18', 'active', NULL, '2026-08-18 04:03:26', '2026-08-18 04:03:26'),
(2, 'EMP-002', 'الصهيبي', '777590913', 'ذمار', 'عامل طرمبه', 'shift', 30000.00, '2026-08-18', 'active', NULL, '2026-08-18 04:28:47', '2026-08-18 04:29:50'),
(3, '003', 'طه المجاهد', '773330258', 'الدائري الغربي', 'عامل طرمبة بترول', 'monthly', 50000.00, '2026-08-23', 'active', NULL, '2026-08-23 23:58:40', '2026-08-23 23:58:40'),
(4, '004', 'محمد انور', '774102586', 'السكنية', 'عامل طرمبة ديزل', 'monthly', 60000.00, '2026-08-24', 'active', NULL, '2026-08-24 03:39:15', '2026-08-24 03:39:15');

-- --------------------------------------------------------

--
-- بنية الجدول `employee_withdrawals`
--

CREATE TABLE `employee_withdrawals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `shift_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `withdrawal_date` datetime NOT NULL DEFAULT current_timestamp(),
  `withdrawal_type` enum('personal','advance_salary','expense','other') NOT NULL DEFAULT 'personal',
  `description` varchar(500) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `expenses`
--

CREATE TABLE `expenses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `expense_no` varchar(50) NOT NULL,
  `expense_date` datetime NOT NULL DEFAULT current_timestamp(),
  `category` varchar(100) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `employee_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `fuel_prices`
--

CREATE TABLE `fuel_prices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `fuel_type_id` int(10) UNSIGNED NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `effective_from` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `fuel_prices`
--

INSERT INTO `fuel_prices` (`id`, `fuel_type_id`, `price`, `effective_from`, `created_at`, `is_active`) VALUES
(1, 1, 1000.00, '2026-08-18 02:40:00', '2026-08-18 03:40:15', 1),
(2, 2, 400.00, '2026-08-18 03:35:00', '2026-08-18 04:35:43', 1);

-- --------------------------------------------------------

--
-- بنية الجدول `fuel_receipts`
--

CREATE TABLE `fuel_receipts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `receipt_no` varchar(50) NOT NULL,
  `tank_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(14,2) NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(14,2) NOT NULL DEFAULT 0.00,
  `receipt_date` datetime NOT NULL DEFAULT current_timestamp(),
  `supplier` varchar(200) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `fuel_types`
--

CREATE TABLE `fuel_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'liter',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `fuel_types`
--

INSERT INTO `fuel_types` (`id`, `code`, `name`, `unit`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'PETROL', 'بنزين', 'liter', 1, '2026-08-18 02:41:33', '2026-08-23 23:43:32'),
(2, 'DIESEL', 'ديزل', 'liter', 1, '2026-08-18 02:41:33', '2026-08-23 23:17:09');

-- --------------------------------------------------------

--
-- بنية الجدول `pumps`
--

CREATE TABLE `pumps` (
  `id` int(10) UNSIGNED NOT NULL,
  `pump_no` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `tank_id` int(10) UNSIGNED NOT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `pumps`
--

INSERT INTO `pumps` (`id`, `pump_no`, `name`, `tank_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PUMP-01', 'طرمبة البترول 1', 1, 'active', '2026-08-18 03:55:23', '2026-08-24 03:29:21'),
(2, 'PUMP-02', 'طرمبه البترول 2', 1, 'active', '2026-08-18 04:25:15', '2026-08-24 03:29:04'),
(3, 'PUMP-03', 'طرمبة البترول 03', 1, 'active', '2026-08-18 04:36:22', '2026-08-24 03:28:52'),
(4, 'PUMP-04', 'طرمبة الديزل 1', 2, 'active', '2026-08-24 03:28:19', '2026-08-24 03:28:19'),
(5, 'PUMP-05', 'طرمبة الديزل 2', 2, 'active', '2026-08-24 03:29:56', '2026-08-24 03:29:56');

-- --------------------------------------------------------

--
-- بنية الجدول `pump_readings`
--

CREATE TABLE `pump_readings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pump_id` int(10) UNSIGNED NOT NULL,
  `reading` decimal(14,2) NOT NULL,
  `reading_date` datetime NOT NULL DEFAULT current_timestamp(),
  `reading_type` enum('opening','closing','manual') NOT NULL DEFAULT 'manual',
  `employee_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `pump_readings`
--

INSERT INTO `pump_readings` (`id`, `pump_id`, `reading`, `reading_date`, `reading_type`, `employee_id`, `notes`, `created_at`) VALUES
(1, 1, 0.00, '2026-08-13 03:12:00', 'opening', 1, 'قراءة بداية الوردية SH-20260818031247', '2026-08-18 04:12:47'),
(2, 1, 10000.00, '2026-08-18 03:19:00', 'opening', 1, NULL, '2026-08-18 04:20:06'),
(3, 2, 20000.00, '2026-08-19 03:25:00', 'opening', 1, NULL, '2026-08-18 04:25:50'),
(4, 1, 1000.00, '2026-08-18 03:26:00', 'closing', 1, 'قراءة إغلاق الوردية SH-20260818031247', '2026-08-18 04:27:03'),
(5, 3, 0.00, '2026-08-18 03:36:00', 'opening', 2, 'قراءة بداية الوردية SH-20260818033649', '2026-08-18 04:36:49'),
(6, 3, 2000.00, '2026-08-18 03:37:00', 'closing', 2, 'قراءة إغلاق الوردية SH-20260818033649', '2026-08-18 04:37:54'),
(7, 2, 20000.00, '2026-08-19 00:12:00', 'opening', 1, 'قراءة بداية الوردية SH-20260819001248', '2026-08-19 01:12:48'),
(8, 1, 1000.00, '2026-08-24 00:01:00', 'opening', 3, 'قراءة بداية الوردية SH-20260823230235', '2026-08-24 00:02:35'),
(9, 4, 1000.00, '2026-08-24 02:30:00', 'opening', 3, NULL, '2026-08-24 03:31:07'),
(10, 1, 1900.00, '2026-08-24 02:34:00', 'closing', 3, 'قراءة إغلاق الوردية SH-20260823230235', '2026-08-24 03:37:24'),
(11, 5, 0.00, '2026-08-24 02:39:00', 'opening', 4, 'قراءة بداية الوردية SH-20260824023948', '2026-08-24 03:39:48'),
(12, 3, 2000.00, '2026-08-24 02:43:00', 'opening', 3, 'قراءة بداية الوردية SH-20260824024403', '2026-08-24 03:44:03');

-- --------------------------------------------------------

--
-- بنية الجدول `salaries`
--

CREATE TABLE `salaries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `salary_month` date NOT NULL,
  `basic_salary` decimal(14,2) NOT NULL DEFAULT 0.00,
  `allowances` decimal(14,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(14,2) NOT NULL DEFAULT 0.00,
  `withdrawals` decimal(14,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `paid_date` datetime DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `sales`
--

CREATE TABLE `sales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `shift_id` bigint(20) UNSIGNED DEFAULT NULL,
  `pump_id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `sale_date` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('cash','card','credit') NOT NULL DEFAULT 'cash',
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `sales`
--

INSERT INTO `sales` (`id`, `invoice_no`, `shift_id`, `pump_id`, `employee_id`, `customer_id`, `sale_date`, `payment_method`, `subtotal`, `discount`, `total`, `notes`, `created_at`) VALUES
(1, 'INV-20260819001330-411', 3, 2, 1, NULL, '2026-08-19 00:12:00', 'cash', 1000000.00, 0.00, 1000000.00, '', '2026-08-19 01:13:30'),
(2, 'INV-20260819004438-945', 3, 2, 1, NULL, '2026-08-19 00:44:00', 'cash', 400000.00, 0.00, 400000.00, '', '2026-08-19 01:44:38'),
(5, 'INV-20260819031716-457', 3, 2, 1, 1, '2026-08-19 03:16:00', 'credit', 500.00, 0.00, 500.00, NULL, '2026-08-19 04:17:16'),
(6, 'INV-20260823230550-425', 4, 1, 3, NULL, '2026-08-24 00:05:00', 'cash', 25000.00, 0.00, 25000.00, NULL, '2026-08-24 00:05:50');

-- --------------------------------------------------------

--
-- بنية الجدول `sale_items`
--

CREATE TABLE `sale_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `fuel_type_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(14,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `total` decimal(14,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `fuel_type_id`, `quantity`, `unit_price`, `total`, `created_at`) VALUES
(1, 1, 1, 2000.00, 500.00, 1000000.00, '2026-08-19 01:13:30'),
(2, 2, 1, 20000.00, 20.00, 400000.00, '2026-08-19 01:44:38'),
(5, 5, 1, 50.00, 10.00, 500.00, '2026-08-19 04:17:16'),
(6, 6, 1, 50.00, 500.00, 25000.00, '2026-08-24 00:05:50');

-- --------------------------------------------------------

--
-- بنية الجدول `settings`
--

CREATE TABLE `settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'station_name', 'محطة الوقود', '2026-08-18 02:41:33', '2026-08-18 02:41:33'),
(2, 'station_phone', '', '2026-08-18 02:41:33', '2026-08-18 02:41:33'),
(3, 'station_address', '', '2026-08-18 02:41:33', '2026-08-18 02:41:33'),
(4, 'currency', 'ريال', '2026-08-18 02:41:33', '2026-08-18 02:41:33');

-- --------------------------------------------------------

--
-- بنية الجدول `shifts`
--

CREATE TABLE `shifts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shift_no` varchar(50) NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `status` enum('open','closed','cancelled') NOT NULL DEFAULT 'open',
  `opening_cash` decimal(14,2) NOT NULL DEFAULT 0.00,
  `closing_cash` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_sales` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_withdrawals` decimal(14,2) NOT NULL DEFAULT 0.00,
  `expected_cash` decimal(14,2) NOT NULL DEFAULT 0.00,
  `difference` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `shifts`
--

INSERT INTO `shifts` (`id`, `shift_no`, `employee_id`, `start_datetime`, `end_datetime`, `status`, `opening_cash`, `closing_cash`, `total_sales`, `total_withdrawals`, `expected_cash`, `difference`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'SH-20260818031247', 1, '2026-08-13 03:12:00', '2026-08-18 03:26:00', 'closed', 1000.00, 1000.00, 1000000.00, 0.00, 1001000.00, -1000000.00, '', '2026-08-18 04:12:47', '2026-08-18 04:27:03'),
(2, 'SH-20260818033649', 2, '2026-08-18 03:36:00', '2026-08-18 03:37:00', 'closed', 3000.00, 3000.00, 2000000.00, 0.00, 2003000.00, -2000000.00, '', '2026-08-18 04:36:49', '2026-08-18 04:37:54'),
(3, 'SH-20260819001248', 1, '2026-08-19 00:12:00', NULL, 'open', 3000.00, 0.00, 1400500.00, 0.00, 1403500.00, 0.00, '', '2026-08-19 01:12:48', '2026-08-19 04:36:56'),
(4, 'SH-20260823230235', 3, '2026-08-24 00:01:00', '2026-08-24 02:34:00', 'closed', 0.00, 50000.00, 25000.00, 0.00, 25000.00, 25000.00, '', '2026-08-24 00:02:35', '2026-08-24 03:37:24'),
(5, 'SH-20260824023948', 4, '2026-08-24 02:39:00', NULL, 'open', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', '2026-08-24 03:39:48', '2026-08-24 03:39:48'),
(6, 'SH-20260824024403', 3, '2026-08-24 02:43:00', NULL, 'open', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', '2026-08-24 03:44:03', '2026-08-24 03:44:03');

-- --------------------------------------------------------

--
-- بنية الجدول `shift_pumps`
--

CREATE TABLE `shift_pumps` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shift_id` bigint(20) UNSIGNED NOT NULL,
  `pump_id` int(10) UNSIGNED NOT NULL,
  `opening_reading` decimal(14,2) NOT NULL DEFAULT 0.00,
  `closing_reading` decimal(14,2) DEFAULT NULL,
  `total_liters` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_sales` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `shift_pumps`
--

INSERT INTO `shift_pumps` (`id`, `shift_id`, `pump_id`, `opening_reading`, `closing_reading`, `total_liters`, `total_sales`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 0.00, 1000.00, 1000.00, 1000000.00, '2026-08-18 04:12:47', '2026-08-18 04:27:03'),
(2, 2, 3, 0.00, 2000.00, 2000.00, 2000000.00, '2026-08-18 04:36:49', '2026-08-18 04:37:54'),
(3, 3, 2, 20000.00, NULL, 22050.00, 1400500.00, '2026-08-19 01:12:48', '2026-08-19 04:36:56'),
(4, 4, 1, 1000.00, 1900.00, 900.00, 25000.00, '2026-08-24 00:02:35', '2026-08-24 03:37:24'),
(5, 5, 5, 0.00, NULL, 0.00, 0.00, '2026-08-24 03:39:48', '2026-08-24 03:39:48'),
(6, 6, 3, 2000.00, NULL, 0.00, 0.00, '2026-08-24 03:44:03', '2026-08-24 03:44:03');

-- --------------------------------------------------------

--
-- بنية الجدول `tanks`
--

CREATE TABLE `tanks` (
  `id` int(10) UNSIGNED NOT NULL,
  `tank_no` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `fuel_type_id` int(10) UNSIGNED NOT NULL,
  `capacity` decimal(14,2) NOT NULL DEFAULT 0.00,
  `current_volume` decimal(14,2) NOT NULL DEFAULT 0.00,
  `minimum_level` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `tanks`
--

INSERT INTO `tanks` (`id`, `tank_no`, `name`, `fuel_type_id`, `capacity`, `current_volume`, `minimum_level`, `status`, `created_at`, `updated_at`) VALUES
(1, 'TANK-01', 'خزان البنزين الرئيسي', 1, 50000.00, 7900.00, 5000.00, 'active', '2026-08-18 03:51:52', '2026-08-24 03:14:49'),
(2, '2', 'خزان الديزل الرئيسييي', 2, 70000.00, 0.00, 70000.00, 'active', '2026-08-23 22:55:30', '2026-08-24 03:27:31');

-- --------------------------------------------------------

--
-- بنية الجدول `tank_movements`
--

CREATE TABLE `tank_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tank_id` int(10) UNSIGNED NOT NULL,
  `movement_type` enum('opening','receipt','sale','adjustment','transfer') NOT NULL,
  `quantity` decimal(14,2) NOT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `movement_date` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(500) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `tank_movements`
--

INSERT INTO `tank_movements` (`id`, `tank_id`, `movement_type`, `quantity`, `reference_id`, `movement_date`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 'sale', -2000.00, 1, '2026-08-19 00:12:00', 'بيع فاتورة INV-20260819001330-411', NULL, '2026-08-19 01:13:30'),
(2, 1, 'sale', -20000.00, 2, '2026-08-19 00:44:00', 'بيع وقود - فاتورة INV-20260819004438-945', NULL, '2026-08-19 01:44:38'),
(5, 1, 'sale', -20.00, 5, '2026-08-19 03:16:00', 'بيع وقود - فاتورة INV-20260819031716-457', NULL, '2026-08-19 04:17:16'),
(6, 1, 'adjustment', -30.00, 5, '2026-08-19 03:16:00', 'تصحيح كمية الفاتورة INV-20260819031716-457', 1, '2026-08-19 04:36:56'),
(7, 1, 'sale', -50.00, 6, '2026-08-24 00:05:00', 'بيع وقود - فاتورة INV-20260823230550-425', NULL, '2026-08-24 00:05:50');

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `role` enum('admin','manager','accountant','employee') NOT NULL DEFAULT 'employee',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$4o26UCz0XEW53btzius8iunsURZmRRt2MWAQSp4SSpIDYhiJjaaQW', 'مدير النظام', 'admin', 1, '2026-08-18 02:55:21', '2026-08-18 02:55:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_no` (`customer_no`),
  ADD KEY `idx_customers_name` (`full_name`),
  ADD KEY `idx_customers_status` (`status`);

--
-- Indexes for table `customer_payments`
--
ALTER TABLE `customer_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_no` (`payment_no`),
  ADD KEY `fk_customer_payments_user` (`created_by`),
  ADD KEY `idx_customer_payments_customer` (`customer_id`),
  ADD KEY `idx_customer_payments_date` (`payment_date`);

--
-- Indexes for table `customer_transactions`
--
ALTER TABLE `customer_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_customer_transactions_user` (`created_by`),
  ADD KEY `idx_customer_transactions_customer` (`customer_id`),
  ADD KEY `idx_customer_transactions_date` (`transaction_date`),
  ADD KEY `idx_customer_transactions_reference` (`reference_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_no` (`employee_no`);

--
-- Indexes for table `employee_withdrawals`
--
ALTER TABLE `employee_withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_withdrawals_shift` (`shift_id`),
  ADD KEY `fk_withdrawals_user` (`created_by`),
  ADD KEY `idx_withdrawals_employee` (`employee_id`),
  ADD KEY `idx_withdrawals_date` (`withdrawal_date`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `expense_no` (`expense_no`),
  ADD KEY `fk_expenses_employee` (`employee_id`),
  ADD KEY `fk_expenses_user` (`created_by`),
  ADD KEY `idx_expenses_date` (`expense_date`);

--
-- Indexes for table `fuel_prices`
--
ALTER TABLE `fuel_prices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fuel_price_date` (`fuel_type_id`,`effective_from`);

--
-- Indexes for table `fuel_receipts`
--
ALTER TABLE `fuel_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_no` (`receipt_no`),
  ADD KEY `fk_receipts_tank` (`tank_id`),
  ADD KEY `fk_receipts_user` (`created_by`);

--
-- Indexes for table `fuel_types`
--
ALTER TABLE `fuel_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `pumps`
--
ALTER TABLE `pumps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pump_no` (`pump_no`),
  ADD KEY `fk_pumps_tank` (`tank_id`);

--
-- Indexes for table `pump_readings`
--
ALTER TABLE `pump_readings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_readings_employee` (`employee_id`),
  ADD KEY `idx_readings_pump_date` (`pump_id`,`reading_date`);

--
-- Indexes for table `salaries`
--
ALTER TABLE `salaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_employee_salary_month` (`employee_id`,`salary_month`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `fk_sales_pump` (`pump_id`),
  ADD KEY `fk_sales_employee` (`employee_id`),
  ADD KEY `idx_sales_date` (`sale_date`),
  ADD KEY `idx_sales_shift` (`shift_id`),
  ADD KEY `idx_sales_customer` (`customer_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sale_items_sale` (`sale_id`),
  ADD KEY `fk_sale_items_fuel` (`fuel_type_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shift_no` (`shift_no`),
  ADD KEY `idx_shifts_employee_date` (`employee_id`,`start_datetime`),
  ADD KEY `idx_shifts_status` (`status`);

--
-- Indexes for table `shift_pumps`
--
ALTER TABLE `shift_pumps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_shift_pump` (`shift_id`,`pump_id`),
  ADD KEY `fk_shift_pumps_pump` (`pump_id`);

--
-- Indexes for table `tanks`
--
ALTER TABLE `tanks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tank_no` (`tank_no`),
  ADD KEY `fk_tanks_fuel` (`fuel_type_id`);

--
-- Indexes for table `tank_movements`
--
ALTER TABLE `tank_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_movements_user` (`created_by`),
  ADD KEY `idx_movements_tank_date` (`tank_id`,`movement_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_payments`
--
ALTER TABLE `customer_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_transactions`
--
ALTER TABLE `customer_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `employee_withdrawals`
--
ALTER TABLE `employee_withdrawals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fuel_prices`
--
ALTER TABLE `fuel_prices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `fuel_receipts`
--
ALTER TABLE `fuel_receipts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fuel_types`
--
ALTER TABLE `fuel_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pumps`
--
ALTER TABLE `pumps`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `pump_readings`
--
ALTER TABLE `pump_readings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `salaries`
--
ALTER TABLE `salaries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `shift_pumps`
--
ALTER TABLE `shift_pumps`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tanks`
--
ALTER TABLE `tanks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tank_movements`
--
ALTER TABLE `tank_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `customer_payments`
--
ALTER TABLE `customer_payments`
  ADD CONSTRAINT `fk_customer_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customer_payments_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- قيود الجداول `customer_transactions`
--
ALTER TABLE `customer_transactions`
  ADD CONSTRAINT `fk_customer_transactions_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_customer_transactions_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- قيود الجداول `employee_withdrawals`
--
ALTER TABLE `employee_withdrawals`
  ADD CONSTRAINT `fk_withdrawals_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_withdrawals_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_withdrawals_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- قيود الجداول `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expenses_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_expenses_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- قيود الجداول `fuel_prices`
--
ALTER TABLE `fuel_prices`
  ADD CONSTRAINT `fk_fuel_prices_fuel` FOREIGN KEY (`fuel_type_id`) REFERENCES `fuel_types` (`id`) ON UPDATE CASCADE;

--
-- قيود الجداول `fuel_receipts`
--
ALTER TABLE `fuel_receipts`
  ADD CONSTRAINT `fk_receipts_tank` FOREIGN KEY (`tank_id`) REFERENCES `tanks` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_receipts_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- قيود الجداول `pumps`
--
ALTER TABLE `pumps`
  ADD CONSTRAINT `fk_pumps_tank` FOREIGN KEY (`tank_id`) REFERENCES `tanks` (`id`) ON UPDATE CASCADE;

--
-- قيود الجداول `pump_readings`
--
ALTER TABLE `pump_readings`
  ADD CONSTRAINT `fk_readings_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_readings_pump` FOREIGN KEY (`pump_id`) REFERENCES `pumps` (`id`) ON UPDATE CASCADE;

--
-- قيود الجداول `salaries`
--
ALTER TABLE `salaries`
  ADD CONSTRAINT `fk_salaries_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON UPDATE CASCADE;

--
-- قيود الجداول `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sales_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sales_pump` FOREIGN KEY (`pump_id`) REFERENCES `pumps` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sales_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- قيود الجداول `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_sale_items_fuel` FOREIGN KEY (`fuel_type_id`) REFERENCES `fuel_types` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `shifts`
--
ALTER TABLE `shifts`
  ADD CONSTRAINT `fk_shifts_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON UPDATE CASCADE;

--
-- قيود الجداول `shift_pumps`
--
ALTER TABLE `shift_pumps`
  ADD CONSTRAINT `fk_shift_pumps_pump` FOREIGN KEY (`pump_id`) REFERENCES `pumps` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_shift_pumps_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `tanks`
--
ALTER TABLE `tanks`
  ADD CONSTRAINT `fk_tanks_fuel` FOREIGN KEY (`fuel_type_id`) REFERENCES `fuel_types` (`id`) ON UPDATE CASCADE;

--
-- قيود الجداول `tank_movements`
--
ALTER TABLE `tank_movements`
  ADD CONSTRAINT `fk_movements_tank` FOREIGN KEY (`tank_id`) REFERENCES `tanks` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_movements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
