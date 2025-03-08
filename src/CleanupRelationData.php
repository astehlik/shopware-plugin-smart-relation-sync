<?php

namespace Swh\SmartRelationSync;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class CleanupRelationData
{
    /**
     * @var non-empty-array<non-empty-string, non-empty-string>[]
     */
    private array $referencedPrimaryKeys = [];

    /**
     * @param non-empty-array<non-empty-string, non-empty-string> $parentPrimaryKey
     */
    public function __construct(
        readonly public EntityDefinition $definition,
        readonly public array $parentPrimaryKey,
        readonly public array $parentPrimaryKeyFields,
        readonly public array $relatedPrimaryKeyFields,
    ) {
    }

    /**
     * @param non-empty-array<non-empty-string, non-empty-string> $primaryKey
     */
    public function addReferencedPrimaryKey(array $primaryKey): void
    {
        $primaryKey = array_diff_key($primaryKey, $this->parentPrimaryKeyFields);

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
