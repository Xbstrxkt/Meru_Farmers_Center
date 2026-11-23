ALTER TABLE users
    ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved';

CREATE INDEX idx_users_approval_status ON users (approval_status);