<?php

declare(strict_types=1);

namespace Swh\SmartRelationSyncTestPlugin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class VersionedChildEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $name = null;

    protected ?VersionedParentEntity $parent = null;

    protected ?string $parentId = null;

    protected string $parentVersionId;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getParent(): ?VersionedParentEntity
    {
        return $this->parent;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getParentVersionId(): string
    {
        return $this->parentVersionId;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function setParent(?VersionedParentEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function setParentId(string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function setParentVersionId(string $parentVersionId): void
    {
        $this->parentVersionId = $parentVersionId;
    }
}
