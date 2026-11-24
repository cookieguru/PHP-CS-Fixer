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
    public function testGenerateEscapesSpecialCharacters(): void
    {
        $reportSummary = new ReportSummary(
            [
                "dir/some,file:100%\n.php" => [
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
            '::error file=dir/some%2Cfile%3A100%25%0A.php,title=PHP-CS-Fixer.some_fixer_name_here::Found violation(s) of type: some_fixer_name_here',
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
        return '::error file=someFile.php,line=5,title=PHP-CS-Fixer.some_fixer_name_here::Found violation(s) of type: some_fixer_name_here';
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
