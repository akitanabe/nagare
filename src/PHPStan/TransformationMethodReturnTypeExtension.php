<?php

declare(strict_types=1);

namespace Nagare\PHPStan;

use Nagare\Transform;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class TransformationMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private TransformationDefaultType $defaultType,
    ) {}

    public function getClass(): string
    {
        return Transform::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'transform';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): ?Type {
        $argument = $methodCall->getArgs()[0] ?? null;
        if ($argument === null) {
            return null;
        }

        $transform = $scope->getType($methodCall->var);
        $output = $transform->getTemplateType(Transform::class, 'TOutput');
        $input = TypeCombinator::removeNull($scope->getType($argument->value));

        return $this->defaultType->replaceInput($output, $input);
    }
}
