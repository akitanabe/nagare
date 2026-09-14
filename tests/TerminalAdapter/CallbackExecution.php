<?php

declare(strict_types=1);

namespace Nagare\Tests\TerminalAdapter;

use Closure;
use Nagare\TerminalExecution;

/**
 * @template TKey
 * @template TResult
 * @implements TerminalExecution<TKey, mixed, TResult>
 */
final class CallbackExecution implements TerminalExecution
{
    /**
     * @param TerminalExecution<TKey, mixed, TResult> $execution
     * @param Closure(mixed): void $callback
     */
    public function __construct(
        private TerminalExecution $execution,
        private Closure $callback,
    ) {}

    /**
     * @param mixed $value
     * @param TKey $key
     */
    public function accept(mixed $value, mixed $key): void
    {
        ($this->callback)($value);
        $this->execution->accept($value, $key);
    }

    public function isComplete(): bool
    {
        return $this->execution->isComplete();
    }

    /** @return TResult */
    public function finish(): mixed
    {
        return $this->execution->finish();
    }
}
