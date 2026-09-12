<?php

declare(strict_types=1);

namespace Nagare\PHPStan;

interface TransformationDefaultTypeMarker
{
    /**
     * @template TInput
     * @param TInput $value
     * @return TInput
     */
    public function fixed(mixed $value): mixed;

    /**
     * @template TInput
     * @param TInput $value
     * @return TInput
     */
    public function factory(mixed $value): mixed;
}
