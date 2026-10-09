-- Seed runs before Symfony migrations on first MySQL init.
-- Default shop and delivery tariffs (business rules from assignment).

CREATE TABLE IF NOT EXISTS shop (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

INSERT INTO shop (id, name) VALUES (1, 'Demo Flower Shop')
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS delivery_district_tariff (
    district_code VARCHAR(32) NOT NULL PRIMARY KEY,
    base_price_rub DECIMAL(10, 2) NOT NULL,
    free_from_items_sum_rub DECIMAL(10, 2) NULL
);

INSERT INTO delivery_district_tariff (district_code, base_price_rub, free_from_items_sum_rub) VALUES
    ('centre', 300.00, 5000.00),
    ('north', 400.00, 7000.00),
    ('south', 400.00, 7000.00),
    ('suburb', 700.00, NULL)
ON DUPLICATE KEY UPDATE
    base_price_rub = VALUES(base_price_rub),
    free_from_items_sum_rub = VALUES(free_from_items_sum_rub);
