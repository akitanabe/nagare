<?php

declare(strict_types=1);

namespace Nagare\PHPStan;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\Php\DummyParameter;
use PHPStan\Type\ClosureType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Generic\TemplateTypeFactory;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Generic\TemplateTypeScope;
use PHPStan\Type\Generic\TemplateTypeVariance;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

final class PipelineEachReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'Nagare\\Pipeline\\each';
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope,
    ): Type {
        $callback = $functionCall->getArgs()[0] ?? null;
        $acceptor = $callback === null
            ? null
            : $scope->getType($callback->value)->getCallableParametersAcceptors($scope)[0] ?? null;
        $parameters = $acceptor === null ? [] : $acceptor->getParameters();
        $valueBound = ($parameters[0] ?? null)?->getType() ?? new MixedType();
        $keyBound = ($parameters[1] ?? null)?->getType() ?? new MixedType();

        // @phpstan-ignore phpstanApi.method (Template types preserve concrete input types while enforcing callback bounds.)
        $templateScope = TemplateTypeScope::createWithFunction('Nagare\\Pipeline\\each');
        // @phpstan-ignore phpstanApi.method (Template types preserve concrete input types while enforcing callback bounds.)
        $key = TemplateTypeFactory::create($templateScope, 'TKey', $keyBound, TemplateTypeVariance::createInvariant());
        // @phpstan-ignore phpstanApi.method (Template types preserve concrete input types while enforcing callback bounds.)
        $value = TemplateTypeFactory::create(
            $templateScope,
            'TValue',
            $valueBound,
            TemplateTypeVariance::createInvariant(),
        );
        $input = new IterableType($key, $value);

        return new ClosureType(
            [
                // @phpstan-ignore phpstanApi.constructor (ClosureType requires a parameter reflection for its input iterable.)
                new DummyParameter('input', $input, false, null, false, null),
            ],
            $input,
            false,
            new TemplateTypeMap(['TKey' => $key, 'TValue' => $value]),
        );
    }
}
