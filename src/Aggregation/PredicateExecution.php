<?php

declare(strict_types=1);

namespace Nagare\Aggregation;

use Nagare\TerminalExecution;

/**
 * @internal
 * @template TValue
 * @implements TerminalExecution<mixed, TValue, bool>
 */
final class PredicateExecution implements TerminalExecution
{
    /** @var \Closure(TValue): bool */
    private \Closure $predicate;

    private bool $complete = false;

    /**
     * @param callable(TValue): bool $predicate
     */
    public function __construct(
        callable $predicate,
        private bool $result,
        private bool $completeWhen,
        private bool $resultWhenComplete,
    ) {
        $this->predicate = $predicate(...);
    }

    public function accept(mixed $value, mixed $key): void
    {
        if ($this->complete) {
            return;
        }

        if (($this->predicate)($value) === $this->completeWhen) {
            $this->result = $this->resultWhenComplete;
            $this->complete = true;
        }
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function finish(): mixed
    {
        return $this->result;
    }
}
