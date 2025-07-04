-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 04, 2025 at 04:21 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kai`
--

-- --------------------------------------------------------

--
-- Table structure for table `import_data`
--

CREATE TABLE `import_data` (
  `id` int(11) NOT NULL,
  `laporan_laba_rugi_komprehensif` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `investasi`
--

CREATE TABLE `investasi` (
  `id` int(11) NOT NULL,
  `no` int(11) NOT NULL,
  `uraian` varchar(255) DEFAULT NULL,
  `wbs` varchar(50) DEFAULT NULL,
  `lokasi_pengadaan` varchar(255) DEFAULT NULL,
  `volume_satuan` varchar(50) DEFAULT NULL,
  `harga_satuan` decimal(20,2) DEFAULT NULL,
  `jumlah_dana` decimal(20,2) DEFAULT NULL,
  `budget_tahun_2024` decimal(20,2) DEFAULT NULL,
  `tambahan_dana` decimal(20,2) DEFAULT NULL,
  `total_tahun_2024` decimal(20,2) DEFAULT NULL,
  `commitment` decimal(20,2) DEFAULT NULL,
  `actual` decimal(20,2) DEFAULT NULL,
  `consumed_budget` decimal(20,2) DEFAULT NULL,
  `available_budget` decimal(20,2) DEFAULT NULL,
  `progres_saat_ini` text DEFAULT NULL,
  `tanggal_kontrak` date DEFAULT NULL,
  `no_kontrak` varchar(100) DEFAULT NULL,
  `nilai_kontrak` decimal(20,2) DEFAULT NULL,
  `ket` text DEFAULT NULL,
  `input_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laporan`
--

CREATE TABLE `laporan` (
  `id` int(11) NOT NULL,
  `kategori` enum('pendapatan','beban','laba rugi usaha','pendapatan beban lain lain','laba rugi sebelum pajak penghasilan','pajak penghasilan','laba rugi bersih tahun berjalan','kepentingan non pengendali','laba yang dapat diatribusikan kepada pemilik entitas induk') NOT NULL DEFAULT 'pendapatan',
  `Uraian` varchar(255) NOT NULL,
  `REALISASI_TAHUN_LALU` decimal(20,2) DEFAULT 0.00,
  `ANGGARAN_TAHUN_INI` decimal(20,2) DEFAULT 0.00,
  `REALISASI_TAHUN_INI` decimal(20,2) DEFAULT 0.00,
  `ANGGARAN_PER_TAHUN` decimal(20,2) DEFAULT 0.00,
  `ACH_1` decimal(6,2) DEFAULT 0.00,
  `GRO` decimal(6,2) DEFAULT 0.00,
  `ACH_2` decimal(6,2) DEFAULT 0.00,
  `ANALISIS_VERTICAL` decimal(6,2) DEFAULT 0.00,
  `input_date` date DEFAULT curdate(),
  `parent_id` int(11) DEFAULT NULL,
  `nomor` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `tahun` int(11) NOT NULL DEFAULT 0,
  `bulan` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`, `profile_picture`) VALUES
(5, 'admin', 'admin@example.com', '$2y$10$U4YQUchx/86fsJgLhfJfd.D90zIDPna8DMdXZbtligp/sDyux92aq', '2025-05-23 03:19:11', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `laporan`
--
ALTER TABLE `laporan`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `laporan`
--
ALTER TABLE `laporan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
