<?php

declare(strict_types=1);

namespace Nagare;

use Closure;

/**
 * Create a single-value transformation.
 *
 * @template TInput
 * @template TOutput
 * @param callable(TInput): TOutput $mapper
 */
function map(callable $mapper): Transform
{
    return new Transform($mapper);
}

/**
 * Lazily transform each value in an iterable while preserving its keys.
 *
 * @template TInput
 * @template TOutput
 * @param callable(TInput): TOutput $mapper
 * @return Closure(iterable<mixed, TInput>): iterable<mixed, TOutput>
 */
function mapping(callable $mapper): Closure
{
    return static function (iterable $input) use ($mapper): iterable {
        foreach ($input as $key => $value) {
            yield $key => $mapper($value);
        }
    };
}

/** Create a terminal that returns the first input value, or null when empty. */
function first(): Terminal
{
    return new Terminal(static fn(): TerminalExecution => new FirstExecution());
}

/** Create a terminal that collects input values into a list. */
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
