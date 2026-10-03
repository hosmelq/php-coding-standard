<?php

declare(strict_types=1);

namespace Example;

use Illuminate\Support\Str;

final class Sample
{
    public function normalize(string $value): string
    {
        return Str::lower(trim($value));
    }
}
