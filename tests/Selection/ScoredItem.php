<?php

declare(strict_types=1);

namespace Nagare\Tests\Selection;

final readonly class ScoredItem
{
    public function __construct(
        public string $name,
        public int $score,
    ) {}
}
