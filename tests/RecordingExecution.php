<?php

declare(strict_types=1);

namespace Nagare\Tests;

use Nagare\TerminalExecution;

final class RecordingExecution implements TerminalExecution
{
    public function __construct(
        private RecordingLog $log,
    ) {}

    public function accept(mixed $value, mixed $key): void
    {
        if (!is_int($key) && !is_string($key)) {
            throw new \TypeError('An iterable key must be an integer or string.');
        }

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
