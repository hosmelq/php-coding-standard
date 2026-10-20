<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\CodingStandard;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([CodingStandard::RECTOR_PHP])
    ->withPaths([__DIR__.'/src', __DIR__.'/tests'])
    ->withSkip([__DIR__.'/tests/Fixtures'])
    ->withCache(__DIR__.'/.cache/rector');
