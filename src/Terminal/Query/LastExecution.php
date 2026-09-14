<?php

declare(strict_types=1);

namespace Nagare\Terminal\Query;

use Nagare\TerminalExecution;

/**
 * @internal
 * @template TValue
 * @implements TerminalExecution<mixed, TValue, TValue|null>
 */
final class LastExecution implements TerminalExecution
{
    /** @var TValue|null */
    private mixed $value = null;

    public function accept(mixed $value, mixed $key): void
    {
        $this->value = $value;
    }

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): mixed
    {
        return $this->value;
    }
}
