<?php

declare(strict_types=1);

namespace Swh\SmartRelationSyncTestPlugin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class VersionedChildDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'versioned_child';

    public function getCollectionClass(): string
    {
        return VersionedChildCollection::class;
    }

    public function getEntityClass(): string
    {
        return VersionedChildEntity::class;
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            new VersionField(),
            (new ReferenceVersionField(VersionedParentDefinition::class, 'parent_version_id'))
                ->addFlags(new Required(), new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),

            (new FkField('parent_id', 'parentId', VersionedParentDefinition::class))
                ->addFlags(new Required(), new ApiAware()),
            (new ManyToOneAssociationField('parent', 'parent_id', VersionedParentDefinition::class))
                ->addFlags(new ApiAware()),
        ]);
    }

    protected function getParentDefinitionClass(): ?string
    {
        return VersionedParentDefinition::class;
    }
}
