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

namespace PhpCsFixer\Console\Report\FixReport;

use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Generates a report in the form of GitHub Actions workflow commands.
 *
 * One annotation is created for every contiguous group of changed lines of a file.
 * The diff does not tell which fixer changed which lines, so when more than one fixer was applied to a file,
 * all of them are listed in each annotation of that file.
 * Without a diff there are no locations, and one file-level annotation per applied fixer is created instead.
 *
 * @see https://docs.github.com/en/actions/reference/workflow-commands-for-github-actions#setting-an-error-message
 *
 * @readonly
 *
 * @internal
 *
 * @no-named-arguments Parameter names are not covered by the backward compatibility promise.
 */
final class GithubReporter implements ReporterInterface
{
    private ChangedLinesResolver $changedLinesResolver;

    public function __construct()
    {
        $this->changedLinesResolver = new ChangedLinesResolver();
    }

    public function getFormat(): string
    {
        return 'github';
    }

    public function generate(ReportSummary $reportSummary): string
    {
        $commands = [];

        foreach ($reportSummary->getChanged() as $filePath => $fixResult) {
            $appliedFixers = $fixResult['appliedFixers'];
            $changedLines = $this->changedLinesResolver->resolveAll($fixResult['diff']);

            if ([] === $changedLines) {
                foreach ($appliedFixers as $appliedFixer) {
                    $commands[] = self::createCommand($filePath, null, [$appliedFixer]);
                }

                continue;
            }

            foreach ($changedLines as $lines) {
                $commands[] = self::createCommand($filePath, $lines, $appliedFixers);
            }
        }

        $result = implode("\n", $commands);

        return $reportSummary->isDecoratedOutput() ? OutputFormatter::escape($result) : $result;
    }

    /**
     * @param null|array{begin: int, end: int} $lines
     * @param list<string>                     $fixers
     */
    private static function createCommand(string $filePath, ?array $lines, array $fixers): string
    {
        $properties = 'file='.self::escapeProperty($filePath);

        if (null !== $lines) {
            $properties .= ',line='.$lines['begin'];
            if ($lines['end'] > $lines['begin']) {
                $properties .= ',endLine='.$lines['end'];
            }
        }

        $title = 1 === \count($fixers) ? 'PHP-CS-Fixer.'.$fixers[0] : 'PHP-CS-Fixer';

        return \sprintf(
            '::error %s,title=%s::%s',
            $properties,
            self::escapeProperty($title),
            self::escapeData('Found violation(s) of type: '.implode(', ', $fixers)),
        );
    }

    private static function escapeData(string $value): string
    {
        return strtr($value, [
            '%' => '%25',
            "\r" => '%0D',
            "\n" => '%0A',
        ]);
    }

    private static function escapeProperty(string $value): string
    {
        return strtr($value, [
            '%' => '%25',
            "\r" => '%0D',
            "\n" => '%0A',
            ':' => '%3A',
            ',' => '%2C',
        ]);
    }
}
