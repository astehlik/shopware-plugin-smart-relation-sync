<?php

declare(strict_types=1);

namespace Swh\SmartRelationSyncTestPlugin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1756750652VersionedRelation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1756750652;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE versioned_parent
            (
                id binary(16) NOT NULL,
                version_id binary(16) NOT NULL,
                parent_version_id binary(16) NOT NULL,
                name varchar(255) NOT NULL,
                created_at datetime(3) NOT NULL,
                updated_at datetime(3) NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT `uniq.versioned_parent_id__parent_version_id` UNIQUE (id, version_id, parent_version_id)
            );
            SQL;

        $connection->executeStatement($sql);

        $sql = <<<'SQL'
            CREATE TABLE versioned_child
            (
                id binary(16) NOT NULL,
                version_id binary(16) NOT NULL,
                parent_id binary(16) NOT NULL,
                parent_version_id binary(16) NOT NULL,
                name varchar(255) NOT NULL,
                created_at datetime(3) NOT NULL,
                updated_at datetime(3) NULL,
                related_property_group_id binary(16) NULL,
                PRIMARY KEY (id, version_id),
                CONSTRAINT `fk.versioned_child.parent_id`
                    FOREIGN KEY (parent_id, parent_version_id) REFERENCES versioned_parent (id, version_id) ON UPDATE CASCADE ON DELETE CASCADE
            );
            SQL;

        $connection->executeStatement($sql);
    }
}
