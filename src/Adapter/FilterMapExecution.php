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
final class FilterMapExecution implements TerminalExecution
{
    /**
     * @param TerminalExecution<TKey, TOutput, TResult> $execution
     * @param Closure(TInput): (TOutput|null) $mapper
     */
    public function __construct(
        private TerminalExecution $execution,
        private Closure $mapper,
    ) {}

    public function accept(mixed $value, mixed $key): void
    {
        $mapped = ($this->mapper)($value);

        if ($mapped !== null) {
            $this->execution->accept($mapped, $key);
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
