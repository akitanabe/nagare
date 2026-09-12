<?php

declare(strict_types=1);

namespace Nagare;

/** Create a terminal that returns the first input value, or null when empty. */
function first(): Terminal
{
    return new Terminal(static fn(): TerminalExecution => new FirstExecution());
}

/** Create a terminal that collects input values while preserving their keys. */
function values(): Terminal
{
    return new Terminal(static fn(): TerminalExecution => new ValuesExecution());
}

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
