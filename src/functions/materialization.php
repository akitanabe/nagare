<?php

declare(strict_types=1);

namespace Nagare\Materialization;

use Nagare\Terminal;
use Nagare\TerminalExecution;

/**
 * Create a terminal that collects input values into a list.
 *
 * @return Terminal<mixed, mixed, list<mixed>>
 */
function values(): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new ValuesExecution());
}

/**
 * Create a terminal that collects input keys into a list.
 *
 * @return Terminal<mixed, mixed, list<mixed>>
 */
function keys(): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new KeysExecution());
}

/**
 * Create a terminal that associates input values by their keys or selected keys.
 *
 * @template TKey of array-key = array-key
 * @template TValue = mixed
 * @template TSelectedKey of array-key = array-key
 * @param (callable(TValue, TKey): TSelectedKey)|null $keySelector
 * @param-later-invoked-callable $keySelector
 * The selector, when supplied, receives the input value followed by its key and
 * must return an array key. Without a selector, input keys are preserved.
 *
 * @return Terminal<TKey, TValue, array<TSelectedKey, TValue>>
 */
function associate(?callable $keySelector = null): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new AssociateExecution($keySelector));
}

/**
 * Create a terminal that collects input key-value pairs into a list.
 *
 * @return Terminal<mixed, mixed, list<array{mixed, mixed}>>
 */
function entries(): Terminal
{
    return Terminal::factory(static fn(): TerminalExecution => new EntriesExecution());
}
