<?php

declare(strict_types=1);

namespace Nagare\Tests\TerminalAdapter;

use Nagare\TerminalAdapter;
use Nagare\TerminalExecution;

/** @implements TerminalAdapter<string, int> */
final class StringLengthAdapter implements TerminalAdapter
{
    /**
     * @template TKey
     * @template TResult
     * @param TerminalExecution<TKey, int, TResult> $execution
     * @return TerminalExecution<TKey, string, TResult>
     */
    public function apply(TerminalExecution $execution): TerminalExecution
    {
        return new StringLengthExecution($execution);
    }
}
