ALTER TABLE users
	MODIFY COLUMN role ENUM('farmer', 'admin', 'finance_manager', 'inventory_manager', 'supplier', 'dispatch_manager', 'driver', 'service_manager', 'trainer') NOT NULL DEFAULT 'farmer';

ALTER TABLE orders
	ADD COLUMN IF NOT EXISTS delivery_address VARCHAR(255) NULL;

ALTER TABLE bookings
	ADD COLUMN IF NOT EXISTS trainer_notes TEXT NULL,
	ADD COLUMN IF NOT EXISTS service_notes TEXT NULL,
	ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL,
	ADD COLUMN IF NOT EXISTS is_certifiable TINYINT(1) NOT NULL DEFAULT 0;

UPDATE bookings
SET is_certifiable = 1
WHERE service_type = 'Training & Workshops';

UPDATE bookings
SET is_certifiable = 0
WHERE service_type IN (
	'Soil Testing & Analysis',
	'Machinery & Tractor Hire',
	'Agronomy Advisory Session',
	'Crop Protection Consultancy'
);

ALTER TABLE supplier_purchase_orders
	MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'dispatched', 'received') NOT NULL DEFAULT 'pending',
	ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',
	ADD COLUMN IF NOT EXISTS payment_method ENUM('cash', 'mpesa') NOT NULL DEFAULT 'cash',
	ADD COLUMN IF NOT EXISTS supplier_note TEXT NULL,
	ADD COLUMN IF NOT EXISTS expected_delivery_date DATE NULL,
	ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100) NULL,
	ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS dispatches (
	dispatch_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	order_id INT NOT NULL UNIQUE,
	driver_id INT NULL,
	dispatch_manager_id INT NULL,
	delivery_status ENUM('assigned', 'in_transit', 'delivered') NOT NULL DEFAULT 'assigned',
	dispatched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	delivered_at DATETIME NULL,
	INDEX idx_dispatches_driver_status (driver_id, delivery_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS certifications (
	cert_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	booking_id INT NOT NULL,
	farmer_id INT NOT NULL,
	title VARCHAR(150) NOT NULL,
	issue_date DATE NOT NULL,
	certificate_code VARCHAR(50) NOT NULL UNIQUE,
	INDEX idx_certifications_booking (booking_id),
	INDEX idx_certifications_farmer (farmer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
