<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext195Plan
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
        $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
        $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next195-ready',
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
                'intersectBeforeExcept' => self::operators($currentPlan) === ['INTERSECT', 'EXCEPT'],
            ],
            'windows' => [
                'current' => self::windowTerms($currentPlan),
                'next' => self::windowTerms($nextPlan),
                'functions' => array_values(array_unique(array_column(self::windowTerms($currentPlan), 'function'))),
                'positionColumn' => 'pos',
            ],
            'recursive' => [
                'name' => $currentTrace['name'],
                'columns' => $currentTrace['columns'],
                'operator' => $currentTrace['operator'],
                'currentRows' => $currentTrace['rows'],
                'nextRows' => $nextTrace['rows'],
                'currentTraceCount' => count($currentTrace['trace']),
                'nextTraceCount' => count($nextTrace['trace']),
                'currentLimitRemaining' => self::lastTraceValue($currentTrace['trace'], 'limit_remaining'),
                'nextLimitRemaining' => self::lastTraceValue($nextTrace['trace'], 'limit_remaining'),
            ],
            'intersectExcept' => [
                'currentMatchedBeforeExcept' => $currentPreLimitRows,
                'nextMatchedBeforeExcept' => $nextPreLimitRows,
                'currentRemovedLabels' => self::removedLabels($currentTrace['rows'], $currentPreLimitRows),
                'nextRemovedLabels' => self::removedLabels($nextTrace['rows'], $nextPreLimitRows),
                'gainedAfterNextSource' => self::gainedLabels($currentRows, $nextRows),
            ],
            'limitTrace' => [
                'current' => self::limitTrace($currentPreLimitRows, $currentRows, $currentPlan),
                'next' => self::limitTrace($nextPreLimitRows, $nextRows, $nextPlan),
            ],
            'boundary' => self::boundaryDelta($currentRows, $nextRows),
            'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreLimitRows, $nextPreLimitRows),
            'dependencies' => [
                'sqlite-select-sql-recursive-window-intersect-next195',
                'sqlite-select-sql-compound-except-antijoin-next195',
                'sqlite-current-source-compound-limit-boundary-next195',
                'sqlite-current-source-next195',
            ],
            'dependency_closure' => 'no new support component needed; next195 reuses lane-local SELECT SQL recursive CTE tracing, row_number() window output, INTERSECT/EXCEPT compound execution, ORDER BY, and final LIMIT/OFFSET machinery',
        ];
    }

    /**
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next195 needs WITH RECURSIVE SQL');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next195 needs compound SELECT SQL');
        }
        if (self::operators($currentPlan) !== ['INTERSECT', 'EXCEPT']) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next195 needs INTERSECT followed by EXCEPT');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || (($currentPlan['compound']['offset'] ?? 0) < 1)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next195 needs final LIMIT/OFFSET');
        }
        $functions = array_map('strtolower', array_column(self::windowTerms($currentPlan), 'function'));
        if (!in_array('row_number', $functions, true)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next195 needs row_number() window output');
        }
        if (preg_match('/WITH\s+RECURSIVE.*?\bORDER\s+BY\s+3\s+DESC\s+LIMIT\s+\d+/is', $sql) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next195 needs ordered recursive LIMIT');
        }
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next195 cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next195 cannot isolate final LIMIT');
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
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                ];
            }
        }

        return $windows;
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
     * @param list<array<string,mixed>> $recursiveRows
     * @param list<array<string,mixed>> $survivingRows
     * @return list<string>
     */
    private static function removedLabels(array $recursiveRows, array $survivingRows): array
    {
        $survivors = array_flip(array_map(static fn (array $row): string => (string) ($row['label'] ?? ''), $survivingRows));
        $removed = [];
        foreach ($recursiveRows as $row) {
            $label = (string) ($row['label'] ?? '');
            if ($label !== '' && !isset($survivors[$label])) {
                $removed[] = $label;
            }
        }

        return array_values(array_unique($removed));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function gainedLabels(array $currentRows, array $nextRows): array
    {
        return array_values(array_diff(array_column($nextRows, 'label'), array_column($currentRows, 'label')));
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
    private static function boundaryDelta(array $currentRows, array $nextRows): array
    {
        return [
            'currentFirst' => $currentRows[0] ?? null,
            'nextFirst' => $nextRows[0] ?? null,
            'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
            'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
            'lostRows' => array_values(array_diff(self::rowSignatures($currentRows), self::rowSignatures($nextRows))),
            'gainedRows' => array_values(array_diff(self::rowSignatures($nextRows), self::rowSignatures($currentRows))),
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
     * @param list<array<string,mixed>> $currentPreLimitRows
     * @param list<array<string,mixed>> $nextPreLimitRows
     * @return list<string>
     */
    private static function replanReasons(array $currentRows, array $nextRows, array $currentPreLimitRows, array $nextPreLimitRows): array
    {
        $reasons = ['recursive-window-intersect-before-except', 'except-antijoin-before-final-limit'];
        if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
            $reasons[] = 'limited-intersect-except-rowset-changed';
        }
        if (self::rowSignatures($currentPreLimitRows) !== self::rowSignatures($nextPreLimitRows)) {
            $reasons[] = 'prelimit-intersect-except-rowset-changed';
        }
        if ($nextRows !== [] && $currentRows !== [] && ($nextRows[0]['label'] ?? null) !== ($currentRows[0]['label'] ?? null)) {
            $reasons[] = 'final-offset-boundary-shifted';
        }

        return $reasons;
    }
}
