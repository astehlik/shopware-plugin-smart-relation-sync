<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\DataAbstractionLayer;

use Symfony\Contracts\Service\ResetInterface;

final class CleanupRelationsRegistry implements ResetInterface
{
    /**
     * @var CleanupRelationData[]
     */
    private array $cleanupRelations = [];

    /**
     * @return CleanupRelationData[]
     */
    public function popCleanupRelations(): array
    {
        $relations = $this->cleanupRelations;

        $this->reset();

        return $relations;
    }

    public function registerRelationsForField(CleanupRelationData $cleanupRelationData): void
    {
        $this->cleanupRelations[] = $cleanupRelationData;
    }

    public function reset(): void
    {
        $this->cleanupRelations = [];
    }
}
