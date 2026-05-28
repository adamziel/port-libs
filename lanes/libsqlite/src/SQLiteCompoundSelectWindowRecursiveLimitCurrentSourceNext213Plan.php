<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext213Plan
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
        $currentWindows = self::windowTerms($currentPlan);
        $nextWindows = self::windowTerms($nextPlan);
        $currentToken = self::sourceToken($currentRows, $currentPreLimitRows, $currentRecursive['trace'], $currentWindows);
        $nextToken = self::sourceToken($nextRows, $nextPreLimitRows, $nextRecursive['trace'], $nextWindows);
        self::validateCursor($cursor, $currentToken);

        $offset = self::offset($currentPlan);
        $limit = self::limit($currentPlan);

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next213-ready',
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
                'hasIntersectTail' => in_array('INTERSECT', self::operators($currentPlan), true),
            ],
            'recursiveQueue' => [
                'name' => $currentRecursive['name'],
                'columns' => $currentRecursive['columns'],
                'operator' => $currentRecursive['operator'],
                'currentTraceCount' => count($currentRecursive['trace']),
                'nextTraceCount' => count($nextRecursive['trace']),
                'currentSkippedLabels' => self::traceLabels($currentRecursive['trace'], false),
                'currentEmittedLabels' => self::traceLabels($currentRecursive['trace'], true),
                'nextEmittedLabels' => self::traceLabels($nextRecursive['trace'], true),
                'currentLimitRemaining' => self::lastTraceValue($currentRecursive['trace'], 'limit_remaining'),
                'currentOffsetRemaining' => self::lastTraceValue($currentRecursive['trace'], 'offset_remaining'),
            ],
            'windows' => [
                'current' => $currentWindows,
                'next' => $nextWindows,
                'functions' => array_values(array_unique(array_column($currentWindows, 'function'))),
                'aggregateFunctions' => self::aggregateFunctions($currentWindows),
                'maxFrameMetrics' => self::numericMetricsForAlias($currentPreLimitRows, 'metric'),
                'minFrameMetrics' => self::numericMetricsForAlias($currentPreLimitRows, 'floor_metric'),
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
                'nextOnlyAdmittedLabels' => self::changedLabels($currentRows, $nextRows, true),
                'currentOnlyAdmittedLabels' => self::changedLabels($currentRows, $nextRows, false),
                'intersectMatchedLabels' => self::intersectMatchedLabels($currentPreLimitRows),
            ],
            'limitTrace' => [
                'current' => self::limitTrace($currentPreLimitRows, $currentRows, $offset, $limit),
                'next' => self::limitTrace($nextPreLimitRows, $nextRows, $offset, $limit),
            ],
            'cursor' => [
                'currentToken' => $currentToken,
                'nextOffset' => $offset + $limit,
                'limit' => $limit,
                'currentRowCount' => count($currentRows),
                'nextRowCount' => count($nextRows),
            ],
            'replanReasons' => [
                'compound-min-max-window-current-source-next213',
                'recursive-ordered-limit-before-extrema-window-next213',
                'intersect-after-window-output-next213',
                'wordpress-option-preview-stale-cursor-fence-next213',
            ],
            'dependencies' => [
                'sqlite-window-min-max-frame-values-next213',
                'sqlite-select-sql-recursive-queue-limit-next213',
                'sqlite-compound-intersect-current-source-token-fence-next213',
            ],
            'dependency_closure' => 'no new support component needed; next213 reuses native SELECT SQL compound execution, recursive queue ORDER BY/LIMIT/OFFSET, min/max window frame execution, INTERSECT membership, and final LIMIT helpers',
            'non_overlap' => 'avoids accepted next212 group_concat/row_number EXCEPT fencing, next209 sum/count EXCEPT+UNION fencing, next208 rank/dense_rank EXCEPT fencing, JSON/WAL/B-tree/VFS clusters, and grouped/JOIN/subquery/ORDER SQL text work; next213 covers min/max aggregate windows before INTERSECT and final compound LIMIT over current and next wp_options sources',
        ];
    }

    /** @param array<string,mixed> $currentPlan @param array<string,mixed> $nextPlan */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next213 needs WITH RECURSIVE SQL');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next213 needs compound SELECT SQL');
        }
        $operators = self::operators($currentPlan);
        if (!in_array('UNION ALL', $operators, true) || !in_array('INTERSECT', $operators, true)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next213 needs UNION ALL and INTERSECT');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || (($currentPlan['compound']['offset'] ?? 0) < 1)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next213 needs final LIMIT/OFFSET');
        }
        if (preg_match('/\bORDER\s+BY\b.*?\bLIMIT\s+\d+\s+OFFSET\s+\d+/is', self::recursiveBody($sql)) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next213 needs ordered recursive LIMIT/OFFSET');
        }
        $functions = array_map('strtolower', array_column(self::windowTerms($currentPlan), 'function'));
        foreach (['max', 'min'] as $function) {
            if (!in_array($function, $functions, true)) {
                throw new \InvalidArgumentException("SQLite compound SELECT window recursive LIMIT next213 needs {$function} window output");
            }
        }
    }

    private static function recursiveBody(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^WITH\s+RECURSIVE\s+[A-Za-z_][A-Za-z0-9_]*\s*\([^)]*\)\s+AS\s*\((.*)\)\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next213 cannot isolate recursive CTE body');
        }

        return $match[1];
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next213 cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next213 cannot isolate final LIMIT');
        }

        return $without;
    }

    /** @param array<string,mixed> $plan @return list<string> */
    private static function operators(array $plan): array
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

        return array_values(array_map('strtoupper', is_array($compound['operators'] ?? null) ? $compound['operators'] : []));
    }

    /** @param array<string,mixed> $plan @return list<string> */
    private static function orderColumns(array $plan): array
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
        if (!is_array($compound['orderBy'] ?? null)) {
            return [];
        }

        return array_values(array_map(static fn (array $term): string => (string) ($term['column'] ?? ''), $compound['orderBy']));
    }

    /** @param array<string,mixed> $plan @return list<array<string,mixed>> */
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
                $frame = is_array($term['frame'] ?? null) ? $term['frame'] : [];
                $windows[] = [
                    'arm' => $armIndex,
                    'selectIndex' => $selectIndex,
                    'alias' => isset($term['alias']) && is_string($term['alias']) ? $term['alias'] : 'expr' . ($selectIndex + 1),
                    'function' => strtolower((string) ($term['function'] ?? '')),
                    'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                    'frameUnit' => isset($frame['unit']) ? (string) $frame['unit'] : null,
                    'hasFrame' => $frame !== [],
                ];
            }
        }

        return $windows;
    }

    /** @param list<array<string,mixed>> $windows @return list<string> */
    private static function aggregateFunctions(array $windows): array
    {
        return array_values(array_intersect(array_column($windows, 'function'), ['count', 'sum', 'min', 'max', 'group_concat']));
    }

    /** @param array<string,mixed> $plan */
    private static function limit(array $plan): int
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

        return is_int($compound['limit'] ?? null) ? $compound['limit'] : 0;
    }

    /** @param array<string,mixed> $plan */
    private static function offset(array $plan): int
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

        return is_int($compound['offset'] ?? null) ? $compound['offset'] : 0;
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function labels(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? ''), $rows));
    }

    /** @param list<array<string,mixed>> $left @param list<array<string,mixed>> $right @return list<string> */
    private static function changedLabels(array $left, array $right, bool $rightOnly): array
    {
        $leftLabels = self::labels($left);
        $rightLabels = self::labels($right);

        return array_values($rightOnly ? array_diff($rightLabels, $leftLabels) : array_diff($leftLabels, $rightLabels));
    }

    /** @param list<array<string,mixed>> $rows @return list<int|float> */
    private static function numericMetricsForAlias(array $rows, string $alias): array
    {
        return array_values(array_filter(
            array_map(static fn (array $row): mixed => $row[$alias] ?? null, $rows),
            static fn (mixed $value): bool => is_int($value) || is_float($value)
        ));
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function intersectMatchedLabels(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $label = (string) ($row['label'] ?? '');
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        return array_values(array_keys(array_filter($counts, static fn (int $count): bool => $count > 1)));
    }

    /** @param list<array<string,mixed>> $trace @return list<string> */
    private static function traceLabels(array $trace, bool $emitted): array
    {
        $labels = [];
        foreach ($trace as $entry) {
            if (($entry['emitted'] ?? false) !== $emitted) {
                continue;
            }
            $row = is_array($entry['current'] ?? null) ? $entry['current'] : (is_array($entry['row'] ?? null) ? $entry['row'] : []);
            $labels[] = (string) ($row['label'] ?? $row['option_name'] ?? '');
        }

        return $labels;
    }

    /** @param list<array<string,mixed>> $trace */
    private static function lastTraceValue(array $trace, string $key): ?int
    {
        $last = $trace[count($trace) - 1] ?? null;
        if (!is_array($last) || !is_int($last[$key] ?? null)) {
            return null;
        }

        return $last[$key];
    }

    /** @param list<array<string,mixed>> $rows @param list<array<string,mixed>> $preLimitRows @param list<array<string,mixed>> $trace @param list<array<string,mixed>> $windows */
    private static function sourceToken(array $rows, array $preLimitRows, array $trace, array $windows): string
    {
        return hash('sha256', json_encode([
            'rows' => $rows,
            'preLimitLabels' => self::labels($preLimitRows),
            'traceLabels' => self::traceLabels($trace, true),
            'traceSkippedLabels' => self::traceLabels($trace, false),
            'windows' => $windows,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param list<array<string,mixed>> $preLimitRows @param list<array<string,mixed>> $limitedRows @return array<string,mixed> */
    private static function limitTrace(array $preLimitRows, array $limitedRows, int $offset, int $limit): array
    {
        return [
            'preLimitCount' => count($preLimitRows),
            'finalCount' => count($limitedRows),
            'skippedBeforeOffset' => array_slice($preLimitRows, 0, $offset),
            'admitted' => $limitedRows,
            'truncatedAfterLimit' => array_slice($preLimitRows, $offset + $limit),
        ];
    }

    /** @param array<string,mixed>|null $cursor */
    private static function validateCursor(?array $cursor, string $currentToken): void
    {
        if ($cursor === null || !array_key_exists('currentToken', $cursor)) {
            return;
        }
        if ($cursor['currentToken'] !== $currentToken) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next213 cursor does not match current source token');
        }
    }
}
