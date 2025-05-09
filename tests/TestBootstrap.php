<?php

declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

$loader = include __DIR__ . '/../vendor/autoload.php';

(new TestBootstrapper())
    ->setClassLoader($loader)
    ->setPlatformEmbedded(true)
    ->addActivePlugins('SmartRelationSyncTestPlugin')
    ->addCallingPlugin(__DIR__ . '/../composer.json')
    ->bootstrap();
