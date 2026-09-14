<?php

declare(strict_types=1);

namespace Nagare\Tests\TerminalAdapter;

use Nagare\TerminalExecution;

/**
 * @template TKey
 * @template TResult
 * @implements TerminalExecution<TKey, mixed, TResult>
 */
final class StatefulExecution implements TerminalExecution
{
    private int $accepted = 0;

    /** @param TerminalExecution<TKey, mixed, TResult> $execution */
    public function __construct(
        private TerminalExecution $execution,
    ) {}

    /**
     * @param mixed $value
     * @param TKey $key
     */
    public function accept(mixed $value, mixed $key): void
    {
        $this->accepted++;
        $this->execution->accept([$this->accepted, $value], $key);
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
