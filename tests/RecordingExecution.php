<?php

declare(strict_types=1);

namespace Nagare\Tests;

use Nagare\TerminalExecution;

/** @implements TerminalExecution<mixed, mixed, string> */
final class RecordingExecution implements TerminalExecution
{
    public function __construct(
        private RecordingLog $log,
    ) {}

    public function accept(mixed $value, mixed $key): void
    {
        $this->log->seen[] = [$key, $value];
    }

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): mixed
    {
        return 'finished';
    }
}
