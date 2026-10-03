<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\CodingStandard;
use PhpCsFixer\Fixer\Alias\MbStrFunctionsFixer;
use PhpCsFixer\Fixer\ClassUsage\DateTimeImmutableFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([CodingStandard::ECS_PHP])
    ->withRules([
        MbStrFunctionsFixer::class,
        DateTimeImmutableFixer::class,
    ]);
