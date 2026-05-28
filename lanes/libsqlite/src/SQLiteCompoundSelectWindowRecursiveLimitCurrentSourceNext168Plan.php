<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext168Plan
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
        $traceSql = self::recursiveTraceSql($sql);
        $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
        $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next168-ready',
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
                'usesCommaLimit' => self::hasFinalCommaLimit($sql),
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
                'currentSkippedLabels' => self::traceLabels($currentTrace, false),
                'nextSkippedLabels' => self::traceLabels($nextTrace, false),
                'currentEmittedLabels' => self::traceLabels($currentTrace, true),
                'nextEmittedLabels' => self::traceLabels($nextTrace, true),
                'currentLimitRemaining' => self::lastTraceValue($currentTrace, 'limit_remaining'),
                'nextLimitRemaining' => self::lastTraceValue($nextTrace, 'limit_remaining'),
                'currentOffsetRemaining' => self::lastTraceValue($currentTrace, 'offset_remaining'),
                'nextOffsetRemaining' => self::lastTraceValue($nextTrace, 'offset_remaining'),
                'dependencies' => array_values(array_unique(array_merge(
                    is_array($currentTrace['dependencies'] ?? null) ? $currentTrace['dependencies'] : [],
                    is_array($nextTrace['dependencies'] ?? null) ? $nextTrace['dependencies'] : [],
                ))),
            ],
            'limitTrace' => [
                'current' => self::limitTrace($currentPreLimitRows, $currentRows, $currentPlan),
                'next' => self::limitTrace($nextPreLimitRows, $nextRows, $nextPlan),
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
                'sqlite-recursive-cte-comma-limit-next168',
                'sqlite-window-arm-before-compound-comma-limit-next168',
                'sqlite-compound-final-comma-limit-current-source-next168',
            ],
            'dependency_closure' => 'no new support component needed; this reuses lane-local SELECT SQL recursive CTE queue, window, compound combiner, ORDER BY, and comma-form LIMIT/OFFSET execution',
        ];
    }

    /**
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next168 needs WITH RECURSIVE SQL');
        }
        if (!preg_match('/\bLIMIT\s+\d+\s*,\s*\d+/i', $sql)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next168 needs comma-form recursive and final LIMIT syntax');
        }
        if (!self::hasFinalCommaLimit($sql)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next168 needs final comma-form LIMIT');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next168 needs a compound SELECT');
        }
        if (!in_array('UNION ALL', self::operators($currentPlan), true)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next168 needs UNION ALL');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null || ($currentPlan['compound']['offset'] ?? null) === null) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next168 needs parsed final LIMIT/OFFSET');
        }
        if (self::windowTerms($currentPlan) === []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next168 needs window arms');
        }
    }

    private static function hasFinalCommaLimit(string $sql): bool
    {
        return preg_match('/\s+LIMIT\s+\d+\s*,\s*\d+\s*;?\s*$/i', trim($sql)) === 1;
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s*,\s*\d+\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next168 cannot isolate final comma LIMIT');
        }

        return $without;
    }

    private static function recursiveTraceSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next168 cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT * FROM ' . $match[2];
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
     * @param array<string,mixed> $trace
     * @return list<string>
     */
    private static function traceLabels(array $trace, bool $emitted): array
    {
        $rows = is_array($trace['trace'] ?? null) ? $trace['trace'] : [];
        $labels = [];
        foreach ($rows as $row) {
            if (!is_array($row) || (bool) ($row['emitted'] ?? false) !== $emitted) {
                continue;
            }
            $current = $row['current'] ?? null;
            if (is_array($current) && isset($current['label'])) {
                $labels[] = (string) $current['label'];
            }
        }

        return $labels;
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
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function labels(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => isset($row['label']) && is_scalar($row['label']) ? (string) $row['label'] : '', $rows));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedSignatures(array $currentRows, array $nextRows): array
    {
        $current = array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $currentRows));
        $next = array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $nextRows));

        return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
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
        $reasons = [
            'recursive-comma-limit-offset-skipped-anchor',
            'window-values-before-compound-union-all',
            'compound-final-comma-limit-offset',
        ];
        if (self::changedSignatures($currentRows, $nextRows) !== []) {
            $reasons[] = 'limited-compound-rowset-changed';
        }
        if (self::changedSignatures($currentPreLimitRows, $nextPreLimitRows) !== []) {
            $reasons[] = 'prelimit-compound-rowset-changed';
        }
        if (self::traceLabels($currentTrace, false) !== []) {
            $reasons[] = 'recursive-anchor-skipped-by-comma-limit';
        }

        return $reasons;
    }
}
