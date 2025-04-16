<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\DataAbstractionLayer;

use RuntimeException;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

final class WriteCommandExtractorDecorator extends WriteCommandExtractor
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(
        private readonly CleanupRelationsRegistry $cleanupRelationsRegistry,
        private readonly WriteCommandExtractor $decorated,
    ) {}

    public static function getCleanupEnableFieldName(Field $field): string
    {
        return sprintf('%sCleanupRelations', $field->getPropertyName());
    }

    /**
     * @phpstan-assert-if-true ManyToManyAssociationField|OneToManyAssociationField $field
     */
    public static function isRelevantField(Field $field): bool
    {
        return $field instanceof ManyToManyAssociationField
            || $field instanceof OneToManyAssociationField;
    }

    /**
     * @param array<mixed, mixed> $rawData
     */
    public function extract(array $rawData, WriteParameterBag $parameters): array
    {
        $this->registerRelations($rawData, $parameters);

        return $this->decorated->extract($rawData, $parameters);
    }

    /**
     * @param non-empty-array<non-empty-string, non-empty-string> $parentPrimaryKey
     */
    private function buildCleanupDataForManyToManyAssociation(
        ManyToManyAssociationField $field,
        array $parentPrimaryKey,
    ): CleanupRelationData {
        $reference = $field->getReferenceDefinition();

        $mappingAssociation = $this->getMappingAssociation($reference, $field);

        $storageName = $mappingAssociation->getStorageName();

        $fkPropertyName = $this->getForeignKeyPropertyNameByStorageName($reference->getFields(), $storageName);

        $referencedDefinition = $field->getMappingDefinition();

        $primaryKeyFields = $this->getPrimaryKeyFields($referencedDefinition);
        unset($primaryKeyFields[$fkPropertyName]);

        return new CleanupRelationData(
            $reference,
            $parentPrimaryKey,
            $primaryKeyFields,
            [$fkPropertyName => true],
        );
    }

    /**
     * @param non-empty-array<non-empty-string, non-empty-string> $parentPrimaryKey
     */
    private function buildCleanupDataForOneToManyAssociation(
        OneToManyAssociationField $field,
        array $parentPrimaryKey,
    ): CleanupRelationData {
        $reference = $field->getReferenceDefinition();

        $fkPropertyName = $this->getForeignKeyPropertyNameByStorageName(
            $reference->getFields(),
            $field->getReferenceField(),
        );

        return new CleanupRelationData(
            $reference,
            $parentPrimaryKey,
            [$fkPropertyName => true],
            $this->getPrimaryKeyFields($reference),
        );
    }

    /**
     * @param non-empty-array<non-empty-string, non-empty-string> $primaryKeys
     *
     * @return non-empty-array<non-empty-string, non-empty-string>
     */
    private function filterVersionFields(array $primaryKeys, EntityDefinition $definition): array
    {
        /** @var array<int, string> $versionFields */
        $versionFields = $definition->getFields()
            ->filter(
                static fn(Field $field) => $field instanceof ReferenceVersionField || $field instanceof VersionField,
            )
            ->map(static fn(Field $field): string => $field->getPropertyName());

        if ($versionFields === []) {
            return $primaryKeys;
        }

        $primaryKeysWithoutVersionFields = array_diff_key($primaryKeys, array_flip($versionFields));

        assert(
            count($primaryKeysWithoutVersionFields) > 0,
            'No primary keys remained after removing the version fields.',
        );

        return $primaryKeysWithoutVersionFields;
    }

    /**
     * @return non-empty-string
     */
    private function getForeignKeyPropertyNameByStorageName(
        CompiledFieldCollection $fields,
        string $storageName,
    ) {
        $fk = $fields->getByStorageName($storageName);

        assert(
            $fk !== null,
            'Could not find foreign key field by storage name ' . $storageName,
        );

        $fkPropertyName = $fk->getPropertyName();

        assert($fkPropertyName !== '', 'Foreign key property name was empty');

        return $fkPropertyName;
    }

    private function getMappingAssociation(
        EntityDefinition $referencedDefinition,
        ManyToManyAssociationField $field,
    ): ManyToOneAssociationField {
        $associations = $referencedDefinition->getFields()->filterInstance(ManyToOneAssociationField::class);

        foreach ($associations as $association) {
            assert($association instanceof ManyToOneAssociationField);
            if ($association->getStorageName() === $field->getMappingReferenceColumn()) {
                return $association;
            }
        }

        throw new RuntimeException('Association not found!'); // @codeCoverageIgnore
    }

    /**
     * @param array<mixed, mixed> $rawData
     *
     * @return non-empty-array<non-empty-string, non-empty-string>|null
     */
    private function getPrimaryKey(array $rawData, EntityDefinition $definition): ?array
    {
        $pk = [];

        $pkFields = $definition->getPrimaryKeys();

        foreach ($pkFields as $pkField) {
            $propertyName = $pkField->getPropertyName();

            if ($propertyName === '') {
                return null; // @codeCoverageIgnore
            }

            $value = $rawData[$propertyName] ?? null;

            if (!is_string($value) || $value === '') {
                return null; // @codeCoverageIgnore
            }

            $pk[$propertyName] = $value;
        }

        if ($pk === []) {
            return null; // @codeCoverageIgnore
        }

        return $pk;
    }

    /**
     * @return array<non-empty-string, true>
     */
    private function getPrimaryKeyFields(EntityDefinition $reference): array
    {
        $fields = [];

        foreach ($reference->getPrimaryKeys() as $primaryKey) {
            if (
                $primaryKey instanceof VersionField
                || $primaryKey instanceof FkField && $primaryKey->getReferenceDefinition() instanceof VersionDefinition
            ) {
                continue;
            }

            $propertyName = $primaryKey->getPropertyName();

            if ($propertyName === '') {
                throw new RuntimeException('Primary property name is empty!'); // @codeCoverageIgnore
            }

            $fields[$propertyName] = true;
        }

        return $fields;
    }

    /**
     * @param array<mixed, mixed> $rawData
     */
    private function registerRelations(array $rawData, WriteParameterBag $parameters): void
    {
        $definition = $parameters->getDefinition();

        $primaryKeys = $this->getPrimaryKey($rawData, $definition);

        if ($primaryKeys === null) {
            return; // @codeCoverageIgnore
        }

        // New entities do not need cleanup.
        if (empty($parameters->getPrimaryKeyBag()->getExistenceState($parameters->getDefinition(), $primaryKeys))) {
            return;
        }

        $primaryKeys = $this->filterVersionFields($primaryKeys, $definition);

        foreach ($definition->getFields() as $field) {
            if (!self::isRelevantField($field)) {
                continue;
            }

            $cleanupEnableField = $this->getCleanupEnableFieldName($field);

            if (!array_key_exists($cleanupEnableField, $rawData)) {
                continue;
            }

            $cleanupEnabled = is_bool($rawData[$cleanupEnableField]) && $rawData[$cleanupEnableField];

            unset($rawData[$cleanupEnableField]);

            if (!$cleanupEnabled) {
                continue; // @codeCoverageIgnore
            }

            $fieldData = $rawData[$field->getPropertyName()] ?? null;

            if (!is_array($fieldData)) {
                continue;
            }

            $this->registerRelationsForField($field, $primaryKeys, $fieldData);
        }
    }

    /**
     * @param non-empty-array<non-empty-string, non-empty-string> $parentPrimaryKey
     * @param array<mixed, mixed> $fieldData
     */
    private function registerRelationsForField(
        ManyToManyAssociationField|OneToManyAssociationField $field,
        array $parentPrimaryKey,
        array $fieldData,
    ): void {
        $reference = $field->getReferenceDefinition();

        $cleanupRelationData = match ($field instanceof OneToManyAssociationField) {
            true => $this->buildCleanupDataForOneToManyAssociation($field, $parentPrimaryKey),
            false => $this->buildCleanupDataForManyToManyAssociation($field, $parentPrimaryKey),
        };

        foreach ($fieldData as $referenceData) {
            if (!is_array($referenceData)) {
                continue; // @codeCoverageIgnore
            }

            $referencePrimaryKey = $this->getPrimaryKey($referenceData, $reference);

            if ($referencePrimaryKey === null) {
                return; // @codeCoverageIgnore
            }

            $referencePrimaryKey = $this->filterVersionFields($referencePrimaryKey, $reference);

            $cleanupRelationData->addReferencedPrimaryKey($referencePrimaryKey);
        }

        $this->cleanupRelationsRegistry->registerRelationsForField($cleanupRelationData);
    }
}
