CREATE TABLE IF NOT EXISTS supplier_purchase_orders (
    purchase_order_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    item_id INT NOT NULL,
    ordered_by INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'dispatched', 'received') NOT NULL DEFAULT 'pending',
    payment_status ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',
    payment_method ENUM('cash', 'mpesa') NOT NULL DEFAULT 'cash',
    supplier_note TEXT NULL,
    expected_delivery_date DATE NULL,
    payment_reference VARCHAR(100) NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    received_at DATETIME NULL,
    INDEX idx_supplier_purchase_orders_supplier (supplier_id, status),
    INDEX idx_supplier_purchase_orders_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;