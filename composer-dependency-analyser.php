<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->addPathToExclude(__DIR__.'/tests/Fixtures')
    ->addPathToScan(__DIR__.'/config', isDev: false)
    ->ignoreErrors([ErrorType::SHADOW_DEPENDENCY])
    ->ignoreErrorsOnPackages([
        'nesbot/carbon',
        'phpstan/extension-installer',
        'phpstan/phpstan',
        'phpstan/phpstan-deprecation-rules',
        'phpstan/phpstan-strict-rules',
        'spaze/phpstan-disallowed-calls',
        'thecodingmachine/phpstan-safe-rule',
        'thecodingmachine/safe',
        'ticketswap/phpstan-error-formatter',
        'tomasvotruba/type-coverage',
    ], [ErrorType::UNUSED_DEPENDENCY]);
