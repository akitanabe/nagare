<?php

declare(strict_types=1);

namespace Nagare\Tests\TerminalAdapter;

use Nagare\TerminalAdapter;
use Nagare\TerminalExecution;

/** @implements TerminalAdapter<mixed, mixed> */
final class StatefulAdapter implements TerminalAdapter
{
    private int $applyCount = 0;

    /**
     * @template TKey
     * @template TResult
     * @param TerminalExecution<TKey, mixed, TResult> $execution
     * @return TerminalExecution<TKey, mixed, TResult>
     */
    public function apply(TerminalExecution $execution): TerminalExecution
    {
        $this->applyCount++;

        return new StatefulExecution($execution);
    }

    public function applyCount(): int
    {
        return $this->applyCount;
    }
}
