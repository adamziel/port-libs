<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext185Plan
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
            'status' => 'compound-select-window-recursive-limit-current-source-next185-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'currentPreLimitRows' => $currentPreLimitRows,
            'nextPreLimitRows' => $nextPreLimitRows,
            'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
            'compound' => [
                'operators' => self::operators($currentPlan),
                'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                'orderColumns' => self::orderColumns($currentPlan),
                'limit' => $currentPlan['compound']['limit'] ?? null,
                'offset' => $currentPlan['compound']['offset'] ?? 0,
                'distinctArmIndex' => self::distinctArmIndex($currentPlan),
                'hasUnionAllTail' => self::operators($currentPlan) === ['UNION', 'UNION ALL'],
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
                'dependencies' => array_values(array_unique(array_merge($currentRecursive['dependencies'], $nextRecursive['dependencies']))),
            ],
            'windows' => [
                'current' => self::windowTerms($currentPlan),
                'next' => self::windowTerms($nextPlan),
                'functions' => array_values(array_unique(array_column(self::windowTerms($currentPlan), 'function'))),
            ],
            'distinctUnion' => [
                'currentDuplicateLabels' => self::duplicateLabels($currentPreLimitRows),
                'nextDuplicateLabels' => self::duplicateLabels($nextPreLimitRows),
                'currentSurvivorLabels' => self::labels($currentPreLimitRows),
                'nextSurvivorLabels' => self::labels($nextPreLimitRows),
                'changedSurvivors' => self::changedLabels($currentPreLimitRows, $nextPreLimitRows),
            ],
            'limitTrace' => [
                'current' => self::limitTrace($currentPreLimitRows, $currentRows, $currentPlan),
                'next' => self::limitTrace($nextPreLimitRows, $nextRows, $nextPlan),
            ],
            'sourceClasses' => [
                'current' => self::sourceClasses($currentRows),
                'next' => self::sourceClasses($nextRows),
                'preLimitCurrent' => self::sourceClasses($currentPreLimitRows),
                'preLimitNext' => self::sourceClasses($nextPreLimitRows),
            ],
            'boundary' => self::boundaryDelta($currentRows, $nextRows),
            'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreLimitRows, $nextPreLimitRows, $currentRecursive, $currentPlan),
            'dependencies' => [
                'sqlite-select-sql-recursive-single-row-limit-offset-next185',
                'sqlite-select-sql-compound-union-distinct-before-union-all-next185',
                'sqlite-select-sql-window-before-distinct-compound-next185',
                'sqlite-select-sql-compound-tail-limit-offset-next185',
                'sqlite-current-source-next185',
            ],
            'dependency_closure' => 'no new support component needed; next185 reuses lane-local SELECT SQL recursive CTE queue, UNION distinct de-duplication, UNION ALL tail preservation, window execution, ORDER BY, and LIMIT/OFFSET helpers',
        ];
    }

    /**
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next185 needs WITH RECURSIVE SQL');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next185 needs compound SELECT SQL');
        }
        if (self::operators($currentPlan) !== ['UNION', 'UNION ALL']) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next185 needs UNION distinct followed by UNION ALL');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || (($currentPlan['compound']['offset'] ?? 0) < 1)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next185 needs final LIMIT/OFFSET');
        }
        if (preg_match('/WITH\s+RECURSIVE.*?\bLIMIT\s+1\s+OFFSET\s+\d+/is', $sql) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next185 needs single-row recursive LIMIT/OFFSET');
        }
        $functions = array_map('strtolower', array_column(self::windowTerms($currentPlan), 'function'));
        foreach (['row_number', 'dense_rank', 'rank'] as $function) {
            if (!in_array($function, $functions, true)) {
                throw new \InvalidArgumentException("SQLite compound SELECT window recursive LIMIT next185 needs {$function} window output");
            }
        }
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next185 cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next185 cannot isolate final LIMIT');
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
    private static function distinctArmIndex(array $plan): ?int
    {
        foreach (self::operators($plan) as $index => $operator) {
            if ($operator === 'UNION') {
                return $index + 1;
            }
        }

        return null;
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
     * @param list<array<string,mixed>> $rows
     * @return array<string,int>
     */
    private static function sourceClasses(array $rows): array
    {
        $classes = [];
        foreach ($rows as $row) {
            $label = (string) ($row['label'] ?? '');
            $class = str_starts_with($label, 'seed') ? 'recursive' : 'table';
            $classes[$class] = ($classes[$class] ?? 0) + 1;
        }
        ksort($classes);

        return $classes;
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
    private static function duplicateLabels(array $rows): array
    {
        $counts = [];
        foreach (self::labels($rows) as $label) {
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        return array_values(array_keys(array_filter($counts, static fn (int $count): bool => $count > 1)));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedLabels(array $currentRows, array $nextRows): array
    {
        return array_values(array_unique(array_merge(
            array_diff(self::labels($nextRows), self::labels($currentRows)),
            array_diff(self::labels($currentRows), self::labels($nextRows)),
        )));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedSignatures(array $currentRows, array $nextRows): array
    {
        $current = array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $currentRows);
        $next = array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $nextRows);

        return array_values(array_unique(array_merge(array_diff($next, $current), array_diff($current, $next))));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    private static function boundaryDelta(array $currentRows, array $nextRows): array
    {
        $currentSignatures = array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $currentRows);
        $nextSignatures = array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $nextRows);

        return [
            'currentFirst' => $currentRows[0] ?? null,
            'nextFirst' => $nextRows[0] ?? null,
            'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
            'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
            'gainedRows' => array_values(array_diff($nextSignatures, $currentSignatures)),
            'lostRows' => array_values(array_diff($currentSignatures, $nextSignatures)),
        ];
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<array<string,mixed>> $currentPreLimitRows
     * @param list<array<string,mixed>> $nextPreLimitRows
     * @param array<string,mixed> $currentRecursive
     * @param array<string,mixed> $currentPlan
     * @return list<string>
     */
    private static function replanReasons(array $currentRows, array $nextRows, array $currentPreLimitRows, array $nextPreLimitRows, array $currentRecursive, array $currentPlan): array
    {
        $reasons = [];
        if (self::changedSignatures($currentRows, $nextRows) !== []) {
            $reasons[] = 'limited-union-distinct-window-rowset-changed';
        }
        if (self::changedSignatures($currentPreLimitRows, $nextPreLimitRows) !== []) {
            $reasons[] = 'prelimit-union-distinct-window-rowset-changed';
        }
        if (self::duplicateLabels($currentPreLimitRows) !== []) {
            $reasons[] = 'union-distinct-arm-collapsed-duplicates-before-union-all-tail';
        }
        if (count($currentRecursive['rows'] ?? []) === 1 && self::lastTraceValue((array) ($currentRecursive['trace'] ?? []), 'offset_remaining') === 0) {
            $reasons[] = 'recursive-limit-offset-emitted-single-row';
        }
        if (self::windowTerms($currentPlan) !== []) {
            $reasons[] = 'window-functions-materialized-before-distinct-union';
        }
        if (($currentPlan['compound']['offset'] ?? 0) > 0) {
            $reasons[] = 'compound-tail-limit-offset-after-distinct-union';
        }

        return $reasons;
    }
}
