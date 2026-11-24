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
 * One annotation is created for every range of lines changed by a rule, so that every annotation names the rule that
 * changed those lines.  The ranges come from the `ruleChanges` of the result of the file, which the runner collects
 * for this format.  Without them there are no locations, and one file-level annotation per applied rule is created.
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
    public function getFormat(): string
    {
        return 'github';
    }

    public function generate(ReportSummary $reportSummary): string
    {
        $commands = [];

        foreach ($reportSummary->getChanged() as $filePath => $fixResult) {
            $locations = [];

            foreach ($fixResult['ruleChanges'] ?? [] as $rule => $ranges) {
                foreach ($ranges as $range) {
                    $locations[] = [$range, $rule];
                }
            }

            if ([] === $locations) {
                foreach ($fixResult['appliedFixers'] as $appliedFixer) {
                    $commands[] = self::createCommand($filePath, null, $appliedFixer);
                }

                continue;
            }

            usort($locations, static fn (array $a, array $b): int => [$a[0]['begin'], $a[0]['end'], $a[1]] <=> [$b[0]['begin'], $b[0]['end'], $b[1]]);

            foreach ($locations as [$range, $rule]) {
                $commands[] = self::createCommand($filePath, $range, (string) $rule);
            }
        }

        $result = implode("\n", $commands);

        return $reportSummary->isDecoratedOutput() ? OutputFormatter::escape($result) : $result;
    }

    /**
     * @param null|array{begin: int, end: int} $lines
     */
    private static function createCommand(string $filePath, ?array $lines, string $rule): string
    {
        $properties = 'file='.self::escapeProperty($filePath);

        if (null !== $lines) {
            $properties .= ',line='.$lines['begin'];
            if ($lines['end'] > $lines['begin']) {
                $properties .= ',endLine='.$lines['end'];
            }
        }

        return \sprintf(
            '::error %s,title=%s::%s',
            $properties,
            self::escapeProperty('PHP-CS-Fixer.'.$rule),
            self::escapeData('Found violation(s) of type: '.$rule),
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
