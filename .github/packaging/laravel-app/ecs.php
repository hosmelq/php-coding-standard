<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\CodingStandard;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([CodingStandard::ECS_LARAVEL_APP])
    ->withPaths([__DIR__.'/src'])
    ->withCache(__DIR__.'/.cache/ecs');
