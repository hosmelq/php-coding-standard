<?php

declare(strict_types=1);

use Example\Sample;

it('advances the date by one day', function (): void {
    $result = new Sample()->nextDay('2026-01-01');

    expect($result === '2026-01-02')->toBeTrue();
});
