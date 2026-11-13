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
