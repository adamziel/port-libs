<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext196Plan
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
        $functions = array_values(array_unique(array_column($currentWindows, 'function')));
        $offset = self::offset($currentPlan);
        $limit = self::limit($currentPlan);

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next196-ready',
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
                'hasDistinctTail' => in_array('UNION', self::operators($currentPlan), true),
            ],
            'recursiveQueue' => [
                'name' => $currentRecursive['name'],
                'columns' => $currentRecursive['columns'],
                'operator' => $currentRecursive['operator'],
                'currentTraceCount' => count($currentRecursive['trace']),
                'nextTraceCount' => count($nextRecursive['trace']),
                'currentSkippedLabels' => self::traceLabels($currentRecursive['trace'], false),
                'nextSkippedLabels' => self::traceLabels($nextRecursive['trace'], false),
                'currentEmittedLabels' => self::traceLabels($currentRecursive['trace'], true),
                'nextEmittedLabels' => self::traceLabels($nextRecursive['trace'], true),
                'currentQueueFronts' => self::queueFrontLabels($currentRecursive['trace']),
                'nextQueueFronts' => self::queueFrontLabels($nextRecursive['trace']),
                'currentLimitRemaining' => self::lastTraceValue($currentRecursive['trace'], 'limit_remaining'),
                'nextLimitRemaining' => self::lastTraceValue($nextRecursive['trace'], 'limit_remaining'),
                'currentOffsetRemaining' => self::lastTraceValue($currentRecursive['trace'], 'offset_remaining'),
                'nextOffsetRemaining' => self::lastTraceValue($nextRecursive['trace'], 'offset_remaining'),
            ],
            'windows' => [
                'current' => $currentWindows,
                'next' => $nextWindows,
                'functions' => $functions,
                'frameFunctions' => array_values(array_filter($functions, static fn (string $function): bool => in_array($function, ['first_value', 'last_value', 'nth_value'], true))),
                'ntileBuckets' => self::ntileBuckets($currentPreLimitRows, self::ntileBucketCount($sql)),
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
                'compound-ntile-first-value-current-source-next196',
                'recursive-order-limit-offset-before-frame-windows-next196',
                'union-distinct-after-window-frame-next196',
                'wordpress-option-preview-stale-cursor-fence-next196',
            ],
            'dependencies' => [
                'sqlite-select-sql-recursive-queue-order-limit-next196',
                'sqlite-select-sql-ntile-first-value-window-next196',
                'sqlite-current-source-token-fence-next196',
            ],
            'dependency_closure' => 'no new support component needed; next196 reuses native SELECT SQL compound, recursive queue ORDER BY/LIMIT/OFFSET, ntile/first_value window frames, UNION distinct, and final LIMIT helpers',
            'non_overlap' => 'avoids accepted next192 percent_rank/cume_dist distribution windows, next191 nth_value/ntile/lead value-offset tape, next190 expression LIMIT windows, and JSON/WAL/B-tree/VFS clusters; this slice fences ntile plus first_value frame output before a final compound LIMIT over current and next wp_options sources',
        ];
    }

    /**
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next196 needs WITH RECURSIVE SQL');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next196 needs compound SELECT SQL');
        }
        $operators = self::operators($currentPlan);
        if (!in_array('UNION ALL', $operators, true) || !in_array('UNION', $operators, true)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next196 needs UNION ALL and UNION distinct');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || (($currentPlan['compound']['offset'] ?? 0) < 1)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next196 needs final LIMIT/OFFSET');
        }
        if (preg_match('/\bORDER\s+BY\b.*?\bLIMIT\s+\d+\s+OFFSET\s+\d+/is', self::recursiveBody($sql)) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next196 needs ordered recursive LIMIT/OFFSET');
        }
        $functions = array_map('strtolower', array_column(self::windowTerms($currentPlan), 'function'));
        foreach (['ntile', 'first_value'] as $function) {
            if (!in_array($function, $functions, true)) {
                throw new \InvalidArgumentException("SQLite compound SELECT window recursive LIMIT next196 needs {$function} window output");
            }
        }
    }

    private static function recursiveBody(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^WITH\s+RECURSIVE\s+[A-Za-z_][A-Za-z0-9_]*\s*\([^)]*\)\s+AS\s*\((.*)\)\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next196 cannot isolate recursive CTE body');
        }

        return $match[1];
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next196 cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next196 cannot isolate final LIMIT');
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
                $windows[] = [
                    'arm' => $armIndex,
                    'selectIndex' => $selectIndex,
                    'alias' => isset($term['alias']) && is_string($term['alias']) ? $term['alias'] : 'expr' . ($selectIndex + 1),
                    'function' => strtolower((string) ($term['function'] ?? '')),
                    'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                    'hasFrame' => is_array($term['frame'] ?? null),
                ];
            }
        }

        return $windows;
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
    private static function ntileBuckets(array $rows, int $bucketCount): array
    {
        $buckets = [];
        foreach ($rows as $row) {
            $value = $row['metric'] ?? null;
            if (is_int($value) && $value >= 1 && $value <= $bucketCount) {
                $buckets[] = $value;
            }
        }

        $buckets = array_values(array_unique($buckets));
        sort($buckets);

        return $buckets;
    }

    private static function ntileBucketCount(string $sql): int
    {
        if (preg_match('/\bntile\s*\(\s*(\d+)\s*\)/i', $sql, $match) !== 1) {
            return 0;
        }

        return (int) $match[1];
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
            $labels[] = (string) ($row['label'] ?? $row[1] ?? '');
        }

        return $labels;
    }

    /**
     * @param list<array<string,mixed>> $trace
     * @return list<string>
     */
    private static function queueFrontLabels(array $trace): array
    {
        $labels = [];
        foreach ($trace as $entry) {
            $queue = is_array($entry['queue_after'] ?? null) ? $entry['queue_after'] : [];
            $front = is_array($queue[0] ?? null) ? $queue[0] : null;
            if ($front !== null) {
                $labels[] = (string) ($front['label'] ?? '');
            }
        }

        return $labels;
    }

    /**
     * @param list<array<string,mixed>> $trace
     */
    private static function lastTraceValue(array $trace, string $key): ?int
    {
        $last = $trace === [] ? null : $trace[count($trace) - 1];
        $value = is_array($last) ? ($last[$key] ?? null) : null;

        return is_int($value) ? $value : null;
    }

    /**
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $limitedRows
     * @param array<string,mixed> $plan
     * @return array{skippedBeforeOffset:list<array<string,mixed>>,admitted:list<array<string,mixed>>,truncatedAfterLimit:list<array<string,mixed>>}
     */
    private static function limitTrace(array $preLimitRows, array $limitedRows, array $plan): array
    {
        $offset = self::offset($plan);
        $limit = self::limit($plan);

        return [
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
            'emitted' => self::traceLabels($trace, true),
            'fronts' => self::queueFrontLabels($trace),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedLabels(array $currentRows, array $nextRows, bool $gained): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[json_encode($row, JSON_THROW_ON_ERROR)] = (string) ($row['label'] ?? '');
        }
        $next = [];
        foreach ($nextRows as $row) {
            $next[json_encode($row, JSON_THROW_ON_ERROR)] = (string) ($row['label'] ?? '');
        }

        return array_values(array_unique($gained ? array_diff($next, $current) : array_diff($current, $next)));
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
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next196 cursor does not match current source');
        }
    }
}
