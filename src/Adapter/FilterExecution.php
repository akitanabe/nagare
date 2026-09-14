<?php

declare(strict_types=1);

namespace Nagare\Adapter;

use Closure;
use Nagare\TerminalExecution;

/**
 * @internal
 * @template TKey
 * @template TValue
 * @template TResult
 * @implements TerminalExecution<TKey, TValue, TResult>
 */
final class FilterExecution implements TerminalExecution
{
    /**
     * @param TerminalExecution<TKey, TValue, TResult> $execution
     * @param Closure(TValue): bool $predicate
     */
    public function __construct(
        private TerminalExecution $execution,
        private Closure $predicate,
    ) {}

    public function accept(mixed $value, mixed $key): void
    {
        if (($this->predicate)($value)) {
            $this->execution->accept($value, $key);
        }
    }

    public function isComplete(): bool
    {
        return $this->execution->isComplete();
    }

    public function finish(): mixed
    {
        return $this->execution->finish();
    }
}
