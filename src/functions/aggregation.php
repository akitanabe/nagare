<?php

declare(strict_types=1);

namespace Nagare\Aggregation;

use InvalidArgumentException;
use Nagare\Terminal;
use Nagare\TerminalExecution;

/**
 * Create a terminal that folds values in input order.
 *
 * @template TAccumulator
 * @template TValue
 * @param TAccumulator $initial
 * @param callable(TAccumulator, TValue): TAccumulator $fold
 * @param-later-invoked-callable $fold
 * @return Terminal<mixed, TValue, TAccumulator>
 */
function fold(mixed $initial, callable $fold): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new FoldExecution($initial, $fold));
}

/**
 * Create a terminal that counts input values.
 *
 * @return Terminal<mixed, mixed, int>
 */
function count(): Terminal
{
    return fold(0, static fn(int $count, mixed $_value): int => $count + 1);
}

/**
 * Create a terminal that sums integer input values.
 *
 * @return Terminal<mixed, int, int>
 */
function sum(): Terminal
{
    return fold(0, static fn(int $sum, int $value): int => $sum + $value);
}

/**
 * Create a terminal that returns the smallest input value, or null when empty.
 *
 * @return Terminal<mixed, mixed, mixed>
 */
function min(): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new ExtremeExecution(
        selector: static fn(mixed $value): mixed => $value,
        minimum: true,
    ));
}

/**
 * Create a terminal that returns the largest input value, or null when empty.
 *
 * @return Terminal<mixed, mixed, mixed>
 */
function max(): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new ExtremeExecution(
        selector: static fn(mixed $value): mixed => $value,
        minimum: false,
    ));
}

/**
 * Create a terminal that returns the input value whose selected value is smallest, or null when empty.
 *
 * @template TValue
 * @template TSelection
 * @param callable(TValue): TSelection $selector
 * @param-later-invoked-callable $selector
 * @return Terminal<mixed, TValue, TValue|null>
 */
function minBy(callable $selector): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new ExtremeExecution(
        selector: $selector,
        minimum: true,
    ));
}

/**
 * Create a terminal that returns the input value whose selected value is largest, or null when empty.
 *
 * @template TValue
 * @template TSelection
 * @param callable(TValue): TSelection $selector
 * @param-later-invoked-callable $selector
 * @return Terminal<mixed, TValue, TValue|null>
 */
function maxBy(callable $selector): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new ExtremeExecution(
        selector: $selector,
        minimum: false,
    ));
}

/**
 * Create a terminal that averages integer input values.
 * Returns null for empty input.
 *
 * @return Terminal<mixed, int, float|null>
 */
function average(): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new AverageExecution());
}

/**
 * Create a terminal that joins string input values in input order.
 * Returns an empty string for empty input.
 *
 * @param string $separator
 * @return Terminal<mixed, string, string>
 */
function join(string $separator): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new JoinExecution($separator));
}

/**
 * Create a terminal that returns the first occurrence of each strictly equal input value.
 *
 * @return Terminal<mixed, mixed, list<mixed>>
 */
function unique(): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new UniqueExecution());
}

/**
 * Create a terminal that returns the first input value for each strictly equal selected value.
 *
 * @template TValue
 * @template TIdentity
 * @param callable(TValue): TIdentity $selector
 * @param-later-invoked-callable $selector
 * @return Terminal<mixed, TValue, list<TValue>>
 */
function uniqueBy(callable $selector): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new UniqueExecution($selector));
}

/**
 * Create a terminal that counts input values by their selected array key.
 * Result keys retain the order in which they first occur in the input.
 * Selected keys follow PHP's native array-key conversion; a value that cannot
 * be used as an array key causes a TypeError.
 *
 * @template TValue
 * @template TKey of array-key
 * @param callable(TValue): TKey $selector
 * @param-later-invoked-callable $selector
 * @return Terminal<mixed, TValue, array<TKey, int>>
 */
function countBy(callable $selector): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new CountByExecution($selector));
}

/**
 * Create a terminal that groups input values by their selected array key.
 * Groups retain the order in which their keys first occur in the input, and
 * values within each group retain input order. Selected keys follow PHP's
 * native array-key conversion; a value that cannot be used as an array key
 * causes a TypeError.
 *
 * @template TValue
 * @template TKey of array-key
 * @param callable(TValue): TKey $selector
 * @param-later-invoked-callable $selector
 * @return Terminal<mixed, TValue, array<TKey, list<TValue>>>
 */
function groupBy(callable $selector): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new GroupByExecution($selector));
}

/**
 * Create a terminal that pivots each supplied terminal over one input pass.
 *
 * Arguments must be all positional or all named, including unpacked arguments.
 * The returned terminal produces an array keyed by terminal positions or names
 * in declaration order. Input is consumed once and stops when all terminals are
 * complete.
 *
 * Prefer pivot() for composing independent terminals. For performance-sensitive
 * aggregations that consume all input, consider fold() with a single callback
 * that updates all accumulators. Measure the complete workload: I/O and per-item
 * processing may outweigh the aggregation overhead.
 *
 * @param Terminal<never, never, mixed> ...$terminals
 * @return Terminal<never, never, array<int|string, mixed>>
 * @throws InvalidArgumentException If positional and named arguments are mixed.
 */
function pivot(Terminal ...$terminals): Terminal
{
    if (is_int(array_key_first($terminals)) && !array_is_list($terminals)) {
        throw new InvalidArgumentException('Positional and named terminals cannot be mixed.');
    }

    return Terminal::factory(static fn(): TerminalExecution => new PivotExecution($terminals));
}
