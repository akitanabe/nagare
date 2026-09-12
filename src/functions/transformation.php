<?php

declare(strict_types=1);

namespace Nagare\Transformation;

use Nagare\Transform;

/**
 * Create a single-value transformation.
 *
 * @template TInput
 * @template TOutput
 * @param callable(TInput): TOutput $mapper
 * @param-later-invoked-callable $mapper
 * @return Transform<TInput, TOutput>
 */
function map(callable $mapper): Transform
{
    return Transform::factory($mapper);
}

/**
 * Evaluate a predicate during transformation. Return the input itself when
 * the result is PHP-truthy; otherwise return null.
 *
 * @template TInput
 * @param callable(TInput): mixed $predicate
 * @param-later-invoked-callable $predicate
 * @return Transform<mixed, mixed>
 */
function then(callable $predicate): Transform
{
    return Transform::factory(static fn(mixed $value): mixed => $predicate($value) ? $value : null);
}

/**
 * Replace a null value with a fixed fallback.
 *
 * @return Transform<mixed, mixed>
 */
function defaults(mixed $fallback): Transform
{
    return Transform::factory(static fn(mixed $value): mixed => $value ?? $fallback);
}

/**
 * Call a factory for each null input during transformation; return present
 * values unchanged without calling the factory.
 *
 * @param callable(): mixed $factory
 * @param-later-invoked-callable $factory
 * @return Transform<mixed, mixed>
 */
function defaultsOr(callable $factory): Transform
{
    return Transform::factory(static fn(mixed $value): mixed => $value ?? $factory());
}
