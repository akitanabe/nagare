<?php

declare(strict_types=1);

namespace Nagare;

/** @internal */
final class ValuesExecution implements TerminalExecution
{
    /** @var array<int|string, mixed> */
    private array $values = [];

    public function accept(mixed $value, mixed $key): void
    {
        if (!is_int($key) && !is_string($key)) {
            throw new \TypeError('An iterable key must be an integer or string.');
        }

        $this->values[$key] = $value;
    }

    public function isComplete(): bool
    {
        return false;
    }

    /** @return array<int|string, mixed> */
    public function finish(): mixed
    {
        return $this->values;
    }
}
