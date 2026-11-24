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

use PhpCsFixer\Console\Report\FixReport\GithubReporter;
use PhpCsFixer\Console\Report\FixReport\ReporterInterface;
use PhpCsFixer\Console\Report\FixReport\ReportSummary;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 *
 * @covers \PhpCsFixer\Console\Report\FixReport\GithubReporter
 *
 * @no-named-arguments Parameter names are not covered by the backward compatibility promise.
 */
#[CoversClass(GithubReporter::class)]
final class GithubReporterTest extends AbstractReporterTestCase
{
    public function testGenerateCreatesOneAnnotationPerRangeOfEveryRule(): void
    {
        $reportSummary = new ReportSummary(
            [
                'someFile.php' => [
                    'appliedFixers' => ['fixer_a', 'fixer_b'],
                    'diff' => '',
                    'ruleChanges' => [
                        'fixer_b' => [['begin' => 10, 'end' => 12]],
                        'fixer_a' => [['begin' => 30, 'end' => 30], ['begin' => 2, 'end' => 2]],
                    ],
                ],
                'anotherFile.php' => [
                    'appliedFixers' => ['fixer_a'],
                    'diff' => '',
                    'ruleChanges' => [
                        'fixer_a' => [['begin' => 5, 'end' => 5]],
                    ],
                ],
            ],
            10,
            0,
            0,
            false,
            false,
            false,
        );

        self::assertSame(
            <<<'TXT'
                ::error file=someFile.php,line=2,title=PHP-CS-Fixer.fixer_a::Found violation(s) of type: fixer_a
                ::error file=someFile.php,line=10,endLine=12,title=PHP-CS-Fixer.fixer_b::Found violation(s) of type: fixer_b
                ::error file=someFile.php,line=30,title=PHP-CS-Fixer.fixer_a::Found violation(s) of type: fixer_a
                ::error file=anotherFile.php,line=5,title=PHP-CS-Fixer.fixer_a::Found violation(s) of type: fixer_a
                TXT,
            $this->reporter->generate($reportSummary),
        );
    }

    public function testGenerateFallsBackToFileLevelAnnotationsWhenNoRuleHasRanges(): void
    {
        $reportSummary = new ReportSummary(
            [
                'someFile.php' => [
                    'appliedFixers' => ['fixer_a', 'fixer_b'],
                    'diff' => '',
                    'ruleChanges' => [],
                ],
            ],
            10,
            0,
            0,
            false,
            false,
            false,
        );

        self::assertSame(
            <<<'TXT'
                ::error file=someFile.php,title=PHP-CS-Fixer.fixer_a::Found violation(s) of type: fixer_a
                ::error file=someFile.php,title=PHP-CS-Fixer.fixer_b::Found violation(s) of type: fixer_b
                TXT,
            $this->reporter->generate($reportSummary),
        );
    }

    public function testGenerateEscapesSpecialCharacters(): void
    {
        $reportSummary = new ReportSummary(
            [
                "dir/some,file:100%\n.php" => [
                    'appliedFixers' => ['some_fixer_name_here'],
                    'diff' => '',
                    'ruleChanges' => [
                        'some_fixer_name_here' => [['begin' => 3, 'end' => 3]],
                    ],
                ],
                'other,file.php' => [
                    'appliedFixers' => ['some_fixer_name_here'],
                    'diff' => '',
                ],
            ],
            10,
            0,
            0,
            false,
            false,
            false,
        );

        self::assertSame(
            <<<'TXT'
                ::error file=dir/some%2Cfile%3A100%25%0A.php,line=3,title=PHP-CS-Fixer.some_fixer_name_here::Found violation(s) of type: some_fixer_name_here
                ::error file=other%2Cfile.php,title=PHP-CS-Fixer.some_fixer_name_here::Found violation(s) of type: some_fixer_name_here
                TXT,
            $this->reporter->generate($reportSummary),
        );
    }

    protected function createReporter(): ReporterInterface
    {
        return new GithubReporter();
    }

    protected function getFormat(): string
    {
        return 'github';
    }

    protected static function createNoErrorReport(): string
    {
        return '';
    }

    protected static function createSimpleReport(): string
    {
        return '::error file=someFile.php,title=PHP-CS-Fixer.some_fixer_name_here::Found violation(s) of type: some_fixer_name_here';
    }

    protected static function createWithDiffReport(): string
    {
        return self::createSimpleReport();
    }

    protected static function createWithAppliedFixersReport(): string
    {
        return <<<'TXT'
            ::error file=someFile.php,title=PHP-CS-Fixer.some_fixer_name_here_1::Found violation(s) of type: some_fixer_name_here_1
            ::error file=someFile.php,title=PHP-CS-Fixer.some_fixer_name_here_2::Found violation(s) of type: some_fixer_name_here_2
            TXT;
    }

    protected static function createWithTimeAndMemoryReport(): string
    {
        return self::createSimpleReport();
    }

    protected static function createComplexReport(): string
    {
        return <<<'TXT'
            ::error file=someFile.php,title=PHP-CS-Fixer.some_fixer_name_here_1::Found violation(s) of type: some_fixer_name_here_1
            ::error file=someFile.php,title=PHP-CS-Fixer.some_fixer_name_here_2::Found violation(s) of type: some_fixer_name_here_2
            ::error file=anotherFile.php,title=PHP-CS-Fixer.another_fixer_name_here::Found violation(s) of type: another_fixer_name_here
            TXT;
    }

    protected static function createDryRunWithNoTimeReport(): string
    {
        return '';
    }

    protected function assertFormat(string $expected, string $input): void
    {
        self::assertSame($expected, $input);
    }
}
