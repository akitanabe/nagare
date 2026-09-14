<?php

declare(strict_types=1);

namespace Nagare\Adapter;

/**
 * Create an adapter that maps each terminal input value.
 *
 * @template TInput
 * @template TOutput
 * @param callable(TInput): TOutput $mapper
 * @param-later-invoked-callable $mapper
 * @return Definition<TInput, TOutput>
 */
function map(callable $mapper): Definition
{
    return Definition::mapping($mapper);
}

/**
 * Forward the original value when its predicate result is PHP-truthy, or null otherwise.
 *
 * @template TValue
 * @param callable(TValue): mixed $predicate
 * @param-later-invoked-callable $predicate
 * @return Definition<mixed, mixed>
 */
function then(callable $predicate): Definition
{
    return map(static fn(mixed $value): mixed => $predicate($value) ? $value : null);
}

/**
 * Replace each null terminal input with a fixed fallback.
 *
 * @template TFallback
 * @param TFallback $fallback
 * @return Definition<mixed, mixed>
 */
function fallback(mixed $fallback): Definition
{
    return map(static fn(mixed $value): mixed => $value ?? $fallback);
}

/**
 * Create a fallback for each null terminal input while leaving present values unchanged.
 *
 * @template TFallback
 * @param callable(): TFallback $factory
 * @param-later-invoked-callable $factory
 * @return Definition<mixed, mixed>
 */
function fallbackWith(callable $factory): Definition
{
    return map(static fn(mixed $value): mixed => $value ?? $factory());
}

/**
 * Forward only values whose predicate result is PHP-truthy.
 *
 * @template TValue
 * @param callable(TValue): bool $predicate
 * @param-later-invoked-callable $predicate
 * @return Definition<TValue, TValue>
 */
function filter(callable $predicate): Definition
{
    return Definition::filtering($predicate);
}

/**
 * Forward every non-null value, including other PHP-falsy values.
 *
 * @return Definition<mixed, mixed>
 */
function some(): Definition
{
    return Definition::filtering(static fn(mixed $value): bool => $value !== null);
}

/**
 * Map each input once and forward only non-null outputs.
 *
 * @template TInput
 * @template TOutput
 * @param callable(TInput): (TOutput|null) $mapper
 * @param-later-invoked-callable $mapper
 * @return Definition<TInput, TOutput>
 */
function filterMap(callable $mapper): Definition
{
    return Definition::filteringMap($mapper);
}
