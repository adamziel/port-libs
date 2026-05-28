<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext211Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
        $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
        self::assertSupported($sql, $currentPlan, $nextPlan);

        $preLimitSql = self::withoutFinalLimit($sql);
        $traceSql = self::recursiveTraceSql($sql);
        $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
        $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
        $currentPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $currentTables);
        $nextPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $nextTables);
        $currentRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
        $nextRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);
        $currentToken = self::sourceToken($currentRows, $currentPreLimitRows, $currentRecursive['trace']);
        $nextToken = self::sourceToken($nextRows, $nextPreLimitRows, $nextRecursive['trace']);
        self::validateCursor($cursor, $currentToken);

        $currentWindows = self::windowTerms($currentPlan);
        $nextWindows = self::windowTerms($nextPlan);
        $offset = self::offset($currentPlan);
        $limit = self::limit($currentPlan);

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next211-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'currentPreLimitRows' => $currentPreLimitRows,
            'nextPreLimitRows' => $nextPreLimitRows,
            'compound' => [
                'operators' => self::operators($currentPlan),
                'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                'orderColumns' => self::orderColumns($currentPlan),
                'limit' => $limit,
                'offset' => $offset,
                'hasUnionAllHead' => (self::operators($currentPlan)[0] ?? null) === 'UNION ALL',
                'hasExceptFilterFence' => in_array('EXCEPT', self::operators($currentPlan), true),
                'hasUnionDistinctTail' => in_array('UNION', self::operators($currentPlan), true),
            ],
            'recursiveQueue' => [
                'name' => $currentRecursive['name'],
                'columns' => $currentRecursive['columns'],
                'operator' => $currentRecursive['operator'],
                'currentSkippedLabels' => self::traceLabels($currentRecursive['trace'], false),
                'nextSkippedLabels' => self::traceLabels($nextRecursive['trace'], false),
                'currentEmittedLabels' => self::traceLabels($currentRecursive['trace'], true),
                'nextEmittedLabels' => self::traceLabels($nextRecursive['trace'], true),
                'currentTraceCount' => count($currentRecursive['trace']),
                'nextTraceCount' => count($nextRecursive['trace']),
                'currentLimitRemaining' => self::lastTraceValue($currentRecursive['trace'], 'limit_remaining'),
                'currentOffsetRemaining' => self::lastTraceValue($currentRecursive['trace'], 'offset_remaining'),
            ],
            'windows' => [
                'current' => $currentWindows,
                'next' => $nextWindows,
                'functions' => array_values(array_unique(array_column($currentWindows, 'function'))),
                'filterCount' => count(array_filter($currentWindows, static fn (array $term): bool => ($term['hasFilter'] ?? false) === true)),
                'filteredFunctions' => self::filteredFunctions($currentWindows),
                'sumFilterMetrics' => self::metricsForLabels($currentPreLimitRows, 'seed'),
                'countFilterMetrics' => self::metricsForNonRecursive($currentPreLimitRows),
                'filteredOutLabels' => self::filteredOutLabels($currentPreLimitRows),
            ],
            'sourceWindow' => [
                'currentToken' => $currentToken,
                'nextToken' => $nextToken,
                'currentAdmittedLabels' => self::labels($currentRows),
                'nextAdmittedLabels' => self::labels($nextRows),
                'currentSkippedLabels' => self::labels(array_slice($currentPreLimitRows, 0, $offset)),
                'nextSkippedLabels' => self::labels(array_slice($nextPreLimitRows, 0, $offset)),
                'currentTruncatedLabels' => self::labels(array_slice($currentPreLimitRows, $offset + $limit)),
                'nextTruncatedLabels' => self::labels(array_slice($nextPreLimitRows, $offset + $limit)),
                'nextOnlyPreLimitLabels' => self::changedLabels($currentPreLimitRows, $nextPreLimitRows, true),
                'currentOnlyPreLimitLabels' => self::changedLabels($currentPreLimitRows, $nextPreLimitRows, false),
            ],
            'limitTrace' => [
                'current' => self::limitTrace($currentPreLimitRows, $currentRows, $currentPlan),
                'next' => self::limitTrace($nextPreLimitRows, $nextRows, $nextPlan),
            ],
            'cursor' => [
                'currentToken' => $currentToken,
                'nextOffset' => $offset + $limit,
                'limit' => $limit,
                'currentRowCount' => count($currentRows),
                'nextRowCount' => count($nextRows),
            ],
            'replanReasons' => [
                'compound-filtered-window-current-source-next211',
                'recursive-limit-offset-before-filter-window-next211',
                'except-removes-filtered-window-row-next211',
                'union-distinct-after-filter-window-next211',
                'wordpress-option-preview-stale-filter-cursor-next211',
            ],
            'dependencies' => [
                'sqlite-select-sql-window-filter-next211',
                'sqlite-select-sql-recursive-limit-offset-next211',
                'sqlite-compound-except-union-filter-current-source-next211',
            ],
            'dependency_closure' => 'no new support component needed; next211 reuses native SELECT SQL compound execution, recursive LIMIT/OFFSET tracing, FILTERed aggregate window frames, EXCEPT/UNION membership, and final LIMIT helpers',
            'non_overlap' => 'avoids accepted next209 unfiltered sum/count aggregate windows, next206 lead/nth_value INTERSECT fencing, next203 lag/last_value EXCEPT fencing, next196 ntile/first_value UNION distinct, JSON/WAL/B-tree/VFS clusters, and current next115/next116 pool work; this slice only fences FILTERed aggregate window output through EXCEPT plus UNION before final compound LIMIT over current and next wp_options sources',
        ];
    }

    /**
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next211 needs WITH RECURSIVE SQL');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next211 needs compound SELECT SQL');
        }
        $operators = self::operators($currentPlan);
        foreach (['UNION ALL', 'EXCEPT', 'UNION'] as $operator) {
            if (!in_array($operator, $operators, true)) {
                throw new \InvalidArgumentException("SQLite compound SELECT window recursive LIMIT next211 needs {$operator}");
            }
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || (($currentPlan['compound']['offset'] ?? 0) < 1)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next211 needs final LIMIT/OFFSET');
        }
        if (preg_match('/\bLIMIT\s+\d+\s+OFFSET\s+\d+/is', self::recursiveBody($sql)) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next211 needs recursive LIMIT/OFFSET');
        }
        $windows = self::windowTerms($currentPlan);
        $functions = array_map(static fn (array $term): string => (string) ($term['function'] ?? ''), $windows);
        foreach (['sum', 'count'] as $function) {
            if (!in_array($function, $functions, true)) {
                throw new \InvalidArgumentException("SQLite compound SELECT window recursive LIMIT next211 needs {$function} window output");
            }
        }
        if (count(array_filter($windows, static fn (array $term): bool => ($term['hasFilter'] ?? false) === true)) < 2) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next211 needs FILTERed aggregate windows');
        }
    }

    private static function recursiveBody(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^WITH\s+RECURSIVE\s+[A-Za-z_][A-Za-z0-9_]*\s*\([^)]*\)\s+AS\s*\((.*)\)\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next211 cannot isolate recursive CTE body');
        }

        return $match[1];
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next211 cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next211 cannot isolate final LIMIT');
        }

        return $without;
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<string>
     */
    private static function operators(array $plan): array
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

        return array_values(array_map('strtoupper', is_array($compound['operators'] ?? null) ? $compound['operators'] : []));
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<string>
     */
    private static function orderColumns(array $plan): array
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
        if (!is_array($compound['orderBy'] ?? null)) {
            return [];
        }

        return array_values(array_map(static fn (array $term): string => (string) ($term['column'] ?? ''), $compound['orderBy']));
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    private static function windowTerms(array $plan): array
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
        $windows = [];
        foreach ((array) ($compound['arms'] ?? []) as $armIndex => $arm) {
            $select = is_array($arm) && is_array($arm['select'] ?? null) ? $arm['select'] : [];
            foreach ($select as $selectIndex => $term) {
                if (!is_array($term) || ($term['type'] ?? null) !== 'window') {
                    continue;
                }
                $filter = is_array($term['filter'] ?? null) ? $term['filter'] : null;
                $windows[] = [
                    'arm' => $armIndex,
                    'selectIndex' => $selectIndex,
                    'alias' => isset($term['alias']) && is_string($term['alias']) ? $term['alias'] : 'expr' . ($selectIndex + 1),
                    'function' => strtolower((string) ($term['function'] ?? '')),
                    'hasFilter' => $filter !== null,
                    'filterColumn' => is_array($filter) ? (string) ($filter['left']['name'] ?? $filter['column'] ?? '') : '',
                    'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                    'hasFrame' => is_array($term['frame'] ?? null),
                ];
            }
        }

        return $windows;
    }

    /**
     * @param list<array<string,mixed>> $windows
     * @return list<string>
     */
    private static function filteredFunctions(array $windows): array
    {
        return array_values(array_map(
            static fn (array $term): string => (string) $term['function'],
            array_values(array_filter($windows, static fn (array $term): bool => ($term['hasFilter'] ?? false) === true))
        ));
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function limit(array $plan): int
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

        return is_int($compound['limit'] ?? null) ? $compound['limit'] : 0;
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function offset(array $plan): int
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

        return is_int($compound['offset'] ?? null) ? $compound['offset'] : 0;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function labels(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? ''), $rows));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int>
     */
    private static function metricsForLabels(array $rows, string $prefix): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) ($row['metric'] ?? 0),
            array_values(array_filter($rows, static fn (array $row): bool => str_starts_with((string) ($row['label'] ?? ''), $prefix)))
        ));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int>
     */
    private static function metricsForNonRecursive(array $rows): array
    {
        return array_values(array_map(
            static fn (array $row): int => (int) ($row['metric'] ?? 0),
            array_values(array_filter($rows, static fn (array $row): bool => !str_starts_with((string) ($row['label'] ?? ''), 'seed')))
        ));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function filteredOutLabels(array $rows): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) ($row['label'] ?? ''),
            array_values(array_filter($rows, static fn (array $row): bool => (int) ($row['metric'] ?? 0) === 0))
        ));
    }

    /**
     * @param list<array<string,mixed>> $trace
     * @return list<string>
     */
    private static function traceLabels(array $trace, bool $emitted): array
    {
        $labels = [];
        foreach ($trace as $entry) {
            if (!is_array($entry) || (bool) ($entry['emitted'] ?? false) !== $emitted) {
                continue;
            }
            $row = is_array($entry['current'] ?? null) ? $entry['current'] : [];
            $labels[] = (string) ($row['label'] ?? '');
        }

        return $labels;
    }

    /**
     * @param list<array<string,mixed>> $trace
     */
    private static function lastTraceValue(array $trace, string $key): mixed
    {
        if ($trace === []) {
            return null;
        }
        $last = $trace[count($trace) - 1];

        return is_array($last) ? ($last[$key] ?? null) : null;
    }

    /**
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $limitedRows
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function limitTrace(array $preLimitRows, array $limitedRows, array $plan): array
    {
        $offset = self::offset($plan);
        $limit = self::limit($plan);

        return [
            'preLimitCount' => count($preLimitRows),
            'finalCount' => count($limitedRows),
            'limit' => $limit,
            'offset' => $offset,
            'skippedBeforeOffset' => array_slice($preLimitRows, 0, $offset),
            'admitted' => $limitedRows,
            'truncatedAfterLimit' => array_slice($preLimitRows, $offset + $limit),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $trace
     */
    private static function sourceToken(array $rows, array $preLimitRows, array $trace): string
    {
        return hash('sha256', json_encode([
            'rows' => $rows,
            'preLimitRows' => $preLimitRows,
            'trace' => $trace,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,mixed>|null $cursor
     */
    private static function validateCursor(?array $cursor, string $currentToken): void
    {
        if ($cursor === null) {
            return;
        }
        if (($cursor['currentToken'] ?? null) !== $currentToken) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next211 stale current-source cursor');
        }
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedLabels(array $currentRows, array $nextRows, bool $nextOnly): array
    {
        $current = self::labels($currentRows);
        $next = self::labels($nextRows);

        return array_values(array_diff($nextOnly ? $next : $current, $nextOnly ? $current : $next));
    }
}
