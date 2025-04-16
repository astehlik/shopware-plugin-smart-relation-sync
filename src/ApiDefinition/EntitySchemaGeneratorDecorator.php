<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\ApiDefinition;

use RuntimeException;
use Shopware\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Swh\SmartRelationSync\DataAbstractionLayer\WriteCommandExtractorDecorator;

/**
 * @internal
 *
 * @phpstan-import-type ApiSchema from DefinitionService
 */
final class EntitySchemaGeneratorDecorator extends EntitySchemaGenerator
{
    private const array CLEANUP_PROPERTY_DEFINITION = [
        'type' => 'boolean',
        'flags' => [
            // Reading is never allowed, the field is not returned by the API
            'read_protected' => [],
            // Writing is only allowed in the admin API context.
            'write_protected' => [AdminApiSource::class],
        ],
    ];

    public function __construct(private ApiDefinitionGeneratorInterface $decorated) {}

    /**
     * @codeCoverageIgnore
     */
    public function generate(
        array $definitions,
        string $api,
        string $apiType = 'jsonapi',
        ?string $bundleName = null,
    ): never {
        throw new RuntimeException();
    }

    /**
     * @param array<string, EntityDefinition&SalesChannelDefinitionInterface>|array<string, EntityDefinition> $definitions
     *
     * @return ApiSchema
     */
    public function getSchema(array $definitions): array
    {
        $schema = $this->decorated->getSchema($definitions);

        foreach ($definitions as $definition) {
            $entity = $definition->getEntityName();

            if (!array_key_exists($entity, $schema)) {
                continue; // @codeCoverageIgnore
            }

            $entityProperties = $schema[$entity]['properties'];

            $relevantFields = $definition->getFields()
                ->filter(fn(Field $field) => $this->isRelevantField($field));

            foreach ($relevantFields as $field) {
                if (!array_key_exists($field->getPropertyName(), $entityProperties)) {
                    continue; // @codeCoverageIgnore
                }

                $fieldName = WriteCommandExtractorDecorator::getCleanupEnableFieldName($field);

                $entityProperties[$fieldName] = self::CLEANUP_PROPERTY_DEFINITION;
            }

            $schema[$entity]['properties'] = $entityProperties;
        }

        return $schema;
    }

    public function supports(string $format, string $api): bool
    {
        return $this->decorated->supports($format, $api);
    }

    /**
     * @phpstan-assert-if-true ManyToManyAssociationField|OneToManyAssociationField $field
     */
    private function isRelevantField(Field $field): bool
    {
        return WriteCommandExtractorDecorator::isRelevantField($field);
    }
}
