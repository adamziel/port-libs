<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class BlobMerge
{
    public const STYLE_MERGE = 'merge';
    public const STYLE_DIFF3 = 'diff3';
    public const PICK_ANCESTOR = 'ancestor';
    public const PICK_OURS = 'ours';
    public const PICK_THEIRS = 'theirs';

    public static function mergeText(
        string $base,
        string $ours,
        string $theirs,
        string $style = self::STYLE_MERGE,
        string $baseLabel = 'base',
        string $oursLabel = 'ours',
        string $theirsLabel = 'theirs',
        int $markerSize = 7,
    ): BlobMergeResult {
        if (!in_array($style, [self::STYLE_MERGE, self::STYLE_DIFF3], true)) {
            throw new \InvalidArgumentException("Unsupported text merge style: {$style}");
        }
        if ($markerSize < 1) {
            throw new \InvalidArgumentException('Conflict marker size must be positive');
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

            $start = min($ourHunk['start'], $theirHunk['start']);
            $end = max($ourHunk['end'], $theirHunk['end']);
            self::appendBase($merged, $baseLines, $basePosition, $start);
            $merged[] = str_repeat('<', $markerSize) . ' ' . $oursLabel . $newline;
            array_push($merged, ...self::applyHunksInRange($baseLines, [$ourHunk], $start, $end));
            if ($style === self::STYLE_DIFF3) {
                $merged[] = str_repeat('|', $markerSize) . ' ' . $baseLabel . $newline;
                self::appendBase($merged, $baseLines, $start, $end);
            }
            $merged[] = str_repeat('=', $markerSize) . $newline;
            array_push($merged, ...self::applyHunksInRange($baseLines, [$theirHunk], $start, $end));
            $merged[] = str_repeat('>', $markerSize) . ' ' . $theirsLabel . $newline;
            $basePosition = $end;
            $ourIndex++;
            $theirIndex++;
            $conflicts++;
        }

        self::appendBase($merged, $baseLines, $basePosition, count($baseLines));

        return new BlobMergeResult(implode('', $merged), $conflicts === 0 ? BlobMergeResult::RESOLUTION_COMPLETE : BlobMergeResult::RESOLUTION_CONFLICT, $conflicts);
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
        if ($left['end'] <= $right['start']) {
            return !($left['start'] === $left['end'] && $right['start'] === $right['end'] && $left['start'] === $right['start']);
        }

        return false;
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
}
