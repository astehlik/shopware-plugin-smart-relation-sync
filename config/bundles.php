<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

use Shopware\Core\Checkout\Checkout;
use Shopware\Core\Content\Content;
use Shopware\Core\DevOps\DevOps;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Maintenance\Maintenance;
use Shopware\Core\Profiling\Profiling;
use Shopware\Core\System\System;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;

return [
    FrameworkBundle::class => ['all' => true],
    Framework::class => ['all' => true],
    System::class => ['all' => true],
    Content::class => ['all' => true],
    Checkout::class => ['all' => true],
    Maintenance::class => ['all' => true],
    DevOps::class => ['e2e' => true],
    Profiling::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    MonologBundle::class => ['all' => true],
    DebugBundle::class => ['dev' => true],
    WebProfilerBundle::class => ['dev' => true, 'test' => true],
];
