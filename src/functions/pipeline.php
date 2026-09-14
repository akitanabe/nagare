<?php

declare(strict_types=1);

namespace Nagare\Pipeline;

use Closure;
use InvalidArgumentException;

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
 * Lazily yield each mapped iterable's keys and values in order, discarding the outer input keys.
 *
 * @template TInput
 * @template TInnerKey
 * @template TInnerValue
 * @param callable(TInput): iterable<TInnerKey, TInnerValue> $mapper
 * @param-later-invoked-callable $mapper
 * @return Closure<TOuterKey>(iterable<TOuterKey, TInput>): iterable<TInnerKey, TInnerValue>
 */
function flatMapping(callable $mapper): Closure
{
    return static function (iterable $input) use ($mapper): iterable {
        foreach ($input as $value) {
            foreach ($mapper($value) as $key => $innerValue) {
                yield $key => $innerValue;
            }
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

/**
 * Lazily yield values while the predicate remains truthy, preserving their keys.
 * The first value rejected by the predicate and all following input values are not yielded.
 * On the first falsey predicate result, iteration stops without requesting the next input value.
 *
 * @template TInput
 * @param callable(TInput): bool $predicate
 * @param-later-invoked-callable $predicate
 * @return Closure<TKey>(iterable<TKey, TInput>): iterable<TKey, TInput>
 */
function takingWhile(callable $predicate): Closure
{
    return static function (iterable $input) use ($predicate): iterable {
        foreach ($input as $key => $value) {
            if (!$predicate($value)) {
                return;
            }

            yield $key => $value;
        }
    };
}

/**
 * Lazily skip values while the predicate remains truthy, then yield the first rejected value and all following values
 * while preserving their keys.
 *
 * @template TInput
 * @param callable(TInput): bool $predicate
 * @param-later-invoked-callable $predicate
 * @return Closure<TKey>(iterable<TKey, TInput>): iterable<TKey, TInput>
 */
function droppingWhile(callable $predicate): Closure
{
    return static function (iterable $input) use ($predicate): iterable {
        $dropping = true;

        foreach ($input as $key => $value) {
            if ($dropping && $predicate($value)) {
                continue;
            }

            $dropping = false;
            yield $key => $value;
        }
    };
}

/**
 * Lazily yield at most the requested number of input values while preserving their keys.
 * A non-positive count yields an empty iterable without consuming the input.
 * After yielding the requested number of values, iteration stops without requesting the next input value.
 *
 * @param int $count
 * @return Closure<TKey, TValue>(iterable<TKey, TValue>): iterable<TKey, TValue>
 */
function taking(int $count): Closure
{
    return static function (iterable $input) use ($count): iterable {
        if ($count <= 0) {
            return;
        }

        $taken = 0;
        foreach ($input as $key => $value) {
            yield $key => $value;
            $taken++;

            if ($taken >= $count) {
                return;
            }
        }
    };
}

/**
 * Lazily skip the requested number of input values and yield the rest in their original iteration order
 * while preserving their keys.
 * A non-positive count does not skip any values.
 *
 * @param int $count
 * @return Closure<TKey, TValue>(iterable<TKey, TValue>): iterable<TKey, TValue>
 */
function dropping(int $count): Closure
{
    return static function (iterable $input) use ($count): iterable {
        $remaining = max(0, $count);

        foreach ($input as $key => $value) {
            if ($remaining > 0) {
                $remaining--;
                continue;
            }

            yield $key => $value;
        }
    };
}

/**
 * Lazily collect input values into chunks while preserving their keys and order.
 * The chunks themselves are yielded with zero-based sequential keys.
 * Input keys must be integers or strings because each chunk is a PHP array.
 *
 * @param int $size
 * @return Closure<TKey, TValue>(iterable<TKey&array-key, TValue>): iterable<int, array<TKey&array-key, TValue>>
 * @throws InvalidArgumentException If the chunk size is not positive.
 */
function chunking(int $size): Closure
{
    if ($size <= 0) {
        throw new InvalidArgumentException('Chunk size must be greater than zero.');
    }

    /**
     * @template TInputKey of array-key
     * @template TInputValue
     * @param iterable<TInputKey, TInputValue> $input
     * @return iterable<int, array<TInputKey, TInputValue>>
     */
    return static function (iterable $input) use ($size): iterable {
        $chunk = [];
        $chunkSize = 0;
        $chunkKey = 0;

        foreach ($input as $key => $value) {
            if (!is_int($key) && !is_string($key)) {
                throw new \TypeError('Chunk keys must be integers or strings.');
            }

            $chunk[$key] = $value;
            $chunkSize++;

            if ($chunkSize < $size) {
                continue;
            }

            yield $chunkKey => $chunk;
            $chunkKey++;
            $chunk = [];
            $chunkSize = 0;
        }

        if ($chunkSize > 0) {
            yield $chunkKey => $chunk;
        }
    };
}

/**
 * Lazily yield the first occurrence of each value using strict comparison while preserving keys and order.
 *
 * @return Closure<TKey, TValue>(iterable<TKey, TValue>): iterable<TKey, TValue>
 */
function distinct(): Closure
{
    return static function (iterable $input): iterable {
        $seen = [];

        foreach ($input as $key => $value) {
            if (in_array($value, $seen, strict: true)) {
                continue;
            }

            $seen[] = $value;
            yield $key => $value;
        }
    };
}
