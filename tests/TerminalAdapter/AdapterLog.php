<?php

declare(strict_types=1);

namespace Nagare\Tests\TerminalAdapter;

final class AdapterLog
{
    /** @var list<array{mixed, mixed}> */
    public array $seen = [];
}
