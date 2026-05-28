<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext156Plan
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
            'status' => 'compound-select-window-recursive-limit-current-source-next156-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'currentPreLimitRows' => $currentPreLimitRows,
            'nextPreLimitRows' => $nextPreLimitRows,
            'compound' => [
                'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                'orderColumns' => self::orderColumns($currentPlan),
                'limit' => $currentPlan['compound']['limit'],
                'offset' => $currentPlan['compound']['offset'] ?? 0,
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
            'limitTrace' => [
                'current' => self::limitTrace($currentPreLimitRows, $currentRows, $currentPlan),
                'next' => self::limitTrace($nextPreLimitRows, $nextRows, $nextPlan),
            ],
            'boundary' => self::boundaryDelta($currentRows, $nextRows),
            'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
            'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreLimitRows, $nextPreLimitRows, $currentTrace, $nextTrace),
            'dependencies' => [
                'sqlite-recursive-cte-queue-limit-before-compound-next156',
                'sqlite-window-arm-values-before-compound-limit-next156',
                'sqlite-compound-final-limit-current-source-boundary-next156',
            ],
            'dependency_closure' => 'no new support component needed; this reuses lane-local SELECT SQL, recursive CTE, compound combiner, window execution, and LIMIT/OFFSET helpers',
        ];
    }

    /**
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     */
    private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next156 needs WITH RECURSIVE SQL');
        }
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next156 needs a compound SELECT');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next156 needs a final LIMIT');
        }
        if (self::windowTerms($currentPlan) === []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next156 needs window expressions in compound arms');
        }
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<string>
     */
    private static function orderColumns(array $plan): array
    {
        $compound = $plan['compound'] ?? null;
        if (!is_array($compound) || !is_array($compound['orderBy'] ?? null)) {
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
        $compound = $plan['compound'] ?? null;
        $arms = is_array($compound) && is_array($compound['arms'] ?? null) ? $compound['arms'] : [];
        $windows = [];
        foreach ($arms as $armIndex => $arm) {
            $select = is_array($arm) && is_array($arm['select'] ?? null) ? $arm['select'] : [];
            foreach ($select as $selectIndex => $term) {
                if (!is_array($term) || ($term['type'] ?? null) !== 'window') {
                    continue;
                }
                $frame = is_array($term['frame'] ?? null) ? $term['frame'] : [];
                $windows[] = [
                    'arm' => $armIndex,
                    'selectIndex' => $selectIndex,
                    'alias' => isset($term['alias']) && is_string($term['alias']) ? $term['alias'] : 'expr' . ($selectIndex + 1),
                    'function' => (string) ($term['function'] ?? ''),
                    'argumentCount' => is_array($term['arguments'] ?? null) ? count($term['arguments']) : 0,
                    'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                    'frameUnit' => isset($frame['unit']) ? (string) $frame['unit'] : null,
                    'preceding' => $frame['preceding'] ?? null,
                    'following' => $frame['following'] ?? null,
                ];
            }
        }

        return $windows;
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next156 cannot isolate final LIMIT');
        }

        return $without;
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
     * @param array<string,mixed> $trace
     */
    private static function lastTraceValue(array $trace, string $key): mixed
    {
        $rows = is_array($trace['trace'] ?? null) ? $trace['trace'] : [];
        $last = $rows === [] ? null : $rows[count($rows) - 1];

        return is_array($last) ? ($last[$key] ?? null) : null;
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
     * @param array<string,mixed> $nextTrace
     * @return list<string>
     */
    private static function replanReasons(array $currentRows, array $nextRows, array $currentPreLimitRows, array $nextPreLimitRows, array $currentTrace, array $nextTrace): array
    {
        $reasons = ['window-before-compound-limit', 'compound-final-limit'];
        if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
            $reasons[] = 'limited-compound-rowset-changed';
        }
        if (self::rowSignatures($currentPreLimitRows) !== self::rowSignatures($nextPreLimitRows)) {
            $reasons[] = 'prelimit-compound-rowset-changed';
        }
        if (($currentTrace['rows'] ?? []) !== ($nextTrace['rows'] ?? [])) {
            $reasons[] = 'recursive-cte-rowset-changed';
        }
        if (self::lastTraceValue($currentTrace, 'limit_remaining') === 0) {
            $reasons[] = 'recursive-limit-exhausted-before-compound';
        }

        return $reasons;
    }
}
