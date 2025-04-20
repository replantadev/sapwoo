CREATE TABLE IF NOT EXISTS wp_sapwc_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT,
    action VARCHAR(100),
    status VARCHAR(20),
    message TEXT,
    docentry BIGINT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
