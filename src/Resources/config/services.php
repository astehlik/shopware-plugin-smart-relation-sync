<?php

declare(strict_types=1);

use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Swh\SmartRelationSync\CleanupRelationsRegistry;
use Swh\SmartRelationSync\EntityWriteSubscriber;
use Swh\SmartRelationSync\ObsoleteRelationsDeleter;
use Swh\SmartRelationSync\WriteCommandExtractorDecorator;
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
};
