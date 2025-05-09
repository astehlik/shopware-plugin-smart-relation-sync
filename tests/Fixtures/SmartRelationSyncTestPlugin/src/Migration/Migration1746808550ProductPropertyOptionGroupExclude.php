<?php

declare(strict_types=1);

namespace Swh\SmartRelationSyncTestPlugin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1746808550ProductPropertyOptionGroupExclude extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1746808550;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `property_group_option_exclude` (
                `property_group_option_id` BINARY(16) NOT NULL,
                `property_group_option_exclude_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`property_group_option_id`, `property_group_option_exclude_id`),
                CONSTRAINT `fk.option_exclude.property_group_option_id`
                    FOREIGN KEY (`property_group_option_id`) REFERENCES `property_group_option` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.option_exclude.property_group_option_exclude_id`
                    FOREIGN KEY (`property_group_option_exclude_id`) REFERENCES `property_group_option` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
            SQL;

        $connection->executeStatement($sql);
    }
}
