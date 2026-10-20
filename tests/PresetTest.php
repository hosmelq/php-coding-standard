<?php

declare(strict_types=1);

function presetFixture(string $name, array $files): string
{
    $directory = __DIR__.'/../.cache/presets/'.$name;

    if (is_dir($directory)) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }

    foreach ($files as $path => $contents) {
        $file = $directory.'/'.$path;

        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0o777, true);
        }

        file_put_contents($file, $contents);
    }

    return $directory;
}

it('skips generated and framework files in Laravel apps', function (): void {
    $unformatted = "<?php\n\$value = array( 1,2 );\n";
    $directory = presetFixture('ecs-laravel-app', [
        '.phpstorm.meta.php' => $unformatted,
        '_ide_helper.php' => $unformatted,
        '_ide_helper_actions.php' => $unformatted,
        '_ide_helper_models.php' => $unformatted,
        'app/Example.php' => $unformatted,
        'bootstrap/cache/services.php' => $unformatted,
        'build/output.php' => $unformatted,
        'ecs.php' => <<<'PHP'
            <?php

            declare(strict_types=1);

            use HosmelQ\PhpCodingStandard\CodingStandard;
            use Symplify\EasyCodingStandard\Config\ECSConfig;

            return ECSConfig::configure()
                ->withSets([CodingStandard::ECS_LARAVEL_APP])
                ->withPaths([__DIR__])
                ->withCache(__DIR__.'/../ecs-laravel-app-cache');

            PHP,
        'node_modules/package/index.php' => $unformatted,
        'resources/views/welcome.blade.php' => $unformatted,
        'storage/framework/views/compiled.php' => $unformatted,
    ]);
    $result = runConfiguredTool('ecs', [
        'check',
        '--config',
        $directory.'/ecs.php',
        '--no-progress-bar',
        '--output-format',
        'json',
    ]);
    $files = array_keys(
        json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR)['files'],
    );

    expect($files)->toBe([realpath($directory.'/app/Example.php')]);
});

it('keeps untyped container parameters in service providers', function (): void {
    $source = <<<'PHP'
        <?php

        declare(strict_types=1);

        final class ExampleServiceProvider
        {
            public function register(object $container): void
            {
                $container->singleton('config.value', function ($app) {
                    return $app['config'];
                });
            }
        }
        PHP;
    $directory = presetFixture('rector-laravel-package', [
        'ExampleServiceProvider.php' => $source,
        'rector.php' => <<<'PHP'
            <?php

            declare(strict_types=1);

            use HosmelQ\PhpCodingStandard\CodingStandard;
            use Rector\Config\RectorConfig;
            use Rector\TypeDeclaration\Rector\ClassMethod\StrictArrayParamDimFetchRector;

            return RectorConfig::configure()
                ->withSets([CodingStandard::RECTOR_LARAVEL_PACKAGE])
                ->withRules([StrictArrayParamDimFetchRector::class]);
            PHP,
    ]);
    $result = runConfiguredTool('rector', [
        'process',
        $directory.'/ExampleServiceProvider.php',
        '--config',
        $directory.'/rector.php',
        '--dry-run',
        '--no-progress-bar',
        '--output-format',
        'json',
    ]);

    expect($result->getOutput())->not->toContain('array $app');
});
