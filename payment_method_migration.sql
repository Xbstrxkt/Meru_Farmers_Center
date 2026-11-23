ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS payment_method ENUM('mpesa', 'cash') NOT NULL DEFAULT 'cash';

ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS payment_method ENUM('mpesa', 'cash') NOT NULL DEFAULT 'cash';