<?php

declare(strict_types=1);

namespace Nagare\Tests\TerminalAdapter;

use Closure;
use Nagare\TerminalAdapter;
use Nagare\TerminalExecution;

/** @implements TerminalAdapter<mixed, mixed> */
final class CallbackAdapter implements TerminalAdapter
{
    /** @var Closure(mixed): void */
    private readonly Closure $callback;

    /** @param callable(mixed): void $callback */
    public function __construct(callable $callback)
    {
        $this->callback = $callback(...);
    }

    /**
     * @template TKey
     * @template TResult
     * @param TerminalExecution<TKey, mixed, TResult> $execution
     * @return TerminalExecution<TKey, mixed, TResult>
     */
    public function apply(TerminalExecution $execution): TerminalExecution
    {
        return new CallbackExecution($execution, $this->callback);
    }
}
