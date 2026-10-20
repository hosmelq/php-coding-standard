<?php
it('formats', function (): void {
    expect(true)->toBeTrue();
})->with(['first long dataset value here', 'second long dataset value here', 'third long dataset value']);

$values = collect(['first long collection value', 'second long collection value', 'third long value']);
$values = merge($defaults, ['first long collection value', 'second long collection value', 'third value']);

-----
<?php
it('formats', function (): void {
    expect(true)->toBeTrue();
})->with([
    'first long dataset value here',
    'second long dataset value here',
    'third long dataset value',
]);

$values = collect([
    'first long collection value',
    'second long collection value',
    'third long value',
]);
$values = merge(
    $defaults,
    ['first long collection value', 'second long collection value', 'third value'],
);
