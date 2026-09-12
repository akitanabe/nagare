<?php

declare(strict_types=1);

namespace Nagare\Aggregation;

use Nagare\TerminalExecution;

/** @internal */
final class FoldExecution implements TerminalExecution
{
    private mixed $result;

    /** @var \Closure(mixed, mixed): mixed */
    private \Closure $fold;

    /** @param callable(mixed, mixed): mixed $fold */
    public function __construct(mixed $initial, callable $fold)
    {
        $this->result = $initial;
        $this->fold = $fold(...);
    }

    public function accept(mixed $value, mixed $key): void
    {
        $this->result = ($this->fold)($this->result, $value);
    }

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): mixed
    {
        return $this->result;
    }
}
