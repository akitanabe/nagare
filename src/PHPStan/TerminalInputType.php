<?php

declare(strict_types=1);

namespace Nagare\PHPStan;

use Nagare\Terminal;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\NonAcceptingNeverType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

final class TerminalInputType
{
    /** @param 'TKey'|'TValue' $templateName */
    public static function resolve(Type $terminals, string $templateName): Type
    {
        $input = new MixedType();
        foreach ($terminals instanceof UnionType ? $terminals->getTypes() : [$terminals] as $terminal) {
            if ($terminal instanceof NeverType) {
                continue;
            }
            $input = TypeCombinator::intersect($input, $terminal->getTemplateType(Terminal::class, $templateName));
        }

        // Inferred NeverType accepts any value; incompatible definitions need a rejecting input type.
        return $input instanceof NeverType ? new NonAcceptingNeverType() : $input;
    }
}
