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
     * @param list<array{begin: int, end: int}> $expected
     *
     * @dataProvider provideResolveAllCases
     */
    #[DataProvider('provideResolveAllCases')]
    public function testResolveAll(array $expected, string $diff): void
    {
        self::assertSame($expected, (new ChangedLinesResolver())->resolveAll($diff));
    }

    /**
     * @return iterable<string, array{list<array{begin: int, end: int}>, string}>
     */
    public static function provideResolveAllCases(): iterable
    {
        yield 'empty diff' => [[], ''];

        yield 'replaced line' => [
            [['begin' => 2, 'end' => 2]],
            <<<'DIFF'
                --- Original
                +++ New
                @@ -1,3 +1,3 @@
                 a
                -b
                +B
                 c
                DIFF,
        ];

        yield 'two groups in one chunk' => [
            [['begin' => 2, 'end' => 2], ['begin' => 6, 'end' => 6]],
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

        yield 'groups in separate chunks' => [
            [['begin' => 2, 'end' => 2], ['begin' => 21, 'end' => 21]],
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

        yield 'multiple removed lines' => [
            [['begin' => 2, 'end' => 3]],
            <<<'DIFF'
                --- Original
                +++ New
                @@ -1,5 +1,3 @@
                 a
                -b
                -c
                 d
                 e
                DIFF,
        ];

        yield 'pure insertion is reported at the line it was inserted before' => [
            [['begin' => 3, 'end' => 3]],
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

        yield 'insertion at the end is reported at the last original line' => [
            [['begin' => 2, 'end' => 2]],
            <<<'DIFF'
                --- Original
                +++ New
                @@ -1,2 +1,3 @@
                 a
                 b
                +c
                DIFF,
        ];

        yield 'the marker for a missing newline at the end of the file is not a line' => [
            [['begin' => 5, 'end' => 5]],
            <<<'DIFF'
                --- Original
                +++ New
                @@ -2,4 +2,4 @@

                 class NoNewline
                 {
                -}
                \ No newline at end of file
                +}
                DIFF,
        ];

        yield 'added lines before removed lines stay in one group' => [
            [['begin' => 2, 'end' => 3]],
            <<<'DIFF'
                --- Original
                +++ New
                @@ -1,4 +1,4 @@
                 a
                +X
                -b
                -c
                +Y
                 d
                DIFF,
        ];
    }

    public function testResolveStillReportsOnlyTheFirstChunk(): void
    {
        $diff = <<<'DIFF'
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
            DIFF;

        self::assertSame(['begin' => 5, 'end' => 9], (new ChangedLinesResolver())->resolve($diff));
        self::assertSame([['begin' => 5, 'end' => 5]], (new ChangedLinesResolver())->resolveAll($diff));
    }
}
