<?php

declare(strict_types=1);

namespace Nagare\Terminal\Query;

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
 * Create a terminal that returns true when the input is empty.
 * Returns false after observing the first input value.
 *
 * @return Terminal<mixed, mixed, bool>
 */
function isEmpty(): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new PredicateExecution(
        predicate: static fn(mixed $_value): bool => true,
        result: true,
        completeWhen: true,
        resultWhenComplete: false,
    ));
}

/**
 * Create a terminal that returns true when the input contains the target value.
 * Uses strict comparison (`===`); objects match only when they are the same instance.
 * Returns false when no input value matches or the input is empty.
 *
 * @param mixed $value
 * @return Terminal<mixed, mixed, bool>
 */
function contains(mixed $value): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new PredicateExecution(
        predicate: static fn(mixed $candidate): bool => $candidate === $value,
        result: false,
        completeWhen: true,
        resultWhenComplete: true,
    ));
}

/**
 * Create a terminal that returns true when any input value matches the predicate.
 * Returns false for empty input.
 *
 * @template TValue
 * @param callable(TValue): bool $predicate
 * @param-later-invoked-callable $predicate
 * @return Terminal<mixed, TValue, bool>
 */
function any(callable $predicate): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new PredicateExecution(
        predicate: $predicate,
        result: false,
        completeWhen: true,
        resultWhenComplete: true,
    ));
}

/**
 * Create a terminal that returns true when all input values match the predicate.
 * Returns true for empty input.
 *
 * @template TValue
 * @param callable(TValue): bool $predicate
 * @param-later-invoked-callable $predicate
 * @return Terminal<mixed, TValue, bool>
 */
function all(callable $predicate): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new PredicateExecution(
        predicate: $predicate,
        result: true,
        completeWhen: false,
        resultWhenComplete: false,
    ));
}
