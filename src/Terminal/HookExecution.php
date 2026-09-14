<?php

declare(strict_types=1);

namespace Nagare\Terminal;

use Closure;
use Nagare\TerminalExecution;

/**
 * @template-contravariant TKey
 * @template-contravariant TValue
 * @template-covariant TResult
 * @implements TerminalExecution<TKey, TValue, TResult>
 */
final class HookExecution implements TerminalExecution
{
    /** @var Closure(TValue, TKey): void */
    private readonly Closure $callback;

    /**
     * @param TerminalExecution<TKey, TValue, TResult> $execution
     * @param callable(TValue, TKey): void $callback
     */
    public function __construct(
        private readonly TerminalExecution $execution,
        callable $callback,
    ) {
        $this->callback = $callback(...);
    }

    public function accept(mixed $value, mixed $key): void
    {
        ($this->callback)($value, $key);
        $this->execution->accept($value, $key);
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
