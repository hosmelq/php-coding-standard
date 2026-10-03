<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->addPathToExclude(__DIR__.'/tests/Fixtures')
    ->addPathToScan(__DIR__.'/config', isDev: false)
    ->ignoreErrors([ErrorType::SHADOW_DEPENDENCY])
    ->ignoreErrorsOnPackages([
        'phpstan/extension-installer',
        'phpstan/phpstan',
        'phpstan/phpstan-deprecation-rules',
        'phpstan/phpstan-strict-rules',
        'spaze/phpstan-disallowed-calls',
        'thecodingmachine/phpstan-safe-rule',
        'thecodingmachine/safe',
        'tomasvotruba/type-coverage',
    ], [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreUnknownClasses([
        'Pest\\Rector\\Rules\\UseSequenceMatcherRector',
        'Pest\\Rector\\Set\\PestSetList',
        'RectorLaravel\\Rector\\Class_\\HiddenPropertyToHiddenAttributeRector',
        'RectorLaravel\\Set\\LaravelSetList',
    ]);
