<?php

declare(strict_types=1);

function configurationFixture(string $name, string $source, string $configuration): array
{
    $directory = __DIR__.'/../.cache/native-configuration/'.$name;

    if (! is_dir($directory)) {
        mkdir($directory, 0o777, true);
    }

    $sourcePath = $directory.'/example.php';
    $configurationPath = $directory.'/configuration.php';
    file_put_contents($sourcePath, $source);
    file_put_contents($configurationPath, $configuration);

    return [$sourcePath, $configurationPath];
}

it('converges for nested assertion chains and arrays', function (
    string $source,
    array $snippets = [],
): void {
    $configuration = <<<'PHP_WRAP'
        <?php
        use HosmelQ\PhpCodingStandard\CodingStandard;
        use Symplify\EasyCodingStandard\Config\ECSConfig;
        return ECSConfig::configure()->withSets([CodingStandard::ECS_PHP]);
        PHP_WRAP;
    [$file, $config] = configurationFixture('nested', $source, $configuration);
    $result = runConfiguredTool(
        'ecs',
        ['check', $file, '--config', $config, '--fix', '--no-progress-bar'],
    );
    $formatted = file_get_contents($file);

    expect($result->isSuccessful())->toBeTrue($result->getOutput().$result->getErrorOutput())
        ->and($formatted)->not->toBe($source);

    foreach ($snippets as $snippet) {
        expect($formatted)->toContain($snippet);
    }

    $result = runConfiguredTool('ecs', ['check', $file, '--config', $config, '--no-progress-bar']);

    expect($result->isSuccessful())->toBeTrue($result->getOutput().$result->getErrorOutput())
        ->and(file_get_contents($file))->toBe($formatted);
})->with([
    'arrow function argument reaching the width with its trailing comma' => [
        <<<'PHP'
            <?php
            it('deletes the address', function (): void {
                test()->group(function (): void {
                    mock(DeleteCustomerContact::class)
                        ->shouldReceive('handle')
                        ->once()
                        ->withArgs(fn (CustomerContact $addressArgument): bool => $addressArgument->is($defaultAddress));
                });
            });
            PHP,
        [
            "->withArgs(\n                fn (CustomerContact \$addressArgument): bool\n                    => \$addressArgument->is(\$defaultAddress),\n            );\n",
        ],
    ],
    'arrow function list' => [
        <<<'PHP'
            <?php
            $callbacks = [fn ($customer) => $customer->billingAddress, fn ($customer) => $customer->preferredLanguage];
            PHP,
        [
            "\$callbacks = [\n    fn (\$customer) => \$customer->billingAddress,\n    fn (\$customer) => \$customer->preferredLanguage,\n];",
        ],
    ],
    'array containing a ternary chain' => [
        <<<'PHP'
            <?php
            $nested = ['callback' => $enabled ? $first->where('a', 1)->orderBy('b')->get() : $second->where('a', 1)->orderBy('b')->get(), 'other' => 'value'];
            PHP,
        ["    'other' => 'value',\n"],
    ],
    'array containing an arrow chain' => [
        <<<'PHP'
            <?php
            $nested = ['callback' => fn ($q) => $q->where('a', 1)->orderBy('b')->get(), 'other' => 'value value value'];
            PHP,
        [
            "    'callback' => fn (\$q) => \$q->where('a', 1)->orderBy('b')->get(),\n",
            "    'other' => 'value value value',\n",
        ],
    ],
    'asymmetric promotions' => [
        <<<'PHP'
            <?php
            class Example
            {
                public function __construct(private(set) string $firstPropertyWithALongName, protected(set) string $secondPropertyWithALongName) {}
            }
            PHP,
        ["__construct(\n", "private(set) string \$firstPropertyWithALongName,\n"],
    ],
    'chain after a closed block' => [
        <<<'PHP'
            <?php
            if ($enabled) { consume(); } $next = $query->first('first argument value')->second('second argument value')->third('third argument');
            PHP,
        ["}\n", "\$next = \$query\n    ->first('first argument value')\n"],
    ],
    'commented arguments' => [
        <<<'PHP'
            <?php
            sendReport($destinationDirectoryWithAVeryLongName, /* required */ $generatedReportFilenameWithAVeryLongName, $includeArchivedRecords);
            PHP,
        [
            "sendReport(\n    \$destinationDirectoryWithAVeryLongName,\n    /* required */ \$generatedReportFilenameWithAVeryLongName,\n    \$includeArchivedRecords,\n);",
        ],
    ],
    'compact positional list' => [
        <<<'PHP'
            <?php
            sendReportToConfiguredDestination($destinationDirectoryWithAVeryLongName, ['draft', 'published', 'archived']);
            PHP,
        [
            "sendReportToConfiguredDestination(\n    \$destinationDirectoryWithAVeryLongName,\n    ['draft', 'published', 'archived'],\n);",
        ],
    ],
    'destructuring assignment' => [
        <<<'PHP'
            <?php
            [$customerIdentifier, $customerDisplayName, $customerBillingAddress, $customerPreferredLanguage] = $customerDetails;
            PHP,
        [
            "[\n    \$customerIdentifier,\n    \$customerDisplayName,\n    \$customerBillingAddress,\n    \$customerPreferredLanguage,\n] = \$customerDetails;",
        ],
    ],
    'empty constructor body at the width' => [
        <<<'PHP'
            <?php
            class Example
            {
                public function __construct(public string $xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx)
                {
                }
            }
            PHP,
        [
            "    public function __construct(\n        public string \$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx,\n    ) {}\n",
        ],
    ],
    'factory chains and assignments' => [
        <<<'PHP'
            <?php
            function example(): void
            {
                $owner = Owner::factory()->createOne();

                $address = $owner->addresses()->create(['label' => 'Home']);

                OwnerContact::factory()
                    ->for($owner)
                    ->createOne(['is_default' => false]);
                $defaultAddress = OwnerContact::factory()
                    ->for($owner)
                    ->createOne(['is_default' => true]);
            }
            PHP,
        [
            "    \$owner = Owner::factory()->createOne();\n"
                ."    \$address = \$owner->addresses()->create(['label' => 'Home']);\n\n"
                ."    OwnerContact::factory()->for(\$owner)->createOne(['is_default' => false]);\n"
                ."    \$defaultAddress = OwnerContact::factory()->for(\$owner)->createOne(['is_default' => true]);\n",
        ],
    ],
    'indented multiple statements' => [
        <<<'PHP'
            <?php
            if ($enabled) {
                $d = $first->a()->b()->c(); $e = $second->first('first argument value')->second('second argument value')->third('third value');
            }
            PHP,
        [
            "    \$d = \$first->a()->b()->c();\n",
            "    \$e = \$second\n        ->first('first argument value')\n",
        ],
    ],
    'late chain indentation' => [
        <<<'PHP'
            <?php
            function example(): void
            {
                expect($record->first()->second()['items'])->toBe([
                    ['alpha' => 'sample value', 'beta' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
                ])->and($other)->toBeTrue();
            }
            PHP,
        ["            'alpha' => 'sample value',\n"],
    ],
    'late nested array indentation' => [
        <<<'PHP'
            <?php
            function example(): void
            {
                expect($record)->toBe([
                    'settings' => [['alpha' => 'sample value', 'beta' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx']],
                ])->and($other)->toBeTrue();
            }
            PHP,
        ["'settings' => [[\n", "'alpha' => 'sample value',\n"],
    ],
    'late nested chain indentation' => [
        <<<'PHP'
            <?php
            function example(): void
            {
                expect($other)->toBeTrue()
                    ->and($record->first()->second()->third())->toBe([
                        ['alpha' => 'sample value', 'beta' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
                    ]);
            }
            PHP,
        ["                'alpha' => 'sample value',\n"],
    ],
    'long syntax array arguments' => [
        <<<'PHP'
            <?php
            $query->first(array('first_very_long_option_name' => 'first_value', 'second_very_long_option_name' => 'second_value'))->second()->third();
            PHP,
        ["\$query\n    ->first([\n", "    ->second()\n    ->third();"],
    ],
    'named arguments' => [
        <<<'PHP'
            <?php
            final class Example
            {
                public static function middleware(): array
                {
                    return [
                        new Middleware('can:create,'.BillingProfilePricingRule::class.',billing_profile', only: ['create', 'store']),
                    ];
                }
            }
            PHP,
        [
            "new Middleware(\n                'can:create,'.BillingProfilePricingRule::class.',billing_profile',\n                only: ['create', 'store'],\n            ),",
        ],
    ],
    'nested arrays' => <<<'PHP'
        <?php

        it('lists questions', function (): void {
            expect($record->modules()->pluck('title')->all())->toBe(['First', 'Second'])
                ->and($record->questions()->get(['answer', 'question'])->toArray())->toBe([
                    ['answer' => 'The default option is enabled.', 'question' => 'Which option is enabled?'],
                    ['answer' => 'The result contains a sample value.', 'question' => 'What does the result contain?'],
                ]);
        });
        PHP,
    'nested chains' => <<<'PHP'
        <?php

        it(
            'records entries',
            function (): void {
                expect($calls)->toBe(3)->and($record->entries()->pluck('start_index')->all())->toBe(
                    [0, 1500],
                )
                    ->and($record->entries()->pluck('run_id')->unique()->all())->toBe([$job->runId]);
            },
        );
        PHP,
    'readonly promotions' => [
        <<<'PHP'
            <?php
            class Example
            {
                public function __construct(readonly string $firstPropertyWithALongName, readonly string $secondPropertyWithALongName) {}
            }
            PHP,
        ["__construct(\n", "readonly string \$firstPropertyWithALongName,\n"],
    ],
    'reindented closure body chains' => [
        <<<'PHP'
            <?php
            run('allows reusing names from soft deleted billing profiles at the database level', function (): void {
                $replacement = BillingProfile::factory()->recycle($billingProfile->organization)->createOne([
                    'name' => 'Pro Monthly',
                ]);
            });
            PHP,
        [
            "        \$replacement = BillingProfile::factory()\n            ->recycle(\$billingProfile->organization)\n",
        ],
    ],
    'short nested arrays' => [
        <<<'PHP'
            <?php
            $config = ['short' => ['a' => 1], 'list' => [1, 2, 3], 'long' => 'value value value value value value value'];
            PHP,
        ["    'short' => ['a' => 1],\n"],
    ],
    'wrapped call with a multiline array' => [
        <<<'PHP'
            <?php
            function verify(array $payload): void
            {
                throw_if($payload['aud'] !== Config::string('services.apple.client_id'), ValidationException::withMessages([
                    'id_token' => Lang::string('auth.providers.apple.invalid_credentials'),
                ]));
            }
            PHP,
        [
            "        ValidationException::withMessages([\n            'id_token' => Lang::string('auth.providers.apple.invalid_credentials'),\n        ]),\n",
        ],
    ],
]);
