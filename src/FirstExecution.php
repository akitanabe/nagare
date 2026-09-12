<?php

declare(strict_types=1);

namespace Nagare;

/** @internal */
final class FirstExecution implements TerminalExecution
{
    private bool $hasValue = false;

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
