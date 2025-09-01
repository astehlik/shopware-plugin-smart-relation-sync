<?php

declare(strict_types=1);

namespace Swh\SmartRelationSyncTestPlugin\Entity;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class PropertyGroupOptionExcludeDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'property_group_option_exclude';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('property_group_option_id', 'optionId', PropertyGroupOptionDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('option', 'property_group_option_id', PropertyGroupOptionDefinition::class, 'id', false),

            (new FkField('property_group_option_exclude_id', 'excludedOptionId', PropertyGroupOptionDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('excludedOption', 'property_group_option_exclude_id', PropertyGroupOptionDefinition::class, 'id', false),
        ]);
    }
}
