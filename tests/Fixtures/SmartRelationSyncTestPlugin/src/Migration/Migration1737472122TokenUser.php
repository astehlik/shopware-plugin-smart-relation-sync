<?php

declare(strict_types=1);

namespace Swh\SmartRelationSyncTestPlugin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Temporary workaround for missing migration, see:
 *
 * https://github.com/shopware/shopware/pull/14977
 */
final class Migration1737472122TokenUser extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1737472122;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS oauth_user (
                `id` BINARY(16) UNIQUE NOT NULL,
                `user_id` BINARY(16) UNIQUE NOT NULL,
                `user_sub` VARCHAR(255) UNIQUE NOT NULL,
                `token` JSON DEFAULT NULL,
                `expiry` DATETIME NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3),
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.oauth_user.user_id` FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                KEY `idx.oauth_user.user_sub` (`user_sub`)
            )
        ');
    }
}
