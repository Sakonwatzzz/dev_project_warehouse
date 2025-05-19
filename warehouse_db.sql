/*
 Navicat Premium Dump SQL

 Source Server         : db_warehouse
 Source Server Type    : MySQL
 Source Server Version : 80042 (8.0.42)
 Source Host           : localhost:3388
 Source Schema         : warehouse_db

 Target Server Type    : MySQL
 Target Server Version : 80042 (8.0.42)
 File Encoding         : 65001

 Date: 19/05/2025 11:38:15
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for lot
-- ----------------------------
DROP TABLE IF EXISTS `lot`;
CREATE TABLE `lot`  (
  `lot_id` int NOT NULL AUTO_INCREMENT,
  `lot_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `lot_detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `warehouse_id` int NULL DEFAULT NULL,
  `supplier_id` int NULL DEFAULT NULL,
  `lot_date` datetime NULL DEFAULT NULL,
  `lot_status_id` int NULL DEFAULT NULL,
  `warehouse_from_id` int NULL DEFAULT NULL,
  `warehouse_inout` int NULL DEFAULT NULL,
  PRIMARY KEY (`lot_id`) USING BTREE,
  INDEX `warehouse_id`(`warehouse_id` ASC) USING BTREE,
  INDEX `supplier_id`(`supplier_id` ASC) USING BTREE,
  INDEX `lot_status_id`(`lot_status_id` ASC) USING BTREE,
  INDEX `warehouse_from_id`(`warehouse_from_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 21 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lot
-- ----------------------------
INSERT INTO `lot` VALUES (1, 'LotA1', 'รับจากชายแดนจำนวนน้อย', 1, 1, '2025-04-05 02:00:08', 1, 1, 1);
INSERT INTO `lot` VALUES (2, 'lot_a333', 'รับจากชายแดนจำนวนน้อย', 5, 1, '2025-04-25 19:08:39', 2, 1, 1);
INSERT INTO `lot` VALUES (3, 'lot_a335', 'รับจากชายแดนจำนวนน้อย', 1, 1, '2025-04-25 19:12:31', 1, 1, 1);
INSERT INTO `lot` VALUES (6, 'lot', '5424', 1, 1, '2025-05-09 23:24:02', 2, 1, 1);
INSERT INTO `lot` VALUES (9, 'WT-005', '', 2, 1, '2025-05-09 23:39:13', 2, 1, 2);
INSERT INTO `lot` VALUES (10, 'WT-006', 'รับจากชายแดนจำนวนน้อย', 1, 2, '2025-05-09 23:40:53', 2, 5, 2);
INSERT INTO `lot` VALUES (11, 'sdfsdfsd', '4245245', 1, 1, '2025-05-09 23:49:25', 2, 1, 1);
INSERT INTO `lot` VALUES (12, 'WT-007', '', 2, 1, '2025-05-10 00:13:37', 1, 1, 2);
INSERT INTO `lot` VALUES (13, 'LOT 555555', '', 1, 1, '2025-05-12 14:40:43', 2, 1, 1);
INSERT INTO `lot` VALUES (14, '452452542', 'รับสินค้าของใช้ครัวเรือนจากจีนจำนวนมาก', 1, 1, '2025-05-12 15:13:28', 1, 1, 1);
INSERT INTO `lot` VALUES (15, 'WT-008', '', 5, 1, '2025-05-15 16:22:48', 1, 1, 2);
INSERT INTO `lot` VALUES (16, 'lot-BDDS', '', 2, 1, '2025-05-15 16:23:21', 2, 1, 1);
INSERT INTO `lot` VALUES (17, 'lot-BDDSdfdf55', 'รับจากจีนจำนวนมาก', 3, 1, '2025-05-15 16:24:12', 2, 1, 1);
INSERT INTO `lot` VALUES (18, 'WT-009', 'sdfdsfdsfsdfdsf', 1, 1, '2025-05-15 16:27:10', 1, 3, 2);
INSERT INTO `lot` VALUES (19, 'WT-010', 'sadsad', 1, 1, '2025-05-15 16:32:28', 1, 2, 2);
INSERT INTO `lot` VALUES (20, 'looooooooooooo', 'รับสินค้าของใช้ครัวเรือนจากจีนจำนวนมาก', 3, 1, '2025-05-17 15:52:02', 1, 1, 1);

-- ----------------------------
-- Table structure for lot_product
-- ----------------------------
DROP TABLE IF EXISTS `lot_product`;
CREATE TABLE `lot_product`  (
  `lot_product_id` bigint NOT NULL AUTO_INCREMENT,
  `lot_id` int NULL DEFAULT NULL,
  `lot_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `lot_product_qty` int NULL DEFAULT NULL,
  `lot_product_remain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `lot_product_capital` int NULL DEFAULT NULL,
  `lot_product_date` datetime NULL DEFAULT NULL,
  `lot_product_status` int NULL DEFAULT NULL,
  `product_id` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`lot_product_id`) USING BTREE,
  INDEX `lot_id`(`lot_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 28 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lot_product
-- ----------------------------
INSERT INTO `lot_product` VALUES (1, 1, NULL, 50, '41', 15, '2025-04-05 02:00:15', 1, '43534534534');
INSERT INTO `lot_product` VALUES (2, 1, NULL, 50, '27', 25, '2025-04-05 02:00:16', 1, '3453534534');
INSERT INTO `lot_product` VALUES (3, 1, NULL, 50, '30', 25, '2025-04-05 02:00:17', 1, '34534534534');
INSERT INTO `lot_product` VALUES (4, 1, NULL, 50, '0', 45, '2025-04-05 02:00:18', 1, '345345345');
INSERT INTO `lot_product` VALUES (6, 2, NULL, 50, '13', 35, '2025-04-25 19:10:10', 1, '3541466646');
INSERT INTO `lot_product` VALUES (7, 2, NULL, 50, '25', 20, '2025-04-25 19:11:08', 1, '34534534534');
INSERT INTO `lot_product` VALUES (8, 3, NULL, 50, '14', 15, '2025-04-25 19:12:40', 1, '452455424');
INSERT INTO `lot_product` VALUES (10, 6, NULL, 10, '10', 20, '2025-05-09 23:24:12', 1, '548454242');
INSERT INTO `lot_product` VALUES (11, 6, NULL, 10, '5', 20, '2025-05-09 23:24:21', 1, '345345345');
INSERT INTO `lot_product` VALUES (12, 9, NULL, 5, '5', 20, '2025-05-09 23:39:32', 1, '345345345');
INSERT INTO `lot_product` VALUES (13, 10, NULL, 25, '25', 20, '2025-05-09 23:42:15', 1, '34534534534');
INSERT INTO `lot_product` VALUES (14, 11, NULL, 100, '96', 35, '2025-05-09 23:49:40', 1, '6536535');
INSERT INTO `lot_product` VALUES (15, 11, NULL, 100, '99', 25, '2025-05-09 23:49:41', 1, '65365653');
INSERT INTO `lot_product` VALUES (20, 12, NULL, 1000, '1000', 20, '2025-05-10 00:14:53', 1, '34534534534');
INSERT INTO `lot_product` VALUES (21, 13, NULL, 1000, '100', 20, '2025-05-12 07:40:57', 1, '865758455');
INSERT INTO `lot_product` VALUES (22, 13, NULL, 1, '0', 25, '2025-05-12 07:45:59', 1, '6858456651');
INSERT INTO `lot_product` VALUES (23, 14, NULL, 1000, '1000', 0, '2025-05-12 08:13:42', 1, '54646141441');
INSERT INTO `lot_product` VALUES (24, 16, NULL, 1000, '1000', 20, '2025-05-15 09:23:45', 1, '89689898989');
INSERT INTO `lot_product` VALUES (25, 17, NULL, 1500, '1500', 25, '2025-05-15 09:25:03', 1, '6858456651');

-- ----------------------------
-- Table structure for lot_status
-- ----------------------------
DROP TABLE IF EXISTS `lot_status`;
CREATE TABLE `lot_status`  (
  `lot_status_id` int NOT NULL AUTO_INCREMENT,
  `lot_status_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`lot_status_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of lot_status
-- ----------------------------
INSERT INTO `lot_status` VALUES (1, 'รอการอนุมัติ');
INSERT INTO `lot_status` VALUES (2, 'ยืนยันแล้ว');

-- ----------------------------
-- Table structure for member
-- ----------------------------
DROP TABLE IF EXISTS `member`;
CREATE TABLE `member`  (
  `member_id` int NOT NULL AUTO_INCREMENT,
  `member_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `member_address` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `member_phone` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `member_register_date` datetime NULL DEFAULT NULL,
  `member_type_id` int NULL DEFAULT NULL,
  PRIMARY KEY (`member_id`) USING BTREE,
  INDEX `member_type_id`(`member_type_id` ASC) USING BTREE,
  CONSTRAINT `member_ibfk_1` FOREIGN KEY (`member_type_id`) REFERENCES `member_type` (`member_type_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of member
-- ----------------------------
INSERT INTO `member` VALUES (1, 'นายปัญญากร ขายยา', 'กรุงเทพ', '0612479091', '2025-04-22 00:00:00', 4);
INSERT INTO `member` VALUES (2, 'นางสาวกนกกาญ ชาบำเน็จ', 'กรุงเทพ', '0612479091', '2025-04-22 00:00:00', 4);

-- ----------------------------
-- Table structure for member_type
-- ----------------------------
DROP TABLE IF EXISTS `member_type`;
CREATE TABLE `member_type`  (
  `member_type_id` int NOT NULL AUTO_INCREMENT,
  `member_type_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `member_type_min` int NULL DEFAULT NULL,
  `member_type_max` int NULL DEFAULT NULL,
  PRIMARY KEY (`member_type_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 10 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of member_type
-- ----------------------------
INSERT INTO `member_type` VALUES (4, 'สมาชิกทั่วไประดับล่าง', NULL, NULL);
INSERT INTO `member_type` VALUES (5, 'สมาชิก', NULL, NULL);
INSERT INTO `member_type` VALUES (6, 'สมาชิกไประดับกลาง', NULL, NULL);
INSERT INTO `member_type` VALUES (7, 'สมาชิกโปรโมชั่น', NULL, NULL);
INSERT INTO `member_type` VALUES (8, 'สมาชิกพิเศษของร้าน', NULL, NULL);
INSERT INTO `member_type` VALUES (9, 'สมาชิกรายเดือน', NULL, NULL);

-- ----------------------------
-- Table structure for personnel
-- ----------------------------
DROP TABLE IF EXISTS `personnel`;
CREATE TABLE `personnel`  (
  `personnel_id` int NOT NULL AUTO_INCREMENT,
  `personnel_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `personnel_address` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `personnel_phone` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `personnel_username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `personnel_password` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `personnel_type_id` int NULL DEFAULT NULL,
  PRIMARY KEY (`personnel_id`) USING BTREE,
  INDEX `personnel_type_id`(`personnel_type_id` ASC) USING BTREE,
  CONSTRAINT `personnel_ibfk_1` FOREIGN KEY (`personnel_type_id`) REFERENCES `personnel_type` (`personnel_type_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of personnel
-- ----------------------------
INSERT INTO `personnel` VALUES (1, 'นายสุดเทพ ประเสริฐศรี', 'กรุงเทพ', '0612479091', 'sudtep', 'sudtep_12345', 4);
INSERT INTO `personnel` VALUES (2, 'นางสาวบันพาการ ชอบชา', 'บุรีรัมย์', '0878773113', 'banpakran', 'banpakran_01', 5);
INSERT INTO `personnel` VALUES (3, 'นายซีอีโอ เจ้าใหญ่', 'กรุงเทพ และปริมณฑน', '0966353407', 'ceo_12345', 'ceo_12345', 6);

-- ----------------------------
-- Table structure for personnel_type
-- ----------------------------
DROP TABLE IF EXISTS `personnel_type`;
CREATE TABLE `personnel_type`  (
  `personnel_type_id` int NOT NULL AUTO_INCREMENT,
  `personnel_type_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`personnel_type_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of personnel_type
-- ----------------------------
INSERT INTO `personnel_type` VALUES (4, 'พนักงานรับสินค้าเข้า');
INSERT INTO `personnel_type` VALUES (5, 'พนักงานขายหน้าร้าน');
INSERT INTO `personnel_type` VALUES (6, 'ผู้จัดการร้าน');

-- ----------------------------
-- Table structure for product
-- ----------------------------
DROP TABLE IF EXISTS `product`;
CREATE TABLE `product`  (
  `product_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `product_price1` decimal(10, 2) NULL DEFAULT NULL,
  `product_price2` decimal(10, 2) NULL DEFAULT NULL,
  `product_price3` decimal(10, 2) NULL DEFAULT NULL,
  `product_price4` decimal(10, 2) NULL DEFAULT NULL,
  `product_capital` decimal(10, 2) NULL DEFAULT NULL,
  `product_type_id` int NULL DEFAULT NULL,
  `product_brand_id` int NULL DEFAULT NULL,
  PRIMARY KEY (`product_id`) USING BTREE,
  INDEX `product_type_id`(`product_type_id` ASC) USING BTREE,
  INDEX `product_brand_id`(`product_brand_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of product
-- ----------------------------
INSERT INTO `product` VALUES ('345345345', 'Dr.PONG GLUTA 250 SL กลูตาไธโอนเพื่อผิวกระจ่างใส ส', 5.00, 10.00, 55.00, 389.00, 20.00, 1, 1);
INSERT INTO `product` VALUES ('34534534534', 'SKINTIFIC Perfect Stay Velvet Matte Cushion สีฟ้า ', 5.00, 10.00, 55.00, 559.00, 20.00, 1, 1);
INSERT INTO `product` VALUES ('3453534534', 'SKINTIFIC Mugwort มาส์กโคลนแบบแท่งที่ช่วยต้นสิว โว', 5.00, 10.00, 555.00, 459.00, 20.00, 1, 1);
INSERT INTO `product` VALUES ('3541466646', 'COKE โค้กแคน 325มล. แพค 6', 25.00, 35.00, 45.00, 90.00, 15.00, 6, 2);
INSERT INTO `product` VALUES ('43534534534', 'SKINTIFIC Perfect Radiant Glow Cushion ผิวโกลว์สวย', 5.00, 100.00, 55.00, 379.00, 20.00, 1, 1);
INSERT INTO `product` VALUES ('452455424', 'Dr.PONG Astaxanthin 6 mg AstaREAL ดอกเตอร์พงศ์ เน', 189.00, 199.00, 299.00, 399.00, 20.00, 1, 1);
INSERT INTO `product` VALUES ('4532456354', 'sdsad', 101.00, 10.00, 10.00, 10.00, 10.00, 14, 6);
INSERT INTO `product` VALUES ('54646141441', 'แว่นสายตาสั้น กรองแสง ป้องกันแสงสีฟ้ แฟชั่น กรอบโลหะ ผู้ชายและผู้หญิง คุณภาพสูง เครื่องประดับแฟชั่น NEW', 50.00, 55.00, 59.00, 62.00, 10.00, 1, 25);
INSERT INTO `product` VALUES ('548454242', 'แว่นตากันแดด เลนส์โพลาไรซ์ TR90 สําหรับผู้ชาย เหมาะกับการตกปลา ปั่นจักรยาน เล่นกีฬา', 13.00, 21.00, 27.00, 29.00, 1.00, 1, 16);
INSERT INTO `product` VALUES ('56756756756', 'Dr.PONG 100,000 mg Collagen Dipeptide Plus Ceramid', 32432.00, 334242.00, 32432.00, 375.00, 20.00, 7, 6);
INSERT INTO `product` VALUES ('6536535', 'ไลฟ์ ซิงค์ พลัส วิตามินซี Life Zinc Plus Vitamin c วิตามินบำรุงผม ชุด life คอลลาเจน พลัส วิตามินซี ผิวสวย ไลฟ์ซิงค์ พลัส', 99.00, 129.00, 169.00, 399.00, 35.00, 8, 29);
INSERT INTO `product` VALUES ('65365653', 'Gluta Collagen (30 เม็ด)แอล กลูต้า พลัส คอลลาเจน วิตามินกลูต้า สูตรเข้มข้น ตราอินเซ้นส์[A2]', 96.00, 101.00, 115.00, 139.00, 25.00, 8, 29);
INSERT INTO `product` VALUES ('6858456651', '500 แผ่น Double A กระดาษถ่ายเอกสาร A4 - 80 แกรม 1 รีม', 78.00, 97.00, 119.00, 127.00, 25.00, 12, 26);
INSERT INTO `product` VALUES ('865758455', 'Kashiwa เตารีดแบบแห้ง รุ่น HD-198-A เครื่องใช้ไฟฟ้าภายในบ้าน กำลังไฟ 1000 วัตต์', 129.00, 145.00, 169.00, 199.00, 20.00, 2, 25);
INSERT INTO `product` VALUES ('89689898989', 'Nescafe เนสกาแฟ 3in1 ( แพ็ค 60 ซอง ) ริชอโรมาและ เอสเปรสโซ', 215.00, 225.00, 235.00, 245.00, 20.00, 1, 1);

-- ----------------------------
-- Table structure for product_brand
-- ----------------------------
DROP TABLE IF EXISTS `product_brand`;
CREATE TABLE `product_brand`  (
  `product_brand_id` int NOT NULL AUTO_INCREMENT,
  `product_brand_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`product_brand_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 30 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of product_brand
-- ----------------------------
INSERT INTO `product_brand` VALUES (1, 'Nestlé');
INSERT INTO `product_brand` VALUES (2, 'Coca-Cola');
INSERT INTO `product_brand` VALUES (3, 'Pepsi');
INSERT INTO `product_brand` VALUES (4, 'Lay’s');
INSERT INTO `product_brand` VALUES (5, 'Oishi');
INSERT INTO `product_brand` VALUES (6, ' Red Bull');
INSERT INTO `product_brand` VALUES (7, 'Dutch Mill');
INSERT INTO `product_brand` VALUES (8, 'P&G');
INSERT INTO `product_brand` VALUES (9, 'Head & Shoulders');
INSERT INTO `product_brand` VALUES (10, 'Pantene');
INSERT INTO `product_brand` VALUES (11, 'Ariel');
INSERT INTO `product_brand` VALUES (12, 'Unilever');
INSERT INTO `product_brand` VALUES (13, 'Lux');
INSERT INTO `product_brand` VALUES (14, 'Sunsilk');
INSERT INTO `product_brand` VALUES (15, 'Breeze');
INSERT INTO `product_brand` VALUES (16, 'Apple');
INSERT INTO `product_brand` VALUES (17, 'Samsung');
INSERT INTO `product_brand` VALUES (18, 'Xiaomi');
INSERT INTO `product_brand` VALUES (19, 'Oppo');
INSERT INTO `product_brand` VALUES (20, 'Vivo');
INSERT INTO `product_brand` VALUES (21, 'LG');
INSERT INTO `product_brand` VALUES (22, 'Sony');
INSERT INTO `product_brand` VALUES (23, 'Sharp');
INSERT INTO `product_brand` VALUES (24, 'Panasonic');
INSERT INTO `product_brand` VALUES (25, 'Philips');
INSERT INTO `product_brand` VALUES (26, 'Adidas');
INSERT INTO `product_brand` VALUES (27, '45242');
INSERT INTO `product_brand` VALUES (28, 'sdsafsdf');
INSERT INTO `product_brand` VALUES (29, 'Life');

-- ----------------------------
-- Table structure for product_type
-- ----------------------------
DROP TABLE IF EXISTS `product_type`;
CREATE TABLE `product_type`  (
  `product_type_id` int NOT NULL AUTO_INCREMENT,
  `product_type_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`product_type_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 16 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of product_type
-- ----------------------------
INSERT INTO `product_type` VALUES (1, 'สินค้าอุปโภคบริโภค');
INSERT INTO `product_type` VALUES (2, 'เครื่องใช้ไฟฟ้าและอิเล็กทรอนิกส์');
INSERT INTO `product_type` VALUES (3, 'เสื้อผ้าและเครื่องแต่งกาย');
INSERT INTO `product_type` VALUES (4, 'เครื่องมือและฮาร์ดแวร์');
INSERT INTO `product_type` VALUES (5, 'ยานยนต์และอุปกรณ์เสริม');
INSERT INTO `product_type` VALUES (6, 'อาหารและเครื่องดื่ม');
INSERT INTO `product_type` VALUES (7, 'ของใช้ในครัวเรือน');
INSERT INTO `product_type` VALUES (8, 'ยาและเวชภัณฑ์');
INSERT INTO `product_type` VALUES (9, 'รองเท้าและกระเป๋า');
INSERT INTO `product_type` VALUES (10, 'เครื่องสำอางและผลิตภัณฑ์ดูแลผิว');
INSERT INTO `product_type` VALUES (11, 'วัสดุก่อสร้างและเครื่องมือช่าง');
INSERT INTO `product_type` VALUES (12, 'อุปกรณ์สำนักงานและการศึกษา');
INSERT INTO `product_type` VALUES (13, 'ขนม');
INSERT INTO `product_type` VALUES (14, 'asdsad');
INSERT INTO `product_type` VALUES (15, 'jyhujhg');

-- ----------------------------
-- Table structure for sale
-- ----------------------------
DROP TABLE IF EXISTS `sale`;
CREATE TABLE `sale`  (
  `sale_id` int NOT NULL AUTO_INCREMENT,
  `sale_date` datetime NULL DEFAULT NULL,
  `sale_status_id` int NULL DEFAULT NULL,
  `member_id` int NULL DEFAULT NULL,
  `personnel_id` int NULL DEFAULT NULL,
  `sale_net_amount` decimal(10, 2) NULL DEFAULT NULL,
  `sale_vat_amount` decimal(10, 2) NULL DEFAULT NULL,
  `sale_paid_amount` decimal(10, 2) NULL DEFAULT NULL,
  `sale_change_amount` decimal(10, 2) NULL DEFAULT NULL,
  `sale_total_amount` decimal(10, 2) NULL DEFAULT NULL,
  PRIMARY KEY (`sale_id`) USING BTREE,
  INDEX `sale_status_id`(`sale_status_id` ASC) USING BTREE,
  INDEX `member_id`(`member_id` ASC) USING BTREE,
  INDEX `personnel_id`(`personnel_id` ASC) USING BTREE,
  CONSTRAINT `sale_ibfk_1` FOREIGN KEY (`sale_status_id`) REFERENCES `sale_status` (`sale_status_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `sale_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `member` (`member_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `sale_ibfk_3` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sale
-- ----------------------------
INSERT INTO `sale` VALUES (1, '2025-05-12 15:07:56', 2, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00);
INSERT INTO `sale` VALUES (2, '2025-05-13 12:07:26', 2, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00);
INSERT INTO `sale` VALUES (3, '2025-05-15 11:10:49', 2, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00);
INSERT INTO `sale` VALUES (4, '2025-05-16 15:27:21', 2, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00);
INSERT INTO `sale` VALUES (5, '2025-05-17 14:37:29', 2, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 1862.00);

-- ----------------------------
-- Table structure for sale_product
-- ----------------------------
DROP TABLE IF EXISTS `sale_product`;
CREATE TABLE `sale_product`  (
  `sale_product_id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int NULL DEFAULT NULL,
  `sale_product_qty` int NULL DEFAULT NULL,
  `sale_product_price` decimal(10, 2) NULL DEFAULT NULL,
  `sale_product_capital` decimal(10, 2) NULL DEFAULT NULL,
  `product_id` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`sale_product_id`) USING BTREE,
  INDEX `sale_id`(`sale_id` ASC) USING BTREE,
  INDEX `product_id`(`product_id` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sale_product
-- ----------------------------
INSERT INTO `sale_product` VALUES (3, 5, 4, 399.00, 35.00, '6536535');
INSERT INTO `sale_product` VALUES (4, 5, 1, 127.00, 25.00, '6858456651');
INSERT INTO `sale_product` VALUES (5, 5, 1, 139.00, 25.00, '65365653');

-- ----------------------------
-- Table structure for sale_status
-- ----------------------------
DROP TABLE IF EXISTS `sale_status`;
CREATE TABLE `sale_status`  (
  `sale_status_id` int NOT NULL AUTO_INCREMENT,
  `sale_status_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`sale_status_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sale_status
-- ----------------------------
INSERT INTO `sale_status` VALUES (1, 'ขายปกติ');
INSERT INTO `sale_status` VALUES (2, 'กำลังขาย');
INSERT INTO `sale_status` VALUES (3, 'ยกเลิก');

-- ----------------------------
-- Table structure for stock_check
-- ----------------------------
DROP TABLE IF EXISTS `stock_check`;
CREATE TABLE `stock_check`  (
  `stock_check_id` bigint NOT NULL AUTO_INCREMENT COMMENT 'รหัสเฉพาะสำหรับการตรวจสอบแต่ละครั้ง',
  `lot_id` int NULL DEFAULT NULL COMMENT 'อ้างอิงล็อตจากlot.lot_id',
  `lot_product_id` int NULL DEFAULT NULL COMMENT 'อ้างอิงรายการสินค้าในล็อตจากlot_product.lot_product_id',
  `product_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'อ้างอิงสินค้าจากproduct.product_id',
  `warehouse_id` int NULL DEFAULT NULL COMMENT 'อ้างอิงคลังจากwarehouse.warehouse_id',
  `stock_check_system` int NULL DEFAULT NULL COMMENT 'จำนวนสินค้าที่ระบบบันทึกไว้ว่ามีอยู่ในสต็อกณขณะที่ทำการตรวจสอบสต็อก\nค่านี้มาจากฟิลด์lot_product.lot_product_remain ในตารางlot_product',
  `stock_check_actual` int NULL DEFAULT NULL COMMENT 'จำนวนที่นับได้จริง',
  `stock_check_date` datetime NULL DEFAULT NULL COMMENT 'วันที่ตรวจสอบสินค้า',
  `stock_check_remark` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT 'หมายเหตุ',
  `personnel_id` int NULL DEFAULT NULL COMMENT 'พนักงานที่ตรวจสอบ',
  `check_status_id` int NULL DEFAULT NULL COMMENT 'สถานะการเช็คสต๊อก',
  PRIMARY KEY (`stock_check_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 15 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of stock_check
-- ----------------------------
INSERT INTO `stock_check` VALUES (1, 11, 14, '6536535', 1, 100, 100, '2025-05-17 14:01:20', '', 1, 1);
INSERT INTO `stock_check` VALUES (2, 11, 14, '6536535', 1, 100, 100, '2025-05-17 14:02:03', 'สินค้าหายไปไหน', 1, 1);
INSERT INTO `stock_check` VALUES (3, 11, 14, '6536535', 1, 100, 10, '2025-05-17 14:23:23', '', 1, 1);
INSERT INTO `stock_check` VALUES (4, 13, 22, '6858456651', 1, 0, 0, '2025-05-17 16:44:53', '', 1, 1);
INSERT INTO `stock_check` VALUES (5, 11, 14, '6536535', 1, 96, 0, '2025-05-17 17:01:28', '', 1, 1);
INSERT INTO `stock_check` VALUES (6, 11, 14, '6536535', 1, 96, 0, '2025-05-17 17:01:45', '', 1, 1);
INSERT INTO `stock_check` VALUES (7, 11, 15, '65365653', 1, 100, 0, '2025-05-17 17:08:48', '', 1, 1);
INSERT INTO `stock_check` VALUES (8, 17, 25, '6858456651', 3, 1500, 0, '2025-05-17 17:10:16', '', 1, 1);
INSERT INTO `stock_check` VALUES (9, 13, 22, '6858456651', 1, 0, 0, '2025-05-17 17:10:42', '', 1, 1);
INSERT INTO `stock_check` VALUES (10, 11, 14, '6536535', 1, 96, 0, '2025-05-17 17:20:35', '', 1, 1);
INSERT INTO `stock_check` VALUES (11, 11, 14, '6536535', 1, 96, 0, '2025-05-17 17:25:22', '', 1, 1);
INSERT INTO `stock_check` VALUES (12, 13, 22, '6858456651', 1, 0, 0, '2025-05-17 17:27:18', '', 1, 1);
INSERT INTO `stock_check` VALUES (13, 17, 25, '6858456651', 3, 1500, 0, '2025-05-17 17:27:30', '', 1, 1);
INSERT INTO `stock_check` VALUES (14, 13, 22, '6858456651', 1, 0, 0, '2025-05-17 17:28:10', '', 1, 1);

-- ----------------------------
-- Table structure for stock_check_status
-- ----------------------------
DROP TABLE IF EXISTS `stock_check_status`;
CREATE TABLE `stock_check_status`  (
  `check_status_id` int NOT NULL AUTO_INCREMENT,
  `check_status_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`check_status_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of stock_check_status
-- ----------------------------
INSERT INTO `stock_check_status` VALUES (1, 'กำลังตรวจสอบ');
INSERT INTO `stock_check_status` VALUES (2, 'ถูกตรวจสอบแล้ว');

-- ----------------------------
-- Table structure for store
-- ----------------------------
DROP TABLE IF EXISTS `store`;
CREATE TABLE `store`  (
  `store_id` int NOT NULL AUTO_INCREMENT,
  `store_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`store_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of store
-- ----------------------------

-- ----------------------------
-- Table structure for supplier
-- ----------------------------
DROP TABLE IF EXISTS `supplier`;
CREATE TABLE `supplier`  (
  `supplier_id` int NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `supplier_address` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `supplier_phone` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `supplier_detail` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`supplier_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of supplier
-- ----------------------------
INSERT INTO `supplier` VALUES (1, 'วรินทร์พิพัชร วัชรพงษ์เกษม', '1693 ถ. พหลโยธิน แขวงจตุจักร เขตจตุจักร กรุงเทพมหานคร 10900', '0987654342', '');
INSERT INTO `supplier` VALUES (2, 'จุลจักร เที่ยงไธส', '1693 ถ. พหลโยธิน แขวงจตุจักร เขตจตุจักร กรุงเทพมหานคร 10900', '9876543421', 'A');
INSERT INTO `supplier` VALUES (3, 'ธนารัตน์ สุดใจ', '1693 ถ. พหลโยธิน แขวงจตุจักร เขตจตุจักร กรุงเทพมหานคร 10900', '9876543421', '');

-- ----------------------------
-- Table structure for transport
-- ----------------------------
DROP TABLE IF EXISTS `transport`;
CREATE TABLE `transport`  (
  `transport_id` int NOT NULL,
  `transport_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `transport_date_out` datetime NULL DEFAULT NULL,
  `transport_date_in` datetime NULL DEFAULT NULL,
  `transport_status_id` int NULL DEFAULT NULL,
  `transport_product_id` int NULL DEFAULT NULL,
  `store_id` int NULL DEFAULT NULL
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of transport
-- ----------------------------

-- ----------------------------
-- Table structure for transport_product
-- ----------------------------
DROP TABLE IF EXISTS `transport_product`;
CREATE TABLE `transport_product`  (
  `transport_product_id` int NOT NULL,
  `transport_product_qty` int NULL DEFAULT NULL,
  `product_id` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of transport_product
-- ----------------------------

-- ----------------------------
-- Table structure for transport_status
-- ----------------------------
DROP TABLE IF EXISTS `transport_status`;
CREATE TABLE `transport_status`  (
  `transport_status_id` int NOT NULL,
  `transport_status_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of transport_status
-- ----------------------------

-- ----------------------------
-- Table structure for warehouse
-- ----------------------------
DROP TABLE IF EXISTS `warehouse`;
CREATE TABLE `warehouse`  (
  `warehouse_id` int NOT NULL AUTO_INCREMENT,
  `warehouse_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `warehouse_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`warehouse_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of warehouse
-- ----------------------------
INSERT INTO `warehouse` VALUES (1, 'คลังหลัก', 'พร้อมใช้งาน');
INSERT INTO `warehouse` VALUES (2, 'คลังสำรอง', 'พร้อมใช้งาน');
INSERT INTO `warehouse` VALUES (3, 'คลังรอตรวจสอบ', 'พร้อมใช้งาน');
INSERT INTO `warehouse` VALUES (4, 'คลังสินค้าหมดอายุ', 'พร้อมใช้งาน');
INSERT INTO `warehouse` VALUES (5, 'คลังสินค้านำเข้าใหม่', 'พร้อมใช้งาน');

SET FOREIGN_KEY_CHECKS = 1;
