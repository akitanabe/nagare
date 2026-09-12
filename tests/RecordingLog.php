<?php

declare(strict_types=1);

namespace Nagare\Tests;

final class RecordingLog
{
    /** @var list<array{string|int, mixed}> */
    public array $seen = [];
}
