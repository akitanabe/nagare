<?php

declare(strict_types=1);

namespace Nagare\Terminal\Query;

use Nagare\TerminalExecution;

/**
 * @internal
 * @template TValue
 * @implements TerminalExecution<mixed, TValue, TValue|null>
 */
final class FindExecution implements TerminalExecution
{
    /** @var \Closure(TValue): bool */
    private \Closure $predicate;

    private bool $complete = false;

    /** @var TValue|null */
    private mixed $value = null;

    /**
     * @param callable(TValue): bool $predicate
     */
    public function __construct(callable $predicate)
    {
        $this->predicate = $predicate(...);
    }

    public function accept(mixed $value, mixed $key): void
    {
        if ($this->complete || !($this->predicate)($value)) {
            return;
        }

        $this->value = $value;
        $this->complete = true;
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function finish(): mixed
    {
        return $this->value;
    }
}
