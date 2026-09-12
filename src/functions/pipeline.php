<?php

declare(strict_types=1);

namespace Nagare\Pipeline;

use Closure;

/**
 * Lazily transform each value in an iterable while preserving its keys.
 *
 * @template TInput
 * @template TOutput
 * @param callable(TInput): TOutput $mapper
 * @param-later-invoked-callable $mapper
 * @return Closure<TKey>(iterable<TKey, TInput>): iterable<TKey, TOutput>
 */
function mapping(callable $mapper): Closure
{
    return static function (iterable $input) use ($mapper): iterable {
        foreach ($input as $key => $value) {
            yield $key => $mapper($value);
        }
    };
}

/**
 * Lazily yield values accepted by the predicate while preserving their keys.
 *
 * @template TInput
 * @param callable(TInput): bool $predicate
 * @param-later-invoked-callable $predicate
 * @return Closure<TKey>(iterable<TKey, TInput>): iterable<TKey, TInput>
 */
function filtering(callable $predicate): Closure
{
    return static function (iterable $input) use ($predicate): iterable {
        foreach ($input as $key => $value) {
            if (!$predicate($value)) {
                continue;
            }

            yield $key => $value;
        }
    };
}
