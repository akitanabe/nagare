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
 */
function map(callable $mapper): Transform
{
    return new Transform($mapper);
}
