<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\ValueObject;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;

readonly class RelevantField
{
    /**
     * @param non-empty-array<non-empty-string, non-empty-string> $parentPrimaryKey
     */
    public function __construct(
        public ManyToManyAssociationField|OneToManyAssociationField $field,
        public array $parentPrimaryKey,
    ) {}

    /**
     * @param non-empty-array<non-empty-string, non-empty-string> $parentPrimaryKey
     */
    public static function create(Field $field, array $parentPrimaryKey): ?self
    {
        if (!self::isRelevant($field)) {
            return null;
        }

        /** @var ManyToManyAssociationField|OneToManyAssociationField $field */
        return new self($field, $parentPrimaryKey);
    }

    /**
     * @phpstan-assert-if-true ManyToManyAssociationField|OneToManyAssociationField $field
     */
    public static function isRelevant(Field $field): bool
    {
        return $field instanceof ManyToManyAssociationField
            || $field instanceof OneToManyAssociationField;
    }
}
