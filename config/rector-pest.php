<?php

declare(strict_types=1);

use Pest\Rector\Rules\UseSequenceMatcherRector;
use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([PestSetList::CODING_STYLE])
    ->withSkip([UseSequenceMatcherRector::class]);
