<?php

declare(strict_types=1);

namespace Nagare\PHPStan;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\TemplateType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

final class TransformationDefaultType
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function fixedInput(): ?TemplateType
    {
        return $this->marker('fixed');
    }

    public function factoryInput(): ?TemplateType
    {
        return $this->marker('factory');
    }

    public function replaceInput(Type $output, Type $input): Type
    {
        $markers = array_values(array_filter([$this->fixedInput(), $this->factoryInput()]));
        if ($this->isInput($output, $markers)) {
            return $input;
        }

        if (!$output instanceof UnionType) {
            return $output;
        }

        return TypeCombinator::union(...array_map(fn(Type $member): Type => $this->isInput($member, $markers)
            ? $input
            : $member, $output->getTypes()));
    }

    /** @param list<TemplateType> $markers */
    private function isInput(Type $type, array $markers): bool
    {
        if (!$type instanceof TemplateType) {
            return false;
        }

        foreach ($markers as $marker) {
            if ($type->getName() === $marker->getName() && $type->getScope()->equals($marker->getScope())) {
                return true;
            }
        }

        return false;
    }

    /** @param 'fixed'|'factory' $method */
    private function marker(string $method): ?TemplateType
    {
        $type = $this->reflectionProvider
            ->getClass(TransformationDefaultTypeMarker::class)
            ->getNativeMethod($method)
            ->getVariants()[0]
            ->getTemplateTypeMap()
            ->getType('TInput');

        return $type instanceof TemplateType ? $type : null;
    }
}
