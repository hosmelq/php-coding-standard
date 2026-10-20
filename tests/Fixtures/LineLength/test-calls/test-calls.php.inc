<?php
it('returns not found when the pricing rule belongs to a different billing profile of the organization', function (): void {
    expect(true)->toBeTrue();
});

test('returns bound integer values as strings and preserves strings and null values', function (null|int|string $value, null|string $expected): void {
    expect($value)->toBe($expected);
});

describe(
    'updates',
    static function (): void {
        it(
            'returns not found when the pricing rule rate belongs to a pricing rule from a different method',
            fn () => expect(true)->toBeTrue(),
        );

        it(
            'keeps comments between the arguments of the test call on their own lines', // why
            function (): void {},
        );
    },
);

$this->it('returns not found when the pricing rule belongs to a different billing profile', function (): void {});

-----
<?php
it('returns not found when the pricing rule belongs to a different billing profile of the organization', function (): void {
    expect(true)->toBeTrue();
});

test('returns bound integer values as strings and preserves strings and null values', function (
    null|int|string $value,
    null|string $expected,
): void {
    expect($value)->toBe($expected);
});

describe('updates', static function (): void {
    it('returns not found when the pricing rule rate belongs to a pricing rule from a different method', fn () => expect(true)->toBeTrue());

    it(
        'keeps comments between the arguments of the test call on their own lines', // why
        function (): void {},
    );
});

$this->it(
    'returns not found when the pricing rule belongs to a different billing profile',
    function (): void {},
);
