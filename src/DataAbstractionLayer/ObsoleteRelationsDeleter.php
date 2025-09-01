<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\DataAbstractionLayer;

use RuntimeException;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;

final readonly class ObsoleteRelationsDeleter
{
    public function __construct(
        private EntityWriter $entityWriter,
        private CleanupRelationsRegistry $registry,
        private DefinitionInstanceRegistry $definitionInstanceRegistry,
    ) {}

    public function deleteObsoleteRelations(Context $context, WriteContext $writeContext): void
    {
        $cleanupRelations = $this->registry->popCleanupRelations();

        $deleteCommands = [];

        foreach ($cleanupRelations as $cleanupRelation) {
            $deletePrimaryKeys = $this->getDeletePrimaryKeys($cleanupRelation, $context);

            if (empty($deletePrimaryKeys)) {
                continue; // @codeCoverageIgnore
            }

            $entity = $cleanupRelation->definition->getEntityName();

            $deleteCommands['cleanup-' . $entity] = $deleteCommands['cleanup-' . $entity] ?? [
                'entity' => $entity,
                'payload' => [],
            ];

            foreach ($deletePrimaryKeys as $deletePrimaryKey) {
                $deleteCommands['cleanup-' . $entity]['payload'][] = is_string($deletePrimaryKey)
                    ? ['id' => $deletePrimaryKey]
                    : $deletePrimaryKey;
            }
        }

        $deleteOperations = [];

        foreach ($deleteCommands as $key => $deleteCommand) {
            $deleteOperations[] = new SyncOperation(
                $key,
                $deleteCommand['entity'],
                'delete',
                $deleteCommand['payload'],
            );
        }

        if ($deleteCommands === []) {
            return;
        }

        $this->entityWriter->sync($deleteOperations, $writeContext);
    }

    /**
     * @return list<array<string,string>>|list<string>
     */
    private function getDeletePrimaryKeys(CleanupRelationData $cleanupRelation, Context $context): array
    {
        $repository = $this->definitionInstanceRegistry->getRepository($cleanupRelation->definition->getEntityName());

        $existingIdFilters = [];

        $referencedPrimaryKeyField = $this->getMainPrimaryKeyField($cleanupRelation->relatedPrimaryKeyFields);

        foreach ($cleanupRelation->getReferencedPrimaryKeys() as $referencedPrimaryKey) {
            $existingIdFilters[] = new EqualsFilter(
                $referencedPrimaryKeyField,
                $this->getMainPrimaryKey($referencedPrimaryKey),
            );
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter(
                $this->getMainPrimaryKeyField($cleanupRelation->parentPrimaryKeyFields),
                $this->getMainPrimaryKey($cleanupRelation->parentPrimaryKey),
            ),
        );
        $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_OR, $existingIdFilters));

        return $repository->searchIds($criteria, $context)->getIds();
    }

    /**
     * @param non-empty-array<non-empty-string, non-empty-string> $primaryKey
     */
    private function getMainPrimaryKey(array $primaryKey): string
    {
        if (count($primaryKey) !== 1) {
            throw new RuntimeException('Primary does not consist of exactly one primary key'); // @codeCoverageIgnore
        }

        return reset($primaryKey);
    }

    /**
     * @param array<non-empty-string, true> $primaryKey
     *
     * @return non-empty-string
     */
    private function getMainPrimaryKeyField(array $primaryKey): string
    {
        if (count($primaryKey) !== 1) {
            throw new RuntimeException('Primary does not consist of exactly one primary key'); // @codeCoverageIgnore
        }

        $fields = array_keys($primaryKey);

        return reset($fields);
    }
}
