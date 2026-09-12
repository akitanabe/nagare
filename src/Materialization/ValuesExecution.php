<?php

declare(strict_types=1);

namespace Nagare\Materialization;

use Nagare\TerminalExecution;

/**
 * @internal
 * @template TValue
 * @implements TerminalExecution<mixed, TValue, list<TValue>>
 */
final class ValuesExecution implements TerminalExecution
{
    /** @var list<TValue> */
    private array $values = [];

    public function accept(mixed $value, mixed $key): void
    {
        $this->values[] = $value;
    }

    public function isComplete(): bool
    {
        return false;
    }

    /** @return list<TValue> */
    public function finish(): mixed
    {
        return $this->values;
    }
}
