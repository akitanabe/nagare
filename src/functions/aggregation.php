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
 * Create a terminal that executes each supplied terminal against one input pass.
 *
 * Arguments must be all positional or all named, including unpacked arguments.
 * The returned terminal produces an array keyed by terminal positions or names
 * in declaration order. Input is consumed once and stops when all terminals are
 * complete.
 *
 * @param Terminal<never, never, mixed> ...$terminals
 * @return Terminal<never, never, array<int|string, mixed>>
 * @throws InvalidArgumentException If positional and named arguments are mixed.
 */
function combine(Terminal ...$terminals): Terminal
{
    if (is_int(array_key_first($terminals)) && !array_is_list($terminals)) {
        throw new InvalidArgumentException('Positional and named terminals cannot be mixed.');
    }

    return Terminal::factory(static fn(): TerminalExecution => new CombineExecution($terminals));
}
