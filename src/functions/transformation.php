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
