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
