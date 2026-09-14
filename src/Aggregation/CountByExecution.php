<?php

declare(strict_types=1);

namespace Nagare\Aggregation;

use Nagare\TerminalExecution;

/**
 * @internal
 * @template TValue
 * @template TKey of array-key
 * @implements TerminalExecution<mixed, TValue, array<TKey, int>>
 */
final class CountByExecution implements TerminalExecution
{
    /** @var array<TKey, int> */
    private array $counts = [];

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
        $this->counts[$group] = ($this->counts[$group] ?? 0) + 1;
    }

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): array
    {
        return $this->counts;
    }
}
