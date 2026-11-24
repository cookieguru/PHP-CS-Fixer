<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Tests\Console\Report\FixReport;

use PhpCsFixer\Console\Report\FixReport\ChangedLinesResolver;
use PhpCsFixer\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * @covers \PhpCsFixer\Console\Report\FixReport\ChangedLinesResolver
 *
 * @no-named-arguments Parameter names are not covered by the backward compatibility promise.
 */
#[CoversClass(ChangedLinesResolver::class)]
final class ChangedLinesResolverTest extends TestCase
{
    /**
     * @param array{begin: int, end: int} $expected
     *
     * @dataProvider provideResolveCases
     */
    #[DataProvider('provideResolveCases')]
    public function testResolve(array $expected, string $diff): void
    {
        self::assertSame($expected, (new ChangedLinesResolver())->resolve($diff));
    }

    /**
     * @return iterable<string, array{array{begin: int, end: int}, string}>
     */
    public static function provideResolveCases(): iterable
    {
        yield 'empty diff' => [['begin' => 0, 'end' => 0], ''];

        yield 'replaced line' => [
            ['begin' => 5, 'end' => 9],
            <<<'DIFF'
                --- Original
                +++ New
                @@ -2,7 +2,7 @@

                 class Foo
                 {
                -    public function bar($foo = 1, $bar)
                +    public function bar($foo, $bar)
                     {
                     }
                 }
                DIFF,
        ];

        yield 'insertion' => [
            ['begin' => 3, 'end' => 4],
            <<<'DIFF'
                --- Original
                +++ New
                @@ -1,3 +1,4 @@
                 a
                 b
                +X
                 c
                DIFF,
        ];

        yield 'only the first chunk is used' => [
            ['begin' => 2, 'end' => 4],
            <<<'DIFF'
                --- Original
                +++ New
                @@ -1,3 +1,3 @@
                 a
                -b
                +B
                 c
                @@ -20,3 +20,3 @@
                 x
                -y
                +Y
                 z
                DIFF,
        ];
    }

    /**
     * @param array{begin: int, end: int} $expected
     *
     * @dataProvider provideResolveFirstChunkChangesCases
     */
    #[DataProvider('provideResolveFirstChunkChangesCases')]
    public function testResolveFirstChunkChanges(array $expected, string $diff): void
    {
        self::assertSame($expected, (new ChangedLinesResolver())->resolveFirstChunkChanges($diff));
    }

    /**
     * @return iterable<string, array{array{begin: int, end: int}, string}>
     */
    public static function provideResolveFirstChunkChangesCases(): iterable
    {
        yield 'empty diff' => [['begin' => 0, 'end' => 0], ''];

        yield 'replaced line' => [
            ['begin' => 5, 'end' => 5],
            <<<'DIFF'
                --- Original
                +++ New
                @@ -2,7 +2,7 @@

                 class Foo
                 {
                -    public function bar($foo = 1, $bar)
                +    public function bar($foo, $bar)
                     {
                     }
                 }
                DIFF,
        ];

        yield 'removed lines are not extended by the context after them' => [
            ['begin' => 3, 'end' => 5],
            <<<'DIFF'
                --- Original
                +++ New
                @@ -1,8 +1,5 @@
                 <?php

                -use Countable;
                -use Iterator;
                -use Stringable;

                 class MultiLine
                 {
                DIFF,
        ];

        yield 'insertion' => [
            ['begin' => 3, 'end' => 3],
            <<<'DIFF'
                --- Original
                +++ New
                @@ -1,3 +1,4 @@
                 a
                 b
                +X
                 c
                DIFF,
        ];

        yield 'changes inside the first chunk are spanned' => [
            ['begin' => 2, 'end' => 6],
            <<<'DIFF'
                --- Original
                +++ New
                @@ -1,8 +1,8 @@
                 a
                -b
                +B
                 c
                 d
                 e
                -f
                +F
                 g
                 h
                DIFF,
        ];

        yield 'only the first chunk is used' => [
            ['begin' => 2, 'end' => 2],
            <<<'DIFF'
                --- Original
                +++ New
                @@ -1,3 +1,3 @@
                 a
                -b
                +B
                 c
                @@ -20,3 +20,3 @@
                 x
                -y
                +Y
                 z
                DIFF,
        ];
    }
}
