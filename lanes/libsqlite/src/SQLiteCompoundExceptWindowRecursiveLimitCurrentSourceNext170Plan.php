<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNext170Plan
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
            'status' => 'compound-except-window-recursive-limit-current-source-next170-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'currentPreLimitRows' => $currentPreLimitRows,
            'nextPreLimitRows' => $nextPreLimitRows,
            'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
            'compound' => [
                'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                'exceptArmIndexes' => self::operatorIndexes($currentPlan, 'EXCEPT'),
                'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                'orderColumns' => self::orderColumns($currentPlan),
                'limit' => $currentPlan['compound']['limit'],
                'offset' => $currentPlan['compound']['offset'] ?? 0,
            ],
            'windows' => [
                'current' => self::windowTerms($currentPlan),
                'next' => self::windowTerms($nextPlan),
            ],
            'recursive' => self::recursiveSummary($currentRecursive, $nextRecursive),
            'exceptTrace' => self::exceptTrace($currentPreLimitRows, $nextPreLimitRows, $currentRows, $nextRows),
            'limitTrace' => [
                'current' => self::limitTrace($currentPreLimitRows, $currentRows, $currentPlan),
                'next' => self::limitTrace($nextPreLimitRows, $nextRows, $nextPlan),
            ],
            'boundary' => self::boundaryDelta($currentRows, $nextRows),
            'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreLimitRows, $nextPreLimitRows, $currentRecursive, $nextRecursive),
            'dependencies' => [
                'sqlite-select-sql-recursive-offset-exhaustion-next170',
                'sqlite-select-sql-window-before-except-next170',
                'sqlite-select-sql-compound-except-tail-next170',
                'sqlite-select-sql-compound-final-limit-next170',
                'sqlite-current-source-next170',
            ],
            'dependency_closure' => 'no new support component needed; next170 reuses lane-local recursive CTE queue tracing, window row-array evaluation, EXCEPT compound reduction, and final LIMIT/OFFSET execution',
        ];
    }

    /**
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 needs WITH RECURSIVE SQL');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 needs a compound SELECT');
        }
        $operators = array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? []));
        if (!in_array('EXCEPT', $operators, true)) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 needs an EXCEPT arm');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || ($currentPlan['compound']['offset'] ?? null) === null) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 needs final LIMIT/OFFSET');
        }
        if (stripos($sql, ' OFFSET ') === false) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 needs recursive OFFSET exhaustion');
        }
        if (self::windowTerms($currentPlan) === []) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 needs a window function arm');
        }
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 cannot isolate final LIMIT');
        }

        return $without;
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<int>
     */
    private static function operatorIndexes(array $plan, string $operator): array
    {
        $indexes = [];
        foreach (($plan['compound']['operators'] ?? []) as $index => $candidate) {
            if (strtoupper((string) $candidate) === $operator) {
                $indexes[] = $index + 1;
            }
        }

        return $indexes;
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
     * @param array<string,mixed> $currentTrace
     * @param array<string,mixed> $nextTrace
     * @return array<string,mixed>
     */
    private static function recursiveSummary(array $currentTrace, array $nextTrace): array
    {
        return [
            'name' => $currentTrace['name'] ?? null,
            'columns' => $currentTrace['columns'] ?? [],
            'operator' => $currentTrace['operator'] ?? null,
            'currentRows' => $currentTrace['rows'] ?? [],
            'nextRows' => $nextTrace['rows'] ?? [],
            'currentSkippedLabels' => self::traceLabels($currentTrace['trace'] ?? [], false),
            'currentEmittedLabels' => self::traceLabels($currentTrace['trace'] ?? [], true),
            'nextSkippedLabels' => self::traceLabels($nextTrace['trace'] ?? [], false),
            'nextEmittedLabels' => self::traceLabels($nextTrace['trace'] ?? [], true),
            'currentTraceCount' => is_array($currentTrace['trace'] ?? null) ? count($currentTrace['trace']) : 0,
            'nextTraceCount' => is_array($nextTrace['trace'] ?? null) ? count($nextTrace['trace']) : 0,
            'currentLimitRemaining' => self::lastTraceValue($currentTrace['trace'] ?? [], 'limit_remaining'),
            'currentOffsetRemaining' => self::lastTraceValue($currentTrace['trace'] ?? [], 'offset_remaining'),
            'nextLimitRemaining' => self::lastTraceValue($nextTrace['trace'] ?? [], 'limit_remaining'),
            'nextOffsetRemaining' => self::lastTraceValue($nextTrace['trace'] ?? [], 'offset_remaining'),
            'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'] ?? [], $nextTrace['dependencies'] ?? []))),
        ];
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
     * @param list<array<string,mixed>> $currentPreLimit
     * @param list<array<string,mixed>> $nextPreLimit
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    private static function exceptTrace(array $currentPreLimit, array $nextPreLimit, array $currentRows, array $nextRows): array
    {
        return [
            'currentPreLimitLabels' => array_values(array_map('strval', array_column($currentPreLimit, 'label'))),
            'nextPreLimitLabels' => array_values(array_map('strval', array_column($nextPreLimit, 'label'))),
            'currentAdmittedLabels' => array_values(array_map('strval', array_column($currentRows, 'label'))),
            'nextAdmittedLabels' => array_values(array_map('strval', array_column($nextRows, 'label'))),
            'removedBeforeLimit' => array_values(array_diff(
                array_map('strval', array_column($nextPreLimit, 'label')),
                array_map('strval', array_column($nextRows, 'label')),
            )),
        ];
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
        ];
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
            'gainedRows' => array_values(array_diff($next, $current)),
            'lostRows' => array_values(array_diff($current, $next)),
        ];
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
     * @return list<string>
     */
    private static function replanReasons(array $currentRows, array $nextRows, array $currentPreLimit, array $nextPreLimit, array $currentRecursive, array $nextRecursive): array
    {
        $reasons = [
            'recursive-offset-exhaustion-before-window-arm',
            'window-before-except-compound-tail',
            'compound-final-limit-offset',
        ];
        if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
            $reasons[] = 'limited-except-window-rowset-changed';
        }
        if (self::rowSignatures($currentPreLimit) !== self::rowSignatures($nextPreLimit)) {
            $reasons[] = 'prelimit-except-window-rowset-changed';
        }
        if (self::traceLabels($currentRecursive['trace'] ?? [], false) !== [] || self::traceLabels($nextRecursive['trace'] ?? [], false) !== []) {
            $reasons[] = 'recursive-offset-skipped-anchor';
        }

        return array_values(array_unique($reasons));
    }
}
