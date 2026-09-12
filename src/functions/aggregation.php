<?php

declare(strict_types=1);

namespace Nagare\Aggregation;

use Nagare\Terminal;
use Nagare\TerminalExecution;

/**
 * Create a terminal that folds values in input order.
 *
 * @template TAccumulator
 * @template TValue
 * @param TAccumulator $initial
 * @param callable(TAccumulator, TValue): TAccumulator $fold
 */
function fold(mixed $initial, callable $fold): Terminal
{
    return new Terminal(static fn(): TerminalExecution => new FoldExecution($initial, $fold));
}

/**
 * Create a terminal that executes each named terminal against one input pass.
 *
 * The returned terminal produces an array keyed by the supplied terminal names
 * in declaration order. Input is consumed once and stops when all terminals
 * are complete.
 *
 * @param Terminal ...$terminals
 */
function combine(Terminal ...$terminals): Terminal
{
    return new Terminal(static fn(): TerminalExecution => new CombineExecution($terminals));
}
