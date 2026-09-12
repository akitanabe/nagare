<?php

declare(strict_types=1);

namespace Nagare;

use Closure;

/**
 * A reusable single-value transformation.
 *
 * Transformations are definitions only. They do not retain a value or any
 * state from an execution, so a composed transformation can be reused.
 */
final class Transform
{
    /** @var Closure(mixed): mixed */
    private readonly Closure $transform;

    /** @param callable(mixed): mixed $transform */
    public function __construct(callable $transform)
    {
        $this->transform = $transform(...);
    }

    /** Compose this transformation after the previous one in a pipe. */
    public function __invoke(self $previous): self
    {
        return $previous->then($this);
    }

    /** Apply this transformation to one value. */
    public function transform(mixed $value): mixed
    {
        return ($this->transform)($value);
    }

    /**
     * Return a transformation that applies this transformation and then the
     * supplied next transformation.
     */
    private function then(self $next): self
    {
        return new self(fn(mixed $value): mixed => $next->transform($this->transform($value)));
    }
}
