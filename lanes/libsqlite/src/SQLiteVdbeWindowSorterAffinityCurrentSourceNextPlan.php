<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param non-empty-list<string> $sortColumns
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function compare(array $currentRows, array $nextRows, array $sortColumns, string $rowidColumn, array $options = []): array
    {
        $base = SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan::compare($currentRows, $nextRows, $sortColumns, $rowidColumn, $options);
        $sortAffinities = $options['sortAffinities'] ?? [];
        $sortCollations = self::stringList($options['sortCollations'] ?? [], 'sort collation');
        $sortDescending = self::boolList($options['sortDescending'] ?? [], 'sort descending');
        $sortNulls = self::nullableStringList($options['sortNulls'] ?? [], 'sort nulls');

        if (!is_array($sortAffinities) && !is_string($sortAffinities)) {
            throw new \InvalidArgumentException('SQLite VDBE window sorter affinity current-source next sort affinities must be a string or list');
        }

        $sorter = SQLiteVdbeAffinityCollationSorterSourcePlan::compareSources(
            $currentRows,
            $nextRows,
            $sortColumns,
            $rowidColumn,
            $sortAffinities,
            $sortCollations,
            $sortDescending,
            $sortNulls
        );

        $currentLoop = self::loopSummaries($sorter['currentTrace'], $base['currentWindow'], $rowidColumn);
        $nextLoop = self::loopSummaries($sorter['nextTrace'], $base['nextWindow'], $rowidColumn);
        $currentLoopById = self::byId($currentLoop);
        $loopChanges = [];
        foreach ($nextLoop as $summary) {
            $key = self::idKey($summary['currentRowid']);
            if (!isset($currentLoopById[$key])) {
                continue;
            }
            $before = $currentLoopById[$key];
            if ($before['nextRowid'] !== $summary['nextRowid'] || $before['frameRowids'] !== $summary['frameRowids'] || $before['groupConcat'] !== $summary['groupConcat']) {
                $loopChanges[] = [
                    'id' => $summary['currentRowid'],
                    'currentNextRowid' => $before['nextRowid'],
                    'nextNextRowid' => $summary['nextRowid'],
                    'currentFrameRowids' => $before['frameRowids'],
                    'nextFrameRowids' => $summary['frameRowids'],
                    'currentGroupConcat' => $before['groupConcat'],
                    'nextGroupConcat' => $summary['groupConcat'],
                ];
            }
        }

        return array_replace($base, [
            'status' => $base['status'] === 'sorter-affinity-window-current-source-stable' && $loopChanges === []
                ? 'window-sorter-affinity-current-source-next-stable'
                : 'window-sorter-affinity-current-source-next-changed',
            'currentLoop' => $currentLoop,
            'nextLoop' => $nextLoop,
            'loopChanges' => $loopChanges,
            'dependencies' => [
                'sqlite-vdbe-window-sorter-affinity-current-source-next',
                'sqlite-vdbe-sorter-affinity-current-source-next',
                'sqlite-vdbe-sorter-current-next-yield',
                'sqlite-vdbe-window-current-source-yield',
                'sqlite-affinity-comparison',
            ],
            'dependency_closure' => 'no new support component needed; reuses lane-local VDBE sorter yield, affinity/collation comparison, and window aggregate cursor primitives',
            'non_overlap' => 'avoids accepted next sorter/window frame recalculation, DISTINCT sorter, expression ORDER BY, JSON/WAL/B-tree/VFS clusters; this slice adds current-source OP_SorterData/OP_SorterNext loop diagnostics over sorter-fed window frames',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $trace
     * @param list<array<string,mixed>> $windows
     * @return list<array<string,mixed>>
     */
    private static function loopSummaries(array $trace, array $windows, string $rowidColumn): array
    {
        $windowById = self::byWindowId($windows);
        $loop = [];
        foreach ($trace as $position => $entry) {
            $row = $entry['row'];
            $id = $entry['id'];
            $window = $windowById[self::idKey($id)] ?? null;
            if ($window === null) {
                throw new \RuntimeException('SQLite VDBE window sorter loop is missing window summary for rowid ' . (string) $id);
            }
            $next = $trace[$position + 1] ?? null;
            $loop[] = [
                'position' => $position,
                'sequence' => $entry['sequence'],
                'previousSequence' => $entry['previousSequence'],
                'currentRowid' => $id,
                'nextRowid' => $next['id'] ?? null,
                'record' => $entry['record'],
                'nextRecord' => $next['record'] ?? null,
                'comparisonToPrevious' => $entry['comparison'],
                'stableTieFromPrevious' => $entry['stableTie'],
                'decidingIndex' => self::decidingStep($entry['steps'])['index'] ?? null,
                'row' => $row,
                'sorterCurrentValue' => $row[$rowidColumn],
                'frameRowids' => $window['frameRowids'],
                'rawFrameRowids' => $window['rawFrameRowids'],
                'filteredRowids' => $window['filteredRowids'],
                'sum' => $window['sum'],
                'groupConcat' => $window['groupConcat'],
                'nextSamePeer' => $window['nextSamePeer'],
            ];
        }

        return $loop;
    }

    /**
     * @param list<array<string,mixed>> $steps
     * @return array<string,mixed>|null
     */
    private static function decidingStep(array $steps): ?array
    {
        foreach ($steps as $step) {
            if (($step['decided'] ?? false) === true) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $windows
     * @return array<string,array<string,mixed>>
     */
    private static function byWindowId(array $windows): array
    {
        $byId = [];
        foreach ($windows as $window) {
            $byId[self::idKey($window['currentRowid'])] = $window;
        }

        return $byId;
    }

    /**
     * @param list<array<string,mixed>> $summaries
     * @return array<string,array<string,mixed>>
     */
    private static function byId(array $summaries): array
    {
        $byId = [];
        foreach ($summaries as $summary) {
            $byId[self::idKey($summary['currentRowid'])] = $summary;
        }

        return $byId;
    }

    private static function idKey(mixed $id): string
    {
        return get_debug_type($id) . ':' . (string) $id;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new \InvalidArgumentException("SQLite VDBE window sorter affinity current-source next {$label} list must be a list");
        }
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new \InvalidArgumentException("SQLite VDBE window sorter affinity current-source next {$label} must be a non-empty string");
            }
        }

        return $values;
    }

    /**
     * @return list<bool>
     */
    private static function boolList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new \InvalidArgumentException("SQLite VDBE window sorter affinity current-source next {$label} list must be a list");
        }
        foreach ($values as $value) {
            if (!is_bool($value)) {
                throw new \InvalidArgumentException("SQLite VDBE window sorter affinity current-source next {$label} values must be booleans");
            }
        }

        return $values;
    }

    /**
     * @return list<string|null>
     */
    private static function nullableStringList(mixed $values, string $label): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            throw new \InvalidArgumentException("SQLite VDBE window sorter affinity current-source next {$label} list must be a list");
        }
        foreach ($values as $value) {
            if ($value !== null && (!is_string($value) || trim($value) === '')) {
                throw new \InvalidArgumentException("SQLite VDBE window sorter affinity current-source next {$label} must be null or a non-empty string");
            }
        }

        return $values;
    }
}
