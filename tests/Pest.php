<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

pest()->in(__DIR__);

function runConfiguredTool(string $tool, array $arguments): Process
{
    $process = new Process([PHP_BINARY, __DIR__.'/../vendor/bin/'.$tool, ...$arguments], dirname(
        __DIR__,
    ));
    $process->setTimeout(120);
    $process->run();

    return $process;
}
