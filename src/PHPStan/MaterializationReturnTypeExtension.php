<?php

declare(strict_types=1);

namespace Nagare\PHPStan;

use Nagare\Terminal;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\TemplateType;
use PHPStan\Type\Generic\TemplateTypeFactory;
use PHPStan\Type\Generic\TemplateTypeScope;
use PHPStan\Type\Generic\TemplateTypeVariance;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class MaterializationReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return in_array(
            $functionReflection->getName(),
            [
                'Nagare\\Materialization\\keys',
                'Nagare\\Materialization\\associate',
                'Nagare\\Materialization\\entries',
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
        if ($name === 'Nagare\\Materialization\\keys') {
            $key = $this->materializationKey($name);

            return $this->inputDependentTerminal(
                new MixedType(),
                static fn(Type $value): Type => TypeCombinator::intersect(
                    new ArrayType(new IntegerType(), $key),
                    new AccessoryArrayListType(),
                ),
                $key,
            );
        }

        if ($name === 'Nagare\\Materialization\\entries') {
            $key = $this->materializationKey($name);

            return $this->inputDependentTerminal(
                new MixedType(),
                static fn(Type $value): Type => TypeCombinator::intersect(
                    new ArrayType(new IntegerType(), self::entryType($key, $value)),
                    new AccessoryArrayListType(),
                ),
                $key,
            );
        }

        $selector = $functionCall->getArgs()[0] ?? null;
        if ($selector === null || $scope->getType($selector->value)->isNull()->yes()) {
            $key = $this->materializationKey($name, TypeCombinator::union(new IntegerType(), new StringType()));

            return $this->inputDependentTerminal(
                new MixedType(),
                static fn(Type $value): Type => new ArrayType($key, $value),
                $key,
            );
        }

        $acceptor = $scope->getType($selector->value)->getCallableParametersAcceptors($scope)[0];
        $parameters = $acceptor->getParameters();
        $value = ($parameters[0] ?? null)?->getType() ?? new MixedType();
        $arrayKey = TypeCombinator::union(new IntegerType(), new StringType());
        $key = TypeCombinator::intersect(($parameters[1] ?? null)?->getType() ?? $arrayKey, $arrayKey);
        $selectedKey = $acceptor->getReturnType()->toArrayKey();
        if (!$scope->getType($selector->value)->isNull()->no()) {
            $key = $this->materializationKey($name, $key);
            $selectedKey = TypeCombinator::union($selectedKey, $key);
        }

        return $this->inputDependentTerminal(
            $value,
            static fn(Type $input): Type => new ArrayType($selectedKey, $input),
            $key,
        );
    }

    /** @param callable(Type): Type $result */
    private function inputDependentTerminal(Type $input, callable $result, Type $key): ?Type
    {
        $value = $this->reflectionProvider->getClass(Terminal::class)->getNativeMethod('__invoke')->getVariants()[0]
            ->getTemplateTypeMap()
            ->getType('TInputValue');
        if ($value === null) {
            return null;
        }

        return new GenericObjectType(Terminal::class, [$key, $input, $result($value)]);
    }

    private static function entryType(Type $key, Type $value): Type
    {
        $entry = ConstantArrayTypeBuilder::createEmpty();
        $entry->setOffsetValueType(new ConstantIntegerType(0), $key);
        $entry->setOffsetValueType(new ConstantIntegerType(1), $value);

        return $entry->getArray();
    }

    private function materializationKey(string $functionName, ?Type $bound = null): TemplateType
    {
        // @phpstan-ignore phpstanApi.method (PHPStan's template factory carries the input key marker.)
        return TemplateTypeFactory::create(
            // @phpstan-ignore phpstanApi.method (The marker needs a function-local scope to distinguish terminals.)
            TemplateTypeScope::createWithFunction($functionName),
            'TKey',
            $bound ?? new MixedType(),
            TemplateTypeVariance::createInvariant(),
        );
    }
}
