<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext184Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext181Plan::compare($sql, $currentTables, $nextTables);
        $recursive = is_array($base['recursive'] ?? null) ? $base['recursive'] : [];
        $yieldTape = is_array($base['yieldTape'] ?? null) ? $base['yieldTape'] : [];
        $currentTape = is_array($yieldTape['current'] ?? null) ? $yieldTape['current'] : [];
        $nextTape = is_array($yieldTape['next'] ?? null) ? $yieldTape['next'] : [];

        self::assertRecursiveLimitExhaustion($recursive, $currentTape, $nextTape);

        $currentRows = is_array($base['currentRows'] ?? null) ? $base['currentRows'] : [];
        $nextRows = is_array($base['nextRows'] ?? null) ? $base['nextRows'] : [];

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next184-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'compound' => $base['compound'],
            'windows' => $base['windows'],
            'recursive' => $recursive,
            'yieldTape' => $yieldTape,
            'recursiveLimitPressure' => [
                'current' => self::recursivePressure($recursive, $currentTape, $currentRows, 'current'),
                'next' => self::recursivePressure($recursive, $nextTape, $nextRows, 'next'),
                'changedAdmittedRecursiveLabels' => self::changedRecursiveLabels($currentRows, $nextRows),
                'sourceBoundaryShift' => self::sourceBoundaryShift($currentTape, $nextTape),
            ],
            'limitTrace' => $base['limitTrace'],
            'boundary' => $base['boundary'],
            'replanReasons' => array_values(array_unique(array_merge(
                is_array($base['replanReasons'] ?? null) ? $base['replanReasons'] : [],
                self::replanReasons($recursive, $currentTape, $nextTape, $currentRows, $nextRows),
            ))),
            'dependencies' => [
                'sqlite-select-sql-recursive-limit-exhaustion-next184',
                'sqlite-select-sql-window-before-union-distinct-next184',
                'sqlite-select-sql-compound-yield-source-boundary-next184',
                'sqlite-select-sql-final-limit-current-source-next184',
                'sqlite-current-source-next184',
            ],
            'dependency_closure' => 'no new support component needed; next184 reuses lane-local SELECT SQL recursive tracing, window evaluation, UNION distinct yield tape, and final LIMIT/OFFSET execution',
        ];
    }

    /**
     * @param array<string,mixed> $recursive
     * @param list<array<string,mixed>> $currentTape
     * @param list<array<string,mixed>> $nextTape
     */
    private static function assertRecursiveLimitExhaustion(array $recursive, array $currentTape, array $nextTape): void
    {
        if (self::remaining($recursive, 'current', 'limit') !== 0 || self::remaining($recursive, 'next', 'limit') !== 0) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next184 needs exhausted recursive LIMIT queues');
        }
        if (self::sourceLabels($currentTape, 'recursive') === [] || self::sourceLabels($nextTape, 'recursive') === []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next184 needs recursive rows in the compound yield tape');
        }
    }

    /**
     * @param array<string,mixed> $recursive
     * @param list<array<string,mixed>> $tape
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function recursivePressure(array $recursive, array $tape, array $rows, string $side): array
    {
        $emittedKey = $side === 'next' ? 'nextEmittedLabels' : 'currentEmittedLabels';
        $skippedKey = $side === 'next' ? 'nextSkippedLabels' : 'currentSkippedLabels';
        $traceKey = $side === 'next' ? 'nextTraceCount' : 'currentTraceCount';
        $limitRemaining = self::remaining($recursive, $side, 'limit');
        $offsetRemaining = self::remaining($recursive, $side, 'offset');
        $admitted = self::recursiveAdmittedLabels($rows);
        $tapeLabels = self::sourceLabels($tape, 'recursive');

        return [
            'traceCount' => $recursive[$traceKey] ?? null,
            'limitRemaining' => $limitRemaining,
            'offsetRemaining' => $offsetRemaining,
            'skippedLabels' => is_array($recursive[$skippedKey] ?? null) ? array_values($recursive[$skippedKey]) : [],
            'emittedLabels' => is_array($recursive[$emittedKey] ?? null) ? array_values($recursive[$emittedKey]) : [],
            'tapeRecursiveLabels' => $tapeLabels,
            'admittedRecursiveLabels' => $admitted,
            'recursiveRowsDroppedByFinalLimit' => array_values(array_diff($tapeLabels, $admitted)),
            'firstTapeSource' => isset($tape[0]['source']) ? (string) $tape[0]['source'] : null,
            'lastTapeSource' => $tape === [] || !isset($tape[count($tape) - 1]['source']) ? null : (string) $tape[count($tape) - 1]['source'],
        ];
    }

    /**
     * @param array<string,mixed> $recursive
     */
    private static function remaining(array $recursive, string $side, string $kind): ?int
    {
        $plain = $side . ucfirst($kind) . 'Remaining';
        $final = $side . 'Final' . ucfirst($kind) . 'Remaining';
        $value = $recursive[$plain] ?? $recursive[$final] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function recursiveAdmittedLabels(array $rows): array
    {
        return array_values(array_filter(
            array_map(static fn (array $row): string => (string) ($row['label'] ?? ''), $rows),
            static fn (string $label): bool => str_starts_with($label, 'seed'),
        ));
    }

    /**
     * @param list<array<string,mixed>> $tape
     * @return list<string>
     */
    private static function sourceLabels(array $tape, string $source): array
    {
        $labels = [];
        foreach ($tape as $entry) {
            if (!is_array($entry) || ($entry['source'] ?? null) !== $source || !isset($entry['label'])) {
                continue;
            }
            $labels[] = (string) $entry['label'];
        }

        return $labels;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedRecursiveLabels(array $currentRows, array $nextRows): array
    {
        $current = self::recursiveAdmittedLabels($currentRows);
        $next = self::recursiveAdmittedLabels($nextRows);

        return array_values(array_unique(array_merge(array_diff($current, $next), array_diff($next, $current))));
    }

    /**
     * @param list<array<string,mixed>> $currentTape
     * @param list<array<string,mixed>> $nextTape
     * @return array<string,mixed>
     */
    private static function sourceBoundaryShift(array $currentTape, array $nextTape): array
    {
        return [
            'currentFirstSources' => array_values(array_map(static fn (array $row): string => (string) ($row['source'] ?? ''), array_slice($currentTape, 0, 4))),
            'nextFirstSources' => array_values(array_map(static fn (array $row): string => (string) ($row['source'] ?? ''), array_slice($nextTape, 0, 4))),
            'currentRecursiveCount' => count(self::sourceLabels($currentTape, 'recursive')),
            'nextRecursiveCount' => count(self::sourceLabels($nextTape, 'recursive')),
            'currentTableCount' => count(self::sourceLabels($currentTape, 'table')),
            'nextTableCount' => count(self::sourceLabels($nextTape, 'table')),
        ];
    }

    /**
     * @param array<string,mixed> $recursive
     * @param list<array<string,mixed>> $currentTape
     * @param list<array<string,mixed>> $nextTape
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function replanReasons(array $recursive, array $currentTape, array $nextTape, array $currentRows, array $nextRows): array
    {
        $reasons = ['recursive-limit-exhausted-before-compound-yield'];
        if (self::sourceBoundaryShift($currentTape, $nextTape)['currentFirstSources'] !== self::sourceBoundaryShift($currentTape, $nextTape)['nextFirstSources']) {
            $reasons[] = 'current-source-yield-source-boundary-shifted';
        }
        if (self::changedRecursiveLabels($currentRows, $nextRows) !== []) {
            $reasons[] = 'admitted-recursive-labels-changed-by-final-limit';
        }
        if (($recursive['currentSkippedLabels'] ?? []) !== [] || ($recursive['nextSkippedLabels'] ?? []) !== []) {
            $reasons[] = 'recursive-offset-skipped-anchor-before-limit';
        }

        return $reasons;
    }
}
