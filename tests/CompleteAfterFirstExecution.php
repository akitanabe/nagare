<?php

declare(strict_types=1);

namespace Nagare\Tests;

use Nagare\TerminalExecution;

/** @implements TerminalExecution<mixed, mixed, int> */
final class CompleteAfterFirstExecution implements TerminalExecution
{
    private int $accepted = 0;

    public function accept(mixed $value, mixed $key): void
    {
        $this->accepted++;
    }

    public function isComplete(): bool
    {
        return $this->accepted > 0;
    }

    public function finish(): mixed
    {
        return $this->accepted;
    }

    public function acceptedCount(): int
    {
        return $this->accepted;
    }
}
