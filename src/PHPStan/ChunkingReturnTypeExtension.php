<?php

declare(strict_types=1);

namespace Nagare\PHPStan;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\NameScope;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Type;

final class ChunkingReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    public function __construct(
        private TypeStringResolver $typeStringResolver,
    ) {}

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'Nagare\\Pipeline\\chunking';
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope,
    ): Type {
        $preserveKeys = $functionCall->getArgs()[1] ?? null;
        if ($preserveKeys === null) {
            return $this->preservedChunks();
        }

        $preserveKeysType = $scope->getType($preserveKeys->value);
        if ($preserveKeysType->isTrue()->yes()) {
            return $this->preservedChunks();
        }

        if ($preserveKeysType->isFalse()->yes()) {
            return $this->valueChunks();
        }

        return $this->dynamicChunks();
    }

    private function preservedChunks(): Type
    {
        return $this->typeStringResolver->resolve(
            'Closure<TKey, TValue>(iterable<TKey&array-key, TValue>): iterable<int, array<TKey&array-key, TValue>>',
            new NameScope(null, []),
        );
    }

    private function valueChunks(): Type
    {
        return $this->typeStringResolver->resolve(
            'Closure<TKey, TValue>(iterable<TKey, TValue>): iterable<int, list<TValue>>',
            new NameScope(null, []),
        );
    }

    private function dynamicChunks(): Type
    {
        return $this->typeStringResolver->resolve(
            'Closure<TKey, TValue>(iterable<TKey&array-key, TValue>): iterable<int, array<TKey&array-key, TValue>|list<TValue>>',
            new NameScope(null, []),
        );
    }
}
