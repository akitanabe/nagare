<?php

declare(strict_types=1);

namespace Nagare\Tests\PHPStan;

use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class TypeInferenceTest extends TypeInferenceTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../phpstan.neon.dist'];
    }

    /** @return iterable<array<mixed>> */
    public static function inferredTypes(): iterable
    {
        require_once __DIR__ . '/fixtures/inference.php';

        yield from self::gatherAssertTypes(__DIR__ . '/fixtures/inference.php');
    }

    #[DataProvider('inferredTypes')]
    public function testPublicResultsKeepTheirTypes(string $assertType, string $file, mixed ...$args): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }
}
