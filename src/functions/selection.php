<?php

declare(strict_types=1);

namespace Nagare\Selection;

use Nagare\Terminal;
use Nagare\TerminalExecution;

/**
 * Create a terminal that returns the first input value, or null when empty.
 *
 * @return Terminal<mixed, mixed, mixed>
 */
function first(): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new FirstExecution());
}

/**
 * Create a terminal that returns the last input value, or null when empty.
 *
 * @return Terminal<mixed, mixed, mixed>
 */
function last(): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new LastExecution());
}

/**
 * Create a terminal that returns the first input value accepted by a predicate, or null when none match.
 *
 * @template TValue
 * @param callable(TValue): bool $predicate
 * @param-later-invoked-callable $predicate
 * @return Terminal<mixed, TValue, TValue|null>
 */
function find(callable $predicate): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new FindExecution($predicate));
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
