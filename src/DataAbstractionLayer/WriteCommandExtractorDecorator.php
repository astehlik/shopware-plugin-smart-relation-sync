<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\DataAbstractionLayer;

use RuntimeException;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Swh\SmartRelationSync\ValueObject\RelevantField;

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
    ): string {
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
     *
     * @return array<mixed, mixed>
     */
    private function getRelevantRawData(Field $field, array $rawData): array
    {
        if (!$field->is(Extension::class)) {
            return $rawData;
        }

        $propertyName = $field->getPropertyName();

        if (isset($rawData[$propertyName])) {
            return $rawData;
        }

        if (
            !isset($rawData['extensions'])
            || !is_array($rawData['extensions'])
            || !isset($rawData['extensions'][$propertyName])) {
            return $rawData;
        }

        return $rawData['extensions'];
    }

    /**
     * @param array<mixed, mixed> $rawData
     */
    private function registerFieldForCleanup(
        RelevantField $relevantField,
        array $rawData,
    ): void {
        $field = $relevantField->field;

        $fieldData = $rawData[$field->getPropertyName()] ?? null;

        if (!is_array($fieldData)) {
            return;
        }

        $cleanupEnableField = $this->getCleanupEnableFieldName($field);

        if (!array_key_exists($cleanupEnableField, $rawData)) {
            return;
        }

        $cleanupEnabled = is_bool($rawData[$cleanupEnableField]) && $rawData[$cleanupEnableField];

        unset($rawData[$cleanupEnableField]);

        if (!$cleanupEnabled) {
            return;
        }

        $this->registerRelationsForField($relevantField, $fieldData);
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
            $relevantField = RelevantField::create($field, $primaryKeys);

            if ($relevantField === null) {
                continue;
            }

            $rawData = $this->getRelevantRawData($field, $rawData);

            $this->registerFieldForCleanup($relevantField, $rawData);
        }
    }

    /**
     * @param array<mixed, mixed> $fieldData
     */
    private function registerRelationsForField(
        RelevantField $relevantField,
        array $fieldData,
    ): void {
        $field = $relevantField->field;
        $parentPrimaryKey = $relevantField->parentPrimaryKey;

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
