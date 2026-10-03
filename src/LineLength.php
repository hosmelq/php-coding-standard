<?php

declare(strict_types=1);

namespace HosmelQ\PhpCodingStandard;

use InvalidArgumentException;

final readonly class LineLength
{
    public function __construct(public int $value = 100)
    {
        if ($this->value < 1) {
            throw new InvalidArgumentException('The line length must be greater than zero.');
        }
    }
}
