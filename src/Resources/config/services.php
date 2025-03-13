<?php

declare(strict_types=1);

use Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Swh\SmartRelationSync\ApiDefinition\EntitySchemaGeneratorDecorator;
use Swh\SmartRelationSync\ApiDefinition\OpenApiDefinitionSchemaBuilderDecorator;
use Swh\SmartRelationSync\DataAbstractionLayer\CleanupRelationsRegistry;
use Swh\SmartRelationSync\DataAbstractionLayer\EntityWriteSubscriber;
use Swh\SmartRelationSync\DataAbstractionLayer\ObsoleteRelationsDeleter;
use Swh\SmartRelationSync\DataAbstractionLayer\WriteCommandExtractorDecorator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->private()
        ->autowire()
        ->autoconfigure(false);

    $services->set(CleanupRelationsRegistry::class);

    $services->set(ObsoleteRelationsDeleter::class);

    $services->set(EntityWriteSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(WriteCommandExtractorDecorator::class)
        ->decorate(WriteCommandExtractor::class)
        ->bind('$decorated', service('.inner'));

    // Priority 10 is used to apply this decorator before the CachedEntitySchemaGenerator
    $services->set(EntitySchemaGeneratorDecorator::class)
        ->decorate(EntitySchemaGenerator::class, priority: 10);

    $services->set(OpenApiDefinitionSchemaBuilderDecorator::class)
        ->decorate(OpenApiDefinitionSchemaBuilder::class);
};
