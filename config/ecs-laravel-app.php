<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\CodingStandard;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([CodingStandard::ECS_LARAVEL_PACKAGE])
    ->withSkip([
        '*.blade.php',
        '*/.phpstorm.meta.php',
        '*/_ide_helper.php',
        '*/_ide_helper_actions.php',
        '*/_ide_helper_models.php',
        '*/bootstrap/cache/*',
        '*/build/*',
        '*/node_modules/*',
        '*/storage/*',
    ]);
