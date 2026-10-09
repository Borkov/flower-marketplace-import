<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Shop, delivery tariffs, orders and items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS shop (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE shop MODIFY id INT NOT NULL AUTO_INCREMENT');

        $this->addSql('CREATE TABLE IF NOT EXISTS delivery_district_tariff (
            district_code VARCHAR(32) NOT NULL,
            base_price_rub NUMERIC(10, 2) NOT NULL,
            free_from_items_sum_rub NUMERIC(10, 2) DEFAULT NULL,
            PRIMARY KEY(district_code)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE IF NOT EXISTS shop_order (
            id INT AUTO_INCREMENT NOT NULL,
            shop_id INT NOT NULL,
            marketplace_id VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(16) NOT NULL,
            delivery_type VARCHAR(16) NOT NULL,
            district VARCHAR(32) DEFAULT NULL,
            address VARCHAR(512) DEFAULT NULL,
            delivery_date VARCHAR(10) DEFAULT NULL,
            time_from VARCHAR(5) DEFAULT NULL,
            time_to VARCHAR(5) DEFAULT NULL,
            marketplace_created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            marketplace_total NUMERIC(10, 2) NOT NULL,
            items_sum NUMERIC(10, 2) NOT NULL,
            delivery_fee NUMERIC(10, 2) NOT NULL,
            grand_total NUMERIC(10, 2) NOT NULL,
            needs_review TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_shop_marketplace_order (shop_id, marketplace_id),
            INDEX IDX_shop_order_shop (shop_id),
            INDEX IDX_shop_order_status (status),
            CONSTRAINT FK_shop_order_shop FOREIGN KEY (shop_id) REFERENCES shop (id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE IF NOT EXISTS shop_order_item (
            id INT AUTO_INCREMENT NOT NULL,
            order_id INT NOT NULL,
            sku VARCHAR(64) NOT NULL,
            name VARCHAR(255) NOT NULL,
            qty INT NOT NULL,
            price NUMERIC(10, 2) NOT NULL,
            INDEX IDX_shop_order_item_order (order_id),
            CONSTRAINT FK_shop_order_item_order FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE CASCADE,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql("INSERT INTO shop (id, name) VALUES (1, 'Demo Flower Shop') ON DUPLICATE KEY UPDATE name = VALUES(name)");
        $this->addSql("INSERT INTO delivery_district_tariff (district_code, base_price_rub, free_from_items_sum_rub) VALUES
            ('centre', 300.00, 5000.00),
            ('north', 400.00, 7000.00),
            ('south', 400.00, 7000.00),
            ('suburb', 700.00, NULL)
            ON DUPLICATE KEY UPDATE
                base_price_rub = VALUES(base_price_rub),
                free_from_items_sum_rub = VALUES(free_from_items_sum_rub)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_order_item DROP FOREIGN KEY FK_shop_order_item_order');
        $this->addSql('ALTER TABLE shop_order DROP FOREIGN KEY FK_shop_order_shop');
        $this->addSql('DROP TABLE shop_order_item');
        $this->addSql('DROP TABLE shop_order');
        $this->addSql('DROP TABLE delivery_district_tariff');
        $this->addSql('DROP TABLE shop');
    }
}
