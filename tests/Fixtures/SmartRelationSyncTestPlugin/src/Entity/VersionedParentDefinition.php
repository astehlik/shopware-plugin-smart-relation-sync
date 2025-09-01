<?php

declare(strict_types=1);

namespace Swh\SmartRelationSyncTestPlugin\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class VersionedParentDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'versioned_parent';

    public function getCollectionClass(): string
    {
        return VersionedParentCollection::class;
    }

    public function getEntityClass(): string
    {
        return VersionedParentEntity::class;
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new VersionField())->addFlags(new ApiAware()),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new Required(), new ApiAware()),

            (new StringField('name', 'name'))
                ->addFlags(new Required(), new ApiAware()),

            (new OneToManyAssociationField('children', VersionedChildDefinition::class, 'parent_id'))
                ->addFlags(new CascadeDelete(), new ApiAware()),
        ]);
    }
}
