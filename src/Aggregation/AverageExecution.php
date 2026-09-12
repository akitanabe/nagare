<?php

declare(strict_types=1);

namespace Nagare\Aggregation;

use Nagare\TerminalExecution;

/**
 * @internal
 * @implements TerminalExecution<mixed, int, float|null>
 */
final class AverageExecution implements TerminalExecution
{
    private int $count = 0;

    private int $sum = 0;

    public function accept(mixed $value, mixed $key): void
    {
        $this->sum += $value;
        ++$this->count;
    }

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): ?float
    {
        return $this->count === 0 ? null : $this->sum / $this->count;
    }
}
