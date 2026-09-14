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
 * Lazily invoke a callback for each input key and value while preserving both in the output.
 *
 * @template TCallbackKey
 * @template TValue
 * @param callable(TValue, TCallbackKey): void $callback
 * @param-later-invoked-callable $callback
 * @return Closure<TKey>(iterable<TKey, TValue>): iterable<TKey, TValue>
 */
function each(callable $callback): Closure
{
    return static function (iterable $input) use ($callback): iterable {
        foreach ($input as $key => $value) {
            $callback($value, $key);
            yield $key => $value;
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
 * Lazily collect input values into chunks in their input order.
 * The chunks themselves are yielded with zero-based sequential keys.
 * By default, input keys are preserved. With preserveKeys enabled, integer and string keys are preserved in each
 * chunk, and non-array-compatible keys cause a TypeError during traversal. With preserveKeys disabled, input keys
 * are discarded and each chunk is a list. A non-positive size yields an empty iterable without consuming the input.
 *
 * @mago-expect lint:no-boolean-flag-parameter The public preserveKeys option selects the documented chunk representation.
 * @param int $size
 * @param bool $preserveKeys
 * @return Closure<TKey, TValue>(iterable<TKey, TValue>): iterable<int, array<TKey&array-key, TValue>|list<TValue>>
 */
function chunking(int $size, bool $preserveKeys = true): Closure
{
    /**
     * @template TInputKey
     * @template TInputValue
     * @param iterable<TInputKey, TInputValue> $input
     * @return iterable<int, array<TInputKey, TInputValue>|list<TInputValue>>
     */
    return static function (iterable $input) use ($size, $preserveKeys): iterable {
        if ($size <= 0) {
            return;
        }

        $chunk = [];
        $chunkKey = 0;

        foreach ($input as $key => $value) {
            if ($preserveKeys) {
                // @phpstan-ignore offsetAccess.invalidOffset (The public ChunkingReturnTypeExtension restricts preserved input keys, and native assignment rejects unchecked calls.)
                $chunk[$key] = $value;
            }

            if (!$preserveKeys) {
                $chunk[] = $value;
            }

            if (count($chunk) < $size) {
                continue;
            }

            yield $chunkKey => $chunk;
            $chunkKey++;
            $chunk = [];
        }

        if (count($chunk) > 0) {
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
