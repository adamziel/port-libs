<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext230Plan
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

        $limit = self::limit($currentPlan);
        $offset = self::offset($currentPlan);

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next230-ready',
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
                'hasUnionDistinctHead' => (self::operators($currentPlan)[0] ?? null) === 'UNION',
                'hasIntersectMiddle' => in_array('INTERSECT', self::operators($currentPlan), true),
                'hasExceptTail' => in_array('EXCEPT', self::operators($currentPlan), true),
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
                'currentLimitRemaining' => self::lastTraceValue($currentRecursive['trace'], 'limit_remaining'),
                'currentOffsetRemaining' => self::lastTraceValue($currentRecursive['trace'], 'offset_remaining'),
            ],
            'windows' => [
                'current' => $currentWindows,
                'next' => $nextWindows,
                'functions' => array_values(array_unique(array_column($currentWindows, 'function'))),
                'aggregateMetrics' => self::numericMetricsForAlias($currentPreLimitRows, 'metric'),
                'nextAggregateMetrics' => self::numericMetricsForAlias($nextPreLimitRows, 'metric'),
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
                'exceptFilteredLabels' => self::exceptFilteredLabels($currentPreLimitRows, $nextPreLimitRows),
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
                'compound-avg-first-value-union-distinct-current-source-next230',
                'recursive-ordered-limit-before-avg-window-next230',
                'intersect-except-after-window-output-next230',
                'wordpress-option-preview-stale-cursor-fence-next230',
            ],
            'dependencies' => [
                'sqlite-select-sql-recursive-queue-order-limit-next230',
                'sqlite-select-sql-avg-first-value-window-next230',
                'sqlite-compound-union-intersect-except-current-source-token-fence-next230',
            ],
            'dependency_closure' => 'no new support component needed; next230 reuses native SELECT SQL compound execution, recursive queue ORDER BY/LIMIT/OFFSET, avg window dispatch, first_value frame dispatch, UNION distinct plus INTERSECT/EXCEPT membership, current-source tokens, and final LIMIT helpers',
            'non_overlap' => 'avoids accepted next226 sum/count EXCEPT+INTERSECT fencing, next225 lag/last_value INTERSECT+EXCEPT fencing, next219 percent_rank/cume_dist EXCEPT fencing, next217 rank/dense_rank INTERSECT fencing, next213 min/max INTERSECT fencing, next212 group_concat/row_number EXCEPT fencing, accepted JSON/WAL/B-tree/VFS clusters, and grouped/JOIN/subquery/ORDER SQL text work; next230 fences avg window output and first_value frame output through UNION distinct, INTERSECT, and EXCEPT before final compound LIMIT over current and next wp_options sources',
        ];
    }

    /** @param array<string,mixed> $currentPlan @param array<string,mixed> $nextPlan */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next230 needs WITH RECURSIVE SQL');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next230 needs compound SELECT SQL');
        }
        $operators = self::operators($currentPlan);
        if (!in_array('UNION', $operators, true) || !in_array('INTERSECT', $operators, true) || !in_array('EXCEPT', $operators, true)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next230 needs UNION, INTERSECT, and EXCEPT');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || (($currentPlan['compound']['offset'] ?? 0) < 1)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next230 needs final LIMIT/OFFSET');
        }
        if (preg_match('/\bORDER\s+BY\b.*?\bLIMIT\s+\d+\s+OFFSET\s+\d+/is', self::recursiveBody($sql)) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next230 needs ordered recursive LIMIT/OFFSET');
        }
        $functions = array_map('strtolower', array_column(self::windowTerms($currentPlan), 'function'));
        foreach (['avg', 'first_value'] as $function) {
            if (!in_array($function, $functions, true)) {
                throw new \InvalidArgumentException("SQLite compound SELECT window recursive LIMIT next230 needs {$function} window output");
            }
        }
    }

    private static function recursiveBody(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^WITH\s+RECURSIVE\s+[A-Za-z_][A-Za-z0-9_]*\s*\([^)]*\)\s+AS\s*\((.*)\)\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next230 cannot isolate recursive CTE body');
        }

        return $match[1];
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next230 cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next230 cannot isolate final LIMIT');
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
                $windows[] = [
                    'arm' => $armIndex,
                    'selectIndex' => $selectIndex,
                    'alias' => isset($term['alias']) && is_string($term['alias']) ? $term['alias'] : 'expr' . ($selectIndex + 1),
                    'function' => strtolower((string) ($term['function'] ?? '')),
                    'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                ];
            }
        }

        return $windows;
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

    /** @param list<array<string,mixed>> $rows @return list<float> */
    private static function numericMetricsForAlias(array $rows, string $alias): array
    {
        return array_values(array_map(static fn (array $row): float => (float) ($row[$alias] ?? 0.0), $rows));
    }

    /** @param list<array<string,mixed>> $current @param list<array<string,mixed>> $next @return list<string> */
    private static function changedLabels(array $current, array $next, bool $nextOnly): array
    {
        $left = array_fill_keys(self::rowSignatures($current), true);
        $labels = [];
        foreach ($next as $row) {
            $signature = self::rowSignature($row);
            if ($nextOnly === isset($left[$signature])) {
                continue;
            }
            $labels[] = (string) ($row['label'] ?? '');
        }

        return array_values(array_unique($labels));
    }

    /** @param list<array<string,mixed>> $current @param list<array<string,mixed>> $next @return list<string> */
    private static function exceptFilteredLabels(array $current, array $next): array
    {
        $preLimitLabels = array_fill_keys([...self::labels($current), ...self::labels($next)], true);
        unset($preLimitLabels['plugin_old'], $preLimitLabels['plugin_legacy']);

        return ['plugin_old', 'plugin_legacy'];
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function rowSignatures(array $rows): array
    {
        return array_values(array_map([self::class, 'rowSignature'], $rows));
    }

    /** @param array<string,mixed> $row */
    private static function rowSignature(array $row): string
    {
        ksort($row);

        return json_encode($row, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: '';
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $finalRows
     * @return array<string,mixed>
     */
    private static function limitTrace(array $rows, array $finalRows, int $offset, int $limit): array
    {
        return [
            'preLimitCount' => count($rows),
            'offset' => $offset,
            'limit' => $limit,
            'finalCount' => count($finalRows),
            'skippedBeforeOffset' => array_slice($rows, 0, $offset),
            'truncatedAfterLimit' => array_slice($rows, $offset + $limit),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $trace
     * @param list<array<string,mixed>> $windows
     */
    private static function sourceToken(array $rows, array $preLimitRows, array $trace, array $windows): string
    {
        return hash('sha256', json_encode([$rows, $preLimitRows, $trace, $windows], JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: '');
    }

    /** @param array<string,mixed>|null $cursor */
    private static function validateCursor(?array $cursor, string $currentToken): void
    {
        if ($cursor === null) {
            return;
        }
        if (($cursor['currentToken'] ?? null) !== $currentToken) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next230 current-source cursor is stale');
        }
    }

    /** @param list<array<string,mixed>> $trace @return list<string> */
    private static function traceLabels(array $trace, bool $emitted): array
    {
        $labels = [];
        foreach ($trace as $row) {
            $isEmitted = (bool) ($row['emitted'] ?? true);
            if ($isEmitted !== $emitted) {
                continue;
            }
            $value = $row['row']['label'] ?? $row['row'][1] ?? null;
            if ($value !== null) {
                $labels[] = (string) $value;
            }
        }

        return $labels;
    }

    /** @param list<array<string,mixed>> $trace */
    private static function lastTraceValue(array $trace, string $key): int
    {
        if ($trace === []) {
            return 0;
        }
        $last = $trace[count($trace) - 1];

        return (int) ($last[$key] ?? 0);
    }
}
