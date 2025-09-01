<?php

declare(strict_types=1);

namespace Swh\SmartRelationSyncTestPlugin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<VersionedChildEntity>
 */
class VersionedChildCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return VersionedChildEntity::class;
    }
}
