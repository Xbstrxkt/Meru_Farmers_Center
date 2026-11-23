CREATE TABLE IF NOT EXISTS payment_transactions (
    payment_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    payment_type ENUM('order', 'booking') NOT NULL,
    payload LONGTEXT NOT NULL,
    amount INT NOT NULL,
    phone VARCHAR(15) NOT NULL,
    checkout_request_id VARCHAR(100) NULL UNIQUE,
    merchant_request_id VARCHAR(100) NULL,
    status ENUM('pending', 'paid', 'failed', 'paid_unfulfilled') NOT NULL DEFAULT 'pending',
    mpesa_receipt VARCHAR(30) NULL,
    result_desc VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payment_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS payment_method ENUM('mpesa', 'cash') NOT NULL DEFAULT 'cash';

ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS payment_method ENUM('mpesa', 'cash') NOT NULL DEFAULT 'cash';

ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid';