<?php

declare(strict_types=1);

namespace Nagare;

use Closure;

/**
 * A reusable terminal definition.
 *
 * The factory is evaluated for every invocation, so mutable execution state
 * belongs to one invocation and cannot leak into another one.
 */
final class Terminal
{
    /** @var Closure(): TerminalExecution */
    private readonly Closure $createExecution;

    /** @param callable(): TerminalExecution $createExecution */
    public function __construct(callable $createExecution)
    {
        $this->createExecution = $createExecution(...);
    }

    /**
     * Evaluate this terminal against one iterable input.
     *
     * @param iterable<int|string, mixed> $input
     */
    public function __invoke(iterable $input): mixed
    {
        $execution = $this->execution();

        if (!$execution->isComplete()) {
            foreach ($input as $key => $value) {
                $execution->accept($value, $key);

                if ($execution->isComplete()) {
                    break;
                }
            }
        }

        return $execution->finish();
    }

    /** @internal */
    public function execution(): TerminalExecution
    {
        return ($this->createExecution)();
    }
}
