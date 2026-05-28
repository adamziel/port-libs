<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext192Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        self::assertSupported($sql);

        $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
        $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next192 needs compound SELECT SQL');
        }

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

        $offset = self::compoundOffset($currentPlan);
        $limit = self::compoundLimit($currentPlan);
        $windowTerms = self::windowTerms($currentPlan);
        $functions = array_values(array_unique(array_column($windowTerms, 'function')));
        if (!in_array('percent_rank', $functions, true) || !in_array('cume_dist', $functions, true)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next192 needs percent_rank() and cume_dist() window arms');
        }

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next192-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'currentPreLimitRows' => $currentPreLimitRows,
            'nextPreLimitRows' => $nextPreLimitRows,
            'compound' => [
                'operators' => self::operators($currentPlan),
                'limit' => $limit,
                'offset' => $offset,
                'orderColumns' => self::orderColumns($currentPlan),
                'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                'nextArms' => count($nextPlan['compound']['arms'] ?? []),
            ],
            'recursiveQueue' => [
                'name' => $currentRecursive['name'],
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
                'current' => $windowTerms,
                'next' => self::windowTerms($nextPlan),
                'functions' => $functions,
                'distributionFunctions' => array_values(array_intersect($functions, ['percent_rank', 'cume_dist'])),
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
            'cursor' => [
                'currentToken' => $currentToken,
                'nextOffset' => $offset + $limit,
                'limit' => $limit,
                'currentRowCount' => count($currentRows),
                'nextRowCount' => count($nextRows),
            ],
            'replanReasons' => [
                'compound-window-distribution-current-source-next192',
                'recursive-queue-order-limit-current-source-next192',
                'percent-rank-cume-dist-before-compound-limit-next192',
                'wordpress-option-preview-stale-cursor-fence-next192',
            ],
            'dependencies' => [
                'sqlite-select-sql-recursive-queue-order-limit-next192',
                'sqlite-select-sql-percent-rank-cume-dist-window-next192',
                'sqlite-current-source-token-fence-next192',
            ],
            'dependency_closure' => 'no new support component needed; next192 reuses native SELECT SQL compound, recursive CTE queue ORDER BY/LIMIT/OFFSET, percent_rank/cume_dist window evaluation, and final compound LIMIT helpers',
            'non_overlap' => 'avoids accepted next189 row_number/dense_rank token fence, next188 endpoint windows, next186 rank/dense_rank comma LIMIT, and JSON/WAL/B-tree/VFS clusters; this slice fences distribution windows after recursive queue ORDER BY current-source changes',
        ];
    }

    private static function assertSupported(string $sql): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next192 needs WITH RECURSIVE SQL');
        }
        if (preg_match('/\bpercent_rank\s*\(/i', $sql) !== 1 || preg_match('/\bcume_dist\s*\(/i', $sql) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next192 needs percent_rank() and cume_dist() windows');
        }
        if (preg_match('/\bUNION(?:\s+ALL)?\b/i', $sql) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next192 needs compound UNION SQL');
        }
        $recursiveBody = self::recursiveBody($sql);
        if (preg_match('/\bORDER\s+BY\b.*?\bLIMIT\s+\d+\s+OFFSET\s+\d+/is', $recursiveBody) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next192 needs recursive ORDER BY LIMIT/OFFSET');
        }
        if (preg_match('/\s+LIMIT\s+\d+\s+OFFSET\s+\d+\s*;?\s*$/i', trim($sql)) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next192 needs final LIMIT/OFFSET');
        }
    }

    private static function recursiveBody(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^WITH\s+RECURSIVE\s+[A-Za-z_][A-Za-z0-9_]*\s*\([^)]*\)\s+AS\s*\((.*)\)\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next192 cannot isolate recursive CTE body');
        }

        return $match[1];
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next192 cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s+OFFSET\s+\d+\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next192 cannot isolate final LIMIT');
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
     */
    private static function compoundLimit(array $plan): int
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

        return is_int($compound['limit'] ?? null) ? $compound['limit'] : 0;
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function compoundOffset(array $plan): int
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

        return is_int($compound['offset'] ?? null) ? $compound['offset'] : 0;
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
                    'function' => (string) ($term['function'] ?? ''),
                    'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                ];
            }
        }

        return $windows;
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
        $last = end($trace);
        if (!is_array($last) || !isset($last[$key])) {
            return null;
        }

        return is_int($last[$key]) ? $last[$key] : null;
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
            'traceLabels' => self::traceLabels($trace, true),
            'queueFronts' => self::queueFrontLabels($trace),
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
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next192 cursor does not match current source');
        }
    }
}
