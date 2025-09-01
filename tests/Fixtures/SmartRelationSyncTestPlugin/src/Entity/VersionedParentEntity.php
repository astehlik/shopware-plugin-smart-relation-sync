<?php

declare(strict_types=1);

namespace Swh\SmartRelationSyncTestPlugin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class VersionedParentEntity extends Entity
{
    use EntityIdTrait;

    protected ?VersionedChildCollection $children = null;

    protected string $name;

    protected string $parentVersionId;

    public function getChildren(): ?VersionedChildCollection
    {
        return $this->children;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParentVersionId(): string
    {
        return $this->parentVersionId;
    }

    public function setChildren(VersionedChildCollection $children): void
    {
        $this->children = $children;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setParentVersionId(string $parentVersionId): void
    {
        $this->parentVersionId = $parentVersionId;
    }
}
