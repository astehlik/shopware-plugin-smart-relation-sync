<?php

declare(strict_types=1);

use Swh\SmartRelationSyncTestPlugin\Entity\PropertyGroupOptionExcludeDefinition;
use Swh\SmartRelationSyncTestPlugin\Entity\PropertyGroupOptionExcludeExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->private()
        ->autowire()
        ->autoconfigure();

    $services->set(PropertyGroupOptionExcludeDefinition::class);
    $services->set(PropertyGroupOptionExcludeExtension::class);
};
