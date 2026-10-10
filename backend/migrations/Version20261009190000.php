<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist urgency flag on shop_order (was computed at import but never stored)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_order ADD is_urgent TINYINT(1) NOT NULL DEFAULT 0');

        // Backfill: same rule as DeliveryCalculator::isUrgent — slot within 2h of creation.
        // MSK is a fixed UTC+3 (no DST), slot is in MSK, marketplace_created_at is stored in UTC:
        // slot_msk - 3h < created_utc + 2h  <=>  slot_msk < created_utc + 5h.
        $this->addSql('UPDATE shop_order
            SET is_urgent = 1
            WHERE delivery_type = \'delivery\'
              AND delivery_date IS NOT NULL
              AND time_from IS NOT NULL
              AND TIMESTAMP(delivery_date, time_from) < DATE_ADD(marketplace_created_at, INTERVAL 5 HOUR)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_order DROP is_urgent');
    }
}
