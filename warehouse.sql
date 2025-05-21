-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 21, 2025 at 09:47 AM
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
  `warehouse_inout` int(11) DEFAULT NULL,
  `personnel_id` int(11) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lot`
--

INSERT INTO `lot` (`lot_id`, `lot_name`, `lot_detail`, `warehouse_id`, `supplier_id`, `lot_date`, `lot_status_id`, `warehouse_from_id`, `warehouse_inout`, `personnel_id`, `store_id`) VALUES
(18, 'L01', '-', 1, 1, '2025-05-12 21:53:02', 2, 1, 1, 1, 1),
(19, 'L02', '-', 1, 1, '2025-05-12 21:58:48', 2, 1, 1, 3, 1),
(22, 'L03', '', 3, 1, '2025-05-17 00:53:22', 2, 1, 1, 5, 1),
(26, 'WT-001', '', 2, 1, '2025-05-17 01:11:20', 2, 3, 2, NULL, NULL),
(28, 'WT-002', '-', 4, 2, '2025-05-17 01:17:05', 2, 3, 2, NULL, NULL),
(31, 'WT-003', '-', 1, 1, '2025-05-17 22:43:15', 2, 3, 2, NULL, NULL),
(34, 'WT-004', '-', 2, 1, '2025-05-17 22:48:22', 2, 3, 2, NULL, NULL),
(36, 'WT-005', '-', 5, 10, '2025-05-17 22:52:58', 2, 1, 2, NULL, NULL),
(37, 'WT-006', '-', 4, 10, '2025-05-17 22:53:52', 1, 1, 2, NULL, NULL),
(38, 'ะเ้พะเะพ', 'เพะพเพะเ', 1, 1, '2025-05-19 16:17:13', 1, 1, 1, 1, 1),
(39, 'WT-007', '4554545', 2, 1, '2025-05-19 16:18:14', 1, 1, 2, NULL, NULL);

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
(21, 13, NULL, 100, 100, 10000, '2025-05-12 16:04:05', 1, '112233445566'),
(22, 13, NULL, 100, 100, 3000, '2025-05-12 16:04:18', 1, '123456789012'),
(23, 13, NULL, 100, 100, 12000, '2025-05-12 16:04:29', 1, '223344556677'),
(24, 13, NULL, 100, 100, 1500, '2025-05-12 16:04:40', 1, '334455667788'),
(25, 13, NULL, 100, 100, 2500, '2025-05-12 16:04:53', 1, '445566778899'),
(26, 13, NULL, 100, 100, 5000, '2025-05-12 16:05:07', 1, '556677889900'),
(27, 13, NULL, 100, 100, 7000, '2025-05-12 16:05:21', 1, '667788990011'),
(29, 18, NULL, 100, 100, 100, '2025-05-12 21:53:12', 1, '8851112233445'),
(30, 18, NULL, 100, 100, 130, '2025-05-12 21:53:27', 1, '8851122334455'),
(31, 18, NULL, 100, 100, 80, '2025-05-12 21:53:41', 1, '8851234567890'),
(32, 18, NULL, 100, 100, 300, '2025-05-12 21:53:52', 1, '8852233445566'),
(33, 18, NULL, 100, 100, 85, '2025-05-12 21:54:04', 1, '8853332221116'),
(34, 18, NULL, 100, 100, 35, '2025-05-12 21:54:20', 1, '8853344556677'),
(35, 19, NULL, 20, 20, 100, '2025-05-12 21:58:59', 1, '8851112233445'),
(36, 20, NULL, 1, 1, 0, '2025-05-15 16:08:20', 1, ''),
(38, 22, NULL, 100, 77, 19000, '2025-05-17 00:53:48', 1, '84954986284'),
(42, 26, NULL, 5, 5, 19000, '2025-05-17 01:11:33', 1, '84954986284'),
(44, 28, NULL, 8, 8, 19000, '2025-05-17 01:17:19', 1, '84954986284'),
(47, 31, NULL, 10, 10, 19000, '2025-05-17 22:43:47', 1, '84954986284'),
(51, 34, NULL, 0, 0, 19000, '2025-05-17 22:49:13', 1, '84954986284'),
(57, 38, NULL, 1, 1, 0, '2025-05-19 16:17:27', 1, '65441455445'),
(58, 38, NULL, 1, 1, 0, '2025-05-19 16:17:40', 1, '65522555454'),
(59, 38, NULL, 1, 1, 0, '2025-05-19 16:17:56', 1, '644545455'),
(60, 38, NULL, 1, 1, 0, '2025-05-19 16:18:06', 1, '65644544848');

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
  `member_type_id` int(11) DEFAULT NULL,
  `personnel_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member`
--

INSERT INTO `member` (`member_id`, `member_name`, `member_address`, `member_phone`, `member_register_date`, `member_type_id`, `personnel_id`) VALUES
(1, 'นายปัญญากร ขายยา', 'กรุงเทพ', '0612479091', '2025-05-19 00:00:00', 5, 1),
(2, 'นางสาวกนกกาญ ชาบำเน็จ', 'กรุงเทพ', '0612479091', '2025-05-19 00:00:00', 5, 3),
(4, 'พีระพัฒน์ กัลบุตร', '112/559', '0671988525', '2025-05-19 17:05:18', 8, 8),
(5, 'ธนารัต สุดใจ', '115/889', '0969683599', '2025-05-19 18:21:21', 8, 7);

-- --------------------------------------------------------

--
-- Table structure for table `member_type`
--

CREATE TABLE `member_type` (
  `member_type_id` int(11) NOT NULL,
  `member_type_name` varchar(50) DEFAULT NULL,
  `member_type_min` int(11) DEFAULT NULL,
  `member_type_max` int(11) DEFAULT NULL,
  `member_type_date` datetime DEFAULT NULL,
  `personnel_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member_type`
--

INSERT INTO `member_type` (`member_type_id`, `member_type_name`, `member_type_min`, `member_type_max`, `member_type_date`, `personnel_id`) VALUES
(5, 'สมาชิกทั่วไป', NULL, NULL, '2025-05-15 22:24:45', 1),
(6, 'สมาชิกไประดับกลาง', NULL, NULL, '2025-05-15 22:24:53', 2),
(7, 'สมาชิกโปรโมชั่น', NULL, NULL, '2025-05-15 22:25:05', 2),
(8, 'สมาชิกพิเศษของร้าน', NULL, NULL, '2025-05-15 22:25:34', 2),
(9, 'สมาชิกรายเดือน', NULL, NULL, '2025-05-15 22:25:42', 3);

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
  `personnel_create_date` datetime DEFAULT NULL,
  `personnel_type_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personnel`
--

INSERT INTO `personnel` (`personnel_id`, `personnel_name`, `personnel_address`, `personnel_phone`, `personnel_username`, `personnel_password`, `personnel_create_date`, `personnel_type_id`) VALUES
(1, 'นายสุดเทพ ประเสริฐศรี', 'กรุงเทพ', '0612479091', 'sudtep', 'sudtep_12345', '2025-05-15 23:20:11', 4),
(2, 'นางสาวบันพาการ ชอบชา', 'บุรีรัมย์', '0878773113', 'banpakran', 'banpakran_01', '2025-05-15 23:20:18', 5),
(3, 'นายซีอีโอ เจ้าใหญ่', 'กรุงเทพ และปริมณฑน', '0966353407', 'ceo_12345', 'ceo_12345', '2025-05-15 23:20:25', 5),
(5, 'สุทธิชัย', NULL, '0999999999', 'ppu1789', '123456', '2025-05-19 14:23:59', 4),
(7, 'มาร์ค สุโก้ย', '998/456', '0678499587', 'mark789@gmail.com', '$2y$10$dV2Dk', '2025-05-19 14:46:31', 4),
(8, 'พิมาน มานพ', '887', '0865458799', 'piman789@gmail.com', 'piman789', '2025-05-19 14:48:22', 5);

-- --------------------------------------------------------

--
-- Table structure for table `personnel_type`
--

CREATE TABLE `personnel_type` (
  `personnel_type_id` int(11) NOT NULL,
  `personnel_type_name` varchar(50) DEFAULT NULL,
  `personnel_type_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personnel_type`
--

INSERT INTO `personnel_type` (`personnel_type_id`, `personnel_type_name`, `personnel_type_date`) VALUES
(4, 'พนักงานรับสินค้าเข้า', '2025-05-19 14:53:03'),
(5, 'พนักงานขายหน้าร้าน', '2025-05-19 14:53:10'),
(6, 'ผู้จัดการร้าน', '2025-05-19 14:53:16'),
(7, 'พนักงานเช็คสต็อก', '2025-05-19 15:24:27');

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
('644545455', 'ยังไม่ระบุ', 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL),
('65441455445', 'ยังไม่ระบุ', 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL),
('65522555454', 'ยังไม่ระบุ', 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL),
('65644544848', 'ยังไม่ระบุ', 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL),
('84954986284', 'Samsung 25 ultra', 50000.00, 45000.00, 35000.00, 24000.00, 19000.00, 4, 17),
('8851112233445', 'กระเป๋าผ้าแคนวาส', 150.00, 145.00, 140.00, 135.00, 100.00, 16, 2),
('8851122334455', 'ร่มพับ 3 ตอน', 180.00, 175.00, 170.00, 165.00, 130.00, 16, 1),
('8851234567890', 'กล่องข้าวสแตนเลส', 120.00, 115.00, 110.00, 105.00, 80.00, 16, 2),
('8852233445566', 'พาวเวอร์แบงค์ 10000mAh', 390.00, 380.00, 370.00, 360.00, 300.00, 4, 17),
('8853332221116', 'แก้วน้ำพกพา', 130.00, 125.00, 120.00, 115.00, 85.00, 1, 29),
('8853344556677', 'ชุดทำความสะอาดหน้าจอ', 70.00, 65.00, 60.00, 55.00, 35.00, 4, 16),
('8854445556667', 'สมุดโน้ตปกแข็ง', 60.00, 55.00, 50.00, 45.00, 30.00, 16, 12),
('8857778889998', 'ไฟฉาย LED', 200.00, 190.00, 180.00, 170.00, 140.00, 2, 15),
('8859876543210', 'ขวดน้ำเก็บความเย็น', 250.00, 240.00, 230.00, 220.00, 180.00, 16, 1),
('8859988776655', 'ชุดเครื่องเขียน', 90.00, 85.00, 80.00, 75.00, 50.00, 16, 6);

-- --------------------------------------------------------

--
-- Table structure for table `product_brand`
--

CREATE TABLE `product_brand` (
  `product_brand_id` int(11) NOT NULL,
  `product_brand_name` varchar(50) DEFAULT NULL,
  `product_brand_date` date DEFAULT NULL,
  `personnel_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_brand`
--

INSERT INTO `product_brand` (`product_brand_id`, `product_brand_name`, `product_brand_date`, `personnel_id`) VALUES
(1, 'Nestlé', '2025-05-16', 3),
(2, 'Coca-Cola', '2025-05-14', 1),
(3, 'Pepsi', '2025-05-14', 2),
(4, 'Lay’s', '2025-05-14', 2),
(5, 'Oishi', '2025-05-14', 3),
(6, ' Red Bull', '2025-05-14', 1),
(7, 'Dutch Mill', '2025-05-14', 1),
(8, 'P&G', '2025-05-14', 1),
(9, 'Head & Shoulders', '2025-05-14', 2),
(10, 'Pantene', '2025-05-14', 3),
(11, 'Ariel', '2025-05-14', 2),
(12, 'Unilever', '2025-05-14', 2),
(13, 'Lux', '2025-05-14', 3),
(14, 'Sunsilk', '2025-05-14', 3),
(15, 'Breeze', '2025-05-14', 2),
(16, 'Apple', '2025-05-14', 2),
(17, 'Samsung', '2025-05-14', 3),
(18, 'Xiaomi', '2025-05-14', 2),
(19, 'Oppo', '2025-05-14', 3),
(30, '333', '2025-05-16', 3),
(31, '111', '2025-05-16', 5);

-- --------------------------------------------------------

--
-- Table structure for table `product_type`
--

CREATE TABLE `product_type` (
  `product_type_id` int(11) NOT NULL,
  `product_type_name` varchar(50) DEFAULT NULL,
  `personnel_id` int(11) DEFAULT NULL,
  `product_type_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_type`
--

INSERT INTO `product_type` (`product_type_id`, `product_type_name`, `personnel_id`, `product_type_date`) VALUES
(1, 'สินค้าอุปโภคบริโภค', 2, '2025-05-14'),
(2, 'เครื่องใช้ไฟฟ้าและอิเล็กทรอนิกส์', 2, '2025-05-14'),
(3, 'เสื้อผ้าและเครื่องแต่งกาย', 2, '2025-05-13'),
(4, 'เครื่องมือและฮาร์ดแวร์', 1, '2025-05-13'),
(5, 'ยานยนต์และอุปกรณ์เสริม', 3, '2025-05-13'),
(6, 'อาหารและเครื่องดื่ม', 3, '2025-05-13'),
(7, 'ของใช้ในครัวเรือน', 1, '2025-05-13'),
(8, 'ยาและเวชภัณฑ์', 1, '2025-05-13'),
(9, 'รองเท้าและกระเป๋า', 3, '2025-05-13'),
(10, 'เครื่องสำอางและผลิตภัณฑ์ดูแลผิว', 2, '2025-05-13'),
(11, 'วัสดุก่อสร้างและเครื่องมือช่าง', 3, '2025-05-13'),
(12, 'อุปกรณ์สำนักงานและการศึกษา', 3, '2025-05-13'),
(32, '222', 5, '2025-05-16'),
(33, '555', 2, '2025-05-17');

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

--
-- Dumping data for table `store`
--

INSERT INTO `store` (`store_id`, `store_name`) VALUES
(1, 'สามพระยา'),
(2, 'เมกะบางนา');

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(50) DEFAULT NULL,
  `supplier_address` varchar(150) DEFAULT NULL,
  `supplier_phone` varchar(10) DEFAULT NULL,
  `supplier_detail` varchar(150) DEFAULT NULL,
  `personnel_id` int(11) DEFAULT NULL,
  `supplier_create_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`supplier_id`, `supplier_name`, `supplier_address`, `supplier_phone`, `supplier_detail`, `personnel_id`, `supplier_create_date`) VALUES
(1, 'วรินทร์พิพัชร วัชรพงษ์เกษม', '1693 ถ. พหลโยธิน แขวงจตุจักร เขตจตุจักร กรุงเทพมหานคร 10900222', '0987654342', '-', 1, '2025-05-15 00:00:00'),
(2, 'จุลจักร เที่ยงไธส', '1693 ถ. พหลโยธิน แขวงจตุจักร เขตจตุจักร กรุงเทพมหานคร 10900', '9876543421', 'A', 1, '2025-05-14 00:00:00'),
(8, 'ออย', '889/45', '0984444444', NULL, 1, '2025-05-15 19:53:49'),
(11, 'ต้า', '88/115', '0222222222', NULL, 2, '2025-05-15 20:01:41'),
(12, 'ธนารัต สุดใจ', 'ร้าน ไทยรัก', '0612588488', NULL, 3, '2025-05-20 23:34:39');

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
  ADD KEY `warehouse_from_id` (`warehouse_from_id`),
  ADD KEY `fk_lot_personnel` (`personnel_id`),
  ADD KEY `fk_store_id` (`store_id`);

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
  ADD KEY `member_type_id` (`member_type_id`),
  ADD KEY `fk_member_personnel` (`personnel_id`);

--
-- Indexes for table `member_type`
--
ALTER TABLE `member_type`
  ADD PRIMARY KEY (`member_type_id`),
  ADD KEY `fk_mt_personnel_id` (`personnel_id`);

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
  ADD PRIMARY KEY (`product_brand_id`),
  ADD KEY `fk_personnel_id` (`personnel_id`);

--
-- Indexes for table `product_type`
--
ALTER TABLE `product_type`
  ADD PRIMARY KEY (`product_type_id`),
  ADD KEY `fk_personnel` (`personnel_id`);

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
  ADD PRIMARY KEY (`supplier_id`),
  ADD KEY `fk_supplier_personnel` (`personnel_id`);

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
  MODIFY `lot_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `lot_product`
--
ALTER TABLE `lot_product`
  MODIFY `lot_product_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `lot_status`
--
ALTER TABLE `lot_status`
  MODIFY `lot_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `member`
--
ALTER TABLE `member`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `member_type`
--
ALTER TABLE `member_type`
  MODIFY `member_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `personnel`
--
ALTER TABLE `personnel`
  MODIFY `personnel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `personnel_type`
--
ALTER TABLE `personnel_type`
  MODIFY `personnel_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `product_brand`
--
ALTER TABLE `product_brand`
  MODIFY `product_brand_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `product_type`
--
ALTER TABLE `product_type`
  MODIFY `product_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

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
  MODIFY `store_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `warehouse`
--
ALTER TABLE `warehouse`
  MODIFY `warehouse_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `lot`
--
ALTER TABLE `lot`
  ADD CONSTRAINT `fk_lot_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_store_id` FOREIGN KEY (`store_id`) REFERENCES `store` (`store_id`) ON DELETE SET NULL;

--
-- Constraints for table `member`
--
ALTER TABLE `member`
  ADD CONSTRAINT `fk_member_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`),
  ADD CONSTRAINT `member_ibfk_1` FOREIGN KEY (`member_type_id`) REFERENCES `member_type` (`member_type_id`);

--
-- Constraints for table `member_type`
--
ALTER TABLE `member_type`
  ADD CONSTRAINT `fk_mt_personnel_id` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`);

--
-- Constraints for table `personnel`
--
ALTER TABLE `personnel`
  ADD CONSTRAINT `personnel_ibfk_1` FOREIGN KEY (`personnel_type_id`) REFERENCES `personnel_type` (`personnel_type_id`);

--
-- Constraints for table `product_brand`
--
ALTER TABLE `product_brand`
  ADD CONSTRAINT `fk_personnel_id` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`) ON UPDATE CASCADE;

--
-- Constraints for table `product_type`
--
ALTER TABLE `product_type`
  ADD CONSTRAINT `fk_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`);

--
-- Constraints for table `sale`
--
ALTER TABLE `sale`
  ADD CONSTRAINT `sale_ibfk_1` FOREIGN KEY (`sale_status_id`) REFERENCES `sale_status` (`sale_status_id`),
  ADD CONSTRAINT `sale_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `member` (`member_id`),
  ADD CONSTRAINT `sale_ibfk_3` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`),
  ADD CONSTRAINT `sale_ibfk_4` FOREIGN KEY (`store_id`) REFERENCES `store` (`store_id`);

--
-- Constraints for table `supplier`
--
ALTER TABLE `supplier`
  ADD CONSTRAINT `fk_supplier_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
