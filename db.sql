CREATE TABLE lot (
    lot_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    lot_name VARCHAR(255),
    lot_detail VARCHAR(255),
    warehouse_id INT(11),
    supplier_id INT(11),
    lot_date DATETIME,
    lot_status_id INT(11),
    warehouse_from_id INT(11),
    warehouse_inout INT(11),
    FOREIGN KEY (warehouse_id) REFERENCES warehouse(warehouse_id),
    FOREIGN KEY (supplier_id) REFERENCES supplier(supplier_id),
    FOREIGN KEY (lot_status_id) REFERENCES lot_status(lot_status_id),
    FOREIGN KEY (warehouse_from_id) REFERENCES warehouse(warehouse_id)
);

CREATE TABLE lot_product (
    lot_product_id BIGINT(20) PRIMARY KEY AUTO_INCREMENT,
    lot_id INT(11),
    lot_name VARCHAR(255),
    lot_product_qty INT(11),
    lot_product_remain INT(11),
    lot_product_capital INT(11),
    lot_product_date DATETIME,
    lot_product_status INT(11),
    product_id VARCHAR(15),
    FOREIGN KEY (lot_id) REFERENCES lot(lot_id),
    FOREIGN KEY (product_id) REFERENCES product(product_id)
);

CREATE TABLE lot_status (
    lot_status_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    lot_status_name VARCHAR(50)
);

CREATE TABLE member (
    member_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    member_name VARCHAR(50),
    member_address VARCHAR(100),
    member_phone VARCHAR(10),
    member_register_date DATETIME,
    member_type_id INT(11),
    FOREIGN KEY (member_type_id) REFERENCES member_type(member_type_id)
);

CREATE TABLE member_type (
    member_type_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    member_type_name VARCHAR(50),
    member_type_min INT(11),
    member_type_max INT(11)
);

CREATE TABLE personnel (
    personnel_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    personnel_name VARCHAR(50),
    personnel_address VARCHAR(150),
    personnel_phone VARCHAR(10),
    personnel_username VARCHAR(50),
    personnel_password VARCHAR(12),
    personnel_type_id INT(11),
    FOREIGN KEY (personnel_type_id) REFERENCES personnel_type(personnel_type_id)
);

CREATE TABLE personnel_type (
    personnel_type_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    personnel_type_name VARCHAR(50)
);

CREATE TABLE product (
    product_id VARCHAR(15) PRIMARY KEY,
    product_name VARCHAR(50),
    product_price1 DECIMAL(10,2),
    product_price2 DECIMAL(10,2),
    product_price3 DECIMAL(10,2),
    product_price4 DECIMAL(10,2),
    product_capital DECIMAL(10,2),
    product_type_id INT(11),
    product_brand_id INT(11),
    FOREIGN KEY (product_type_id) REFERENCES product_type(product_type_id),
    FOREIGN KEY (product_brand_id) REFERENCES product_brand(product_brand_id)
);

CREATE TABLE product_brand (
    product_brand_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    product_brand_name VARCHAR(50)
);

CREATE TABLE product_type (
    product_type_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    product_type_name VARCHAR(50)
);

CREATE TABLE sale (
    sale_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    sale_date DATETIME,
    sale_status_id INT(11),
    member_id INT(11),
    personnel_id INT(11),
    store_id INT(11),
    FOREIGN KEY (sale_status_id) REFERENCES sale_status(sale_status_id),
    FOREIGN KEY (member_id) REFERENCES member(member_id),
    FOREIGN KEY (personnel_id) REFERENCES personnel(personnel_id),
    FOREIGN KEY (store_id) REFERENCES store(store_id)
);

CREATE TABLE sale_product (
    sale_product_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    sale_id INT(11),
    sale_product_qty INT(11),
    sale_product_price DECIMAL(10,2),
    sale_product_capital DECIMAL(10,2),
    sale_product_booking INT(11),
    sale_product_status INT(11),
    product_id VARCHAR(15),
    warehouse_id INT(11),
    sale_deposit INT(11),
    sale_reference INT(11),
    FOREIGN KEY (sale_id) REFERENCES sale(sale_id),
    FOREIGN KEY (product_id) REFERENCES product(product_id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouse(warehouse_id)
);

CREATE TABLE sale_status (
    sale_status_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    sale_status_name VARCHAR(50)
);

CREATE TABLE store (
    store_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    store_name VARCHAR(50)
);

CREATE TABLE supplier (
    supplier_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    supplier_name VARCHAR(50),
    supplier_address VARCHAR(150),
    supplier_phone VARCHAR(10),
    supplier_detail VARCHAR(150)
);

CREATE TABLE warehouse (
    warehouse_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    warehouse_name VARCHAR(50),
    warehouse_status VARCHAR(50)
);
