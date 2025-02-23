<?php

namespace Swh\SmartRelationSync;

use RuntimeException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

use function assert;

class WriteCommandExtractorDecorator extends WriteCommandExtractor
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(
        private readonly CleanupRelationsRegistry $cleanupRelationsRegistry,
        private readonly WriteCommandExtractor $decorated,
    ) {
    }

    public function extract(array $rawData, WriteParameterBag $parameters): array
    {
        $this->registerRelations($rawData, $parameters);

        return $this->decorated->extract($rawData, $parameters);
    }

    private function buildCleanupDataForManyToManyAssociation(
        ManyToManyAssociationField $field,
        array $parentPrimaryKey
    ): CleanupRelationData {
        $reference = $field->getReferenceDefinition();

        $mappingAssociation = $this->getMappingAssociation($reference, $field);

        $fk = $reference->getFields()->getByStorageName(
            $mappingAssociation->getStorageName()
        );

        $referencedDefinition = $field->getMappingDefinition();

        $primaryKeyFields = $this->getPrimaryKeyFields($referencedDefinition);
        unset($primaryKeyFields[$fk->getPropertyName()]);

        return new CleanupRelationData(
            $reference,
            $parentPrimaryKey,
            $primaryKeyFields,
            [$fk->getPropertyName() => true],
        );
    }

    private function buildCleanupDataForOneToManyAssociation(
        OneToManyAssociationField $field,
        array $parentPrimaryKey
    ): CleanupRelationData {
        $reference = $field->getReferenceDefinition();

        $fkField = $reference->getFields()->getByStorageName($field->getReferenceField());

        return new CleanupRelationData(
            $reference,
            $parentPrimaryKey,
            [$fkField->getPropertyName() => true],
            $this->getPrimaryKeyFields($reference)
        );
    }

    private function getCleanupEnableFieldName(Field $field): string
    {
        return sprintf('%sCleanupRelations', $field->getPropertyName());
    }

    private function getMappingAssociation(
        EntityDefinition $referencedDefinition,
        ManyToManyAssociationField $field
    ): ManyToOneAssociationField {
        $associations = $referencedDefinition->getFields()->filterInstance(ManyToOneAssociationField::class);

        foreach ($associations as $association) {
            assert($association instanceof ManyToOneAssociationField);
            if ($association->getStorageName() === $field->getMappingReferenceColumn()) {
                return $association;
            }
        }

        throw new RuntimeException('Association not found!');
    }

    /**
     * @return non-empty-array<non-empty-string, non-empty-string>|null
     */
    private function getPrimaryKey(array $rawData, EntityDefinition $definition): ?array
    {
        $pk = [];

        $pkFields = $definition->getPrimaryKeys();
        foreach ($pkFields as $pkField) {
            $propertyName = $pkField->getPropertyName();

            $value = $rawData[$propertyName] ?? null;

            if (!is_string($value) || $value === '') {
                return null;
            }

            $pk[$propertyName] = $value;
        }

        if ($pk === []) {
            return null;
        }

        return $pk;
    }

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
            $fields[$propertyName] = true;
        }

        return $fields;
    }

    private function registerRelations(array $rawData, WriteParameterBag $parameters): void
    {
        $definition = $parameters->getDefinition();

        $primaryKeys = $this->getPrimaryKey($rawData, $definition);

        if ($primaryKeys === null) {
            return;
        }

        // New entities do not need cleanup.
        if (empty($parameters->getPrimaryKeyBag()->getExistenceState($parameters->getDefinition(), $primaryKeys))) {
            return;
        }

        foreach ($definition->getFields() as $field) {
            if (
                !$field instanceof ManyToManyAssociationField
                && !$field instanceof OneToManyAssociationField
            ) {
                continue;
            }

            $cleanupEnableField = $this->getCleanupEnableFieldName($field);

            if (!array_key_exists($cleanupEnableField, $rawData)) {
                continue;
            }

            $cleanupEnabled = is_bool($rawData[$cleanupEnableField]) && $rawData[$cleanupEnableField];

            unset($rawData[$cleanupEnableField]);

            if (!$cleanupEnabled) {
                continue;
            }

            $fieldData = $rawData[$field->getPropertyName()] ?? null;

            if (!is_array($fieldData) || empty($fieldData)) {
                continue;
            }

            $this->registerRelationsForField($field, $primaryKeys, $fieldData);
        }
    }

    private function registerRelationsForField(
        ManyToManyAssociationField|OneToManyAssociationField $field,
        array $parentPrimaryKey,
        array $fieldData
    ): void {
        $reference = $field->getReferenceDefinition();

        $cleanupRelationData = match ($field instanceof OneToManyAssociationField) {
            true => $this->buildCleanupDataForOneToManyAssociation($field, $parentPrimaryKey),
            false => $this->buildCleanupDataForManyToManyAssociation($field, $parentPrimaryKey),
        };

        foreach ($fieldData as $referenceData) {
            $referencePrimaryKey = $this->getPrimaryKey($referenceData, $reference);

            if ($referencePrimaryKey === null) {
                return;
            }

            $cleanupRelationData->addReferencedPrimaryKey($referencePrimaryKey);
        }

        $this->cleanupRelationsRegistry->registerRelationsForField($cleanupRelationData);
    }
}
