<?php

/**
 * This file is based on vendor/shopware/core/DevOps/StaticAnalyze/console-application.php.
 *
 * Differences to the original are documented in the code via comments.
 */

declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan;

use Shopware\Core\DevOps\StaticAnalyze\StaticAnalyzeKernel;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Symfony\Bundle\FrameworkBundle\Console\Application;

// CHANGE: Adjust path to the PHPStan bootstrap file
$classLoader = require __DIR__ . '/../../../vendor/shopware/core/DevOps/StaticAnalyze/phpstan-bootstrap.php';

// CHANGE: Use the ComposerPluginLoader instead of the \Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader
// This is necessary to correctly load the plugins in the PHPStan environment.
$pluginLoader = new ComposerPluginLoader($classLoader);

KernelFactory::$kernelClass = StaticAnalyzeKernel::class;

/** @var StaticAnalyzeKernel $kernel */
$kernel = KernelFactory::create(
    environment: 'phpstan_dev',
    debug: true,
    classLoader: $classLoader,
    pluginLoader: $pluginLoader,
);

$kernel->boot();

return new Application($kernel);
