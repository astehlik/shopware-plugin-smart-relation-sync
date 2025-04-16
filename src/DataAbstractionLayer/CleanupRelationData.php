<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

final class CleanupRelationData
{
    /**
     * @var non-empty-array<non-empty-string, non-empty-string>[]
     */
    private array $referencedPrimaryKeys = [];

    /**
     * @param non-empty-array<non-empty-string, non-empty-string> $parentPrimaryKey
     * @param array<non-empty-string, true> $parentPrimaryKeyFields
     * @param array<non-empty-string, true> $relatedPrimaryKeyFields
     */
    public function __construct(
        readonly public EntityDefinition $definition,
        readonly public array $parentPrimaryKey,
        readonly public array $parentPrimaryKeyFields,
        readonly public array $relatedPrimaryKeyFields,
    ) {}

    /**
     * @param non-empty-array<non-empty-string, non-empty-string> $primaryKey
     */
    public function addReferencedPrimaryKey(array $primaryKey): void
    {
        $primaryKey = array_diff_key($primaryKey, $this->parentPrimaryKeyFields);

        if ($primaryKey === []) {
            return; // @codeCoverageIgnore
        }

        $this->referencedPrimaryKeys[] = $primaryKey;
    }

    /**
     * @return non-empty-array<non-empty-string, non-empty-string>[]
     */
    public function getReferencedPrimaryKeys(): array
    {
        return $this->referencedPrimaryKeys;
    }
}
