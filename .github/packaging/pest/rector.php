<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\CodingStandard;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([CodingStandard::RECTOR_PHP, CodingStandard::RECTOR_PEST])
    ->withPaths([__DIR__.'/tests'])
    ->withCache(__DIR__.'/.cache/rector-pest');
