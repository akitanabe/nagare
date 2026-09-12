<?php

declare(strict_types=1);

namespace Nagare\Query;

use Nagare\TerminalExecution;

/**
 * @internal
 * @template TValue
 * @implements TerminalExecution<mixed, TValue, TValue|null>
 */
final class FirstExecution implements TerminalExecution
{
    private bool $hasValue = false;

    /** @var TValue|null */
    private mixed $value = null;

    public function accept(mixed $value, mixed $key): void
    {
        if ($this->hasValue) {
            return;
        }

        $this->value = $value;
        $this->hasValue = true;
    }

    public function isComplete(): bool
    {
        return $this->hasValue;
    }

    public function finish(): mixed
    {
        return $this->value;
    }
}
