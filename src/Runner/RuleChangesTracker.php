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

namespace PhpCsFixer\Runner;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;

/**
 * Tracks which lines of the original code were changed by which rule, while the rules are applied one after another.
 *
 * Every line of the current code remembers the line of the original code it comes from, so that the changes of a rule
 * can be reported with the line numbers of the original code, even when earlier rules added or removed lines.
 *
 * A removed line is reported at its own line.  An insertion is reported at the line before it (or at the first line
 * when it is the first thing of the code), and lines added in place of removed ones belong to the last removed line.
 *
 * @readonly
 *
 * @internal
 *
 * @no-named-arguments Parameter names are not covered by the backward compatibility promise.
 */
final class RuleChangesTracker
{
    private const END_OF_CODE = -1;

    /** A line of the original code that was removed or replaced. */
    private const REMOVED_LINE = 1;

    /** A line of the original code that was chosen to show where lines were inserted, because nothing was removed. */
    private const INSERTION_ANCHOR = 2;

    private string $originalCode;

    private string $currentCode;

    /**
     * Line of the original code for every line of the current code; empty while the current code is the original one.
     *
     * @var list<int>
     */
    private array $origins;

    /**
     * Lines of the original code changed by a rule, for every rule.
     *
     * @var array<string, array<int, int>> the kind of the change for every line
     */
    private array $touched;

    /**
     * @param list<int>                      $origins
     * @param array<string, array<int, int>> $touched
     */
    private function __construct(string $originalCode, string $currentCode, array $origins, array $touched)
    {
        $this->originalCode = $originalCode;
        $this->currentCode = $currentCode;
        $this->origins = $origins;
        $this->touched = $touched;
    }

    public static function start(string $originalCode): self
    {
        return new self($originalCode, $originalCode, [], []);
    }

    public function withRule(string $rule, string $codeAfter): self
    {
        if ($codeAfter === $this->currentCode) {
            return $this;
        }

        $comparison = self::compare($this->currentCode, $codeAfter, $this->origins);

        $touched = $this->touched;
        $touched[$rule] ??= [];
        foreach ($comparison['touched'] as $line => $kind) {
            $touched[$rule][$line] = min($touched[$rule][$line] ?? $kind, $kind);
        }

        return new self($this->originalCode, $codeAfter, $comparison['origins'], $touched);
    }

    /**
     * Returns, for every rule, the ranges of the original code it changed.  Changes that are not part of the
     * difference between the original code and the final one, because another rule reverted them, are left out.
     *
     * @return array<string, list<array{begin: int, end: int}>>
     */
    public function resolve(string $finalCode): array
    {
        $changedInTheEnd = self::compare($this->originalCode, $finalCode, [])['touched'];

        // The line chosen for an insertion is only a convention, and the insertion can end up merged with a change
        // next to it, so it is enough for such a line to be next to a change of the end.
        $nextToChangeInTheEnd = [];
        foreach (array_keys($changedInTheEnd) as $line) {
            $nextToChangeInTheEnd[$line - 1] = true;
            $nextToChangeInTheEnd[$line + 1] = true;
        }

        $ranges = [];

        foreach ($this->touched as $rule => $lines) {
            $linesChangedInTheEnd = [];

            foreach ($lines as $line => $kind) {
                if (isset($changedInTheEnd[$line]) || (self::INSERTION_ANCHOR === $kind && isset($nextToChangeInTheEnd[$line]))) {
                    $linesChangedInTheEnd[$line] = true;
                }
            }

            if ([] === $linesChangedInTheEnd) {
                continue;
            }

            ksort($linesChangedInTheEnd);

            $ranges[$rule] = self::toRanges(array_keys($linesChangedInTheEnd));
        }

        return $ranges;
    }

    /**
     * @param list<int> $origins
     *
     * @return array{origins: list<int>, touched: array<int, int>}
     */
    private static function compare(string $from, string $to, array $origins): array
    {
        $newOrigins = [];
        $touched = [];

        $currentIndex = 0;
        $previousKeptOrigin = null;
        $removedOrigins = [];
        $addedCount = 0;

        $entries = (new Differ(new StrictUnifiedDiffOutputBuilder(['fromFile' => 'Original', 'toFile' => 'New'])))->diffToArray($from, $to);
        $entries[] = ['', self::END_OF_CODE];

        foreach ($entries as [$line, $type]) {
            if ($type > Differ::REMOVED) { // warnings about line endings, not lines of the code
                continue;
            }

            if (Differ::REMOVED === $type) {
                $removedOrigins[] = $origins[$currentIndex] ?? $currentIndex + 1;
                ++$currentIndex;

                continue;
            }

            if (Differ::ADDED === $type) {
                ++$addedCount;

                continue;
            }

            // a kept line, or the end of the code, finishes the hunk of removed and added lines before it
            if ([] !== $removedOrigins || 0 !== $addedCount) {
                $anchor = [] !== $removedOrigins ? $removedOrigins[\count($removedOrigins) - 1] : ($previousKeptOrigin ?? 1);

                foreach ($removedOrigins as $origin) {
                    $touched[$origin] = self::REMOVED_LINE;
                }

                if ([] === $removedOrigins) {
                    $touched[$anchor] = self::INSERTION_ANCHOR;
                }

                for ($i = 0; $i < $addedCount; ++$i) {
                    $newOrigins[] = $anchor;
                }

                $removedOrigins = [];
                $addedCount = 0;
            }

            if (self::END_OF_CODE === $type) {
                break;
            }

            $previousKeptOrigin = $origins[$currentIndex] ?? $currentIndex + 1;
            ++$currentIndex;

            $newOrigins[] = $previousKeptOrigin;
        }

        return ['origins' => $newOrigins, 'touched' => $touched];
    }

    /**
     * @param non-empty-list<int> $lines sorted, unique
     *
     * @return list<array{begin: int, end: int}>
     */
    private static function toRanges(array $lines): array
    {
        $ranges = [];
        $begin = $lines[0];
        $end = $lines[0];

        foreach (\array_slice($lines, 1) as $line) {
            if ($line === $end + 1) {
                $end = $line;

                continue;
            }

            $ranges[] = ['begin' => $begin, 'end' => $end];
            $begin = $line;
            $end = $line;
        }

        $ranges[] = ['begin' => $begin, 'end' => $end];

        return $ranges;
    }
}
