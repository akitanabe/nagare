<?php

declare(strict_types=1);

namespace Nagare\Terminal\Aggregation;

use Nagare\TerminalExecution;

/**
 * @internal
 * @template TValue
 * @template TKey of array-key
 * @implements TerminalExecution<mixed, TValue, array<TKey, list<TValue>>>
 */
final class GroupByExecution implements TerminalExecution
{
    /** @var array<TKey, list<TValue>> */
    private array $groups = [];

    /** @var \Closure(TValue): TKey */
    private \Closure $selector;

    /** @param callable(TValue): TKey $selector */
    public function __construct(callable $selector)
    {
        $this->selector = $selector(...);
    }

    public function accept(mixed $value, mixed $key): void
    {
        $group = ($this->selector)($value);
        $this->groups[$group][] = $value;
    }

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): array
    {
        return $this->groups;
    }
}
