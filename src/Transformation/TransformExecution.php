<?php

declare(strict_types=1);

namespace Nagare\Transformation;

use Nagare\TerminalExecution;
use Nagare\Transform;

/** @internal */
final class TransformExecution implements TerminalExecution
{
    public function __construct(
        private TerminalExecution $execution,
        private Transform $transform,
    ) {}

    public function accept(mixed $value, mixed $key): void
    {
        $this->execution->accept($this->transform->transform($value), $key);
    }

    public function isComplete(): bool
    {
        return $this->execution->isComplete();
    }

    public function finish(): mixed
    {
        return $this->execution->finish();
    }
}
