<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class LineDiffer
{
    private const DEFAULT_MAX_QUADRATIC_CELLS = 250_000;

    public function __construct(
        private readonly int $maxQuadraticCells = self::DEFAULT_MAX_QUADRATIC_CELLS,
    ) {
    }

    /**
     * @param list<string> $old
     * @param list<string> $new
     * @return list<array{op:string, old?:int, new?:int}>
     */
    public function diff(array $old, array $new): array
    {
        return $this->diffRange($old, $new, 0, count($old), 0, count($new));
    }

    /**
     * @param list<string> $old
     * @param list<string> $new
     * @return list<array{op:string, old?:int, new?:int}>
     */
    private function diffRange(array $old, array $new, int $oldStart, int $oldEnd, int $newStart, int $newEnd): array
    {
        $ops = [];

        while ($oldStart < $oldEnd && $newStart < $newEnd && $old[$oldStart] === $new[$newStart]) {
            $ops[] = ['op' => '=', 'old' => $oldStart, 'new' => $newStart];
            $oldStart++;
            $newStart++;
        }

        $suffix = [];
        while ($oldStart < $oldEnd && $newStart < $newEnd && $old[$oldEnd - 1] === $new[$newEnd - 1]) {
            $oldEnd--;
            $newEnd--;
            $suffix[] = ['op' => '=', 'old' => $oldEnd, 'new' => $newEnd];
        }

        if ($oldStart === $oldEnd || $newStart === $newEnd) {
            return array_merge(
                $ops,
                $this->oneSidedOps($oldStart, $oldEnd, $newStart, $newEnd),
                array_reverse($suffix),
            );
        }

        if ($this->canUseExactLcs($oldStart, $oldEnd, $newStart, $newEnd)) {
            return array_merge(
                $ops,
                $this->exactDiffRange($old, $new, $oldStart, $oldEnd, $newStart, $newEnd),
                array_reverse($suffix),
            );
        }

        $anchors = $this->uniqueCommonAnchors($old, $new, $oldStart, $oldEnd, $newStart, $newEnd);
        if ($anchors === []) {
            return array_merge(
                $ops,
                $this->oneSidedOps($oldStart, $oldEnd, $newStart, $newEnd),
                array_reverse($suffix),
            );
        }

        $cursorOld = $oldStart;
        $cursorNew = $newStart;
        foreach ($anchors as [$anchorOld, $anchorNew]) {
            foreach ($this->diffRange($old, $new, $cursorOld, $anchorOld, $cursorNew, $anchorNew) as $op) {
                $ops[] = $op;
            }
            $ops[] = ['op' => '=', 'old' => $anchorOld, 'new' => $anchorNew];
            $cursorOld = $anchorOld + 1;
            $cursorNew = $anchorNew + 1;
        }

        foreach ($this->diffRange($old, $new, $cursorOld, $oldEnd, $cursorNew, $newEnd) as $op) {
            $ops[] = $op;
        }
        foreach (array_reverse($suffix) as $op) {
            $ops[] = $op;
        }

        return $ops;
    }

    private function canUseExactLcs(int $oldStart, int $oldEnd, int $newStart, int $newEnd): bool
    {
        return ($oldEnd - $oldStart + 1) * ($newEnd - $newStart + 1) <= $this->maxQuadraticCells;
    }

    /**
     * @return list<array{op:string, old?:int, new?:int}>
     */
    private function oneSidedOps(int $oldStart, int $oldEnd, int $newStart, int $newEnd): array
    {
        $ops = [];
        for ($index = $oldStart; $index < $oldEnd; $index++) {
            $ops[] = ['op' => '-', 'old' => $index];
        }
        for ($index = $newStart; $index < $newEnd; $index++) {
            $ops[] = ['op' => '+', 'new' => $index];
        }

        return $ops;
    }

    /**
     * @param list<string> $old
     * @param list<string> $new
     * @return list<array{0:int, 1:int}>
     */
    private function uniqueCommonAnchors(array $old, array $new, int $oldStart, int $oldEnd, int $newStart, int $newEnd): array
    {
        $oldCounts = [];
        for ($index = $oldStart; $index < $oldEnd; $index++) {
            $oldCounts[$old[$index]] = ($oldCounts[$old[$index]] ?? 0) + 1;
        }

        $newCounts = [];
        $newPositions = [];
        for ($index = $newStart; $index < $newEnd; $index++) {
            $line = $new[$index];
            $newCounts[$line] = ($newCounts[$line] ?? 0) + 1;
            $newPositions[$line] = $index;
        }

        $anchors = [];
        $nextNew = $newStart;
        for ($index = $oldStart; $index < $oldEnd; $index++) {
            $line = $old[$index];
            $newIndex = $newPositions[$line] ?? null;
            if (
                $newIndex === null
                || $newIndex < $nextNew
                || ($oldCounts[$line] ?? 0) !== 1
                || ($newCounts[$line] ?? 0) !== 1
            ) {
                continue;
            }

            $anchors[] = [$index, $newIndex];
            $nextNew = $newIndex + 1;
        }

        return $anchors;
    }

    /**
     * @param list<string> $old
     * @param list<string> $new
     * @return list<array{op:string, old?:int, new?:int}>
     */
    private function exactDiffRange(array $old, array $new, int $oldStart, int $oldEnd, int $newStart, int $newEnd): array
    {
        $oldItems = array_slice($old, $oldStart, $oldEnd - $oldStart);
        $newItems = array_slice($new, $newStart, $newEnd - $newStart);
        $table = $this->lcsTable($oldItems, $newItems);
        $ops = [];
        $i = 0;
        $j = 0;

        while ($i < count($oldItems) && $j < count($newItems)) {
            if ($oldItems[$i] === $newItems[$j]) {
                $ops[] = ['op' => '=', 'old' => $oldStart + $i, 'new' => $newStart + $j];
                $i++;
                $j++;
                continue;
            }

            if ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $ops[] = ['op' => '-', 'old' => $oldStart + $i];
                $i++;
            } else {
                $ops[] = ['op' => '+', 'new' => $newStart + $j];
                $j++;
            }
        }

        while ($i < count($oldItems)) {
            $ops[] = ['op' => '-', 'old' => $oldStart + $i];
            $i++;
        }
        while ($j < count($newItems)) {
            $ops[] = ['op' => '+', 'new' => $newStart + $j];
            $j++;
        }

        return $ops;
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     * @return list<list<int>>
     */
    private function lcsTable(array $a, array $b): array
    {
        $table = array_fill(0, count($a) + 1, array_fill(0, count($b) + 1, 0));
        for ($i = count($a) - 1; $i >= 0; $i--) {
            for ($j = count($b) - 1; $j >= 0; $j--) {
                $table[$i][$j] = $a[$i] === $b[$j] ? $table[$i + 1][$j + 1] + 1 : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        return $table;
    }
}
