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
            $lines = $this->changedLinesResolver->resolveFirstChunkChanges($fixResult['diff']);
            $location = 'file='.self::escapeProperty($filePath);
            if ($lines['begin'] > 0) {
                $location .= ',line='.$lines['begin'];
                if ($lines['end'] > $lines['begin']) {
                    $location .= ',endLine='.$lines['end'];
                }
            }

            foreach ($fixResult['appliedFixers'] as $appliedFixer) {
                $commands[] = \sprintf(
                    '::error %s,title=%s::%s',
                    $location,
                    self::escapeProperty('PHP-CS-Fixer.'.$appliedFixer),
                    self::escapeData('Found violation(s) of type: '.$appliedFixer),
                );
            }
        }

        $result = implode("\n", $commands);

        return $reportSummary->isDecoratedOutput() ? OutputFormatter::escape($result) : $result;
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
