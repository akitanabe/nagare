<?php

declare(strict_types=1);

namespace Nagare\PHPStan;

use Nagare\Terminal;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\NameScope;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ClosureType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\TemplateType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;

final class TerminalApplyReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private TypeStringResolver $typeStringResolver,
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function getClass(): string
    {
        return Terminal::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'apply';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): ?Type {
        $terminal = $scope->getType($methodCall->var);
        $closure = $this->typeStringResolver->resolve(
            'Closure<TSource, TTransformed of TValue>(\\Nagare\\Transform<TSource, TTransformed&TValue>): \\Nagare\\Terminal<TKey, TSource, TResult>',
            new NameScope(null, [], templateTypeMap: new TemplateTypeMap([
                'TKey' => TerminalInputType::resolve($terminal, 'TKey'),
                'TValue' => TerminalInputType::resolve($terminal, 'TValue'),
                'TResult' => $terminal->getTemplateType(Terminal::class, 'TResult'),
            ])),
        );
        $input = $this->reflectionProvider->getClass(Terminal::class)->getNativeMethod('__invoke')->getVariants()[0]
            ->getTemplateTypeMap()
            ->getType('TInputValue');
        if (!$closure instanceof ClosureType || !$input instanceof TemplateType) {
            return null;
        }
        $transformed = $closure->getTemplateTypeMap()->getType('TTransformed');
        if ($transformed === null) {
            return null;
        }

        return TypeTraverser::map($closure, static function (Type $type, callable $traverse) use (
            $input,
            $transformed,
        ): Type {
            if (
                $type instanceof TemplateType
                && $type->getName() === 'TInputValue'
                && $type->getScope()->equals($input->getScope())
            ) {
                return $transformed;
            }

            return $traverse($type);
        });
    }
}
