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
     * Resolves the lines from the first to the last change of the first chunk of the diff, in the line numbering of
     * the original code.  Unlike {@see self::resolve()}, the range does not include the unchanged lines around the changes.
     *
     * @return array{begin: int, end: int} zeros when the diff has no chunks
     */
    public function resolveFirstChunkChanges(string $diff): array
    {
        $diffs = array_values($this->diffParser->parse($diff));

        if (!isset($diffs[0])) {
            return ['begin' => 0, 'end' => 0];
        }

        $firstDiff = $diffs[0];
        $firstChunk = \Closure::bind(static fn (Diff $diff) => array_shift($diff->chunks), null, $firstDiff)($firstDiff);

        if (!$firstChunk instanceof Chunk) {
            return ['begin' => 0, 'end' => 0];
        }

        $start = \Closure::bind(static fn (Chunk $chunk): int => $chunk->start, null, $firstChunk)($firstChunk);
        $startRange = \Closure::bind(static fn (Chunk $chunk): int => $chunk->startRange, null, $firstChunk)($firstChunk);
        $lines = \Closure::bind(static fn (Chunk $chunk): array => $chunk->lines, null, $firstChunk)($firstChunk);

        $lastOriginalLine = max(1, $startRange > 0 ? $start + $startRange - 1 : $start);
        $originalLine = $start;
        $begin = null;
        $end = null;

        foreach ($lines as $line) {
            $type = \Closure::bind(static fn (Line $line): int => $line->type, null, $line)($line);

            if (self::isNoNewlineMarker($line)) {
                continue;
            }

            if (Line::UNCHANGED === $type) {
                ++$originalLine;

                continue;
            }

            $begin ??= $originalLine;

            if (Line::REMOVED === $type) {
                $end = $originalLine;
                ++$originalLine;

                continue;
            }

            // added lines do not exist in the original code, so they do not advance the original line counter,
            // and they extend the range only when they are not directly attached to the previous change
            if (Line::ADDED === $type && (null === $end || $end < $originalLine - 1)) {
                $end = $originalLine;
            }
        }

        if (null === $begin || null === $end) {
            return ['begin' => 0, 'end' => 0];
        }

        return ['begin' => min($begin, $lastOriginalLine), 'end' => min($end, $lastOriginalLine)];
    }

    /**
     * The parser reports the "\ No newline at end of file" marker as an unchanged line, but it is not a line of the code.
     */
    private static function isNoNewlineMarker(Line $line): bool
    {
        $content = \Closure::bind(static fn (Line $line): string => $line->content, null, $line)($line);

        return str_starts_with($content, '\ No newline at end of file');
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
