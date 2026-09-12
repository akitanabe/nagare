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
 * @return Closure(iterable<mixed, TInput>): iterable<mixed, TOutput>
 */
function mapping(callable $mapper): Closure
{
    return static function (iterable $input) use ($mapper): iterable {
        foreach ($input as $key => $value) {
            yield $key => $mapper($value);
        }
    };
}
