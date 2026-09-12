<?php

declare(strict_types=1);

namespace Nagare\Aggregation;

use Nagare\TerminalExecution;

/**
 * @internal
 * @template TValue
 * @template TAccumulator
 * @implements TerminalExecution<mixed, TValue, TAccumulator>
 */
final class FoldExecution implements TerminalExecution
{
    /** @var TAccumulator */
    private mixed $result;

    /** @var \Closure(TAccumulator, TValue): TAccumulator */
    private \Closure $fold;

    /**
     * @param TAccumulator $initial
     * @param callable(TAccumulator, TValue): TAccumulator $fold
     */
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
