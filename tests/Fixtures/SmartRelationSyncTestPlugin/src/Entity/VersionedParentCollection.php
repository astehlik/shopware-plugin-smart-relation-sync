<?php

declare(strict_types=1);

namespace Swh\SmartRelationSyncTestPlugin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<VersionedParentEntity>
 */
class VersionedParentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return VersionedParentEntity::class;
    }
}
