<?php

declare(strict_types=1);

namespace Nagare\Adapter;

use Closure;
use Nagare\TerminalExecution;

/**
 * @internal
 * @template TKey
 * @template TInput
 * @template TOutput
 * @template TResult
 * @implements TerminalExecution<TKey, TInput, TResult>
 */
final class MapExecution implements TerminalExecution
{
    /**
     * @param TerminalExecution<TKey, TOutput, TResult> $execution
     * @param Closure(TInput): TOutput $mapper
     */
    public function __construct(
        private TerminalExecution $execution,
        private Closure $mapper,
    ) {}

    public function accept(mixed $value, mixed $key): void
    {
        $this->execution->accept(($this->mapper)($value), $key);
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
