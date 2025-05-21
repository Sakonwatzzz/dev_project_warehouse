-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 09, 2025 at 07:20 PM
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
-- Database: `warehouse`
--

-- --------------------------------------------------------

--
-- Table structure for table `lot`
--

CREATE TABLE `lot` (
  `lot_id` int(11) NOT NULL,
  `lot_name` varchar(255) DEFAULT NULL,
  `lot_detail` varchar(255) DEFAULT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `lot_date` datetime DEFAULT NULL,
  `lot_status_id` int(11) DEFAULT NULL,
  `warehouse_from_id` int(11) DEFAULT NULL,
  `warehouse_inout` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lot`
--

INSERT INTO `lot` (`lot_id`, `lot_name`, `lot_detail`, `warehouse_id`, `supplier_id`, `lot_date`, `lot_status_id`, `warehouse_from_id`, `warehouse_inout`) VALUES
(1, 'LotA1', 'รับจากชายแดนจำนวนน้อย', 1, 1, '2025-04-05 02:00:08', 1, 1, 1),
(2, 'lot_a333', 'รับจากชายแดนจำนวนน้อย', 5, 1, '2025-04-25 19:08:39', 2, 1, 1),
(3, 'lot_a335', 'รับจากชายแดนจำนวนน้อย', 1, 1, '2025-04-25 19:12:31', 1, 1, 1),
(6, 'lot', '5424', 1, 1, '2025-05-09 23:24:02', 2, 1, 1),
(9, 'WT-005', '', 2, 1, '2025-05-09 23:39:13', 2, 1, 2),
(10, 'WT-006', 'รับจากชายแดนจำนวนน้อย', 1, 2, '2025-05-09 23:40:53', 2, 5, 2),
(11, 'sdfsdfsd', '4245245', 1, 1, '2025-05-09 23:49:25', 2, 1, 1),
(12, 'WT-007', '', 2, 1, '2025-05-10 00:13:37', 1, 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `lot_product`
--

CREATE TABLE `lot_product` (
  `lot_product_id` bigint(20) NOT NULL,
  `lot_id` int(11) DEFAULT NULL,
  `lot_name` varchar(255) DEFAULT NULL,
  `lot_product_qty` int(11) DEFAULT NULL,
  `lot_product_remain` int(11) DEFAULT NULL,
  `lot_product_capital` int(11) DEFAULT NULL,
  `lot_product_date` datetime DEFAULT NULL,
  `lot_product_status` int(11) DEFAULT NULL,
  `product_id` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lot_product`
--

INSERT INTO `lot_product` (`lot_product_id`, `lot_id`, `lot_name`, `lot_product_qty`, `lot_product_remain`, `lot_product_capital`, `lot_product_date`, `lot_product_status`, `product_id`) VALUES
(1, 1, NULL, 50, 41, 15, '2025-04-05 02:00:15', 1, '43534534534'),
(2, 1, NULL, 50, 27, 25, '2025-04-05 02:00:16', 1, '3453534534'),
(3, 1, NULL, 50, 30, 25, '2025-04-05 02:00:17', 1, '34534534534'),
(4, 1, NULL, 50, 0, 45, '2025-04-05 02:00:18', 1, '345345345'),
(6, 2, NULL, 50, 13, 35, '2025-04-25 19:10:10', 1, '3541466646'),
(7, 2, NULL, 50, 25, 20, '2025-04-25 19:11:08', 1, '34534534534'),
(8, 3, NULL, 50, 14, 15, '2025-04-25 19:12:40', 1, '452455424'),
(10, 6, NULL, 10, 10, 20, '2025-05-09 23:24:12', 1, '548454242'),
(11, 6, NULL, 10, 5, 20, '2025-05-09 23:24:21', 1, '345345345'),
(12, 9, NULL, 5, 5, 20, '2025-05-09 23:39:32', 1, '345345345'),
(13, 10, NULL, 25, 25, 20, '2025-05-09 23:42:15', 1, '34534534534'),
(14, 11, NULL, 100, 100, 35, '2025-05-09 23:49:40', 1, '6536535'),
(15, 11, NULL, 100, 100, 25, '2025-05-09 23:49:41', 1, '65365653'),
(20, 12, NULL, 1, 1, 20, '2025-05-10 00:14:53', 1, '34534534534');

-- --------------------------------------------------------

--
-- Table structure for table `lot_status`
--

CREATE TABLE `lot_status` (
  `lot_status_id` int(11) NOT NULL,
  `lot_status_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lot_status`
--

INSERT INTO `lot_status` (`lot_status_id`, `lot_status_name`) VALUES
(1, 'รอการอนุมัติ'),
(2, 'ยืนยันแล้ว');

-- --------------------------------------------------------

--
-- Table structure for table `member`
--

CREATE TABLE `member` (
  `member_id` int(11) NOT NULL,
  `member_name` varchar(50) DEFAULT NULL,
  `member_address` varchar(100) DEFAULT NULL,
  `member_phone` varchar(10) DEFAULT NULL,
  `member_register_date` datetime DEFAULT NULL,
  `member_type_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member`
--

INSERT INTO `member` (`member_id`, `member_name`, `member_address`, `member_phone`, `member_register_date`, `member_type_id`) VALUES
(1, 'นายปัญญากร ขายยา', 'กรุงเทพ', '0612479091', '2025-04-22 00:00:00', 4),
(2, 'นางสาวกนกกาญ ชาบำเน็จ', 'กรุงเทพ', '0612479091', '2025-04-22 00:00:00', 4);

-- --------------------------------------------------------

--
-- Table structure for table `member_type`
--

CREATE TABLE `member_type` (
  `member_type_id` int(11) NOT NULL,
  `member_type_name` varchar(50) DEFAULT NULL,
  `member_type_min` int(11) DEFAULT NULL,
  `member_type_max` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member_type`
--

INSERT INTO `member_type` (`member_type_id`, `member_type_name`, `member_type_min`, `member_type_max`) VALUES
(4, 'สมาชิกทั่วไประดับล่าง', NULL, NULL),
(5, 'สมาชิก', NULL, NULL),
(6, 'สมาชิกไประดับกลาง', NULL, NULL),
(7, 'สมาชิกโปรโมชั่น', NULL, NULL),
(8, 'สมาชิกพิเศษของร้าน', NULL, NULL),
(9, 'สมาชิกรายเดือน', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `personnel`
--

CREATE TABLE `personnel` (
  `personnel_id` int(11) NOT NULL,
  `personnel_name` varchar(50) DEFAULT NULL,
  `personnel_address` varchar(150) DEFAULT NULL,
  `personnel_phone` varchar(10) DEFAULT NULL,
  `personnel_username` varchar(50) DEFAULT NULL,
  `personnel_password` varchar(12) DEFAULT NULL,
  `personnel_type_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personnel`
--

INSERT INTO `personnel` (`personnel_id`, `personnel_name`, `personnel_address`, `personnel_phone`, `personnel_username`, `personnel_password`, `personnel_type_id`) VALUES
(1, 'นายสุดเทพ ประเสริฐศรี', 'กรุงเทพ', '0612479091', 'sudtep', 'sudtep_12345', 4),
(2, 'นางสาวบันพาการ ชอบชา', 'บุรีรัมย์', '0878773113', 'banpakran', 'banpakran_01', 5),
(3, 'นายซีอีโอ เจ้าใหญ่', 'กรุงเทพ และปริมณฑน', '0966353407', 'ceo_12345', 'ceo_12345', 6);

-- --------------------------------------------------------

--
-- Table structure for table `personnel_type`
--

CREATE TABLE `personnel_type` (
  `personnel_type_id` int(11) NOT NULL,
  `personnel_type_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personnel_type`
--

INSERT INTO `personnel_type` (`personnel_type_id`, `personnel_type_name`) VALUES
(4, 'พนักงานรับสินค้าเข้า'),
(5, 'พนักงานขายหน้าร้าน'),
(6, 'ผู้จัดการร้าน');

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `product_id` varchar(255) NOT NULL,
  `product_name` varchar(50) DEFAULT NULL,
  `product_price1` decimal(10,2) DEFAULT NULL,
  `product_price2` decimal(10,2) DEFAULT NULL,
  `product_price3` decimal(10,2) DEFAULT NULL,
  `product_price4` decimal(10,2) DEFAULT NULL,
  `product_capital` decimal(10,2) DEFAULT NULL,
  `product_type_id` int(11) DEFAULT NULL,
  `product_brand_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`product_id`, `product_name`, `product_price1`, `product_price2`, `product_price3`, `product_price4`, `product_capital`, `product_type_id`, `product_brand_id`) VALUES
('', 'ยังไม่ระบุ', 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL),
('345345345', 'Dr.PONG GLUTA 250 SL กลูตาไธโอนเพื่อผิวกระจ่างใส ส', 5.00, 10.00, 55.00, 389.00, 20.00, 1, 1),
('34534534534', 'SKINTIFIC Perfect Stay Velvet Matte Cushion สีฟ้า ', 5.00, 10.00, 55.00, 559.00, 20.00, 1, 1),
('3453534534', 'SKINTIFIC Mugwort มาส์กโคลนแบบแท่งที่ช่วยต้นสิว โว', 5.00, 10.00, 555.00, 459.00, 20.00, 1, 1),
('3541466646', 'COKE โค้กแคน 325มล. แพค 6', 25.00, 35.00, 45.00, 90.00, 15.00, 6, 2),
('43534534534', 'SKINTIFIC Perfect Radiant Glow Cushion ผิวโกลว์สวย', 5.00, 100.00, 55.00, 379.00, 20.00, 1, 1),
('452455424', 'Dr.PONG Astaxanthin 6 mg AstaREAL ดอกเตอร์พงศ์ เน', 189.00, 199.00, 299.00, 399.00, 20.00, 1, 1),
('4532456354', 'sdsad', 101.00, 10.00, 10.00, 10.00, 10.00, 14, 6),
('548454242', 'asddasd', 0.00, 0.00, 0.00, 0.00, 20.00, NULL, NULL),
('56756756756', 'Dr.PONG 100,000 mg Collagen Dipeptide Plus Ceramid', 32432.00, 334242.00, 32432.00, 375.00, 20.00, 7, 6),
('653', 'ยังไม่ระบุ', 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL),
('6536535', 'Zinc', 0.00, 0.00, 0.00, 0.00, 35.00, NULL, NULL),
('65365653', 'Gluta', 0.00, 0.00, 0.00, 0.00, 25.00, NULL, NULL),
('656+5+', 'ยังไม่ระบุ', 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL),
('656+6', 'ยังไม่ระบุ', 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL),
('dfds', 'sdfsd', 10.00, 10.00, 10.00, 10.00, 10.00, 13, 6);

-- --------------------------------------------------------

--
-- Table structure for table `product_brand`
--

CREATE TABLE `product_brand` (
  `product_brand_id` int(11) NOT NULL,
  `product_brand_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_brand`
--

INSERT INTO `product_brand` (`product_brand_id`, `product_brand_name`) VALUES
(1, 'Nestlé'),
(2, 'Coca-Cola'),
(3, 'Pepsi'),
(4, 'Lay’s'),
(5, 'Oishi'),
(6, ' Red Bull'),
(7, 'Dutch Mill'),
(8, 'P&G'),
(9, 'Head & Shoulders'),
(10, 'Pantene'),
(11, 'Ariel'),
(12, 'Unilever'),
(13, 'Lux'),
(14, 'Sunsilk'),
(15, 'Breeze'),
(16, 'Apple'),
(17, 'Samsung'),
(18, 'Xiaomi'),
(19, 'Oppo'),
(20, 'Vivo'),
(21, 'LG'),
(22, 'Sony'),
(23, 'Sharp'),
(24, 'Panasonic'),
(25, 'Philips'),
(26, 'Adidas'),
(27, '45242'),
(28, 'sdsafsdf');

-- --------------------------------------------------------

--
-- Table structure for table `product_type`
--

CREATE TABLE `product_type` (
  `product_type_id` int(11) NOT NULL,
  `product_type_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_type`
--

INSERT INTO `product_type` (`product_type_id`, `product_type_name`) VALUES
(1, 'สินค้าอุปโภคบริโภค'),
(2, 'เครื่องใช้ไฟฟ้าและอิเล็กทรอนิกส์'),
(3, 'เสื้อผ้าและเครื่องแต่งกาย'),
(4, 'เครื่องมือและฮาร์ดแวร์'),
(5, 'ยานยนต์และอุปกรณ์เสริม'),
(6, 'อาหารและเครื่องดื่ม'),
(7, 'ของใช้ในครัวเรือน'),
(8, 'ยาและเวชภัณฑ์'),
(9, 'รองเท้าและกระเป๋า'),
(10, 'เครื่องสำอางและผลิตภัณฑ์ดูแลผิว'),
(11, 'วัสดุก่อสร้างและเครื่องมือช่าง'),
(12, 'อุปกรณ์สำนักงานและการศึกษา'),
(13, 'ขนม'),
(14, 'asdsad'),
(15, 'jyhujhg');

-- --------------------------------------------------------

--
-- Table structure for table `sale`
--

CREATE TABLE `sale` (
  `sale_id` int(11) NOT NULL,
  `sale_date` datetime DEFAULT NULL,
  `sale_status_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `personnel_id` int(11) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `paid_amount` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_product`
--

CREATE TABLE `sale_product` (
  `sale_product_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `sale_product_qty` int(11) DEFAULT NULL,
  `sale_product_price` decimal(10,2) DEFAULT NULL,
  `sale_product_capital` decimal(10,2) DEFAULT NULL,
  `product_id` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sale_status`
--

CREATE TABLE `sale_status` (
  `sale_status_id` int(11) NOT NULL,
  `sale_status_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_status`
--

INSERT INTO `sale_status` (`sale_status_id`, `sale_status_name`) VALUES
(1, 'ขายปกติ'),
(2, 'กำลังขาย'),
(3, 'ยกเลิก');

-- --------------------------------------------------------

--
-- Table structure for table `store`
--

CREATE TABLE `store` (
  `store_id` int(11) NOT NULL,
  `store_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(50) DEFAULT NULL,
  `supplier_address` varchar(150) DEFAULT NULL,
  `supplier_phone` varchar(10) DEFAULT NULL,
  `supplier_detail` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`supplier_id`, `supplier_name`, `supplier_address`, `supplier_phone`, `supplier_detail`) VALUES
(1, 'วรินทร์พิพัชร วัชรพงษ์เกษม', '1693 ถ. พหลโยธิน แขวงจตุจักร เขตจตุจักร กรุงเทพมหานคร 10900', '0987654342', ''),
(2, 'จุลจักร เที่ยงไธส', '1693 ถ. พหลโยธิน แขวงจตุจักร เขตจตุจักร กรุงเทพมหานคร 10900', '9876543421', 'A'),
(3, 'ธนารัตน์ สุดใจ', '1693 ถ. พหลโยธิน แขวงจตุจักร เขตจตุจักร กรุงเทพมหานคร 10900', '9876543421', '');

-- --------------------------------------------------------

--
-- Table structure for table `transport`
--

CREATE TABLE `transport` (
  `transport_id` int(11) NOT NULL,
  `transport_name` varchar(255) DEFAULT NULL,
  `transport_date_out` datetime DEFAULT NULL,
  `transport_date_in` datetime DEFAULT NULL,
  `transport_status_id` int(11) DEFAULT NULL,
  `transport_product_id` int(11) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transport_product`
--

CREATE TABLE `transport_product` (
  `transport_product_id` int(11) NOT NULL,
  `transport_product_qty` int(11) DEFAULT NULL,
  `product_id` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transport_status`
--

CREATE TABLE `transport_status` (
  `transport_status_id` int(11) NOT NULL,
  `transport_status_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouse`
--

CREATE TABLE `warehouse` (
  `warehouse_id` int(11) NOT NULL,
  `warehouse_name` varchar(50) DEFAULT NULL,
  `warehouse_status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `warehouse`
--

INSERT INTO `warehouse` (`warehouse_id`, `warehouse_name`, `warehouse_status`) VALUES
(1, 'คลังหลัก', 'พร้อมใช้งาน'),
(2, 'คลังสำรอง', 'พร้อมใช้งาน'),
(3, 'คลังรอตรวจสอบ', 'พร้อมใช้งาน'),
(4, 'คลังสินค้าหมดอายุ', 'พร้อมใช้งาน'),
(5, 'คลังสินค้านำเข้าใหม่', 'พร้อมใช้งาน');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `lot`
--
ALTER TABLE `lot`
  ADD PRIMARY KEY (`lot_id`),
  ADD KEY `warehouse_id` (`warehouse_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `lot_status_id` (`lot_status_id`),
  ADD KEY `warehouse_from_id` (`warehouse_from_id`);

--
-- Indexes for table `lot_product`
--
ALTER TABLE `lot_product`
  ADD PRIMARY KEY (`lot_product_id`),
  ADD KEY `lot_id` (`lot_id`);

--
-- Indexes for table `lot_status`
--
ALTER TABLE `lot_status`
  ADD PRIMARY KEY (`lot_status_id`);

--
-- Indexes for table `member`
--
ALTER TABLE `member`
  ADD PRIMARY KEY (`member_id`),
  ADD KEY `member_type_id` (`member_type_id`);

--
-- Indexes for table `member_type`
--
ALTER TABLE `member_type`
  ADD PRIMARY KEY (`member_type_id`);

--
-- Indexes for table `personnel`
--
ALTER TABLE `personnel`
  ADD PRIMARY KEY (`personnel_id`),
  ADD KEY `personnel_type_id` (`personnel_type_id`);

--
-- Indexes for table `personnel_type`
--
ALTER TABLE `personnel_type`
  ADD PRIMARY KEY (`personnel_type_id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `product_type_id` (`product_type_id`),
  ADD KEY `product_brand_id` (`product_brand_id`);

--
-- Indexes for table `product_brand`
--
ALTER TABLE `product_brand`
  ADD PRIMARY KEY (`product_brand_id`);

--
-- Indexes for table `product_type`
--
ALTER TABLE `product_type`
  ADD PRIMARY KEY (`product_type_id`);

--
-- Indexes for table `sale`
--
ALTER TABLE `sale`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `sale_status_id` (`sale_status_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `personnel_id` (`personnel_id`),
  ADD KEY `store_id` (`store_id`);

--
-- Indexes for table `sale_product`
--
ALTER TABLE `sale_product`
  ADD PRIMARY KEY (`sale_product_id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sale_status`
--
ALTER TABLE `sale_status`
  ADD PRIMARY KEY (`sale_status_id`);

--
-- Indexes for table `store`
--
ALTER TABLE `store`
  ADD PRIMARY KEY (`store_id`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `warehouse`
--
ALTER TABLE `warehouse`
  ADD PRIMARY KEY (`warehouse_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `lot`
--
ALTER TABLE `lot`
  MODIFY `lot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `lot_product`
--
ALTER TABLE `lot_product`
  MODIFY `lot_product_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `lot_status`
--
ALTER TABLE `lot_status`
  MODIFY `lot_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `member`
--
ALTER TABLE `member`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `member_type`
--
ALTER TABLE `member_type`
  MODIFY `member_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `personnel`
--
ALTER TABLE `personnel`
  MODIFY `personnel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `personnel_type`
--
ALTER TABLE `personnel_type`
  MODIFY `personnel_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `product_brand`
--
ALTER TABLE `product_brand`
  MODIFY `product_brand_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `product_type`
--
ALTER TABLE `product_type`
  MODIFY `product_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `sale`
--
ALTER TABLE `sale`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_product`
--
ALTER TABLE `sale_product`
  MODIFY `sale_product_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sale_status`
--
ALTER TABLE `sale_status`
  MODIFY `sale_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `store`
--
ALTER TABLE `store`
  MODIFY `store_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `warehouse`
--
ALTER TABLE `warehouse`
  MODIFY `warehouse_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `member`
--
ALTER TABLE `member`
  ADD CONSTRAINT `member_ibfk_1` FOREIGN KEY (`member_type_id`) REFERENCES `member_type` (`member_type_id`);

--
-- Constraints for table `personnel`
--
ALTER TABLE `personnel`
  ADD CONSTRAINT `personnel_ibfk_1` FOREIGN KEY (`personnel_type_id`) REFERENCES `personnel_type` (`personnel_type_id`);

--
-- Constraints for table `sale`
--
ALTER TABLE `sale`
  ADD CONSTRAINT `sale_ibfk_1` FOREIGN KEY (`sale_status_id`) REFERENCES `sale_status` (`sale_status_id`),
  ADD CONSTRAINT `sale_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `member` (`member_id`),
  ADD CONSTRAINT `sale_ibfk_3` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`),
  ADD CONSTRAINT `sale_ibfk_4` FOREIGN KEY (`store_id`) REFERENCES `store` (`store_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
