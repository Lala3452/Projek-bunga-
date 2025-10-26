-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 18, 2025 at 02:04 PM
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
-- Database: `financial_management`
--
CREATE DATABASE IF NOT EXISTS `financial_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `financial_management`;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `update_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `username`, `email`, `password`, `role`, `is_active`, `created_at`, `update_at`) VALUES
(1, 'userka', 'userka@gmail.com', 'ee11cbb19052e40b07aac0ca060c23ee', 'user', 1, '2025-10-17 15:34:24', '2025-10-18 04:57:44'),
(2, 'adminka', 'adminka@gmail.com', '21232f297a57a5a743894a0e4a801fc3', 'admin', 1, '2025-10-17 15:39:33', '2025-10-18 04:59:39'),
(3, 'john_doe', 'john.doe@example.com', 'ee11cbb19052e40b07aac0ca060c23ee', 'user', 1, '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(4, 'jane_smith', 'jane.smith@example.com', 'ee11cbb19052e40b07aac0ca060c23ee', 'user', 1, '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(5, 'bob_wilson', 'bob.wilson@example.com', 'ee11cbb19052e40b07aac0ca060c23ee', 'user', 1, '2025-10-18 12:00:00', '2025-10-18 12:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `type`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Gaji', 'income', 'Pendapatan dari gaji bulanan', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(2, 'Bonus', 'income', 'Pendapatan dari bonus', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(3, 'Investasi', 'income', 'Pendapatan dari investasi', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(4, 'Makanan', 'expense', 'Pengeluaran untuk makanan', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(5, 'Transportasi', 'expense', 'Pengeluaran untuk transportasi', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(6, 'Belanja', 'expense', 'Pengeluaran untuk belanja', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(7, 'Hiburan', 'expense', 'Pengeluaran untuk hiburan', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(8, 'Kesehatan', 'expense', 'Pengeluaran untuk kesehatan', '2025-10-18 12:00:00', '2025-10-18 12:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `category_id`, `amount`, `description`, `transaction_date`, `type`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 5000000.00, 'Gaji bulan Oktober', '2025-10-05', 'income', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(2, 1, 4, 150000.00, 'Makan siang restoran', '2025-10-07', 'expense', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(3, 1, 5, 100000.00, 'Bensin motor', '2025-10-08', 'expense', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(4, 1, 6, 750000.00, 'Belanja bulanan', '2025-10-10', 'expense', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(5, 1, 2, 1000000.00, 'Bonus proyek', '2025-10-15', 'income', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(6, 2, 1, 8500000.00, 'Gaji bulan Oktober', '2025-10-05', 'income', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(7, 2, 3, 3000000.00, 'Beli saham', '2025-10-15', 'expense', '2025-10-18 12:00:00', '2025-10-18 12:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`id`, `user_id`, `full_name`, `phone`, `address`, `birth_date`, `created_at`, `updated_at`) VALUES
(1, 1, 'User KA', '081234567890', 'Jl. Contoh No. 123, Jakarta', '1990-05-15', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(2, 2, 'Administrator KA', '081298765432', 'Jl. Admin No. 456, Jakarta', '1985-08-20', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(3, 3, 'John Doe', '08111222333', 'Jl. Merdeka No. 10, Bandung', '1992-03-10', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(4, 4, 'Jane Smith', '08144555666', 'Jl. Sudirman No. 25, Surabaya', '1988-11-25', '2025-10-18 12:00:00', '2025-10-18 12:00:00'),
(5, 5, 'Bob Wilson', '08177888999', 'Jl. Gatot Subroto No. 50, Medan', '1995-07-08', '2025-10-18 12:00:00', '2025-10-18 12:00:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
