<?php

declare(strict_types=1);

namespace Nagare\PHPStan;

use Nagare\Adapter\Definition;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class AdapterReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    public function __construct(
        private TransformationDefaultType $defaultType,
    ) {}

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return in_array(
            $functionReflection->getName(),
            [
                'Nagare\\Adapter\\then',
                'Nagare\\Adapter\\fallback',
                'Nagare\\Adapter\\fallbackWith',
                'Nagare\\Adapter\\some',
            ],
            strict: true,
        );
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope,
    ): ?Type {
        $name = $functionReflection->getName();
        if ($name === 'Nagare\\Adapter\\then') {
            $argument = $functionCall->getArgs()[0] ?? null;
            if ($argument === null) {
                return null;
            }

            $acceptor = $scope->getType($argument->value)->getCallableParametersAcceptors($scope)[0] ?? null;
            $input = ($acceptor?->getParameters()[0] ?? null)?->getType() ?? new MixedType();

            return new GenericObjectType(Definition::class, [
                $input,
                TypeCombinator::union($input, new NullType()),
            ]);
        }

        if ($name === 'Nagare\\Adapter\\some') {
            $input = $this->defaultType->fixedInput();
            if ($input === null) {
                return null;
            }

            return new GenericObjectType(Definition::class, [
                TypeCombinator::union($input, new NullType()),
                $input,
            ]);
        }

        $argument = $functionCall->getArgs()[0] ?? null;
        if ($argument === null) {
            return null;
        }

        $argumentType = $scope->getType($argument->value);
        if ($name === 'Nagare\\Adapter\\fallbackWith') {
            $acceptor = $argumentType->getCallableParametersAcceptors($scope)[0] ?? null;
            $argumentType = $acceptor === null ? new MixedType() : $acceptor->getReturnType();
        }

        $input = $name === 'Nagare\\Adapter\\fallback'
            ? $this->defaultType->fixedInput()
            : $this->defaultType->factoryInput();
        if ($input === null) {
            return null;
        }

        return new GenericObjectType(Definition::class, [
            TypeCombinator::union($input, new NullType()),
            TypeCombinator::union($input, $argumentType),
        ]);
    }
}
