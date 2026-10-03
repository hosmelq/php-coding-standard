<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\CodingStandard;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([CodingStandard::ECS_PHP])
    ->withPaths([
        __DIR__.'/composer-dependency-analyser.php',
        __DIR__.'/config',
        __FILE__,
        __DIR__.'/rector.php',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([__DIR__.'/tests/Fixtures'])
    ->withCache(__DIR__.'/.cache/ecs');
