<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext187Plan
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

        $traceSql = self::recursiveTraceSql($sql);
        $preLimitSql = self::withoutFinalLimit($sql);
        $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
        $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
        $currentPreLimit = SQLiteSelectSql::execute($preLimitSql, $currentTables);
        $nextPreLimit = SQLiteSelectSql::execute($preLimitSql, $nextTables);
        $currentRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
        $nextRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next187-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'compound' => [
                'operators' => self::operators($currentPlan),
                'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                'orderColumns' => self::orderColumns($currentPlan),
                'limit' => $currentPlan['compound']['limit'] ?? null,
                'offset' => $currentPlan['compound']['offset'] ?? 0,
                'hasUnionDistinct' => in_array('UNION', self::operators($currentPlan), true),
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
            ],
            'negativeLimitOffset' => [
                'current' => self::negativeLimitSummary($currentRecursive['trace'], $currentPreLimit, $currentRows),
                'next' => self::negativeLimitSummary($nextRecursive['trace'], $nextPreLimit, $nextRows),
                'changedAdmittedLabels' => self::changedLabels($currentRows, $nextRows),
                'changedRecursiveLabels' => self::changedRecursiveLabels($currentRows, $nextRows),
            ],
            'yieldTape' => [
                'current' => self::yieldTape($currentPreLimit, $currentRows),
                'next' => self::yieldTape($nextPreLimit, $nextRows),
            ],
            'limitTrace' => [
                'current' => self::limitTrace($currentPreLimit, $currentRows),
                'next' => self::limitTrace($nextPreLimit, $nextRows),
            ],
            'replanReasons' => self::replanReasons($currentRecursive['trace'], $nextRecursive['trace'], $currentPreLimit, $nextPreLimit, $currentRows, $nextRows),
            'dependencies' => [
                'sqlite-recursive-cte-negative-limit-offset-next187',
                'sqlite-select-sql-window-before-union-distinct-next187',
                'sqlite-select-sql-compound-final-limit-current-source-next187',
                'sqlite-current-source-next187',
            ],
            'dependency_closure' => 'no new support component needed; next187 reuses native PHP SELECT SQL recursive CTE tracing, window execution, UNION distinct, and final LIMIT/OFFSET helpers',
        ];
    }

    /**
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next187 needs WITH RECURSIVE SQL');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next187 needs compound SELECT SQL');
        }
        if (!in_array('UNION ALL', self::operators($currentPlan), true) || !in_array('UNION', self::operators($currentPlan), true)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next187 needs UNION ALL plus UNION distinct arms');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || (($currentPlan['compound']['offset'] ?? 0) < 1)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next187 needs final LIMIT/OFFSET');
        }
        if (preg_match('/WITH\s+RECURSIVE.*?\bLIMIT\s+-1\s+OFFSET\s+\d+/is', $sql) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next187 needs recursive LIMIT -1 OFFSET');
        }
        $functions = array_map('strtolower', array_column(self::windowTerms($currentPlan), 'function'));
        foreach (['lag', 'lead'] as $function) {
            if (!in_array($function, $functions, true)) {
                throw new \InvalidArgumentException("SQLite compound SELECT window recursive LIMIT current-source next187 needs {$function} window output");
            }
        }
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next187 cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next187 cannot isolate final LIMIT');
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
     * @param list<array<string,mixed>> $trace
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function negativeLimitSummary(array $trace, array $preLimitRows, array $rows): array
    {
        return [
            'traceCount' => count($trace),
            'limitRemaining' => self::lastTraceValue($trace, 'limit_remaining'),
            'offsetRemaining' => self::lastTraceValue($trace, 'offset_remaining'),
            'skippedLabels' => self::traceLabels($trace, false),
            'emittedLabels' => self::traceLabels($trace, true),
            'admittedRecursiveLabels' => self::recursiveLabels($rows),
            'recursiveRowsDroppedByFinalLimit' => array_values(array_diff(self::recursiveLabels($preLimitRows), self::recursiveLabels($rows))),
            'preLimitRecursiveCount' => count(self::recursiveLabels($preLimitRows)),
            'finalRecursiveCount' => count(self::recursiveLabels($rows)),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function recursiveLabels(array $rows): array
    {
        return array_values(array_filter(
            array_map(static fn (array $row): string => (string) ($row['label'] ?? ''), $rows),
            static fn (string $label): bool => str_starts_with($label, 'seed')
        ));
    }

    /**
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $limitedRows
     * @return list<array<string,mixed>>
     */
    private static function yieldTape(array $preLimitRows, array $limitedRows): array
    {
        $limited = array_flip(self::rowSignatures($limitedRows));
        $seen = [];
        $tape = [];
        foreach ($preLimitRows as $index => $row) {
            $signature = json_encode($row, JSON_THROW_ON_ERROR);
            $duplicate = isset($seen[$signature]);
            $seen[$signature] = true;
            $label = (string) ($row['label'] ?? '');
            $tape[] = [
                'index' => $index,
                'label' => $label,
                'source' => str_starts_with($label, 'seed') ? 'recursive' : 'table',
                'duplicateSuppressed' => $duplicate,
                'admittedByFinalLimit' => isset($limited[$signature]),
            ];
        }

        return $tape;
    }

    /**
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $limitedRows
     * @return array<string,mixed>
     */
    private static function limitTrace(array $preLimitRows, array $limitedRows): array
    {
        $limited = array_flip(self::rowSignatures($limitedRows));

        return [
            'preLimitCount' => count($preLimitRows),
            'finalCount' => count($limitedRows),
            'skippedBeforeOffset' => array_values(array_filter($preLimitRows, static fn (array $row): bool => !isset($limited[json_encode($row, JSON_THROW_ON_ERROR)]))),
            'firstFinalLabel' => isset($limitedRows[0]['label']) ? (string) $limitedRows[0]['label'] : null,
            'lastFinalLabel' => $limitedRows === [] || !isset($limitedRows[count($limitedRows) - 1]['label']) ? null : (string) $limitedRows[count($limitedRows) - 1]['label'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedLabels(array $currentRows, array $nextRows): array
    {
        $changed = array_merge(array_diff(self::rowSignatures($currentRows), self::rowSignatures($nextRows)), array_diff(self::rowSignatures($nextRows), self::rowSignatures($currentRows)));

        return array_values(array_unique(array_map(static function (string $signature): string {
            $row = json_decode($signature, true, 512, JSON_THROW_ON_ERROR);

            return is_array($row) ? (string) ($row['label'] ?? '') : '';
        }, $changed)));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedRecursiveLabels(array $currentRows, array $nextRows): array
    {
        return array_values(array_unique(array_merge(
            array_diff(self::recursiveLabels($currentRows), self::recursiveLabels($nextRows)),
            array_diff(self::recursiveLabels($nextRows), self::recursiveLabels($currentRows))
        )));
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
     * @param list<array<string,mixed>> $currentTrace
     * @param list<array<string,mixed>> $nextTrace
     * @param list<array<string,mixed>> $currentPreLimit
     * @param list<array<string,mixed>> $nextPreLimit
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function replanReasons(array $currentTrace, array $nextTrace, array $currentPreLimit, array $nextPreLimit, array $currentRows, array $nextRows): array
    {
        $reasons = ['recursive-negative-limit-offset-drains-queue'];
        if (self::traceLabels($currentTrace, false) !== [] || self::traceLabels($nextTrace, false) !== []) {
            $reasons[] = 'recursive-offset-skipped-anchor-with-unbounded-limit';
        }
        if (count($nextPreLimit) > count($currentPreLimit)) {
            $reasons[] = 'next-source-prelimit-rowset-expanded';
        }
        if (self::changedLabels($currentRows, $nextRows) !== []) {
            $reasons[] = 'current-next-final-limit-boundary-shifted';
        }

        return $reasons;
    }
}
