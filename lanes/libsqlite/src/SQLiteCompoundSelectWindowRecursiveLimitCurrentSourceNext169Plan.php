<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext169Plan
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
            'status' => 'compound-select-window-recursive-limit-current-source-next169-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'currentPreLimitRows' => $currentPreLimitRows,
            'nextPreLimitRows' => $nextPreLimitRows,
            'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
            'compound' => [
                'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                'orderColumns' => self::orderColumns($currentPlan),
                'limit' => $currentPlan['compound']['limit'],
                'offset' => $currentPlan['compound']['offset'] ?? 0,
                'usesFinalLimitOffset' => true,
            ],
            'windows' => [
                'current' => self::windowTerms($currentPlan),
                'next' => self::windowTerms($nextPlan),
                'functions' => array_values(array_unique(array_column(self::windowTerms($currentPlan), 'function'))),
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
                'currentLimitRemaining' => self::lastTraceValue($currentRecursive['trace'], 'limit_remaining'),
                'nextLimitRemaining' => self::lastTraceValue($nextRecursive['trace'], 'limit_remaining'),
                'currentOffsetRemaining' => self::lastTraceValue($currentRecursive['trace'], 'offset_remaining'),
                'nextOffsetRemaining' => self::lastTraceValue($nextRecursive['trace'], 'offset_remaining'),
                'usesCommaQueueLimit' => true,
                'dependencies' => array_values(array_unique(array_merge($currentRecursive['dependencies'], $nextRecursive['dependencies']))),
            ],
            'limitTrace' => [
                'current' => self::limitTrace($currentPreLimitRows, $currentRows, $currentPlan),
                'next' => self::limitTrace($nextPreLimitRows, $nextRows, $nextPlan),
            ],
            'bucketDelta' => self::bucketDelta($currentRows, $nextRows),
            'boundary' => self::boundaryDelta($currentRows, $nextRows),
            'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreLimitRows, $nextPreLimitRows, $currentRecursive, $nextRecursive, $currentPlan),
            'dependencies' => [
                'sqlite-recursive-cte-order-limit-comma-next169',
                'sqlite-select-sql-window-ntile-before-compound-next169',
                'sqlite-select-sql-compound-final-limit-offset-next169',
                'sqlite-current-source-next169',
            ],
            'dependency_closure' => 'no new support component needed; next169 reuses lane-local recursive CTE queue ORDER BY/LIMIT comma parsing, SELECT SQL compound execution, ntile() window row-array evaluation, and tail LIMIT/OFFSET helpers',
        ];
    }

    /**
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next169 plan needs WITH RECURSIVE SQL');
        }
        if (preg_match('/\bORDER\s+BY\b.+\bLIMIT\s+\d+\s*,\s*\d+/is', $sql) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next169 plan needs recursive ORDER BY plus comma LIMIT');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next169 plan needs a compound SELECT');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || preg_match('/\s+LIMIT\s+\d+\s+OFFSET\s+\d+\s*$/i', rtrim(trim($sql), ';')) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next169 plan needs final LIMIT/OFFSET');
        }
        $functions = array_map('strtolower', array_column(self::windowTerms($currentPlan), 'function'));
        if (!in_array('ntile', $functions, true)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next169 plan needs ntile() window arms');
        }
        if (($currentPlan['compound']['arms'] ?? []) === ($nextPlan['compound']['arms'] ?? []) && self::windowTerms($currentPlan) === []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next169 plan needs window metadata');
        }
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next169 plan cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s+OFFSET\s+\d+\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next169 plan cannot isolate final LIMIT/OFFSET');
        }

        return $without;
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
        $arms = is_array($compound['arms'] ?? null) ? $compound['arms'] : [];
        $windows = [];
        foreach ($arms as $armIndex => $arm) {
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
                    'argumentCount' => is_array($term['arguments'] ?? null) ? count($term['arguments']) : 0,
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
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $limitedRows
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function limitTrace(array $preLimitRows, array $limitedRows, array $plan): array
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
        $offset = isset($compound['offset']) && is_int($compound['offset']) ? $compound['offset'] : 0;
        $limit = isset($compound['limit']) && is_int($compound['limit']) ? $compound['limit'] : count($limitedRows);

        return [
            'preLimitCount' => count($preLimitRows),
            'acceptedCount' => count($limitedRows),
            'skippedBeforeOffset' => array_slice($preLimitRows, 0, $offset),
            'admitted' => $limitedRows,
            'truncatedAfterLimit' => array_slice($preLimitRows, $offset + $limit),
            'firstAdmitted' => $limitedRows[0] ?? null,
            'lastAdmitted' => $limitedRows === [] ? null : $limitedRows[count($limitedRows) - 1],
        ];
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    private static function bucketDelta(array $currentRows, array $nextRows): array
    {
        return [
            'current' => self::countByBucket($currentRows),
            'next' => self::countByBucket($nextRows),
            'newLabels' => array_values(array_diff(self::labels($nextRows), self::labels($currentRows))),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,int>
     */
    private static function countByBucket(array $rows): array
    {
        $buckets = [];
        foreach ($rows as $row) {
            $bucket = $row['bucket'] ?? null;
            if (!is_int($bucket)) {
                continue;
            }
            $buckets[$bucket] = ($buckets[$bucket] ?? 0) + 1;
        }
        ksort($buckets);

        return $buckets;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    private static function boundaryDelta(array $currentRows, array $nextRows): array
    {
        $current = self::rowSignatures($currentRows);
        $next = self::rowSignatures($nextRows);

        return [
            'currentFirst' => $currentRows[0] ?? null,
            'nextFirst' => $nextRows[0] ?? null,
            'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
            'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
            'lostRows' => array_values(array_diff($current, $next)),
            'gainedRows' => array_values(array_diff($next, $current)),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function labels(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => isset($row['label']) && is_scalar($row['label']) ? (string) $row['label'] : '', $rows));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function rowSignatures(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedSignatures(array $currentRows, array $nextRows): array
    {
        $current = self::rowSignatures($currentRows);
        $next = self::rowSignatures($nextRows);

        return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<array<string,mixed>> $currentPreLimit
     * @param list<array<string,mixed>> $nextPreLimit
     * @param array<string,mixed> $currentRecursive
     * @param array<string,mixed> $nextRecursive
     * @param array<string,mixed> $currentPlan
     * @return list<string>
     */
    private static function replanReasons(array $currentRows, array $nextRows, array $currentPreLimit, array $nextPreLimit, array $currentRecursive, array $nextRecursive, array $currentPlan): array
    {
        $reasons = ['recursive-queue-order-limit-comma'];
        if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
            $reasons[] = 'limited-compound-rowset-changed';
        }
        if (self::rowSignatures($currentPreLimit) !== self::rowSignatures($nextPreLimit)) {
            $reasons[] = 'prelimit-compound-rowset-changed';
        }
        if (($currentRecursive['rows'] ?? []) !== ($nextRecursive['rows'] ?? [])) {
            $reasons[] = 'recursive-limit-comma-rowset-compared';
        }
        if (self::traceLabels(is_array($currentRecursive['trace'] ?? null) ? $currentRecursive['trace'] : [], false) !== []) {
            $reasons[] = 'recursive-comma-limit-skipped-anchor';
        }
        if (self::windowTerms($currentPlan) !== []) {
            $reasons[] = 'ntile-window-before-compound-limit';
        }
        if (($currentPlan['compound']['limit'] ?? null) !== null) {
            $reasons[] = 'compound-tail-limit-offset';
        }

        return $reasons;
    }
}
