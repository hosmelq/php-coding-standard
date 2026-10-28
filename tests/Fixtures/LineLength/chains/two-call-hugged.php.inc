<?php

$response = $this->actingAs($user)->post(route('invoices.store'), ['number' => 'INV-001', 'currency' => 'USD', 'paid' => true]);
$query->first('first argument value that is long')->second(['first option value', 'second option value']);
$query->first('first argument value that is long')->second(fn () => $page->component('auth/Register'));
$query->first('first argument value that is long')->second(static function () { return 'a result value'; });
$response = $this->actingAs($userWithAnExceptionallyLongVariableName)->post(route('invoices.store'), [
    'number' => 'INV-001',
    'currency' => 'USD',
]);

-----
<?php

$response = $this->actingAs($user)->post(route('invoices.store'), [
    'number' => 'INV-001',
    'currency' => 'USD',
    'paid' => true,
]);
$query->first('first argument value that is long')->second([
    'first option value',
    'second option value',
]);
$query->first('first argument value that is long')->second(
    fn () => $page->component('auth/Register'),
);
$query->first('first argument value that is long')->second(
    static function () { return 'a result value'; },
);
$response = $this->actingAs($userWithAnExceptionallyLongVariableName)->post(
    route('invoices.store'),
    [
        'number' => 'INV-001',
        'currency' => 'USD',
    ],
);
