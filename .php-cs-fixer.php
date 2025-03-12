<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

if (PHP_SAPI !== 'cli') {
    exit('This script supports command line usage only. Please check your command.');
}

$rules = include __DIR__ . '/vendor/de-swebhosting/php-codestyle/PhpCsFixer/PerCsDefaultRules.php';

return (new Config())
    ->setFinder(
        (new Finder())
            ->ignoreVCSIgnored(true)
            ->in(__DIR__)
            ->exclude(['vendor'])
    )
    ->setRiskyAllowed(true)
    ->setRules($rules);
