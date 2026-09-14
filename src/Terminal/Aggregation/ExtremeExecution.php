<?php

declare(strict_types=1);

namespace Nagare\Terminal\Aggregation;

use Nagare\TerminalExecution;

/**
 * @internal
 * @template TValue
 * @template TComparison
 * @implements TerminalExecution<mixed, TValue, TValue|null>
 */
final class ExtremeExecution implements TerminalExecution
{
    /** @var \Closure(TValue): TComparison */
    private \Closure $selector;

    private bool $hasValue = false;

    /** @var TValue|null */
    private mixed $value = null;

    /** @var TComparison|null */
    private mixed $comparison = null;

    /**
     * @param callable(TValue): TComparison $selector
     */
    public function __construct(
        callable $selector,
        private bool $minimum,
    ) {
        $this->selector = $selector(...);
    }

    public function accept(mixed $value, mixed $key): void
    {
        $comparison = ($this->selector)($value);
        if ($this->hasValue && !($this->minimum ? $comparison < $this->comparison : $comparison > $this->comparison)) {
            return;
        }

        $this->value = $value;
        $this->comparison = $comparison;
        $this->hasValue = true;
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
