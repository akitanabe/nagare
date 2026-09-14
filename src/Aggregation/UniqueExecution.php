<?php

declare(strict_types=1);

namespace Nagare\Aggregation;

use Nagare\TerminalExecution;

/**
 * @internal
 * @template TValue
 * @template TIdentity
 * @implements TerminalExecution<mixed, TValue, list<TValue>>
 */
final class UniqueExecution implements TerminalExecution
{
    /** @var list<TValue> */
    private array $values = [];

    /** @var list<TIdentity|TValue> */
    private array $identities = [];

    /** @var (\Closure(TValue): TIdentity)|null */
    private ?\Closure $selector;

    /** @param (callable(TValue): TIdentity)|null $selector */
    public function __construct(?callable $selector = null)
    {
        $this->selector = $selector === null ? null : $selector(...);
    }

    public function accept(mixed $value, mixed $key): void
    {
        $identity = $this->selector === null ? $value : ($this->selector)($value);
        if (in_array($identity, $this->identities, strict: true)) {
            return;
        }

        $this->identities[] = $identity;
        $this->values[] = $value;
    }

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): array
    {
        return $this->values;
    }
}
