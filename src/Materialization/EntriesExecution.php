<?php

declare(strict_types=1);

namespace Nagare\Materialization;

use Nagare\TerminalExecution;

/**
 * @internal
 * @template TKey
 * @template TValue
 * @implements TerminalExecution<TKey, TValue, list<array{0: TKey, 1: TValue}>>
 */
final class EntriesExecution implements TerminalExecution
{
    /** @var list<array{0: TKey, 1: TValue}> */
    private array $entries = [];

    public function accept(mixed $value, mixed $key): void
    {
        $this->entries[] = [$key, $value];
    }

    public function isComplete(): bool
    {
        return false;
    }

    /** @return list<array{0: TKey, 1: TValue}> */
    public function finish(): mixed
    {
        return $this->entries;
    }
}
