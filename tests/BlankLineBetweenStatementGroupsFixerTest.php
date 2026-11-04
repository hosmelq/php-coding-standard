<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\Fixer\BlankLineBetweenStatementGroupsFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;

it('separates statements by subject', function (
    string $input,
    string $expected,
): void {
    $fixer = new BlankLineBetweenStatementGroupsFixer();
    $tokens = Tokens::fromCode($input);

    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe($expected);

    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe($expected);
})->with([
    'all subject groups' => [
        "<?php\n\$file = new SplFileInfo('a.php');\nnew Foo()->fix(\$file);\nnew Bar()->fix(\$file);\n\$tokens->clearEmptyTokens();\n\$tokens->rewind();\nconsume(\$tokens);\nEvent::fake();\nMail::fake();\n",
        "<?php\n\$file = new SplFileInfo('a.php');\n\nnew Foo()->fix(\$file);\nnew Bar()->fix(\$file);\n\n\$tokens->clearEmptyTokens();\n\$tokens->rewind();\n\nconsume(\$tokens);\n\nEvent::fake();\nMail::fake();\n",
    ],
    'variable subjects use their names' => [
        "<?php\n\$this->a();\n\$this->b();\n\$tokens->a();\n\$tokens->b();\n\$this->c();\n",
        "<?php\n\$this->a();\n\$this->b();\n\n\$tokens->a();\n\$tokens->b();\n\n\$this->c();\n",
    ],
    'variable calls offsets and increments share a subject' => [
        "<?php\n\$fn();\n\$fn['handler']();\n\$fn++;\n\$i++;\n\$i--;\n",
        "<?php\n\$fn();\n\$fn['handler']();\n\$fn++;\n\n\$i++;\n\$i--;\n",
    ],
    'static calls share a subject across class names' => [
        "<?php\nEvent::fake();\nMail::fake();\nApp\\Events::fake();\n\\App\\Mail::fake();\nstatic::fake();\nself::fake();\nparent::fake();\nexpect(true);\n",
        "<?php\nEvent::fake();\nMail::fake();\nApp\\Events::fake();\n\\App\\Mail::fake();\nstatic::fake();\nself::fake();\nparent::fake();\n\nexpect(true);\n",
    ],
    'function calls share a subject across names' => [
        "<?php\nactingAs(\$user);\nexpect(true);\nApp\\prepare();\n\\App\\consume();\nnew Foo();\n",
        "<?php\nactingAs(\$user);\nexpect(true);\nApp\\prepare();\n\\App\\consume();\n\nnew Foo();\n",
    ],
    'all subjects separate from assignments in both directions' => [
        "<?php\nnew Foo();\n\$a = 1;\nEvent::fake();\n\$b = 2;\nconsume();\n\$c = 3;\n\$tokens->rewind();\n\$d = 4;\n",
        "<?php\nnew Foo();\n\n\$a = 1;\n\nEvent::fake();\n\n\$b = 2;\n\nconsume();\n\n\$c = 3;\n\n\$tokens->rewind();\n\n\$d = 4;\n",
    ],
    'same subjects retain existing blank lines' => [
        "<?php\nnew Foo();\n\nnew Bar();\n\n\$tokens->a();\n\n\$tokens->b();\n\nEvent::fake();\n\nMail::fake();\n\nactingAs(\$user);\n\nexpect(true);\n",
        "<?php\nnew Foo();\n\nnew Bar();\n\n\$tokens->a();\n\n\$tokens->b();\n\nEvent::fake();\n\nMail::fake();\n\nactingAs(\$user);\n\nexpect(true);\n",
    ],
    'comments prevent subject separation' => [
        "<?php\nnew Foo();\n// Note.\nconsume(); // Note.\n\$tokens->rewind();\n/** Note. */\nEvent::fake();\n",
        "<?php\nnew Foo();\n// Note.\nconsume(); // Note.\n\$tokens->rewind();\n/** Note. */\nEvent::fake();\n",
    ],
    'same-line subject changes are untouched' => [
        '<?php new Foo(); $tokens->rewind(); consume(); Event::fake();',
        '<?php new Foo(); $tokens->rewind(); consume(); Event::fake();',
    ],
    'block boundaries only separate assignments' => [
        "<?php\nif (\$enabled) {\n    consume();\n}\nnew Foo();\nif (\$enabled) {\n    Event::fake();\n}\n\$tokens->rewind();\nif (\$enabled) {\n    new Foo();\n}\nconsume();\nif (\$enabled) {}\nEvent::fake();\n",
        "<?php\nif (\$enabled) {\n    consume();\n}\nnew Foo();\nif (\$enabled) {\n    Event::fake();\n}\n\$tokens->rewind();\nif (\$enabled) {\n    new Foo();\n}\nconsume();\nif (\$enabled) {}\nEvent::fake();\n",
    ],
    'subject groups in closure and method bodies' => [
        "<?php\nit('groups', function (): void {\n    Event::fake();\n    consume();\n});\nclass Example {\n    public function run(): void {\n        \$this->a();\n        \$tokens->rewind();\n    }\n}\n",
        "<?php\nit('groups', function (): void {\n    Event::fake();\n\n    consume();\n});\nclass Example {\n    public function run(): void {\n        \$this->a();\n\n        \$tokens->rewind();\n    }\n}\n",
    ],
    'class declarations trait adaptations and constant expressions' => [
        <<<'PHP'
            <?php
            class Example {
                use A, B {
                    A::foo insteadof B;
                    B::foo as other;
                }
                public const HANDLERS = [Foo::class, Bar::class];
                public const CALLBACK = Foo::CALLBACK;
                public $value = 1;
                public $other = 2;
            }
            enum Mode: string {
                case First = 'first';
                case Second = 'second';
                public const DEFAULT_MODE = self::First;
            }
            PHP,
        <<<'PHP'
            <?php
            class Example {
                use A, B {
                    A::foo insteadof B;
                    B::foo as other;
                }
                public const HANDLERS = [Foo::class, Bar::class];
                public const CALLBACK = Foo::CALLBACK;
                public $value = 1;
                public $other = 2;
            }
            enum Mode: string {
                case First = 'first';
                case Second = 'second';
                public const DEFAULT_MODE = self::First;
            }
            PHP,
    ],
    'match arms arguments and array items are expressions' => [
        "<?php\nconsume(match (\$kind) {\n    1 => new Foo(),\n    default => \$tokens->rewind(),\n}, new Bar(), [Event::fake(), \$tokens->count()]);\n",
        "<?php\nconsume(match (\$kind) {\n    1 => new Foo(),\n    default => \$tokens->rewind(),\n}, new Bar(), [Event::fake(), \$tokens->count()]);\n",
    ],
    'nested match does not hide following subject groups' => [
        "<?php\n\$value = match (\$kind) {\n    1 => match (\$other) { default => 1 },\n    default => 2,\n};\nconsume(\$value);\nEvent::fake();\n",
        "<?php\n\$value = match (\$kind) {\n    1 => match (\$other) { default => 1 },\n    default => 2,\n};\n\nconsume(\$value);\n\nEvent::fake();\n",
    ],
    'for headers exclude every subject and nested closure' => [
        "<?php\nfor (\$tokens->rewind();\n    Event::fake();\n    consume()) {\n    new Foo();\n    \$tokens->rewind();\n}\nfor (\$callback = function (): void {\n    new Foo();\n    Event::fake();\n};\n    \$condition = false;\n    \$step += 1\n) {}\n",
        "<?php\nfor (\$tokens->rewind();\n    Event::fake();\n    consume()) {\n    new Foo();\n\n    \$tokens->rewind();\n}\nfor (\$callback = function (): void {\n    new Foo();\n    Event::fake();\n};\n    \$condition = false;\n    \$step += 1\n) {}\n",
    ],
    'switch and alternative syntax subject groups' => [
        "<?php\nswitch (\$mode) {\n    case \$enabled ? 1 : 2:\n        Event::fake();\n        consume();\n        break;\n    default:\n        new Foo();\n        \$tokens->rewind();\n}\nif (\$enabled):\n    consume();\n    Event::fake();\nelse:\n    new Foo();\n    \$tokens->rewind();\nendif;\n",
        "<?php\nswitch (\$mode) {\n    case \$enabled ? 1 : 2:\n        Event::fake();\n\n        consume();\n        break;\n    default:\n        new Foo();\n\n        \$tokens->rewind();\n}\nif (\$enabled):\n    consume();\n\n    Event::fake();\nelse:\n    new Foo();\n\n    \$tokens->rewind();\nendif;\n",
    ],
    'namespaces imports and declare are unkeyed' => [
        "<?php\ndeclare(strict_types=1);\nnamespace App;\nuse App\\Foo;\nconsume();\nEvent::fake();\n",
        "<?php\ndeclare(strict_types=1);\nnamespace App;\nuse App\\Foo;\nconsume();\n\nEvent::fake();\n",
    ],
    'unbraced bodies and goto labels do not start subjects' => [
        "<?php\nif (\$enabled)\n    new Foo();\nlabel:\n    Event::fake();\n",
        "<?php\nif (\$enabled)\n    new Foo();\nlabel:\n    Event::fake();\n",
    ],
    'subjects require terminating semicolons' => [
        "<?php\nconsume();\n\$tokens->rewind()\n?>",
        "<?php\nconsume();\n\$tokens->rewind()\n?>",
    ],
    'anonymous class constructor closures and methods' => [
        "<?php\nnew class(function () {\n    Event::fake();\n    consume();\n}) {\n    public function run(): void {\n        \$this->a();\n        \$tokens->rewind();\n    }\n};\nconsume();\n",
        "<?php\nnew class(function () {\n    Event::fake();\n\n    consume();\n}) {\n    public function run(): void {\n        \$this->a();\n\n        \$tokens->rewind();\n    }\n};\n\nconsume();\n",
    ],
    'translator assertions' => [
        "<?php\n\$translator = app(Translator::class);\nexpect(Lang::getFacadeRoot())->toBe(\$translator);\n\$message = app(Catalog::class)->all()['messages'][0];\n",
        "<?php\n\$translator = app(Translator::class);\n\nexpect(Lang::getFacadeRoot())->toBe(\$translator);\n\n\$message = app(Catalog::class)->all()['messages'][0];\n",
    ],
    'generator call' => [
        "<?php\n\$generator = new ClassMapGenerator();\n\$generator->avoidDuplicateScans();\n",
        "<?php\n\$generator = new ClassMapGenerator();\n\n\$generator->avoidDuplicateScans();\n",
    ],
    'compound assignments' => [
        "<?php\n\$value = 1;\n\$value += 1;\n\$value -= 1;\n\$value *= 1;\n\$value /= 1;\n\$value %= 1;\n\$value **= 1;\n\$value .= 1;\n\$value ??= 1;\n\$value &= 1;\n\$value |= 1;\n\$value ^= 1;\n\$value <<= 1;\n\$value >>= 1;\nconsume(\$value);\n",
        "<?php\n\$value = 1;\n\$value += 1;\n\$value -= 1;\n\$value *= 1;\n\$value /= 1;\n\$value %= 1;\n\$value **= 1;\n\$value .= 1;\n\$value ??= 1;\n\$value &= 1;\n\$value |= 1;\n\$value ^= 1;\n\$value <<= 1;\n\$value >>= 1;\n\nconsume(\$value);\n",
    ],
    'assignment group' => [
        "<?php\n\$first = 1;\n\$second = 2;\n\$third = 3;\nconsume(\$first, \$second, \$third);\n",
        "<?php\n\$first = 1;\n\$second = 2;\n\$third = 3;\n\nconsume(\$first, \$second, \$third);\n",
    ],
    'call before assignment' => [
        "<?php\nprepare();\n\$value = 1;\n",
        "<?php\nprepare();\n\n\$value = 1;\n",
    ],
    'if block before assignment' => [
        "<?php\nif (\$enabled) {\n    consume();\n}\n\$value = 1;\n",
        "<?php\nif (\$enabled) {\n    consume();\n}\n\n\$value = 1;\n",
    ],
    'foreach block before assignment' => [
        "<?php\nforeach (\$values as \$value) {\n    consume(\$value);\n}\n\$result = 1;\n",
        "<?php\nforeach (\$values as \$value) {\n    consume(\$value);\n}\n\n\$result = 1;\n",
    ],
    'try block before assignment' => [
        "<?php\ntry {\n    consume();\n} finally {\n    finish();\n}\n\$value = 1;\n",
        "<?php\ntry {\n    consume();\n} finally {\n    finish();\n}\n\n\$value = 1;\n",
    ],
    'function before assignment' => [
        "<?php\nfunction example(): void {\n    consume();\n}\n\$value = 1;\n",
        "<?php\nfunction example(): void {\n    consume();\n}\n\n\$value = 1;\n",
    ],
    'first and last statements in block' => [
        "<?php\nif (\$enabled) {\n    \$first = 1;\n    \$last = 2;\n}\n",
        "<?php\nif (\$enabled) {\n    \$first = 1;\n    \$last = 2;\n}\n",
    ],
    'Pest closure body' => [
        "<?php\nit('works', function (): void {\n    \$first = 1;\n    expect(\$first)->toBe(1);\n    \$second = 2;\n});\n",
        "<?php\nit('works', function (): void {\n    \$first = 1;\n\n    expect(\$first)->toBe(1);\n\n    \$second = 2;\n});\n",
    ],
    'nested assignment blocks' => [
        "<?php\n\$callback = function (): int {\n    \$first = 1;\n    \$second = 2;\n    return \$first + \$second;\n};\n\$object = new class {\n    public function example(): void {}\n};\nconsume(\$callback, \$object);\n",
        "<?php\n\$callback = function (): int {\n    \$first = 1;\n    \$second = 2;\n\n    return \$first + \$second;\n};\n\$object = new class {\n    public function example(): void {}\n};\n\nconsume(\$callback, \$object);\n",
    ],
    'line comments between statements' => [
        "<?php\n\$first = 1;\n// Note.\nconsume();\n// Note.\n\$second = 2;\n",
        "<?php\n\$first = 1;\n// Note.\nconsume();\n// Note.\n\$second = 2;\n",
    ],
    'trailing comments between statements' => [
        "<?php\n\$first = 1; // Note.\nconsume(); // Note.\n\$second = 2;\n",
        "<?php\n\$first = 1; // Note.\nconsume(); // Note.\n\$second = 2;\n",
    ],
    'docblocks between statements' => [
        "<?php\n\$first = 1;\n/** Note. */\nconsume();\n/** @var Foo \$second */\n\$second = create();\n",
        "<?php\n\$first = 1;\n/** Note. */\nconsume();\n/** @var Foo \$second */\n\$second = create();\n",
    ],
    'same-line statements' => [
        '<?php $first = 1; consume(); $second = 2; consume();',
        '<?php $first = 1; consume(); $second = 2; consume();',
    ],
    'same-line block boundary' => [
        '<?php if ($enabled) { consume(); } $value = 1;',
        '<?php if ($enabled) { consume(); } $value = 1;',
    ],
    'existing blank lines' => [
        "<?php\n\$first = 1;\n\nconsume();\n\n\$second = 2;\n\n\nconsume();\n",
        "<?php\n\$first = 1;\n\nconsume();\n\n\$second = 2;\n\n\nconsume();\n",
    ],
    'consecutive assignments with blank lines' => [
        "<?php\n\$first = 1;\n\n\$second = 2;\n",
        "<?php\n\$first = 1;\n\n\$second = 2;\n",
    ],
    'property and array-item assignments' => [
        "<?php\n\$end = 1;\n\$calls[] = \$end;\n\$this->value = 2;\n\$this->items['key'][] = 3;\n\$object::\$count = 4;\nconsume(\$calls);\n",
        "<?php\n\$end = 1;\n\$calls[] = \$end;\n\$this->value = 2;\n\$this->items['key'][] = 3;\n\$object::\$count = 4;\n\nconsume(\$calls);\n",
    ],
    'static property assignments' => [
        "<?php\nReceipt::\$markup = '<a>';\n\$this->path = '/tmp';\nself::\$items[] = 1;\nHttp::fake();\n",
        "<?php\nReceipt::\$markup = '<a>';\n\$this->path = '/tmp';\nself::\$items[] = 1;\n\nHttp::fake();\n",
    ],
    'dynamic variable and property assignments' => [
        "<?php\n\$first = 1;\n\$this->{\$name} = 1;\n\$\$name = 2;\n\${'value'} = 3;\nconsume();\n\$\$name->run();\n\$\$name->stop();\n\$this->consume();\n",
        "<?php\n\$first = 1;\n\$this->{\$name} = 1;\n\$\$name = 2;\n\${'value'} = 3;\n\nconsume();\n\n\$\$name->run();\n\$\$name->stop();\n\n\$this->consume();\n",
    ],
    'dynamic property names' => [
        "<?php\n\$first = 1;\nFoo::\${'value'} = 2;\n\$obj->\$\$name = 3;\nFoo::\$\$name = 4;\nconsume();\n",
        "<?php\n\$first = 1;\nFoo::\${'value'} = 2;\n\$obj->\$\$name = 3;\nFoo::\$\$name = 4;\n\nconsume();\n",
    ],
    'assignments to properties of call results' => [
        "<?php\n\$name = 'Alice';\n\$this->getUser()->name = \$name;\n\$this->users()[0]->name = \$name;\n\$this->save();\n\$this->refresh();\n",
        "<?php\n\$name = 'Alice';\n\$this->getUser()->name = \$name;\n\$this->users()[0]->name = \$name;\n\n\$this->save();\n\$this->refresh();\n",
    ],
    'relative namespace names' => [
        "<?php\nnamespace App;\n\nnamespace\\prepare();\nnew Foo();\nnamespace\\Foo::\$value = 1;\n\$second = 2;\nnamespace\\Foo::run();\n",
        "<?php\nnamespace App;\n\nnamespace\\prepare();\n\nnew Foo();\n\nnamespace\\Foo::\$value = 1;\n\$second = 2;\n\nnamespace\\Foo::run();\n",
    ],
    'property hook bodies' => [
        "<?php\nclass Example\n{\n    public int \$value {\n        set(int \$value) {\n            \$first = 1;\n\n            \$second = 2;\n            consume(\$second);\n        }\n    }\n}\n",
        "<?php\nclass Example\n{\n    public int \$value {\n        set(int \$value) {\n            \$first = 1;\n\n            \$second = 2;\n\n            consume(\$second);\n        }\n    }\n}\n",
    ],
    'method calls on variables are other statements' => [
        "<?php\n\$first = 1;\n\$this->consume(\$first);\n\$this->items->add(\$first);\n\$second = 2;\n",
        "<?php\n\$first = 1;\n\n\$this->consume(\$first);\n\$this->items->add(\$first);\n\n\$second = 2;\n",
    ],
    'comparisons are other statements' => [
        "<?php\n\$first = 1;\n\$first === 1;\n\$second = 2;\n",
        "<?php\n\$first = 1;\n\n\$first === 1;\n\n\$second = 2;\n",
    ],
    'for headers' => [
        "<?php\nfor (\n    \$index = 0;\n    \$next = 1;\n    \$index += 1\n) {\n}\n",
        "<?php\nfor (\n    \$index = 0;\n    \$next = 1;\n    \$index += 1\n) {\n}\n",
    ],
    'nested closure in for header' => [
        "<?php\nfor (\$callback = function (): void {\n    \$value = 1;\n    consume(\$value);\n};\n    \$condition = false;\n    \$step += 1\n) {\n}\n",
        "<?php\nfor (\$callback = function (): void {\n    \$value = 1;\n    consume(\$value);\n};\n    \$condition = false;\n    \$step += 1\n) {\n}\n",
    ],
    'for body still formatted' => [
        "<?php\nfor (\$index = 0; \$index < 3; ++\$index) {\n    \$value = \$index;\n    consume(\$value);\n}\n",
        "<?php\nfor (\$index = 0; \$index < 3; ++\$index) {\n    \$value = \$index;\n\n    consume(\$value);\n}\n",
    ],
    'return after assignment' => [
        "<?php\nfunction example(): int {\n    \$value = 1;\n    return \$value;\n}\n",
        "<?php\nfunction example(): int {\n    \$value = 1;\n\n    return \$value;\n}\n",
    ],
    'assignment before PHP close tag' => [
        "<?php\n\$value = 1;\n?>",
        "<?php\n\$value = 1;\n?>",
    ],
    'assignment at end of file' => [
        "<?php\n\$value = 1;\n",
        "<?php\n\$value = 1;\n",
    ],
    'alternative syntax first statement' => [
        "<?php\nif (\$enabled):\n    \$value = 1;\n\nendif;\n",
        "<?php\nif (\$enabled):\n    \$value = 1;\n\nendif;\n",
    ],
    'case first statement' => [
        "<?php\nswitch (\$value) {\n    case 1:\n        \$result = 2;\n}\n",
        "<?php\nswitch (\$value) {\n    case 1:\n        \$result = 2;\n}\n",
    ],
    'case assignment group' => [
        "<?php\nswitch (\$mode) {\n    case 1:\n        \$first = 1;\n        \$second = 2;\n        consume(\$first, \$second);\n        break;\n    default:\n        \$value = 1;\n        consume(\$value);\n}\n",
        "<?php\nswitch (\$mode) {\n    case 1:\n        \$first = 1;\n        \$second = 2;\n\n        consume(\$first, \$second);\n        break;\n    default:\n        \$value = 1;\n\n        consume(\$value);\n}\n",
    ],
    'case expressions with calls and ternaries' => [
        "<?php\nswitch (\$mode) {\n    case calculate():\n        \$first = 1;\n        \$second = 2;\n        consume(\$first, \$second);\n        break;\n    case \$enabled ? 1 : 2:\n        \$value = 1;\n        consume(\$value);\n}\n",
        "<?php\nswitch (\$mode) {\n    case calculate():\n        \$first = 1;\n        \$second = 2;\n\n        consume(\$first, \$second);\n        break;\n    case \$enabled ? 1 : 2:\n        \$value = 1;\n\n        consume(\$value);\n}\n",
    ],
    'enum cases are not statement openers' => [
        "<?php\nenum Mode: string\n{\n    case First = 'first';\n}\n\$value = Mode::First;\nconsume(\$value);\n",
        "<?php\nenum Mode: string\n{\n    case First = 'first';\n}\n\n\$value = Mode::First;\n\nconsume(\$value);\n",
    ],
    'alternative syntax openers' => [
        "<?php\nforeach (\$values as \$value):\n    \$item = \$value;\n    consume(\$item);\nendforeach;\nif (\$enabled):\n    \$item = 1;\n    consume(\$item);\nelse:\n    \$item = 2;\n    consume(\$item);\nendif;\n",
        "<?php\nforeach (\$values as \$value):\n    \$item = \$value;\n\n    consume(\$item);\nendforeach;\nif (\$enabled):\n    \$item = 1;\n\n    consume(\$item);\nelse:\n    \$item = 2;\n\n    consume(\$item);\nendif;\n",
    ],
    'alternative syntax closers' => [
        "<?php\nwhile (\$enabled):\n    consume();\n    \$last = 1;\nendwhile;\nif (\$enabled):\n    consume();\n    \$value = 1;\nelseif (\$other):\n    consume();\n    \$value = 2;\nelse:\n    consume();\n    \$value = 3;\nendif;\n",
        "<?php\nwhile (\$enabled):\n    consume();\n\n    \$last = 1;\nendwhile;\nif (\$enabled):\n    consume();\n\n    \$value = 1;\nelseif (\$other):\n    consume();\n\n    \$value = 2;\nelse:\n    consume();\n\n    \$value = 3;\nendif;\n",
    ],
    'ternary colons are not statement starts' => [
        "<?php\n\$value = \$enabled ? \$first : \$second;\nconsume(\$value);\n",
        "<?php\n\$value = \$enabled ? \$first : \$second;\n\nconsume(\$value);\n",
    ],
    'label first statement' => [
        "<?php\nlabel:\n\$value = 1;\n",
        "<?php\nlabel:\n\$value = 1;\n",
    ],
    'unbraced first statement' => [
        "<?php\nif (\$enabled)\n    \$value = 1;\n",
        "<?php\nif (\$enabled)\n    \$value = 1;\n",
    ],
]);

it('only separates unkeyed statements from assignments', function (string $statement): void {
    $fixer = new BlankLineBetweenStatementGroupsFixer();
    $input = "<?php\nfunction example() {\n    consume();\n    "
        .$statement
        ."\n    \$tokens->rewind();\n}\n";
    $tokens = Tokens::fromCode($input);

    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe($input);

    $tokens = Tokens::fromCode(
        "<?php\nfunction example() {\n    \$first = 1;\n    ".$statement."\n    \$second = 2;\n}\n",
    );

    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe(
        "<?php\nfunction example() {\n    \$first = 1;\n\n    "
            .$statement
            ."\n\n    \$second = 2;\n}\n",
    );
})->with([
    'echo' => 'echo 1;',
    'unset' => 'unset($value);',
    'return' => 'return 1;',
    'throw' => 'throw new Exception();',
    'yield' => 'yield 1;',
    'static declaration' => 'static $value = 1;',
    'destructuring' => '[$a, $b] = [1, 2];',
    'parenthesized new' => '(new Foo())->run();',
    'goto' => 'goto label;',
]);

it('uses the configured line ending and keeps tab indentation', function (): void {
    $fixer = new BlankLineBetweenStatementGroupsFixer();

    $fixer->setWhitespacesConfig(new WhitespacesFixerConfig("\t", "\r\n"));

    $tokens = Tokens::fromCode("<?php\r\nconsume();\r\n\t\$value = 1;\r\n\tconsume(\$value);\r\n");

    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe(
        "<?php\r\nconsume();\r\n\r\n\t\$value = 1;\r\n\r\n\tconsume(\$value);\r\n",
    );
});

it('separates non-assignment subjects with configured CRLF', function (): void {
    $fixer = new BlankLineBetweenStatementGroupsFixer();

    $fixer->setWhitespacesConfig(new WhitespacesFixerConfig("\t", "\r\n"));

    $tokens = Tokens::fromCode(
        "<?php\r\n\tnew Foo();\r\n\t\$tokens->rewind();\r\n\tconsume();\r\n\tEvent::fake();\r\n",
    );

    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe(
        "<?php\r\n\tnew Foo();\r\n\r\n\t\$tokens->rewind();\r\n\r\n\tconsume();\r\n\r\n\tEvent::fake();\r\n",
    );
});

it('provides the custom fixer contract', function (): void {
    $fixer = new BlankLineBetweenStatementGroupsFixer();

    expect($fixer->getName())->toBe('HosmelQ/blank_line_between_statement_groups')
        ->and($fixer->getDefinition()->getCodeSamples())->not->toBeEmpty()
        ->and($fixer->isRisky())->toBeFalse()
        ->and($fixer->isCandidate(Tokens::fromCode('<?php echo 1;')))->toBeFalse();
});
