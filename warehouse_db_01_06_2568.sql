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
  `personnel_id` int NULL DEFAULT NULL,
  `store_id` int NULL DEFAULT NULL,
  PRIMARY KEY (`lot_id`) USING BTREE,
  INDEX `warehouse_id`(`warehouse_id` ASC) USING BTREE,
  INDEX `supplier_id`(`supplier_id` ASC) USING BTREE,
  INDEX `lot_status_id`(`lot_status_id` ASC) USING BTREE,
  INDEX `warehouse_from_id`(`warehouse_from_id` ASC) USING BTREE,
  INDEX `fk_lot_personnel`(`personnel_id` ASC) USING BTREE,
  INDEX `fk_store_id`(`store_id` ASC) USING BTREE,
  CONSTRAINT `fk_lot_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_store_id` FOREIGN KEY (`store_id`) REFERENCES `store` (`store_id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 49 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

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
  `personnel_id` int NULL DEFAULT NULL,
  PRIMARY KEY (`member_id`) USING BTREE,
  INDEX `member_type_id`(`member_type_id` ASC) USING BTREE,
  INDEX `fk_member_personnel`(`personnel_id` ASC) USING BTREE,
  CONSTRAINT `fk_member_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `member_ibfk_1` FOREIGN KEY (`member_type_id`) REFERENCES `member_type` (`member_type_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for member_type
-- ----------------------------
DROP TABLE IF EXISTS `member_type`;
CREATE TABLE `member_type`  (
  `member_type_id` int NOT NULL AUTO_INCREMENT,
  `member_type_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `member_type_min` int NULL DEFAULT NULL,
  `member_type_max` int NULL DEFAULT NULL,
  `member_type_date` datetime NULL DEFAULT NULL,
  `personnel_id` int NULL DEFAULT NULL,
  PRIMARY KEY (`member_type_id`) USING BTREE,
  INDEX `fk_mt_personnel_id`(`personnel_id` ASC) USING BTREE,
  CONSTRAINT `fk_mt_personnel_id` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for personnel
-- ----------------------------
DROP TABLE IF EXISTS `personnel`;
CREATE TABLE `personnel`  (
  `personnel_id` int NOT NULL AUTO_INCREMENT,
  `personnel_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `personnel_address` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `personnel_phone` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `personnel_username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `personnel_password` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `personnel_create_date` datetime NULL DEFAULT NULL,
  `personnel_type_id` int NULL DEFAULT NULL,
  PRIMARY KEY (`personnel_id`) USING BTREE,
  INDEX `personnel_type_id`(`personnel_type_id` ASC) USING BTREE,
  CONSTRAINT `personnel_ibfk_1` FOREIGN KEY (`personnel_type_id`) REFERENCES `personnel_type` (`personnel_type_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for personnel_type
-- ----------------------------
DROP TABLE IF EXISTS `personnel_type`;
CREATE TABLE `personnel_type`  (
  `personnel_type_id` int NOT NULL AUTO_INCREMENT,
  `personnel_type_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `personnel_type_date` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`personnel_type_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;
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
-- Table structure for product_brand
-- ----------------------------
DROP TABLE IF EXISTS `product_brand`;
CREATE TABLE `product_brand`  (
  `product_brand_id` int NOT NULL AUTO_INCREMENT,
  `product_brand_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `product_brand_date` date NULL DEFAULT NULL,
  `personnel_id` int NULL DEFAULT NULL,
  PRIMARY KEY (`product_brand_id`) USING BTREE,
  INDEX `fk_personnel_id`(`personnel_id` ASC) USING BTREE,
  CONSTRAINT `fk_personnel_id` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 32 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;


-- ----------------------------
-- Table structure for product_type
-- ----------------------------
DROP TABLE IF EXISTS `product_type`;
CREATE TABLE `product_type`  (
  `product_type_id` int NOT NULL AUTO_INCREMENT,
  `product_type_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `personnel_id` int NULL DEFAULT NULL,
  `product_type_date` date NULL DEFAULT NULL,
  PRIMARY KEY (`product_type_id`) USING BTREE,
  INDEX `fk_personnel`(`personnel_id` ASC) USING BTREE,
  CONSTRAINT `fk_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 34 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;
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
  `stock_check_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT 'หมายเหตุ',
  `stock_check_remark_id` int NULL DEFAULT NULL COMMENT 'พนักงานที่ตรวจสอบ',
  `stock_id` int NULL DEFAULT NULL COMMENT 'รหัสอ้างอิงใบสต๊อก',
  PRIMARY KEY (`stock_check_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 15 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

DROP TABLE IF EXISTS `stock`;
CREATE TABLE `stock` (
  `stock_id` bigint NOT NULL AUTO_INCREMENT COMMENT 'รหัสเฉพาะสำหรับการตรวจสอบแต่ละครั้ง',
  `stock_date` datetime NULL DEFAULT NULL,
  `check_status_id` int NULL DEFAULT NULL COMMENT 'สถานะการเช็คสต๊อก',
  `warehouse_id` int NULL DEFAULT NULL COMMENT 'คลัง',
  `personnel_id` int NULL DEFAULT NULL COMMENT 'พนักงานที่ตรวจสอบ',
  PRIMARY KEY (`stock_id`) USING BTREE,
  INDEX `fk_stock_warehouse` (`warehouse_id` ASC) USING BTREE,
  INDEX `fk_stock_personnel` (`personnel_id` ASC) USING BTREE,
  INDEX `fk_stock_check_status` (`check_status_id` ASC) USING BTREE,
  CONSTRAINT `fk_stock_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouse` (`warehouse_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_check_status` FOREIGN KEY (`check_status_id`) REFERENCES `stock_check_status` (`check_status_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 15 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

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
-- Table structure for stock_check_status
-- ----------------------------
DROP TABLE IF EXISTS `stock_check_remark`;
CREATE TABLE `stock_check_remark`  (
  `stock_check_remark_id` int NOT NULL AUTO_INCREMENT,
  `stock_check_remark_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`stock_check_remark_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of stock_check_status
-- ----------------------------
INSERT INTO `stock_check_remark` VALUES (1, 'ครบถ้วน');
INSERT INTO `stock_check_remark` VALUES (2, 'ขาดหาย');
INSERT INTO `stock_check_remark` VALUES (3, 'จำนวนสินค้าเกิน');

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
-- Table structure for supplier
-- ----------------------------
DROP TABLE IF EXISTS `supplier`;
CREATE TABLE `supplier`  (
  `supplier_id` int NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `supplier_address` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `supplier_phone` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `supplier_detail` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `personnel_id` int NULL DEFAULT NULL,
  `supplier_create_date` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`supplier_id`) USING BTREE,
  INDEX `fk_supplier_personnel`(`personnel_id` ASC) USING BTREE,
  CONSTRAINT `fk_supplier_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`personnel_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of supplier
-- ----------------------------
INSERT INTO `supplier` VALUES (1, 'วรินทร์พิพัชร วัชรพงษ์เกษม', '1693 ถ. พหลโยธิน แขวงจตุจักร เขตจตุจักร กรุงเทพมหานคร 10900222', '0987654342', '-', 1, '2025-05-15 00:00:00');
INSERT INTO `supplier` VALUES (2, 'จุลจักร เที่ยงไธส', '1693 ถ. พหลโยธิน แขวงจตุจักร เขตจตุจักร กรุงเทพมหานคร 10900', '9876543421', 'A', 1, '2025-05-14 00:00:00');
INSERT INTO `supplier` VALUES (8, 'ออย', '889/45', '0984444444', NULL, 1, '2025-05-15 19:53:49');
INSERT INTO `supplier` VALUES (11, 'ต้า', '88/115', '0222222222', NULL, 2, '2025-05-15 20:01:41');
INSERT INTO `supplier` VALUES (12, 'ธนารัต สุดใจ', 'ร้าน ไทยรัก', '0612588488', NULL, 3, '2025-05-20 23:34:39');
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
