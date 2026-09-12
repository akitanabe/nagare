<?php

declare(strict_types=1);

namespace Nagare\PHPStan;

use Nagare\Transform;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class TransformationReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    public function __construct(
        private TransformationDefaultType $defaultType,
    ) {}

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return in_array(
            $functionReflection->getName(),
            [
                'Nagare\\Transformation\\then',
                'Nagare\\Transformation\\defaults',
                'Nagare\\Transformation\\defaultsOr',
            ],
            strict: true,
        );
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope,
    ): ?Type {
        $argument = $functionCall->getArgs()[0] ?? null;
        if ($argument === null) {
            return null;
        }

        $argumentType = $scope->getType($argument->value);
        if ($functionReflection->getName() === 'Nagare\\Transformation\\then') {
            $acceptors = $argumentType->getCallableParametersAcceptors($scope);
            $acceptor = $acceptors[0] ?? null;
            $parameters = $acceptor === null ? [] : $acceptor->getParameters();
            $parameter = $parameters[0] ?? null;
            $input = $parameter === null ? new MixedType() : $parameter->getType();

            return new GenericObjectType(Transform::class, [
                $input,
                TypeCombinator::union($input, new NullType()),
            ]);
        }

        if ($functionReflection->getName() === 'Nagare\\Transformation\\defaultsOr') {
            $acceptors = $argumentType->getCallableParametersAcceptors($scope);
            $acceptor = $acceptors[0] ?? null;
            $argumentType = $acceptor === null ? new MixedType() : $acceptor->getReturnType();
        }

        $input = $functionReflection->getName() === 'Nagare\\Transformation\\defaults'
            ? $this->defaultType->fixedInput()
            : $this->defaultType->factoryInput();
        if ($input === null) {
            return null;
        }

        return new GenericObjectType(Transform::class, [
            TypeCombinator::union($input, new NullType()),
            TypeCombinator::union($input, $argumentType),
        ]);
    }
}
