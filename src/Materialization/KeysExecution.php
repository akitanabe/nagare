<?php

declare(strict_types=1);

namespace Nagare\Materialization;

use Nagare\TerminalExecution;

/**
 * @internal
 * @template TKey
 * @implements TerminalExecution<TKey, mixed, list<TKey>>
 */
final class KeysExecution implements TerminalExecution
{
    /** @var list<TKey> */
    private array $keys = [];

    public function accept(mixed $value, mixed $key): void
    {
        $this->keys[] = $key;
    }

    public function isComplete(): bool
    {
        return false;
    }

    /** @return list<TKey> */
    public function finish(): mixed
    {
        return $this->keys;
    }
}
