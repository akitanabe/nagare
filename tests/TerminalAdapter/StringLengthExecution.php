<?php

declare(strict_types=1);

namespace Nagare\Tests\TerminalAdapter;

use Nagare\TerminalExecution;

/**
 * @template TKey
 * @template TResult
 * @implements TerminalExecution<TKey, string, TResult>
 */
final class StringLengthExecution implements TerminalExecution
{
    /**
     * @param TerminalExecution<TKey, int, TResult> $execution
     */
    public function __construct(
        private TerminalExecution $execution,
    ) {}

    /**
     * @param string $value
     * @param TKey $key
     */
    public function accept(mixed $value, mixed $key): void
    {
        $this->execution->accept(strlen($value), $key);
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
