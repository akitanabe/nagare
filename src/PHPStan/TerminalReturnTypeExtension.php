<?php

declare(strict_types=1);

namespace Nagare\PHPStan;

use Nagare\Terminal;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\ArgumentsNormalizer;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;

final class TerminalReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return in_array(
            $functionReflection->getName(),
            [
                'Nagare\\Selection\\first',
                'Nagare\\Materialization\\values',
                'Nagare\\Aggregation\\pivot',
            ],
            strict: true,
        );
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope,
    ): ?Type {
        if ($functionReflection->getName() === 'Nagare\\Aggregation\\pivot') {
            return $this->pivotedType($functionCall, $scope);
        }

        $value = $this->reflectionProvider->getClass(Terminal::class)->getNativeMethod('__invoke')->getVariants()[0]
            ->getTemplateTypeMap()
            ->getType('TInputValue');
        if ($value === null) {
            return null;
        }
        $result = $functionReflection->getName() === 'Nagare\\Selection\\first'
            ? TypeCombinator::union($value, new NullType())
            : TypeCombinator::intersect(new ArrayType(new IntegerType(), $value), new AccessoryArrayListType());

        return new GenericObjectType(Terminal::class, [new MixedType(), new MixedType(), $result]);
    }

    private function pivotedType(FuncCall $functionCall, Scope $scope): Type
    {
        $arguments = array_map(static function (Arg $argument): Arg {
            $original = $argument->getAttribute(ArgumentsNormalizer::ORIGINAL_ARG_ATTRIBUTE);

            return $original instanceof Arg ? $original : $argument;
        }, $functionCall->getArgs());
        // PHPStan reorders arguments named "terminals"; the variadic array retains source order.
        usort(
            $arguments,
            static fn(Arg $left, Arg $right): int => $left->getStartFilePos() <=> $right->getStartFilePos(),
        );
        $items = [];
        foreach ($arguments as $argument) {
            $items[] = new ArrayItem(
                $argument->value,
                $argument->name === null ? null : new String_($argument->name->toString()),
                unpack: $argument->unpack,
            );
        }

        $definitions = $scope->getType(new Array_($items));
        $terminals = $definitions->getIterableValueType();
        $results = TypeTraverser::map($definitions, static function (Type $type, callable $traverse): Type {
            if (new ObjectType(Terminal::class)->isSuperTypeOf($type)->yes()) {
                return $type->getTemplateType(Terminal::class, 'TResult');
            }

            return $traverse($type);
        });

        return new GenericObjectType(Terminal::class, [
            TerminalInputType::resolve($terminals, 'TKey'),
            TerminalInputType::resolve($terminals, 'TValue'),
            $results,
        ]);
    }
}
