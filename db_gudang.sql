-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 12 Des 2025 pada 07.12
-- Versi server: 8.0.30
-- Versi PHP: 8.3.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_gudang`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `goods_in`
--

CREATE TABLE `goods_in` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `date` date NOT NULL,
  `po_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `so_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `quantity` int NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `goods_out`
--

CREATE TABLE `goods_out` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `date` date NOT NULL,
  `po_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `so_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `quantity` int NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Struktur dari tabel `rfid_registrations`
--

CREATE TABLE `rfid_registrations` (
  `id` int NOT NULL,
  `api_product_id` int NOT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `po_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `so_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `name_label` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `batch_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `pcs` int NOT NULL,
  `rfid_tag` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `rfid_registrations`
--

INSERT INTO `rfid_registrations` (`id`, `api_product_id`, `product_name`, `po_number`, `so_number`, `name_label`, `batch_number`, `pcs`, `rfid_tag`, `is_active`, `created_at`) VALUES
(1, 759, 'Bright And Glow All Day Acne Soap', '018/ZAN/PO/XI/2025', '018/ZAN/PO/XI/2025 - A02', 'Tes', '', 1000, 'E28069150000401AD9A45C3D', 0, '2025-12-07 05:53:07'),
(2, 759, 'Bright And Glow All Day Acne Soap', '018/ZAN/PO/XI/2025', '018/ZAN/PO/XI/2025 - A02', 'tes', '1', 1000, 'E28069150000501AD9A4583D', 1, '2025-12-07 06:24:35'),
(3, 1765, 'phytofresh', '005/PNF/PO/XI/2025', '005/PNF/PO/XI/2025 - A01', 'dr.anton', '', 1000, 'E28069150000401AD9A4883D', 0, '2025-12-08 09:37:23'),
(4, 760, 'Bright And Glow All Day Cream', '027/ZAN/PO/XI/2025', '027/ZAN/PO/XI/2025 - A01', 'ABDUL KADIR JAELANI', 'GL 14061999', 2000, 'E28069150000501AD9A4543D', 0, '2025-12-08 15:36:09'),
(5, 760, 'Bright And Glow All Day Cream', '027/ZAN/PO/XI/2025', '027/ZAN/PO/XI/2025 - A01', 'ABDUL KADIR JAELANI', 'GL 14061999', 2000, 'E28069150000401AD9A4A03D', 0, '2025-12-08 15:36:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` bigint UNSIGNED NOT NULL,
  `rfid_tag` varchar(64) NOT NULL,
  `registration_id` bigint UNSIGNED DEFAULT NULL,
  `warehouse_id` bigint UNSIGNED NOT NULL,
  `movement_type` enum('IN','OUT') NOT NULL,
  `movement_time` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(100) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `rfid_tag`, `registration_id`, `warehouse_id`, `movement_type`, `movement_time`, `created_at`, `created_by`, `notes`) VALUES
(1, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-07 06:45:02', '2025-12-07 06:45:02', NULL, ''),
(2, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-07 06:45:02', '2025-12-07 06:45:02', NULL, ''),
(3, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 06:45:27', '2025-12-07 06:45:27', NULL, ''),
(4, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 06:45:27', '2025-12-07 06:45:27', NULL, ''),
(5, 'E28069150000401AD9A45C3D', 1, 2, 'IN', '2025-12-07 07:09:16', '2025-12-07 07:09:16', NULL, ''),
(6, 'E28069150000501AD9A4583D', 2, 2, 'IN', '2025-12-07 07:09:16', '2025-12-07 07:09:16', NULL, ''),
(7, 'E28069150000401AD9A45C3D', 1, 2, 'IN', '2025-12-07 07:09:25', '2025-12-07 07:09:25', NULL, ''),
(8, 'E28069150000501AD9A4583D', 2, 2, 'IN', '2025-12-07 07:09:25', '2025-12-07 07:09:25', NULL, ''),
(9, 'E28069150000401AD9A45C3D', 1, 3, 'OUT', '2025-12-07 14:18:02', '2025-12-07 07:18:02', NULL, ''),
(10, 'E28069150000501AD9A4583D', 2, 3, 'OUT', '2025-12-07 14:18:02', '2025-12-07 07:18:02', NULL, ''),
(11, 'E28069150000401AD9A45C3D', 1, 2, 'OUT', '2025-12-07 14:18:22', '2025-12-07 07:18:22', NULL, ''),
(12, 'E28069150000501AD9A4583D', 2, 2, 'OUT', '2025-12-07 14:18:22', '2025-12-07 07:18:22', NULL, ''),
(13, 'E28069150000401AD9A45C3D', 1, 3, 'IN', '2025-12-07 14:18:52', '2025-12-07 07:18:52', NULL, ''),
(14, 'E28069150000501AD9A4583D', 2, 3, 'IN', '2025-12-07 14:18:52', '2025-12-07 07:18:52', NULL, ''),
(15, 'E28069150000401AD9A45C3D', 1, 2, 'OUT', '2025-12-07 14:23:49', '2025-12-07 07:23:49', NULL, ''),
(16, 'E28069150000501AD9A4583D', 2, 2, 'OUT', '2025-12-07 14:23:49', '2025-12-07 07:23:49', NULL, ''),
(17, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-07 14:24:03', '2025-12-07 07:24:03', NULL, ''),
(18, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-07 14:24:03', '2025-12-07 07:24:03', NULL, ''),
(19, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 14:29:27', '2025-12-07 07:29:27', NULL, ''),
(20, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 14:29:27', '2025-12-07 07:29:27', NULL, ''),
(21, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-07 14:34:14', '2025-12-07 07:34:14', 'admin', ''),
(22, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-07 14:34:14', '2025-12-07 07:34:14', 'admin', ''),
(23, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 14:34:52', '2025-12-07 07:34:52', 'admin', ''),
(24, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 14:34:52', '2025-12-07 07:34:52', 'admin', ''),
(25, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 14:42:07', '2025-12-07 07:42:07', 'admin', ''),
(26, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 14:42:07', '2025-12-07 07:42:07', 'admin', ''),
(27, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-07 15:17:05', '2025-12-07 08:17:05', 'admin', ''),
(28, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-07 15:17:05', '2025-12-07 08:17:05', 'admin', ''),
(29, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 15:20:28', '2025-12-07 08:20:28', 'admin', ''),
(30, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 15:20:28', '2025-12-07 08:20:28', 'admin', ''),
(31, 'E28069150000401AD9A45C3D', 1, 2, 'IN', '2025-12-07 15:27:19', '2025-12-07 08:27:19', 'admin', ''),
(32, 'E28069150000501AD9A4583D', 2, 2, 'IN', '2025-12-07 15:27:19', '2025-12-07 08:27:19', 'admin', ''),
(33, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 15:36:05', '2025-12-07 08:36:05', 'admin', ''),
(34, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 15:36:05', '2025-12-07 08:36:05', 'admin', ''),
(35, 'E28069150000401AD9A45C3D', 1, 2, 'IN', '2025-12-07 15:41:07', '2025-12-07 08:41:07', 'admin', ''),
(36, 'E28069150000501AD9A4583D', 2, 2, 'IN', '2025-12-07 15:41:07', '2025-12-07 08:41:07', 'admin', ''),
(37, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 15:48:30', '2025-12-07 08:48:30', 'admin', ''),
(38, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-07 15:48:30', '2025-12-07 08:48:30', 'admin', ''),
(41, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-07 16:01:55', '2025-12-07 09:01:55', 'admin', ''),
(42, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-07 16:01:55', '2025-12-07 09:01:55', 'admin', ''),
(43, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-07 16:17:33', '2025-12-07 09:17:33', 'admin', ''),
(44, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-07 18:49:38', '2025-12-07 11:49:38', 'System', 'A'),
(45, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-07 18:49:38', '2025-12-07 11:49:39', 'System', 'A'),
(46, 'E28069150000401AD9A45C3D', 1, 2, 'OUT', '2025-12-07 19:03:08', '2025-12-07 12:03:08', 'System', ''),
(47, 'E28069150000501AD9A4583D', 2, 2, 'OUT', '2025-12-07 19:03:08', '2025-12-07 12:03:08', 'System', ''),
(48, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-07 19:03:38', '2025-12-07 12:03:38', 'admin', ''),
(49, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-07 19:03:38', '2025-12-07 12:03:38', 'admin', ''),
(50, 'E28069150000401AD9A45C3D', 1, 2, 'IN', '2025-12-07 19:03:50', '2025-12-07 12:03:50', 'admin', ''),
(51, 'E28069150000501AD9A4583D', 2, 2, 'IN', '2025-12-07 19:03:50', '2025-12-07 12:03:50', 'admin', ''),
(52, 'E28069150000401AD9A45C3D', 1, 2, 'OUT', '2025-12-07 19:04:07', '2025-12-07 12:04:07', 'admin', ''),
(53, 'E28069150000501AD9A4583D', 2, 2, 'OUT', '2025-12-07 19:04:07', '2025-12-07 12:04:07', 'admin', ''),
(54, 'E28069150000401AD9A45C3D', 1, 4, 'IN', '2025-12-08 13:38:07', '2025-12-08 06:38:07', 'admin', ''),
(55, 'E28069150000501AD9A4583D', 2, 4, 'IN', '2025-12-08 13:38:07', '2025-12-08 06:38:07', 'admin', ''),
(56, 'E28069150000501AD9A4583D', 2, 4, 'OUT', '2025-12-08 14:01:02', '2025-12-08 07:01:02', 'admin', ''),
(57, 'E28069150000401AD9A45C3D', 1, 2, 'OUT', '2025-12-08 14:17:43', '2025-12-08 07:17:43', 'admin', ''),
(58, 'E28069150000401AD9A45C3D', 1, 2, 'OUT', '2025-12-08 14:18:33', '2025-12-08 07:18:33', 'admin', ''),
(59, 'E28069150000401AD9A45C3D', 1, 4, 'OUT', '2025-12-08 14:19:01', '2025-12-08 07:19:01', 'admin', ''),
(60, 'E28069150000401AD9A45C3D', 1, 4, 'OUT', '2025-12-08 14:28:16', '2025-12-08 07:28:16', 'admin', ''),
(61, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-08 14:57:23', '2025-12-08 07:57:23', 'admin', ''),
(62, 'E28069150000501AD9A4583D', 2, 1, 'OUT', '2025-12-08 14:57:23', '2025-12-08 07:57:23', 'admin', ''),
(63, 'E28069150000401AD9A45C3D', 1, 3, 'OUT', '2025-12-08 15:17:31', '2025-12-08 08:17:31', 'admin', ''),
(64, 'E28069150000401AD9A45C3D', 1, 3, 'OUT', '2025-12-08 15:18:35', '2025-12-08 08:18:35', 'admin', ''),
(65, 'E28069150000501AD9A4583D', 2, 3, 'OUT', '2025-12-08 15:18:35', '2025-12-08 08:18:35', 'admin', ''),
(66, 'E28069150000401AD9A4883D', 3, 3, 'IN', '2025-12-08 16:41:11', '2025-12-08 09:41:11', 'System', ''),
(67, 'E28069150000401AD9A4883D', 3, 2, 'OUT', '2025-12-08 16:43:26', '2025-12-08 09:43:26', 'System', ''),
(68, 'E28069150000401AD9A45C3D', 1, 4, 'IN', '2025-12-08 18:03:23', '2025-12-08 11:03:23', 'System', ''),
(69, 'E28069150000401AD9A4883D', 3, 4, 'IN', '2025-12-08 18:03:23', '2025-12-08 11:03:23', 'System', ''),
(70, 'E28069150000501AD9A4583D', 2, 4, 'IN', '2025-12-08 18:03:23', '2025-12-08 11:03:23', 'System', ''),
(71, 'E28069150000401AD9A45C3D', 1, 4, 'OUT', '2025-12-08 18:14:49', '2025-12-08 11:14:49', 'System', ''),
(72, 'E28069150000401AD9A4883D', 3, 4, 'OUT', '2025-12-08 18:14:49', '2025-12-08 11:14:49', 'System', ''),
(73, 'E28069150000401AD9A45C3D', 1, 3, 'OUT', '2025-12-08 18:16:37', '2025-12-08 11:16:37', 'System', ''),
(74, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-08 20:29:21', '2025-12-08 13:29:21', 'admin', 'aaaaa'),
(75, 'E28069150000401AD9A4883D', 3, 1, 'OUT', '2025-12-08 20:29:21', '2025-12-08 13:29:21', 'admin', 'aaaaa'),
(76, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-08 20:37:15', '2025-12-08 13:37:15', 'admin', ''),
(77, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-08 20:37:31', '2025-12-08 13:37:31', 'admin', ''),
(78, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-08 20:39:39', '2025-12-08 13:39:39', 'admin', ''),
(79, 'E28069150000401AD9A45C3D', 1, 4, 'OUT', '2025-12-08 20:46:28', '2025-12-08 13:46:28', 'admin', 'TES'),
(80, 'E28069150000401AD9A4883D', 3, 4, 'OUT', '2025-12-08 20:46:28', '2025-12-08 13:46:28', 'admin', 'TES'),
(81, 'E28069150000501AD9A4583D', 2, 4, 'OUT', '2025-12-08 20:46:28', '2025-12-08 13:46:28', 'admin', 'TES'),
(82, 'E28069150000401AD9A45C3D', 1, 4, 'OUT', '2025-12-08 20:51:23', '2025-12-08 13:51:23', 'admin', 'TES'),
(83, 'E28069150000401AD9A4883D', 3, 4, 'OUT', '2025-12-08 20:51:23', '2025-12-08 13:51:23', 'admin', 'TES'),
(84, 'E28069150000501AD9A4583D', 2, 4, 'OUT', '2025-12-08 20:51:23', '2025-12-08 13:51:23', 'admin', 'TES'),
(85, 'E28069150000401AD9A4A03D', 5, 1, 'IN', '2025-12-08 22:39:00', '2025-12-08 15:39:00', 'admin', 'coba catatan di barang masuk'),
(86, 'E28069150000501AD9A4543D', 4, 1, 'IN', '2025-12-08 22:39:00', '2025-12-08 15:39:00', 'admin', 'coba catatan di barang masuk'),
(87, 'E28069150000401AD9A4A03D', 5, 1, 'OUT', '2025-12-08 22:44:00', '2025-12-08 15:44:00', 'admin', ''),
(88, 'E28069150000501AD9A4543D', 4, 1, 'OUT', '2025-12-08 22:44:00', '2025-12-08 15:44:00', 'admin', ''),
(89, 'E28069150000401AD9A4A03D', 5, 2, 'OUT', '2025-12-08 22:45:58', '2025-12-08 15:45:58', 'admin', ''),
(90, 'E28069150000501AD9A4543D', 4, 2, 'OUT', '2025-12-08 22:45:58', '2025-12-08 15:45:58', 'admin', ''),
(91, 'E28069150000401AD9A4A03D', 5, 1, 'OUT', '2025-12-08 22:47:09', '2025-12-08 15:47:09', 'admin', 'coba catatan barang keluar'),
(92, 'E28069150000501AD9A4543D', 4, 1, 'OUT', '2025-12-08 22:47:09', '2025-12-08 15:47:09', 'admin', 'coba catatan barang keluar'),
(93, 'E28069150000501AD9A4543D', 4, 2, 'IN', '2025-12-08 22:49:12', '2025-12-08 15:49:12', 'admin', ''),
(94, 'E28069150000401AD9A4A03D', 5, 2, 'IN', '2025-12-08 22:49:12', '2025-12-08 15:49:12', 'admin', ''),
(95, 'E28069150000401AD9A45C3D', 1, 4, 'OUT', '2025-12-09 05:04:37', '2025-12-08 22:04:37', 'admin', 'aaaaaa'),
(96, 'E28069150000501AD9A4543D', 4, 4, 'OUT', '2025-12-09 05:04:37', '2025-12-08 22:04:37', 'admin', 'aaaaaa'),
(97, 'E28069150000501AD9A4583D', 2, 4, 'OUT', '2025-12-09 05:04:37', '2025-12-08 22:04:37', 'admin', 'aaaaaa'),
(98, 'E28069150000401AD9A4883D', 3, 4, 'OUT', '2025-12-09 05:04:37', '2025-12-08 22:04:37', 'admin', 'aaaaaa'),
(99, 'E28069150000401AD9A4A03D', 5, 4, 'OUT', '2025-12-09 05:04:37', '2025-12-08 22:04:37', 'admin', 'aaaaaa'),
(100, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-09 06:24:52', '2025-12-08 23:24:52', 'admin', ''),
(101, 'E28069150000401AD9A4883D', 3, 1, 'IN', '2025-12-09 06:24:52', '2025-12-08 23:24:52', 'admin', ''),
(102, 'E28069150000401AD9A4A03D', 5, 1, 'IN', '2025-12-09 06:24:52', '2025-12-08 23:24:52', 'admin', ''),
(103, 'E28069150000501AD9A4543D', 4, 1, 'IN', '2025-12-09 06:24:52', '2025-12-08 23:24:52', 'admin', ''),
(104, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-09 06:24:52', '2025-12-08 23:24:52', 'admin', ''),
(105, 'E28069150000401AD9A4883D', 3, 1, 'IN', '2025-12-09 06:25:04', '2025-12-08 23:25:04', 'admin', ''),
(106, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-09 06:25:04', '2025-12-08 23:25:04', 'admin', ''),
(107, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-09 06:25:04', '2025-12-08 23:25:04', 'admin', ''),
(108, 'E28069150000401AD9A4A03D', 5, 1, 'IN', '2025-12-09 06:25:04', '2025-12-08 23:25:04', 'admin', ''),
(109, 'E28069150000501AD9A4543D', 4, 1, 'IN', '2025-12-09 06:25:04', '2025-12-08 23:25:04', 'admin', ''),
(110, 'E28069150000401AD9A4883D', 3, 1, 'IN', '2025-12-09 06:26:22', '2025-12-08 23:26:22', 'admin', ''),
(111, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-09 06:26:22', '2025-12-08 23:26:22', 'admin', ''),
(112, 'E28069150000501AD9A4583D', 2, 1, 'IN', '2025-12-09 06:26:22', '2025-12-08 23:26:22', 'admin', ''),
(113, 'E28069150000401AD9A4A03D', 5, 1, 'IN', '2025-12-09 06:26:22', '2025-12-08 23:26:22', 'admin', ''),
(114, 'E28069150000501AD9A4543D', 4, 1, 'IN', '2025-12-09 06:26:22', '2025-12-08 23:26:22', 'admin', ''),
(115, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-09 06:28:46', '2025-12-08 23:28:46', 'admin', ''),
(116, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-09 06:28:59', '2025-12-08 23:28:59', 'admin', ''),
(117, 'E28069150000401AD9A45C3D', 1, 1, 'IN', '2025-12-09 06:29:12', '2025-12-08 23:29:12', 'admin', ''),
(118, 'E28069150000501AD9A4543D', 4, 1, 'OUT', '2025-12-09 06:30:58', '2025-12-08 23:30:58', 'admin', ''),
(119, 'E28069150000501AD9A4543D', 4, 1, 'OUT', '2025-12-09 06:31:40', '2025-12-08 23:31:40', 'admin', ''),
(120, 'E28069150000501AD9A4543D', 4, 1, 'OUT', '2025-12-09 06:31:49', '2025-12-08 23:31:49', 'admin', ''),
(121, 'E28069150000501AD9A4543D', 4, 1, 'OUT', '2025-12-09 06:31:56', '2025-12-08 23:31:56', 'admin', ''),
(122, 'E28069150000501AD9A4543D', 4, 1, 'OUT', '2025-12-09 06:32:01', '2025-12-08 23:32:01', 'admin', ''),
(123, 'E28069150000501AD9A4543D', 4, 1, 'OUT', '2025-12-09 06:32:05', '2025-12-08 23:32:05', 'admin', ''),
(124, 'E28069150000401AD9A45C3D', 1, 2, 'OUT', '2025-12-09 18:40:21', '2025-12-09 11:40:21', 'System', 'aa'),
(125, 'E28069150000401AD9A4883D', 3, 2, 'OUT', '2025-12-09 18:40:21', '2025-12-09 11:40:21', 'System', 'aa'),
(126, 'E28069150000401AD9A45C3D', 1, 4, 'OUT', '2025-12-10 06:08:37', '2025-12-09 23:08:37', 'System', ''),
(127, 'E28069150000401AD9A45C3D', 1, 4, 'OUT', '2025-12-10 07:56:45', '2025-12-10 00:56:45', 'System', ''),
(128, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-10 07:56:56', '2025-12-10 00:56:56', 'System', ''),
(129, 'E28069150000401AD9A45C3D', 1, 1, 'OUT', '2025-12-10 08:07:46', '2025-12-10 01:07:46', 'System', ''),
(130, 'E28069150000401AD9A45C3D', 1, 4, 'OUT', '2025-12-10 08:12:32', '2025-12-10 01:12:32', 'System', ''),
(131, 'E28069150000401AD9A45C3D', 1, 2, 'IN', '2025-12-11 15:19:23', '2025-12-11 08:19:23', 'System', ''),
(132, 'E28069150000401AD9A45C3D', 1, 3, 'OUT', '2025-12-11 15:20:13', '2025-12-11 08:20:13', 'System', 'DUA NAGA'),
(133, 'E28069150000401AD9A4A03D', 5, 2, 'OUT', '2025-12-12 13:20:59', '2025-12-12 06:20:59', 'System', 'aaa'),
(134, 'E28069150000401AD9A4A03D', 5, 2, 'OUT', '2025-12-12 13:26:04', '2025-12-12 06:26:04', 'System', ''),
(135, 'E28069150000401AD9A4A03D', 5, 2, 'OUT', '2025-12-12 13:30:43', '2025-12-12 06:30:43', 'System', 'BBB'),
(136, 'E28069150000401AD9A4A03D', 5, 4, 'OUT', '2025-12-12 13:31:11', '2025-12-12 06:31:11', 'System', 'BBB'),
(137, 'E28069150000401AD9A4A03D', 5, 1, 'OUT', '2025-12-12 13:31:37', '2025-12-12 06:31:37', 'System', 'BBB'),
(138, 'E28069150000401AD9A4A03D', 5, 3, 'OUT', '2025-12-12 13:31:59', '2025-12-12 06:31:59', 'System', 'BBB');

-- --------------------------------------------------------

--
-- Struktur dari tabel `surat_jalan`
--

CREATE TABLE `surat_jalan` (
  `id` bigint UNSIGNED NOT NULL,
  `no_sj` varchar(50) NOT NULL,
  `tanggal_sj` date NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_address` varchar(255) NOT NULL,
  `po_number` varchar(100) DEFAULT NULL,
  `warehouse_id` bigint UNSIGNED NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `surat_jalan`
--

INSERT INTO `surat_jalan` (`id`, `no_sj`, `tanggal_sj`, `customer_name`, `customer_address`, `po_number`, `warehouse_id`, `notes`, `created_by`, `created_at`) VALUES
(1, 'SJ/2512/0001', '2025-12-07', 'A', 'A', '018/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-07 07:42:07'),
(2, 'SJ/2512/0002', '2025-12-07', 'A', 'A', '018/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-07 08:20:28'),
(3, 'SJ/2512/0003', '2025-12-07', 'a', 'a', '018/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-07 08:36:05'),
(4, 'SJ/2512/0004', '2025-12-07', 'tes', 'tes', '018/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-07 08:48:30'),
(5, 'SJ/2512/0005', '2025-12-07', 'a', 'a', '018/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-07 09:17:33'),
(6, 'SJ/2512/0006', '2025-12-07', 'a', 'a', '018/ZAN/PO/XI/2025', 2, '', 'System', '2025-12-07 12:03:08'),
(7, 'SJ/2512/0007', '2025-12-07', 'a', 'a', '018/ZAN/PO/XI/2025', 2, '', 'admin', '2025-12-07 12:04:07'),
(8, 'SJ/2512/0008', '2025-12-08', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '018/ZAN/PO/XI/2025', 4, '', 'admin', '2025-12-08 07:01:02'),
(9, 'SJ/2512/0009', '2025-12-08', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '018/ZAN/PO/XI/2025', 2, '', 'admin', '2025-12-08 07:17:43'),
(10, 'SJ/2512/0010', '2025-12-08', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '018/ZAN/PO/XI/2025', 2, '', 'admin', '2025-12-08 07:18:33'),
(11, 'SJ/2512/0011', '2025-12-08', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '018/ZAN/PO/XI/2025', 4, '', 'admin', '2025-12-08 07:19:01'),
(12, 'SJ/2512/0012', '2025-12-08', '(1) LUKI ADHI SULAKSONO(2) BAGASKORO PONCO NUGROHO', '(1) Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah\r\n(2) Perum Gentan Pelangi Indah No. B-17 RT.008 / RW.006,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 4, '', 'admin', '2025-12-08 07:28:16'),
(13, 'SJ/2512/0013', '2025-12-08', 'BAGASKORO PONCO NUGROHO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 1, '', 'admin', '2025-12-08 07:57:23'),
(14, 'SJ/2512/0014', '2025-12-08', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '018/ZAN/PO/XI/2025', 3, '', 'admin', '2025-12-08 08:17:31'),
(15, 'SJ/2512/0015', '2025-12-08', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '018/ZAN/PO/XI/2025', 3, '', 'admin', '2025-12-08 08:18:35'),
(16, 'SJ/2512/0016', '2025-12-08', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 2, '', 'System', '2025-12-08 09:43:26'),
(17, 'SJ/2512/0017', '2025-12-08', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 4, '', 'System', '2025-12-08 11:14:49'),
(18, 'SJ/2512/0018', '2025-12-08', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '018/ZAN/PO/XI/2025', 3, '', 'System', '2025-12-08 11:16:37'),
(19, 'SJ/2512/0019', '2025-12-08', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 1, 'aaaaa', 'admin', '2025-12-08 13:29:21'),
(20, 'SJ/2512/0020', '2025-12-08', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 1, '', 'admin', '2025-12-08 13:37:15'),
(21, 'SJ/2512/0021', '2025-12-08', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 1, '', 'admin', '2025-12-08 13:37:31'),
(22, 'SJ/2512/0022', '2025-12-08', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 1, '', 'admin', '2025-12-08 13:39:39'),
(23, 'TES', '2025-12-08', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 4, 'TES', 'admin', '2025-12-08 13:46:28'),
(24, 'TES', '2025-12-08', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 4, 'TES', 'admin', '2025-12-08 13:51:23'),
(25, '-', '2025-12-08', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '027/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-08 15:44:00'),
(26, '001', '2025-12-08', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 2, '', 'admin', '2025-12-08 15:45:58'),
(27, 'SJ/2512/0027', '2025-12-08', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '027/ZAN/PO/XI/2025', 1, 'coba catatan barang keluar', 'admin', '2025-12-08 15:47:09'),
(28, 'SJ/2512/0028', '2025-12-09', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 4, 'aaaaaa', 'admin', '2025-12-08 22:04:37'),
(29, 'SJ/2512/0029', '2025-12-09', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '027/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-08 23:30:58'),
(30, 'SJ/2512/0030', '2025-12-09', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '027/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-08 23:31:40'),
(31, 'SJ/2512/0031', '2025-12-09', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '027/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-08 23:31:49'),
(32, 'SJ/2512/0032', '2025-12-09', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '027/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-08 23:31:56'),
(33, 'SJ/2512/0033', '2025-12-09', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '027/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-08 23:32:01'),
(34, 'SJ/2512/0034', '2025-12-09', 'ABDUL KADIR JAELANI', 'Mattoangin, Rt 2 Rw 03 Bonto Jai, Bissapu, Kabupaten Bantaeng, Sulawesi selatan', '027/ZAN/PO/XI/2025', 1, '', 'admin', '2025-12-08 23:32:05'),
(35, 'SJ/2512/0035', '2025-12-09', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 2, 'aa', 'System', '2025-12-09 11:40:21'),
(36, 'SJ/2512/0036', '2025-12-10', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 4, '', 'System', '2025-12-09 23:08:37'),
(37, 'SJ/2512/0037', '2025-12-10', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 4, '', 'System', '2025-12-10 00:56:45'),
(38, 'SJ/2512/0038', '2025-12-10', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 1, '', 'System', '2025-12-10 00:56:56'),
(39, 'SJ/2512/0039', '2025-12-10', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 1, '', 'System', '2025-12-10 01:07:46'),
(40, 'SJ/2512/0040', '2025-12-10', 'AIDA FARADINA', 'Jl. Pahlawan RT. 004/ RW. 001,  Kel/Desa Cabenge,  Kec. Lilirilau,  Kab. Soppeng,  Prov. Sulawesi Selatan', '001/DNK/PO/XII/2025', 4, '', 'System', '2025-12-10 01:12:32'),
(41, 'SJ/2512/0041', '2025-12-11', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 3, 'DUA NAGA', 'System', '2025-12-11 08:20:13'),
(42, 'SJ/2512/0042', '2025-12-12', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 2, 'aaa', 'System', '2025-12-12 06:20:59'),
(43, 'SJ/2512/0043', '2025-12-12', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 2, '', 'System', '2025-12-12 06:26:04'),
(44, 'SJ/2512/0044', '2025-12-12', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 2, 'BBB', 'System', '2025-12-12 06:30:43'),
(45, 'SJ/2512/0045', '2025-12-12', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 4, 'BBB', 'System', '2025-12-12 06:31:11'),
(46, 'SJ/2512/0046', '2025-12-12', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 1, 'BBB', 'System', '2025-12-12 06:31:37'),
(47, 'SJ/2512/0047', '2025-12-12', 'LUKI ADHI SULAKSONO', 'Jl. Empu Tantular No.11 RT.004 / RW.005,  Kel/Desa Gentan,  Kec. Baki,  Kab. Sukoharjo,  Prov. Jawa Tengah', '001/DNK/PO/XI/2025', 3, 'BBB', 'System', '2025-12-12 06:31:59');

-- --------------------------------------------------------

--
-- Struktur dari tabel `surat_jalan_items`
--

CREATE TABLE `surat_jalan_items` (
  `id` bigint UNSIGNED NOT NULL,
  `surat_jalan_id` bigint UNSIGNED NOT NULL,
  `rfid_tag` varchar(64) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `qty` int NOT NULL DEFAULT '1',
  `unit` varchar(20) DEFAULT 'PCS'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `surat_jalan_items`
--

INSERT INTO `surat_jalan_items` (`id`, `surat_jalan_id`, `rfid_tag`, `product_name`, `batch_number`, `qty`, `unit`) VALUES
(1, 1, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(2, 1, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(3, 2, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(4, 2, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(5, 3, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(6, 3, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(7, 4, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(8, 4, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(9, 5, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(10, 6, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(11, 6, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(12, 7, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(13, 7, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(14, 8, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(15, 9, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(16, 10, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(17, 11, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(18, 12, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(19, 13, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(20, 13, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(21, 14, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(22, 15, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(23, 15, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(24, 16, 'E28069150000401AD9A4883D', 'phytofresh', '', 1000, 'PCS'),
(25, 17, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(26, 17, 'E28069150000401AD9A4883D', 'phytofresh', '', 1000, 'PCS'),
(27, 18, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(28, 19, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(29, 19, 'E28069150000401AD9A4883D', 'phytofresh', '', 1000, 'PCS'),
(30, 20, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(31, 21, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(32, 22, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(33, 23, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(34, 23, 'E28069150000401AD9A4883D', 'phytofresh', '', 1000, 'PCS'),
(35, 23, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(36, 24, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(37, 24, 'E28069150000401AD9A4883D', 'phytofresh', '', 1000, 'PCS'),
(38, 24, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(39, 25, 'E28069150000401AD9A4A03D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(40, 25, 'E28069150000501AD9A4543D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(41, 26, 'E28069150000401AD9A4A03D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(42, 26, 'E28069150000501AD9A4543D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(43, 27, 'E28069150000401AD9A4A03D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(44, 27, 'E28069150000501AD9A4543D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(45, 28, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(46, 28, 'E28069150000501AD9A4543D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(47, 28, 'E28069150000501AD9A4583D', 'Bright And Glow All Day Acne Soap', '1', 1000, 'PCS'),
(48, 28, 'E28069150000401AD9A4883D', 'phytofresh', '', 1000, 'PCS'),
(49, 28, 'E28069150000401AD9A4A03D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(50, 29, 'E28069150000501AD9A4543D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(51, 30, 'E28069150000501AD9A4543D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(52, 31, 'E28069150000501AD9A4543D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(53, 32, 'E28069150000501AD9A4543D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(54, 33, 'E28069150000501AD9A4543D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(55, 34, 'E28069150000501AD9A4543D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(56, 35, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(57, 35, 'E28069150000401AD9A4883D', 'phytofresh', '', 1000, 'PCS'),
(58, 36, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(59, 37, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(60, 38, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(61, 39, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(62, 40, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(63, 41, 'E28069150000401AD9A45C3D', 'Bright And Glow All Day Acne Soap', '', 1000, 'PCS'),
(64, 42, 'E28069150000401AD9A4A03D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(65, 43, 'E28069150000401AD9A4A03D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(66, 44, 'E28069150000401AD9A4A03D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(67, 45, 'E28069150000401AD9A4A03D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(68, 46, 'E28069150000401AD9A4A03D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS'),
(69, 47, 'E28069150000401AD9A4A03D', 'Bright And Glow All Day Cream', 'GL 14061999', 2000, 'PCS');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `password`, `role`, `is_active`, `created_at`) VALUES
(1, 'admin', 'Administrator', 'admin123', 'admin', 1, '2025-12-07 07:02:43');

-- --------------------------------------------------------

--
-- Struktur dari tabel `warehouses`
--

CREATE TABLE `warehouses` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `warehouses`
--

INSERT INTO `warehouses` (`id`, `name`, `code`, `description`, `created_at`, `updated_at`) VALUES
(1, 'CV. Zweena Adi Nugraha', 'CV. Zweena Adi Nugraha', 'Gudang utama', '2025-12-07 06:39:39', '2025-12-08 03:53:51'),
(2, 'PT. Dua Naga Kosmetindo', 'PT. Dua Naga Kosmetindo', 'Gudang bahan baku', '2025-12-07 06:39:39', '2025-12-08 03:54:08'),
(3, 'PT. Phytomed Neo Farma', 'PT. Phytomed Neo Farma', 'Gudang finished goods', '2025-12-07 06:39:39', '2025-12-08 03:54:22'),
(4, 'CV. Indo Naga Food', 'CV. Indo Naga Food', 'Perusahaan', '2025-12-08 03:54:54', '2025-12-08 03:54:54');

-- --------------------------------------------------------

--
-- Struktur dari tabel `warehouse_items`
--

CREATE TABLE `warehouse_items` (
  `id` int NOT NULL,
  `api_product_id` int NOT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `storage_location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `batch_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=DYNAMIC;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `goods_in`
--
ALTER TABLE `goods_in`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `item_id` (`item_id`) USING BTREE;

--
-- Indeks untuk tabel `goods_out`
--
ALTER TABLE `goods_out`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `item_id` (`item_id`) USING BTREE;

--
-- Indeks untuk tabel `rfid_registrations`
--
ALTER TABLE `rfid_registrations`
  ADD PRIMARY KEY (`id`) USING BTREE;

--
-- Indeks untuk tabel `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tag_time` (`rfid_tag`,`movement_time`),
  ADD KEY `idx_wh_type_time` (`warehouse_id`,`movement_type`,`movement_time`);

--
-- Indeks untuk tabel `surat_jalan`
--
ALTER TABLE `surat_jalan`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `surat_jalan_items`
--
ALTER TABLE `surat_jalan_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sj_items_header` (`surat_jalan_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_warehouses_code` (`code`);

--
-- Indeks untuk tabel `warehouse_items`
--
ALTER TABLE `warehouse_items`
  ADD PRIMARY KEY (`id`) USING BTREE;

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `goods_in`
--
ALTER TABLE `goods_in`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `goods_out`
--
ALTER TABLE `goods_out`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `rfid_registrations`
--
ALTER TABLE `rfid_registrations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT untuk tabel `surat_jalan`
--
ALTER TABLE `surat_jalan`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT untuk tabel `surat_jalan_items`
--
ALTER TABLE `surat_jalan_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `warehouse_items`
--
ALTER TABLE `warehouse_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `goods_in`
--
ALTER TABLE `goods_in`
  ADD CONSTRAINT `goods_in_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `warehouse_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `goods_out`
--
ALTER TABLE `goods_out`
  ADD CONSTRAINT `goods_out_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `warehouse_items` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `surat_jalan_items`
--
ALTER TABLE `surat_jalan_items`
  ADD CONSTRAINT `fk_sj_items_header` FOREIGN KEY (`surat_jalan_id`) REFERENCES `surat_jalan` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
