<?php

declare(strict_types=1);

namespace Nagare\Tests\PHPStan;

use Nagare\TerminalExecution;

/** @implements TerminalExecution<object, int, string> */
final class ObjectKeyExecution implements TerminalExecution
{
    public function accept(mixed $value, mixed $key): void {}

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): string
    {
        return 'finished';
    }
}
