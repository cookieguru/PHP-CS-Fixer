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

namespace PhpCsFixer\Tests\Runner;

use PhpCsFixer\Runner\RuleChangesTracker;
use PhpCsFixer\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * @covers \PhpCsFixer\Runner\RuleChangesTracker
 *
 * @no-named-arguments Parameter names are not covered by the backward compatibility promise.
 */
#[CoversClass(RuleChangesTracker::class)]
final class RuleChangesTrackerTest extends TestCase
{
    /**
     * @param list<array{string, string}>                      $steps    rule name and the code after the rule
     * @param array<string, list<array{begin: int, end: int}>> $expected
     *
     * @dataProvider provideResolveCases
     */
    #[DataProvider('provideResolveCases')]
    public function testResolve(array $expected, string $originalCode, array $steps): void
    {
        $tracker = RuleChangesTracker::start($originalCode);
        $finalCode = $originalCode;

        foreach ($steps as [$rule, $codeAfter]) {
            $tracker = $tracker->withRule($rule, $codeAfter);
            $finalCode = $codeAfter;
        }

        self::assertSame($expected, $tracker->resolve($finalCode));
    }

    /**
     * @return iterable<string, array{array<string, list<array{begin: int, end: int}>>, string, list<array{string, string}>}>
     */
    public static function provideResolveCases(): iterable
    {
        yield 'no rule' => [[], "a\nb\n", []];

        yield 'code that did not change is ignored' => [
            [],
            "a\nb\n",
            [['rule', "a\nb\n"]],
        ];

        yield 'replaced line' => [
            ['rule' => [['begin' => 2, 'end' => 2]]],
            "a\nb\nc\n",
            [['rule', "a\nB\nc\n"]],
        ];

        yield 'removed lines' => [
            ['rule' => [['begin' => 2, 'end' => 3]]],
            "a\nb\nc\nd\n",
            [['rule', "a\nd\n"]],
        ];

        yield 'several lines replaced by one' => [
            ['rule' => [['begin' => 2, 'end' => 3]]],
            "a\nb\nc\nd\n",
            [['rule', "a\nX\nd\n"]],
        ];

        yield 'one line replaced by several' => [
            ['rule' => [['begin' => 2, 'end' => 2]]],
            "a\nb\nc\n",
            [['rule', "a\nX\nY\nZ\nc\n"]],
        ];

        yield 'insertion is reported at the line before it' => [
            ['rule' => [['begin' => 1, 'end' => 1]]],
            "a\nb\n",
            [['rule', "a\nX\nb\n"]],
        ];

        yield 'insertion at the start' => [
            ['rule' => [['begin' => 1, 'end' => 1]]],
            "a\n",
            [['rule', "X\na\n"]],
        ];

        yield 'insertion at the end' => [
            ['rule' => [['begin' => 2, 'end' => 2]]],
            "a\nb\n",
            [['rule', "a\nb\nX\n"]],
        ];

        yield 'separate changes of one rule' => [
            ['rule' => [['begin' => 2, 'end' => 2], ['begin' => 5, 'end' => 5]]],
            "a\nb\nc\nd\ne\n",
            [['rule', "a\nB\nc\nd\nE\n"]],
        ];

        yield 'changes of one rule next to each other are one range' => [
            ['rule' => [['begin' => 2, 'end' => 4]]],
            "a\nb\nc\nd\ne\n",
            [['rule', "a\nB\nC\nD\ne\n"]],
        ];

        yield 'line endings' => [
            ['rule' => [['begin' => 1, 'end' => 2]]],
            "a\r\nb\r\n",
            [['rule', "a\nb\n"]],
        ];

        yield 'missing newline at the end of the code' => [
            ['rule' => [['begin' => 2, 'end' => 2]]],
            "a\nb",
            [['rule', "a\nb\n"]],
        ];

        yield 'the first rule adds lines above the change of the second one' => [
            [
                'first' => [['begin' => 1, 'end' => 1]],
                'second' => [['begin' => 4, 'end' => 4]],
            ],
            "a\nb\nc\nd\n",
            [
                ['first', "a\nX\nY\nb\nc\nd\n"],
                ['second', "a\nX\nY\nb\nc\nD\n"],
            ],
        ];

        yield 'the first rule removes lines above the change of the second one' => [
            [
                'first' => [['begin' => 2, 'end' => 3]],
                'second' => [['begin' => 5, 'end' => 5]],
            ],
            "a\nb\nc\nd\ne\n",
            [
                ['first', "a\nd\ne\n"],
                ['second', "a\nd\nE\n"],
            ],
        ];

        yield 'the second rule changes lines added by the first one' => [
            [
                'first' => [['begin' => 1, 'end' => 1]],
                'second' => [['begin' => 1, 'end' => 1]],
            ],
            "a\nb\n",
            [
                ['first', "a\nX\nb\n"],
                ['second', "a\nx\nb\n"],
            ],
        ];

        yield 'a rule that is reverted by another one is left out' => [
            ['second' => [['begin' => 3, 'end' => 3]]],
            "a\nb\nc\n",
            [
                ['first', "a\nB\nc\n"],
                ['second', "a\nb\nC\n"],
            ],
        ];

        yield 'an insertion that ends up merged with the removal next to it' => [
            [
                'first' => [['begin' => 3, 'end' => 3]],
                'second' => [['begin' => 2, 'end' => 2]],
            ],
            'a
b
c
d
',
            [
                ['first', 'a
b
d
'],
                ['second', 'a
b

d
'],
            ],
        ];

        yield 'rules that cancel each other' => [
            [],
            "a\nb\nc\n",
            [
                ['first', "a\nB\nc\n"],
                ['second', "a\nb\nc\n"],
            ],
        ];
    }
}
