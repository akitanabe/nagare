<?php

declare(strict_types=1);

namespace Nagare\Terminal\Materialization;

use Closure;
use Nagare\TerminalExecution;

/**
 * @internal
 * @template TKey of array-key
 * @template TValue
 * @template TSelectedKey of array-key
 * @implements TerminalExecution<TKey, TValue, array<TSelectedKey, TValue>>
 */
final class AssociateExecution implements TerminalExecution
{
    /** @var (Closure(TValue, TKey): TSelectedKey)|null */
    private ?Closure $keySelector;

    /** @var array<TSelectedKey, TValue> */
    private array $associated = [];

    /** @param (callable(TValue, TKey): TSelectedKey)|null $keySelector */
    public function __construct(?callable $keySelector)
    {
        $this->keySelector = $keySelector === null ? null : $keySelector(...);
    }

    public function accept(mixed $value, mixed $key): void
    {
        $selectedKey = $this->keySelector === null ? $key : ($this->keySelector)($value, $key);
        // @phpstan-ignore assign.propertyType (The nullable selector preserves TKey when absent, which PHPStan cannot relate to TSelectedKey.)
        $this->associated[$selectedKey] = $value;
    }

    public function isComplete(): bool
    {
        return false;
    }

    /** @return array<TSelectedKey, TValue> */
    public function finish(): mixed
    {
        return $this->associated;
    }
}
