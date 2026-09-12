<?php

declare(strict_types=1);

namespace Nagare;

/**
 * Mutable state for one evaluation of a terminal definition.
 *
 * Implementations must not traverse the source themselves. The owning
 * terminal supplies source values in input order and stops supplying them
 * when the execution reports completion.
 */
interface TerminalExecution
{
    /**
     * @param mixed $value The current input value.
     * @param mixed $key The current input key.
     */
    public function accept(mixed $value, mixed $key): void;

    /** Whether this execution is complete and requires no additional input. */
    public function isComplete(): bool;

    /** Return the result accumulated by this execution. */
    public function finish(): mixed;
}
