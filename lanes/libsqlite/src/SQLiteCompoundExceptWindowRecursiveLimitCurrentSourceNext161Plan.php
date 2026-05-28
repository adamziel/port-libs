<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNext161Plan
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
        $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
        $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
        $currentPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $currentTables);
        $nextPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $nextTables);
        $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($sql, $currentTables);
        $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($sql, $nextTables);

        return [
            'status' => 'compound-except-window-recursive-limit-current-source-next161-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'currentPreLimitRows' => $currentPreLimitRows,
            'nextPreLimitRows' => $nextPreLimitRows,
            'compound' => [
                'operators' => self::operators($currentPlan),
                'armCount' => count($currentPlan['compound']['arms'] ?? []),
                'orderColumns' => self::orderColumns($currentPlan),
                'limit' => $currentPlan['compound']['limit'] ?? null,
                'offset' => $currentPlan['compound']['offset'] ?? 0,
                'exceptArmIndex' => self::exceptArmIndex($currentPlan),
            ],
            'windows' => [
                'current' => self::windowTerms($currentPlan),
                'next' => self::windowTerms($nextPlan),
                'functions' => array_values(array_unique(array_column(self::windowTerms($currentPlan), 'function'))),
            ],
            'recursive' => [
                'name' => $currentTrace['name'] ?? null,
                'columns' => $currentTrace['columns'] ?? [],
                'operator' => $currentTrace['operator'] ?? null,
                'currentRows' => $currentTrace['rows'] ?? [],
                'nextRows' => $nextTrace['rows'] ?? [],
                'currentTraceCount' => is_array($currentTrace['trace'] ?? null) ? count($currentTrace['trace']) : 0,
                'nextTraceCount' => is_array($nextTrace['trace'] ?? null) ? count($nextTrace['trace']) : 0,
                'currentLimitRemaining' => self::lastTraceValue($currentTrace, 'limit_remaining'),
                'nextLimitRemaining' => self::lastTraceValue($nextTrace, 'limit_remaining'),
                'dependencies' => array_values(array_unique(array_merge(
                    is_array($currentTrace['dependencies'] ?? null) ? $currentTrace['dependencies'] : [],
                    is_array($nextTrace['dependencies'] ?? null) ? $nextTrace['dependencies'] : [],
                ))),
            ],
            'except' => [
                'currentExcludedLabels' => self::exceptLabels($currentPreLimitRows, $currentRows),
                'nextExcludedLabels' => self::exceptLabels($nextPreLimitRows, $nextRows),
                'changedExcludedLabels' => self::changedExceptLabels($currentPreLimitRows, $currentRows, $nextPreLimitRows, $nextRows),
                'survivingSkipLabels' => self::survivingSkipLabels($currentRows, $nextRows),
            ],
            'yieldBoundary' => [
                'current' => self::yieldBoundary($currentPreLimitRows, $currentRows, $currentPlan),
                'next' => self::yieldBoundary($nextPreLimitRows, $nextRows, $nextPlan),
            ],
            'boundary' => [
                'currentFirst' => $currentRows[0] ?? null,
                'nextFirst' => $nextRows[0] ?? null,
                'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                'gainedLabels' => array_values(array_diff(self::labels($nextRows), self::labels($currentRows))),
                'lostLabels' => array_values(array_diff(self::labels($currentRows), self::labels($nextRows))),
            ],
            'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
            'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreLimitRows, $nextPreLimitRows, $currentTrace),
            'dependencies' => [
                'sqlite-recursive-cte-before-compound-except-next161',
                'sqlite-window-arm-before-except-next161',
                'sqlite-compound-except-final-limit-yield-next161',
            ],
            'dependency_closure' => 'no new support component needed; this reuses lane-local SELECT SQL, recursive CTE, compound EXCEPT, window, and LIMIT/OFFSET execution',
        ];
    }

    /**
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT next161 needs WITH RECURSIVE SQL');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT next161 needs a compound SELECT');
        }
        if (!in_array('EXCEPT', self::operators($currentPlan), true)) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT next161 needs an EXCEPT arm');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || stripos($sql, ' OFFSET ') === false) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT next161 needs final LIMIT/OFFSET');
        }
        if (self::windowTerms($currentPlan) === []) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT next161 needs a window arm');
        }
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
    private static function exceptArmIndex(array $plan): ?int
    {
        foreach (self::operators($plan) as $index => $operator) {
            if ($operator === 'EXCEPT') {
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
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                ];
            }
        }

        return $windows;
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s+OFFSET\s+\d+\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT next161 cannot isolate final LIMIT/OFFSET');
        }

        return $without;
    }

    /**
     * @param array<string,mixed> $trace
     */
    private static function lastTraceValue(array $trace, string $key): mixed
    {
        $rows = is_array($trace['trace'] ?? null) ? $trace['trace'] : [];
        $last = $rows === [] ? null : $rows[count($rows) - 1];

        return is_array($last) ? ($last[$key] ?? null) : null;
    }

    /**
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $limitedRows
     * @return list<string>
     */
    private static function exceptLabels(array $preLimitRows, array $limitedRows): array
    {
        return array_values(array_diff(self::labels($preLimitRows), self::labels($limitedRows)));
    }

    /**
     * @param list<array<string,mixed>> $currentPreLimitRows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextPreLimitRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedExceptLabels(array $currentPreLimitRows, array $currentRows, array $nextPreLimitRows, array $nextRows): array
    {
        $current = self::exceptLabels($currentPreLimitRows, $currentRows);
        $next = self::exceptLabels($nextPreLimitRows, $nextRows);

        return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function survivingSkipLabels(array $currentRows, array $nextRows): array
    {
        return array_values(array_filter(array_unique(array_merge(self::labels($currentRows), self::labels($nextRows))), static fn (string $label): bool => str_starts_with($label, 'skip_')));
    }

    /**
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $limitedRows
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function yieldBoundary(array $preLimitRows, array $limitedRows, array $plan): array
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
        $offset = isset($compound['offset']) && is_int($compound['offset']) ? $compound['offset'] : 0;
        $limit = isset($compound['limit']) && is_int($compound['limit']) ? $compound['limit'] : count($limitedRows);

        return [
            'offset' => $offset,
            'limit' => $limit,
            'preLimitCount' => count($preLimitRows),
            'yieldedCount' => count($limitedRows),
            'skippedBeforeOffset' => array_slice($preLimitRows, 0, $offset),
            'yielded' => $limitedRows,
            'truncatedAfterLimit' => array_slice($preLimitRows, $offset + $limit),
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
        return array_values(array_merge(array_diff(self::rowSignatures($currentRows), self::rowSignatures($nextRows)), array_diff(self::rowSignatures($nextRows), self::rowSignatures($currentRows))));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<array<string,mixed>> $currentPreLimitRows
     * @param list<array<string,mixed>> $nextPreLimitRows
     * @param array<string,mixed> $currentTrace
     * @return list<string>
     */
    private static function replanReasons(array $currentRows, array $nextRows, array $currentPreLimitRows, array $nextPreLimitRows, array $currentTrace): array
    {
        $reasons = ['recursive-window-before-compound-except', 'compound-except-before-final-limit'];
        if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
            $reasons[] = 'limited-compound-except-rowset-changed';
        }
        if (self::rowSignatures($currentPreLimitRows) !== self::rowSignatures($nextPreLimitRows)) {
            $reasons[] = 'prelimit-compound-except-rowset-changed';
        }
        if (self::lastTraceValue($currentTrace, 'limit_remaining') === 0) {
            $reasons[] = 'recursive-limit-exhausted-before-except';
        }

        return $reasons;
    }
}
