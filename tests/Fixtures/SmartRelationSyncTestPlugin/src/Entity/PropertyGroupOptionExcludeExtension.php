<?php

declare(strict_types=1);

namespace Swh\SmartRelationSyncTestPlugin\Entity;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class PropertyGroupOptionExcludeExtension extends EntityExtension
{
    public const EXTENSION_NAME = 'excludedOptions';

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new ManyToManyAssociationField(
                self::EXTENSION_NAME,
                PropertyGroupOptionDefinition::class,
                PropertyGroupOptionExcludeDefinition::class,
                'property_group_option_id',
                'property_group_option_exclude_id',
            )
            )->addFlags(new ApiAware(), new CascadeDelete()),
        );
    }

    public function getDefinitionClass(): string
    {
        return PropertyGroupOptionDefinition::class;
    }
}
