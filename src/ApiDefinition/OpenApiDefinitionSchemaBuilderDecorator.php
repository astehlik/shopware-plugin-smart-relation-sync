<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\ApiDefinition;

use OpenApi\Annotations\Property;
use OpenApi\Annotations\Schema;
use RuntimeException;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\IgnoreInOpenapiSchema;
use Swh\SmartRelationSync\DataAbstractionLayer\WriteCommandExtractorDecorator;
use Swh\SmartRelationSync\ValueObject\RelevantField;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class OpenApiDefinitionSchemaBuilderDecorator extends OpenApiDefinitionSchemaBuilder
{
    private readonly CamelCaseToSnakeCaseNameConverter $converter;

    public function __construct()
    {
        parent::__construct();

        $this->converter = new CamelCaseToSnakeCaseNameConverter(null, false);
    }

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

        if ($forSalesChannel) {
            return $schemas; // @codeCoverageIgnore
        }

        $relevantSchemas = $this->getRelevantSchemas($schemas, $definition);

        if (count($relevantSchemas) === 0) {
            return $schemas; // @codeCoverageIgnore
        }

        $relevantFields = $definition->getFields()
            ->filter(static fn(Field $field) => RelevantField::isRelevant($field));

        foreach ($relevantFields as $field) {
            if (!$this->shouldFieldBeIncluded($field, $forSalesChannel)) {
                continue;
            }

            $enableFieldName = WriteCommandExtractorDecorator::getCleanupEnableFieldName($field);

            $property = new Property([
                'property' => $enableFieldName,
                'type' => 'boolean',
                'writeOnly' => true,
            ]);

            match ($field->is(Extension::class)) {
                true => $this->addExtensionToSchemas($relevantSchemas, $property),
                false => $this->addPropertyToSchemas($relevantSchemas, $property),
            };
        }

        return $schemas;
    }

    /**
     * @param Schema[] $schemas
     */
    private function addExtensionToSchemas(array $schemas, Property $property): void
    {
        foreach ($schemas as $schema) {
            $extensionSchema = $this->getExtensionSchema($schema);

            $extensionSchema->properties[] = $property;
        }
    }

    /**
     * @param Schema[] $schemas
     */
    private function addPropertyToSchemas(array $schemas, Property $property): void
    {
        foreach ($schemas as $schema) {
            $schema->properties[] = $property;
        }
    }

    private function getExtensionSchema(Schema $schema): Schema
    {
        foreach ($schema->properties as $property) {
            if ($property->property === 'extensions') {
                return $property;
            }
        }

        throw new RuntimeException('extensions property not found');
    }

    /**
     * @param Schema[] $schemas
     *
     * @return Schema[]
     */
    private function getRelevantSchemas(array $schemas, EntityDefinition $definition): array
    {
        $schemaName = $this->snakeCaseToCamelCase($definition->getEntityName());

        if (!array_key_exists($schemaName, $schemas)) {
            return [];  // @codeCoverageIgnore
        }

        $relevantSchemas = [$schemas[$schemaName]];

        $schemaNameJsonApi = $schemaName . 'JsonApi';

        if (!array_key_exists($schemaNameJsonApi, $schemas)) {
            return $relevantSchemas;
        }

        $jsonApiSchema = $schemas[$schemaNameJsonApi];

        $childSchema = $jsonApiSchema->allOf[1] ?? null;

        if ($childSchema instanceof Schema) {
            $relevantSchemas[] = $childSchema;
        }

        return $relevantSchemas;
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
            return false; // @codeCoverageIgnore
        }

        $flag = $field->getFlag(ApiAware::class);
        if ($flag === null) {
            return false;
        }

        if (!$flag->isSourceAllowed($forSalesChannel ? SalesChannelApiSource::class : AdminApiSource::class)) {
            return false; // @codeCoverageIgnore
        }

        return true;
    }

    private function snakeCaseToCamelCase(string $input): string
    {
        return $this->converter->denormalize($input);
    }
}
