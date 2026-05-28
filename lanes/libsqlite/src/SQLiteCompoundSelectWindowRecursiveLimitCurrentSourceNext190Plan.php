<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext190Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables): array
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

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next190-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'currentPreLimitRows' => $currentPreLimitRows,
            'nextPreLimitRows' => $nextPreLimitRows,
            'compound' => [
                'operators' => self::operators($currentPlan),
                'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                'orderColumns' => self::orderColumns($currentPlan),
                'limit' => $currentPlan['compound']['limit'] ?? null,
                'offset' => $currentPlan['compound']['offset'] ?? 0,
                'limitExpression' => self::finalLimitExpression($sql),
                'offsetExpression' => self::finalOffsetExpression($sql),
            ],
            'recursive' => [
                'name' => $currentRecursive['name'],
                'columns' => $currentRecursive['columns'],
                'operator' => $currentRecursive['operator'],
                'currentRows' => $currentRecursive['rows'],
                'nextRows' => $nextRecursive['rows'],
                'currentTraceCount' => count($currentRecursive['trace']),
                'nextTraceCount' => count($nextRecursive['trace']),
                'currentSkippedLabels' => self::traceLabels($currentRecursive['trace'], false),
                'nextSkippedLabels' => self::traceLabels($nextRecursive['trace'], false),
                'currentEmittedLabels' => self::traceLabels($currentRecursive['trace'], true),
                'nextEmittedLabels' => self::traceLabels($nextRecursive['trace'], true),
                'currentFinalLimitRemaining' => self::lastTraceValue($currentRecursive['trace'], 'limit_remaining'),
                'nextFinalLimitRemaining' => self::lastTraceValue($nextRecursive['trace'], 'limit_remaining'),
                'currentFinalOffsetRemaining' => self::lastTraceValue($currentRecursive['trace'], 'offset_remaining'),
                'nextFinalOffsetRemaining' => self::lastTraceValue($nextRecursive['trace'], 'offset_remaining'),
                'limitExpression' => self::recursiveLimitExpression($sql),
                'offsetExpression' => self::recursiveOffsetExpression($sql),
            ],
            'windows' => [
                'current' => self::windowTerms($currentPlan),
                'next' => self::windowTerms($nextPlan),
                'functions' => array_values(array_unique(array_column(self::windowTerms($currentPlan), 'function'))),
            ],
            'expressionLimitBoundary' => [
                'currentAdmittedLabels' => self::labels($currentRows),
                'nextAdmittedLabels' => self::labels($nextRows),
                'currentSkippedBeforeFinalOffset' => self::labels(array_slice($currentPreLimitRows, 0, self::offset($currentPlan))),
                'nextSkippedBeforeFinalOffset' => self::labels(array_slice($nextPreLimitRows, 0, self::offset($nextPlan))),
                'currentTruncatedAfterFinalLimit' => self::labels(array_slice($currentPreLimitRows, self::offset($currentPlan) + self::limit($currentPlan))),
                'nextTruncatedAfterFinalLimit' => self::labels(array_slice($nextPreLimitRows, self::offset($nextPlan) + self::limit($nextPlan))),
                'currentRecursivePreLimitLabels' => self::recursiveLabels($currentPreLimitRows),
                'nextRecursivePreLimitLabels' => self::recursiveLabels($nextPreLimitRows),
                'gainedAdmittedLabels' => self::changedLabels($currentRows, $nextRows, true),
                'lostAdmittedLabels' => self::changedLabels($currentRows, $nextRows, false),
            ],
            'limitTrace' => [
                'current' => self::limitTrace($currentPreLimitRows, $currentRows, $currentPlan),
                'next' => self::limitTrace($nextPreLimitRows, $nextRows, $nextPlan),
            ],
            'replanReasons' => [
                'recursive-limit-expression-current-source-next190',
                'compound-tail-limit-expression-current-source-next190',
                'window-values-before-compound-limit-expression-next190',
                'wordpress-option-source-boundary-shifts-expression-limit-next190',
            ],
            'dependencies' => [
                'sqlite-select-sql-recursive-limit-expression-next190',
                'sqlite-select-sql-compound-final-limit-expression-next190',
                'sqlite-select-sql-window-before-expression-limit-next190',
                'sqlite-current-source-next190',
            ],
            'dependency_closure' => 'no new support component needed; next190 reuses native SELECT SQL expression-valued LIMIT/OFFSET evaluation, recursive CTE tracing, compound UNION execution, and window result dispatch',
        ];
    }

    /**
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next190 needs WITH RECURSIVE SQL');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next190 needs compound SELECT SQL');
        }
        if (!in_array('UNION ALL', self::operators($currentPlan), true) || !in_array('UNION', self::operators($currentPlan), true)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next190 needs UNION ALL plus UNION distinct arms');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || (($currentPlan['compound']['offset'] ?? 0) < 1)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next190 needs final LIMIT/OFFSET');
        }
        if (preg_match('/WITH\s+RECURSIVE.*?\bLIMIT\s*\([^)]*[+*\/-][^)]*\)\s+OFFSET\s*\([^)]*[+*\/-][^)]*\)/is', $sql) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next190 needs recursive expression LIMIT/OFFSET');
        }
        if (preg_match('/\s+LIMIT\s*\([^)]*[+*\/-][^)]*\)\s+OFFSET\s*\([^)]*[+*\/-][^)]*\)\s*;?\s*$/is', trim($sql)) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next190 needs final expression LIMIT/OFFSET');
        }
        $functions = array_map('strtolower', array_column(self::windowTerms($currentPlan), 'function'));
        foreach (['lag', 'lead', 'first_value'] as $function) {
            if (!in_array($function, $functions, true)) {
                throw new \InvalidArgumentException("SQLite compound SELECT window recursive LIMIT current-source next190 needs {$function} window output");
            }
        }
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next190 cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $limitOffset = self::finalLimitOffset($trimmed);
        if ($limitOffset === null) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next190 cannot isolate final LIMIT');
        }

        return rtrim(substr($trimmed, 0, $limitOffset));
    }

    private static function finalLimitExpression(string $sql): string
    {
        $tail = self::finalLimitTail($sql);
        $parts = preg_split('/\s+OFFSET\s+/i', $tail, 2);

        return trim((string) ($parts[0] ?? ''));
    }

    private static function finalOffsetExpression(string $sql): string
    {
        $tail = self::finalLimitTail($sql);
        $parts = preg_split('/\s+OFFSET\s+/i', $tail, 2);

        return trim((string) ($parts[1] ?? ''));
    }

    private static function recursiveLimitExpression(string $sql): string
    {
        if (preg_match('/WITH\s+RECURSIVE.*?\bLIMIT\s*(\([^)]*[+*\/-][^)]*\))\s+OFFSET\s*\([^)]*[+*\/-][^)]*\)/is', $sql, $match) !== 1) {
            return '';
        }

        return trim($match[1]);
    }

    private static function recursiveOffsetExpression(string $sql): string
    {
        if (preg_match('/WITH\s+RECURSIVE.*?\bLIMIT\s*\([^)]*[+*\/-][^)]*\)\s+OFFSET\s*(\([^)]*[+*\/-][^)]*\))/is', $sql, $match) !== 1) {
            return '';
        }

        return trim($match[1]);
    }

    private static function finalLimitTail(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $offset = self::finalLimitOffset($trimmed);
        if ($offset === null) {
            return '';
        }

        return trim(substr($trimmed, $offset + strlen('LIMIT')));
    }

    private static function finalLimitOffset(string $sql): ?int
    {
        $matches = [];
        if (preg_match_all('/\bLIMIT\b/i', $sql, $matches, PREG_OFFSET_CAPTURE) === false) {
            return null;
        }
        if ($matches === [] || !isset($matches[0]) || $matches[0] === []) {
            return null;
        }

        return (int) $matches[0][count($matches[0]) - 1][1];
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
                    'function' => (string) ($term['function'] ?? ''),
                    'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                ];
            }
        }

        return $windows;
    }

    /**
     * @param list<array<string,mixed>> $trace
     * @return list<string>
     */
    private static function traceLabels(array $trace, bool $emitted): array
    {
        $labels = [];
        foreach ($trace as $step) {
            if (!is_array($step) || (bool) ($step['emitted'] ?? false) !== $emitted) {
                continue;
            }
            $current = $step['current'] ?? null;
            if (is_array($current) && isset($current['label'])) {
                $labels[] = (string) $current['label'];
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
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function labels(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? ''), $rows));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function recursiveLabels(array $rows): array
    {
        return array_values(array_filter(
            self::labels($rows),
            static fn (string $label): bool => str_starts_with($label, 'seed')
        ));
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
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $limitedRows
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function limitTrace(array $preLimitRows, array $limitedRows, array $plan): array
    {
        return [
            'preLimitCount' => count($preLimitRows),
            'finalCount' => count($limitedRows),
            'limit' => self::limit($plan),
            'offset' => self::offset($plan),
            'firstFinalLabel' => isset($limitedRows[0]['label']) ? (string) $limitedRows[0]['label'] : null,
            'lastFinalLabel' => $limitedRows === [] || !isset($limitedRows[count($limitedRows) - 1]['label']) ? null : (string) $limitedRows[count($limitedRows) - 1]['label'],
        ];
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
}
