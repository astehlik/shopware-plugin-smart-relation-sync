<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\ApiDefinition;

use OpenApi\Annotations\Property;
use OpenApi\Annotations\Schema;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\IgnoreInOpenapiSchema;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Swh\SmartRelationSync\DataAbstractionLayer\WriteCommandExtractorDecorator;

class OpenApiDefinitionSchemaBuilderDecorator extends OpenApiDefinitionSchemaBuilder
{
    /**
     * @return Schema[]
     */
    public function getSchemaByDefinition(
        EntityDefinition $definition,
        string $path,
        bool $forSalesChannel,
        bool $onlyFlat = false,
        string $apiType = DefinitionService::TYPE_JSON_API,
    ): array {
        $schemas = parent::getSchemaByDefinition($definition, $path, $forSalesChannel, $onlyFlat, $apiType);

        if (count($schemas) !== 1) {
            return $schemas;
        }

        $relevantFields = $definition->getFields()
            ->filter(fn(Field $field) => $this->isRelevantField($field));

        $schema = current($schemas);

        foreach ($relevantFields as $field) {
            if (!$this->shouldFieldBeIncluded($field, $forSalesChannel)) {
                continue;
            }

            $enableFieldName = WriteCommandExtractorDecorator::getCleanupEnableFieldName($field);

            $property = new Property([
                'property' => $enableFieldName,
                'type' => 'boolean',
            ]);

            $schema->properties[] = $property;
        }

        return $schemas;
    }

    /**
     * @phpstan-assert-if-true ManyToManyAssociationField|OneToManyAssociationField $field
     */
    private function isRelevantField(Field $field): bool
    {
        return WriteCommandExtractorDecorator::isRelevantField($field);
    }

    private function shouldFieldBeIncluded(Field $field, bool $forSalesChannel): bool
    {
        if ($field->getPropertyName() === 'translations'
            || preg_match('#translations$#i', $field->getPropertyName())
        ) {
            return false;
        }

        $ignoreOpenApiSchemaFlag = $field->getFlag(IgnoreInOpenapiSchema::class);
        if ($ignoreOpenApiSchemaFlag !== null) {
            return false;
        }

        $flag = $field->getFlag(ApiAware::class);
        if ($flag === null) {
            return false;
        }

        if (!$flag->isSourceAllowed($forSalesChannel ? SalesChannelApiSource::class : AdminApiSource::class)) {
            return false;
        }

        return true;
    }
}
