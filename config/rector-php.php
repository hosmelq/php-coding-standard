<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withPhpSets()
    ->withPreparedSets(
        codeQuality: true,
        codingStyle: true,
        deadCode: true,
        earlyReturn: true,
        instanceOf: true,
        privatization: true,
        rectorPreset: true,
        typeDeclarations: true,
    )
    ->withSkip([
        ClosureToArrowFunctionRector::class,
        NewlineBeforeNewAssignSetRector::class,
    ]);
