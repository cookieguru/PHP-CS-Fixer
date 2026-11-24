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

use SebastianBergmann\Diff\Chunk;
use SebastianBergmann\Diff\Diff;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser;

/**
 * Resolves the range of lines affected by a diff, based on its first chunk.
 *
 * @readonly
 *
 * @internal
 *
 * @no-named-arguments Parameter names are not covered by the backward compatibility promise.
 */
final class ChangedLinesResolver
{
    private Parser $diffParser;

    public function __construct()
    {
        $this->diffParser = new Parser();
    }

    /**
     * @return array{begin: int, end: int} zeros when the diff has no chunks
     */
    public function resolve(string $diff): array
    {
        $diffs = array_values( // before PHPUnit 13, result of `->parse(...)` is array and not list
            $this->diffParser->parse($diff),
        );

        if (isset($diffs[0])) {
            $firstDiff = $diffs[0];

            $firstChunk = \Closure::bind(static fn (Diff $diff) => array_shift($diff->chunks), null, $firstDiff)($firstDiff);

            if ($firstChunk instanceof Chunk) {
                return self::getBeginEndForDiffChunk($firstChunk);
            }
        }

        return ['begin' => 0, 'end' => 0];
    }

    /**
     * Resolves every contiguous group of changed lines of the diff, in the line numbering of the original code.
     *
     * Removed lines are reported at their own position. An insertion is reported at the original line it was inserted
     * before (or at the last original line of its chunk, when inserted at the very end).
     *
     * @return list<array{begin: int, end: int}> empty when the diff has no changes
     */
    public function resolveAll(string $diff): array
    {
        $groups = [];

        foreach ($this->diffParser->parse($diff) as $parsedDiff) {
            $chunks = \Closure::bind(static fn (Diff $diff): array => $diff->chunks, null, $parsedDiff)($parsedDiff);

            foreach ($chunks as $chunk) {
                array_push($groups, ...self::getChangeGroupsForDiffChunk($chunk));
            }
        }

        return $groups;
    }

    /**
     * @return list<array{begin: int, end: int}>
     */
    private static function getChangeGroupsForDiffChunk(Chunk $chunk): array
    {
        $start = \Closure::bind(static fn (Chunk $chunk): int => $chunk->start, null, $chunk)($chunk);
        $startRange = \Closure::bind(static fn (Chunk $chunk): int => $chunk->startRange, null, $chunk)($chunk);
        $lines = \Closure::bind(static fn (Chunk $chunk): array => $chunk->lines, null, $chunk)($chunk);

        $lastOriginalLine = max(1, $startRange > 0 ? $start + $startRange - 1 : $start);
        $originalLine = $start;
        $groups = [];
        $group = null;

        foreach ($lines as $line) {
            $type = \Closure::bind(static fn (Line $line): int => $line->type, null, $line)($line);

            if (Line::UNCHANGED === $type) {
                if (null !== $group) {
                    $groups[] = $group;
                    $group = null;
                }
                ++$originalLine;

                continue;
            }

            if (Line::REMOVED === $type) {
                $group ??= ['begin' => $originalLine, 'end' => $originalLine];
                $group['end'] = $originalLine;
                ++$originalLine;

                continue;
            }

            if (Line::ADDED === $type) {
                // added lines do not exist in the original code, so they do not advance the original line counter
                $group ??= ['begin' => $originalLine, 'end' => $originalLine];
            }
        }

        if (null !== $group) {
            $groups[] = $group;
        }

        return array_map(
            static fn (array $group): array => [
                'begin' => min($group['begin'], $lastOriginalLine),
                'end' => min($group['end'], $lastOriginalLine),
            ],
            $groups,
        );
    }

    /**
     * @return array{begin: int, end: int}
     */
    private static function getBeginEndForDiffChunk(Chunk $chunk): array
    {
        $start = \Closure::bind(static fn (Chunk $chunk): int => $chunk->start, null, $chunk)($chunk);
        $startRange = \Closure::bind(static fn (Chunk $chunk): int => $chunk->startRange, null, $chunk)($chunk);
        $lines = \Closure::bind(static fn (Chunk $chunk): array => $chunk->lines, null, $chunk)($chunk);

        \assert(\count($lines) > 0);

        $firstModifiedLineOffset = array_find_key($lines, static function (Line $line): bool {
            $type = \Closure::bind(static fn (Line $line): int => $line->type, null, $line)($line);

            return Line::UNCHANGED !== $type;
        });
        \assert(\is_int($firstModifiedLineOffset));

        return [
            // offset the start by where the first line is actually modified
            'begin' => $start + $firstModifiedLineOffset,
            // it's not where last modification takes place, only where diff (with --context) ends
            'end' => $start + $startRange,
        ];
    }
}
