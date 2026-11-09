<?php

it('keeps grouped expectations', function (): void {
    expect($result->isSuccessful())->toBeTrue($result->getOutput().$result->getErrorOutput())
        ->and($formatted)->not->toBe($source);
    \expect($query)->first('first argument value that is long')->toBe('second argument value')
        ->and($other)->toBe(1);
    Checks\expect($query)->first('first argument value')
        ->second('second argument value')->third('third argument value');
    namespace\expect($query)->first('first argument value')
        ->second('second argument value')->third('third argument value');
    new \expect($query)->first('first argument value')
        ->second('second argument value')->third('third argument value');
});

-----
<?php

it('keeps grouped expectations', function (): void {
    expect($result->isSuccessful())->toBeTrue($result->getOutput().$result->getErrorOutput())
        ->and($formatted)->not->toBe($source);
    \expect($query)->first('first argument value that is long')->toBe('second argument value')
        ->and($other)->toBe(1);
    Checks\expect($query)
        ->first('first argument value')
        ->second('second argument value')
        ->third('third argument value');
    namespace\expect($query)
        ->first('first argument value')
        ->second('second argument value')
        ->third('third argument value');
    new \expect($query)
        ->first('first argument value')
        ->second('second argument value')
        ->third('third argument value');
});
