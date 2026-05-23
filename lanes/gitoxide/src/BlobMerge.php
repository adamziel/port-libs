<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class BlobMerge
{
    public const STYLE_MERGE = 'merge';
    public const STYLE_DIFF3 = 'diff3';
    public const STYLE_ZDIFF3 = 'zdiff3';
    public const STYLE_ZEALOUS_DIFF3 = 'zealous-diff3';
    public const STYLE_UNION = 'union';
    public const STYLE_OURS = 'ours';
    public const STYLE_THEIRS = 'theirs';
    public const PICK_ANCESTOR = 'ancestor';
    public const PICK_OURS = 'ours';
    public const PICK_THEIRS = 'theirs';

    public static function mergeText(
        string $base,
        string $ours,
        string $theirs,
        string $style = self::STYLE_MERGE,
        ?string $baseLabel = 'base',
        ?string $oursLabel = 'ours',
        ?string $theirsLabel = 'theirs',
        int $markerSize = 7,
    ): BlobMergeResult {
        if ($style === self::STYLE_ZDIFF3) {
            $style = self::STYLE_ZEALOUS_DIFF3;
        }
        if (!in_array($style, [self::STYLE_MERGE, self::STYLE_DIFF3, self::STYLE_ZEALOUS_DIFF3, self::STYLE_UNION, self::STYLE_OURS, self::STYLE_THEIRS], true)) {
            throw new \InvalidArgumentException("Unsupported text merge style: {$style}");
        }
        if ($markerSize < 1) {
            throw new \InvalidArgumentException('Conflict marker size must be positive');
        }
        if ($markerSize > 255) {
            throw new \InvalidArgumentException('Conflict marker size must fit in one byte');
        }

        if ($ours === $theirs) {
            return new BlobMergeResult($ours, BlobMergeResult::RESOLUTION_COMPLETE, 0);
        }
        if ($ours === $base) {
            return new BlobMergeResult($theirs, BlobMergeResult::RESOLUTION_COMPLETE, 0);
        }
        if ($theirs === $base) {
            return new BlobMergeResult($ours, BlobMergeResult::RESOLUTION_COMPLETE, 0);
        }

        $baseLines = self::splitLines($base);
        $ourHunks = self::changedHunks($baseLines, self::splitLines($ours));
        $theirHunks = self::changedHunks($baseLines, self::splitLines($theirs));

        $merged = [];
        $basePosition = 0;
        $ourIndex = 0;
        $theirIndex = 0;
        $conflicts = 0;
        $autoResolvedConflicts = 0;
        $newline = self::detectLineEnding($base . $ours . $theirs);

        while ($ourIndex < count($ourHunks) || $theirIndex < count($theirHunks)) {
            $ourHunk = $ourHunks[$ourIndex] ?? null;
            $theirHunk = $theirHunks[$theirIndex] ?? null;

            if ($theirHunk === null || ($ourHunk !== null && self::hunkComesBefore($ourHunk, $theirHunk))) {
                self::appendBase($merged, $baseLines, $basePosition, $ourHunk['start']);
                array_push($merged, ...$ourHunk['replacement']);
                $basePosition = $ourHunk['end'];
                $ourIndex++;
                continue;
            }

            if ($ourHunk === null || self::hunkComesBefore($theirHunk, $ourHunk)) {
                self::appendBase($merged, $baseLines, $basePosition, $theirHunk['start']);
                array_push($merged, ...$theirHunk['replacement']);
                $basePosition = $theirHunk['end'];
                $theirIndex++;
                continue;
            }

            if ($ourHunk['start'] === $theirHunk['start']
                && $ourHunk['end'] === $theirHunk['end']
                && $ourHunk['replacement'] === $theirHunk['replacement']) {
                self::appendBase($merged, $baseLines, $basePosition, $ourHunk['start']);
                array_push($merged, ...$ourHunk['replacement']);
                $basePosition = $ourHunk['end'];
                $ourIndex++;
                $theirIndex++;
                continue;
            }

            if (self::isContextDuplicateInsertionBeforeDeletion($ourHunk, $theirHunk, $baseLines, $basePosition)) {
                self::appendBase($merged, $baseLines, $basePosition, $ourHunk['start']);
                array_push($merged, ...$ourHunk['replacement']);
                $basePosition = $ourHunk['end'];
                $ourIndex++;
                continue;
            }

            if (self::isContextDuplicateInsertionBeforeDeletion($theirHunk, $ourHunk, $baseLines, $basePosition)) {
                self::appendBase($merged, $baseLines, $basePosition, $theirHunk['start']);
                array_push($merged, ...$theirHunk['replacement']);
                $basePosition = $theirHunk['end'];
                $theirIndex++;
                continue;
            }

            $ourConflictHunks = [];
            $theirConflictHunks = [];
            $start = min($ourHunk['start'], $theirHunk['start']);
            $end = max($ourHunk['end'], $theirHunk['end']);
            do {
                $changed = false;
                while ($ourIndex < count($ourHunks) && self::hunkTouchesRange($ourHunks[$ourIndex], $start, $end)) {
                    $hunk = $ourHunks[$ourIndex];
                    $ourConflictHunks[] = $hunk;
                    $start = min($start, $hunk['start']);
                    $end = max($end, $hunk['end']);
                    $ourIndex++;
                    $changed = true;
                }
                while ($theirIndex < count($theirHunks) && self::hunkTouchesRange($theirHunks[$theirIndex], $start, $end)) {
                    $hunk = $theirHunks[$theirIndex];
                    $theirConflictHunks[] = $hunk;
                    $start = min($start, $hunk['start']);
                    $end = max($end, $hunk['end']);
                    $theirIndex++;
                    $changed = true;
                }
            } while ($changed);

            self::appendBase($merged, $baseLines, $basePosition, $start);
            $ourLines = self::applyHunksInRange($baseLines, $ourConflictHunks, $start, $end);
            $theirLines = self::applyHunksInRange($baseLines, $theirConflictHunks, $start, $end);
            if ($style === self::STYLE_OURS || $style === self::STYLE_THEIRS) {
                array_push($merged, ...($style === self::STYLE_OURS ? $ourLines : $theirLines));
                $basePosition = $end;
                $autoResolvedConflicts++;
                continue;
            }

            if ($style === self::STYLE_UNION) {
                $contracted = self::contractCommonLines($ourLines, $theirLines);
                $conflictNewline = self::detectConflictLineEnding(
                    $contracted['prefix'],
                    [],
                    $contracted['left'],
                    $newline,
                );
                array_push($merged, ...$contracted['prefix'], ...$contracted['left']);
                self::assureEndsWithNewline($merged, $conflictNewline);
                array_push($merged, ...$contracted['right']);
                if ($contracted['suffix'] !== []) {
                    self::assureEndsWithNewline($merged, $conflictNewline);
                    array_push($merged, ...$contracted['suffix']);
                }
                $basePosition = $end;
                if ($contracted['left'] !== [] || $contracted['right'] !== []) {
                    $autoResolvedConflicts++;
                }
                continue;
            }

            if ($style === self::STYLE_MERGE || $style === self::STYLE_ZEALOUS_DIFF3) {
                $contracted = self::contractCommonLines($ourLines, $theirLines);
                array_push($merged, ...$contracted['prefix']);
                $ourConflictLines = $contracted['left'];
                $theirConflictLines = $contracted['right'];
                $suffixLines = $contracted['suffix'];
                $conflictNewline = self::detectConflictLineEnding(
                    $contracted['prefix'],
                    array_slice($baseLines, $basePosition, max(0, $start - $basePosition)),
                    $ourConflictLines,
                    $newline,
                );
            } else {
                $ourConflictLines = $ourLines;
                $theirConflictLines = $theirLines;
                $suffixLines = [];
                $conflictNewline = self::detectConflictLineEnding(
                    [],
                    array_slice($baseLines, $basePosition, max(0, $start - $basePosition)),
                    $ourConflictLines,
                    $newline,
                );
            }

            if ($ourConflictLines === [] && $theirConflictLines === []) {
                array_push($merged, ...$suffixLines);
                $basePosition = $end;
                continue;
            }

            self::assureEndsWithNewline($merged, $conflictNewline);
            $merged[] = self::conflictMarker('<', $markerSize, $oursLabel, $conflictNewline);
            array_push($merged, ...$ourConflictLines);
            if ($style === self::STYLE_DIFF3 || $style === self::STYLE_ZEALOUS_DIFF3) {
                $ancestorNewline = self::detectLineEndingFromLines(array_slice($baseLines, $start, max(0, $end - $start))) ?? $conflictNewline;
                self::assureEndsWithNewline($merged, $ancestorNewline);
                $merged[] = self::conflictMarker('|', $markerSize, $baseLabel, $ancestorNewline);
                self::appendBase($merged, $baseLines, $start, $end);
            }
            self::assureEndsWithNewline($merged, $conflictNewline);
            $merged[] = str_repeat('=', $markerSize) . $conflictNewline;
            array_push($merged, ...$theirConflictLines);
            self::assureEndsWithNewline($merged, $conflictNewline);
            $merged[] = self::conflictMarker('>', $markerSize, $theirsLabel, $conflictNewline);
            array_push($merged, ...$suffixLines);
            $basePosition = $end;
            $conflicts++;
        }

        self::appendBase($merged, $baseLines, $basePosition, count($baseLines));

        $resolution = match (true) {
            $conflicts > 0 => BlobMergeResult::RESOLUTION_CONFLICT,
            $autoResolvedConflicts > 0 => BlobMergeResult::RESOLUTION_AUTO_RESOLVED,
            default => BlobMergeResult::RESOLUTION_COMPLETE,
        };

        return new BlobMergeResult(implode('', $merged), $resolution, $conflicts);
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     * @return array{prefix:list<string>,left:list<string>,right:list<string>,suffix:list<string>}
     */
    private static function contractCommonLines(array $left, array $right): array
    {
        $prefixLength = 0;
        $maxPrefix = min(count($left), count($right));
        while ($prefixLength < $maxPrefix && $left[$prefixLength] === $right[$prefixLength]) {
            $prefixLength++;
        }

        $leftEnd = count($left);
        $rightEnd = count($right);
        $suffix = [];
        while ($leftEnd > $prefixLength
            && $rightEnd > $prefixLength
            && $left[$leftEnd - 1] === $right[$rightEnd - 1]) {
            array_unshift($suffix, $left[$leftEnd - 1]);
            $leftEnd--;
            $rightEnd--;
        }

        return [
            'prefix' => array_slice($left, 0, $prefixLength),
            'left' => array_slice($left, $prefixLength, $leftEnd - $prefixLength),
            'right' => array_slice($right, $prefixLength, $rightEnd - $prefixLength),
            'suffix' => $suffix,
        ];
    }

    private static function conflictMarker(string $marker, int $markerSize, ?string $label, string $newline): string
    {
        return str_repeat($marker, $markerSize) . ($label === null ? '' : ' ' . $label) . $newline;
    }

    public static function mergeBinary(string $base, string $ours, string $theirs, ?string $resolveWith = null): BlobMergeResult
    {
        if ($ours === $theirs) {
            return new BlobMergeResult($ours, BlobMergeResult::RESOLUTION_COMPLETE, 0);
        }
        if ($ours === $base) {
            return new BlobMergeResult($theirs, BlobMergeResult::RESOLUTION_COMPLETE, 0);
        }
        if ($theirs === $base) {
            return new BlobMergeResult($ours, BlobMergeResult::RESOLUTION_COMPLETE, 0);
        }

        return match ($resolveWith) {
            self::PICK_ANCESTOR => new BlobMergeResult($base, BlobMergeResult::RESOLUTION_AUTO_RESOLVED, 0),
            self::PICK_OURS => new BlobMergeResult($ours, BlobMergeResult::RESOLUTION_AUTO_RESOLVED, 0),
            self::PICK_THEIRS => new BlobMergeResult($theirs, BlobMergeResult::RESOLUTION_AUTO_RESOLVED, 0),
            null => new BlobMergeResult($ours, BlobMergeResult::RESOLUTION_CONFLICT, 1),
            default => throw new \InvalidArgumentException("Unsupported binary merge pick: {$resolveWith}"),
        };
    }

    /**
     * @return list<string>
     */
    private static function splitLines(string $text): array
    {
        if ($text === '') {
            return [];
        }
        preg_match_all('/[^\n]*\n|[^\n]+/', $text, $matches);

        return $matches[0];
    }

    /**
     * @param list<string> $base
     * @param list<string> $side
     * @return list<array{start:int,end:int,replacement:list<string>}>
     */
    private static function changedHunks(array $base, array $side): array
    {
        if ($base === $side) {
            return [];
        }
        if (count($base) * max(1, count($side)) > 250000) {
            return [['start' => 0, 'end' => count($base), 'replacement' => $side]];
        }

        $matrix = array_fill(0, count($base) + 1, array_fill(0, count($side) + 1, 0));
        for ($i = count($base) - 1; $i >= 0; $i--) {
            for ($j = count($side) - 1; $j >= 0; $j--) {
                $matrix[$i][$j] = $base[$i] === $side[$j]
                    ? $matrix[$i + 1][$j + 1] + 1
                    : max($matrix[$i + 1][$j], $matrix[$i][$j + 1]);
            }
        }

        $hunks = [];
        $start = null;
        $end = 0;
        $replacement = [];
        $baseIndex = 0;
        $sideIndex = 0;
        $flush = static function () use (&$hunks, &$start, &$end, &$replacement): void {
            if ($start === null) {
                return;
            }
            if ($end < $start) {
                $end = $start;
            }
            $hunks[] = ['start' => $start, 'end' => $end, 'replacement' => $replacement];
            $start = null;
            $end = 0;
            $replacement = [];
        };

        while ($baseIndex < count($base) && $sideIndex < count($side)) {
            if ($base[$baseIndex] === $side[$sideIndex]) {
                $flush();
                $baseIndex++;
                $sideIndex++;
                continue;
            }
            $start ??= $baseIndex;
            if ($matrix[$baseIndex + 1][$sideIndex] >= $matrix[$baseIndex][$sideIndex + 1]) {
                $baseIndex++;
                $end = $baseIndex;
            } else {
                $replacement[] = $side[$sideIndex];
                $sideIndex++;
            }
        }

        if ($baseIndex < count($base) || $sideIndex < count($side)) {
            $start ??= $baseIndex;
            while ($baseIndex < count($base)) {
                $baseIndex++;
                $end = $baseIndex;
            }
            while ($sideIndex < count($side)) {
                $replacement[] = $side[$sideIndex];
                $sideIndex++;
            }
        }
        $flush();

        return $hunks;
    }

    /**
     * @param array{start:int,end:int,replacement:list<string>} $left
     * @param array{start:int,end:int,replacement:list<string>} $right
     */
    private static function hunkComesBefore(array $left, array $right): bool
    {
        return $left['end'] < $right['start'];
    }

    /**
     * @param array{start:int,end:int,replacement:list<string>} $hunk
     */
    private static function hunkTouchesRange(array $hunk, int $start, int $end): bool
    {
        return $hunk['end'] >= $start && $hunk['start'] <= $end;
    }

    /**
     * @param array{start:int,end:int,replacement:list<string>} $insertion
     * @param array{start:int,end:int,replacement:list<string>} $deletion
     * @param list<string> $base
     */
    private static function isContextDuplicateInsertionBeforeDeletion(array $insertion, array $deletion, array $base, int $basePosition): bool
    {
        if ($insertion['start'] !== $insertion['end']
            || $deletion['start'] !== $insertion['start']
            || $deletion['replacement'] !== []
            || $basePosition >= $insertion['start']
            || $insertion['replacement'] === []) {
            return false;
        }

        return $insertion['replacement'] === array_slice($base, $basePosition, $insertion['start'] - $basePosition);
    }

    /**
     * @param list<string> $out
     * @param list<string> $base
     */
    private static function appendBase(array &$out, array $base, int $start, int $end): void
    {
        for ($i = $start; $i < $end; $i++) {
            $out[] = $base[$i];
        }
    }

    /**
     * @param list<string> $out
     */
    private static function assureEndsWithNewline(array &$out, string $newline): void
    {
        if ($out === []) {
            return;
        }
        $last = $out[array_key_last($out)];
        if ($last === '' || str_ends_with($last, "\n")) {
            return;
        }

        $out[] = $newline;
    }

    /**
     * @param list<string> $base
     * @param list<array{start:int,end:int,replacement:list<string>}> $hunks
     * @return list<string>
     */
    private static function applyHunksInRange(array $base, array $hunks, int $start, int $end): array
    {
        $out = [];
        $position = $start;
        foreach ($hunks as $hunk) {
            self::appendBase($out, $base, $position, $hunk['start']);
            array_push($out, ...$hunk['replacement']);
            $position = $hunk['end'];
        }
        self::appendBase($out, $base, $position, $end);

        return $out;
    }

    private static function detectLineEnding(string $text): string
    {
        $rn = strpos($text, "\r\n");
        $n = strpos($text, "\n");

        return $rn !== false && $rn === $n - 1 ? "\r\n" : "\n";
    }

    /**
     * @param list<string> $front
     * @param list<string> $ancestorBefore
     * @param list<string> $ours
     */
    private static function detectConflictLineEnding(array $front, array $ancestorBefore, array $ours, string $fallback): string
    {
        foreach ([$front, $ancestorBefore, $ours] as $lines) {
            $detected = self::detectLineEndingFromLines($lines);
            if ($detected !== null) {
                return $detected;
            }
        }

        return $fallback;
    }

    /**
     * @param list<string> $lines
     */
    private static function detectLineEndingFromLines(array $lines): ?string
    {
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (str_ends_with($lines[$i], "\n")) {
                return str_ends_with($lines[$i], "\r\n") ? "\r\n" : "\n";
            }
            if ($i > 0 && str_ends_with($lines[$i - 1], "\n")) {
                return str_ends_with($lines[$i - 1], "\r\n") ? "\r\n" : "\n";
            }
        }

        return null;
    }
}
