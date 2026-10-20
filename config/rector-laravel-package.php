<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\CodingStandard;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\StrictArrayParamDimFetchRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withSets([
        CodingStandard::RECTOR_PHP,
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_TYPE_DECLARATIONS,
    ])
    ->withPreparedSets(carbon: true)
    ->withSkip([
        StrictArrayParamDimFetchRector::class => [
            '*ServiceProvider.php',
        ],
    ]);
