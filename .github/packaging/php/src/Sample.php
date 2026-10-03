<?php

declare(strict_types=1);

namespace Example;

use Safe\DateTime;

final class Sample
{
    public function nextDay(string $value): string
    {
        $date = new DateTime($value);
        $date->modify("+1 day");

        return $date->format("Y-m-d");
    }
}
