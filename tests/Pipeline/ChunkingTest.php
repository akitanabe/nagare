<?php

declare(strict_types=1);

namespace Nagare\Tests\Pipeline;

use Iterator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use WeakReference;

use function Nagare\Pipeline\chunking;
use function Nagare\Query\first;

final class ChunkingTest extends TestCase
{
    public function testChunkingPreservesInputKeysByDefault(): void
    {
        $source = static function (): iterable {
            yield 'first' => 'first';
            yield 'second' => 'second';
            yield 5 => 'third';
            yield 'fourth' => 'fourth';
            yield 'fifth' => 'fifth';
        };

        $chunks = [];
        foreach (chunking(2)($source()) as $chunkKey => $chunk) {
            $chunks[] = [$chunkKey, $chunk];
        }

        self::assertSame(
            [
                [0, ['first' => 'first', 'second' => 'second']],
                [1, [5 => 'third', 'fourth' => 'fourth']],
                [2, ['fifth' => 'fifth']],
            ],
            $chunks,
        );
    }

    public function testChunkingDefersSourceConsumptionAndConsumesOnlyTheRequestedChunk(): void
    {
        $sourceLog = [];
        $source = static function () use (&$sourceLog): iterable {
            $sourceLog[] = 'first';
            yield 'first' => 1;
            $sourceLog[] = 'second';
            yield 'second' => 2;
            $sourceLog[] = 'third';
            yield 'third' => 3;
            $sourceLog[] = 'fourth';
            throw new RuntimeException('The fourth value was requested.');
        };

        $chunks = chunking(3)($source());

        self::assertSame([], $sourceLog);
        self::assertSame(['first' => 1, 'second' => 2, 'third' => 3], first()($chunks));
        self::assertSame(['first', 'second', 'third'], $sourceLog);
    }

    public function testChunkingCanDiscardInputKeys(): void
    {
        $source = static function (): iterable {
            yield 'first' => 'first';
            yield 5 => 'second';
            yield 'third' => 'third';
        };

        self::assertSame(
            [
                ['first', 'second'],
                ['third'],
            ],
            iterator_to_array(chunking(2, preserveKeys: false)($source())),
        );
    }

    public function testChunkingWithNonPositiveSizeIsEmptyAndDoesNotConsumeInput(): void
    {
        foreach ([0, -3] as $size) {
            $consumed = 0;
            $source = static function () use (&$consumed): iterable {
                $consumed++;
                yield 'first' => 'first';
            };

            self::assertSame([], iterator_to_array(chunking($size)($source())));
            self::assertSame(0, $consumed);
        }
    }

    public function testChunkingDoesNotYieldAChunkForAnEmptyInput(): void
    {
        self::assertSame([], iterator_to_array(chunking(2)([])));
    }

    public function testChunkingReleasesAConsumedChunkAfterTheConsumerAdvances(): void
    {
        /** @return iterable<string, stdClass> */
        $source = static function (): iterable {
            yield 'first' => new stdClass();
            yield 'second' => new stdClass();
        };

        $firstValue = null;
        $chunks = chunking(1)($source());
        foreach ($chunks as $chunkKey => $chunk) {
            if ($chunkKey === 0) {
                $firstValue = WeakReference::create($chunk['first']);
                continue;
            }

            self::assertNull($firstValue?->get());
            break;
        }

        self::assertNotNull($firstValue);
        self::assertNull($firstValue->get());
    }

    public function testChunkingUsesNativeArrayKeyConversionForPreservedKeys(): void
    {
        $source = static function (): iterable {
            yield '8' => 'a';
            yield 8 => 'b';
            yield 'tail' => 'c';
        };

        $expected = [[8 => 'b', 'tail' => 'c']];
        self::assertSame($expected, iterator_to_array(chunking(2)($source())));
        self::assertSame($expected, iterator_to_array(chunking(2, preserveKeys: true)($source())));
    }

    public function testChunkingRejectsObjectKeysWhenPreservingKeys(): void
    {
        $source = new class implements Iterator {
            private bool $valid = true;

            public function current(): mixed
            {
                return 'value';
            }

            public function key(): mixed
            {
                return new stdClass();
            }

            public function next(): void
            {
                $this->valid = false;
            }

            public function rewind(): void {}

            public function valid(): bool
            {
                return $this->valid;
            }
        };

        $this->expectException(\TypeError::class);

        iterator_to_array(chunking(1)($source));
    }

    public function testChunkingCanDiscardObjectKeys(): void
    {
        $source = new class implements Iterator {
            private bool $valid = true;

            public function current(): mixed
            {
                return 'value';
            }

            public function key(): mixed
            {
                return new stdClass();
            }

            public function next(): void
            {
                $this->valid = false;
            }

            public function rewind(): void {}

            public function valid(): bool
            {
                return $this->valid;
            }
        };

        self::assertSame([['value']], iterator_to_array(chunking(1, preserveKeys: false)($source)));
    }

    public function testChunkingPropagatesSourceIteratorExceptionsWithTheirOriginalIdentity(): void
    {
        $failure = new RuntimeException('Source iterator failure.');
        $source = static function () use ($failure): iterable {
            yield 'first' => 1;
            throw $failure;
        };

        try {
            iterator_to_array(chunking(2)($source()));
            self::fail('The source iterator exception was not thrown.');
        } catch (RuntimeException $thrown) {
            self::assertSame($failure, $thrown);
        }
    }
}
