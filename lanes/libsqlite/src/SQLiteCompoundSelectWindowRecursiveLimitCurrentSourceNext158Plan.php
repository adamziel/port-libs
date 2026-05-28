<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext158Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables): array
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next158 needs WITH RECURSIVE SQL');
        }
        if (stripos($sql, ' LIMIT ') === false || stripos($sql, ' OFFSET ') === false) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next158 needs LIMIT/OFFSET boundaries');
        }

        $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
        $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
        if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next158 needs a compound SELECT');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next158 needs a final LIMIT');
        }
        if (self::windowTerms($currentPlan) === []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next158 needs a window arm');
        }

        $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
        $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
        $preLimitSql = self::withoutFinalLimit($sql);
        $currentPreLimit = SQLiteSelectSql::execute($preLimitSql, $currentTables);
        $nextPreLimit = SQLiteSelectSql::execute($preLimitSql, $nextTables);
        $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($sql, $currentTables);
        $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($sql, $nextTables);

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next158-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'currentPreLimitRows' => $currentPreLimit,
            'nextPreLimitRows' => $nextPreLimit,
            'currentSignatures' => self::rowSignatures($currentRows),
            'nextSignatures' => self::rowSignatures($nextRows),
            'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
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
            ],
            'recursive' => self::recursiveSummary($currentTrace, $nextTrace),
            'limitTrace' => [
                'current' => self::limitTrace($currentPreLimit, $currentRows, $currentPlan),
                'next' => self::limitTrace($nextPreLimit, $nextRows, $nextPlan),
            ],
            'boundary' => [
                'currentFirst' => $currentRows[0] ?? null,
                'nextFirst' => $nextRows[0] ?? null,
                'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                'newAdmittedLabels' => self::newLabels($currentRows, $nextRows),
                'truncatedLabelsChanged' => self::changedTruncatedLabels($currentPreLimit, $nextPreLimit, $currentPlan, $nextPlan),
            ],
            'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreLimit, $nextPreLimit, $currentTrace, $nextTrace, $currentPlan, $nextPlan),
            'dependencies' => [
                'sqlite-recursive-cte-limit-offset-queue',
                'sqlite-window-arm-before-compound-select',
                'sqlite-compound-select-final-limit-offset',
                'sqlite-current-source-next158',
            ],
        ];
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
            if (!is_array($arm) || !is_array($arm['select'] ?? null)) {
                continue;
            }
            foreach ($arm['select'] as $selectIndex => $term) {
                if (!is_array($term) || ($term['type'] ?? null) !== 'window') {
                    continue;
                }
                $frame = is_array($term['frame'] ?? null) ? $term['frame'] : [];
                $windows[] = [
                    'arm' => $armIndex,
                    'selectIndex' => $selectIndex,
                    'alias' => isset($term['alias']) && is_string($term['alias']) ? $term['alias'] : 'expr' . ($selectIndex + 1),
                    'function' => (string) ($term['function'] ?? ''),
                    'hasFilter' => is_array($term['filter'] ?? null),
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
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next158 cannot isolate final LIMIT');
        }

        return $without;
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
            'currentTraceCount' => is_array($currentTrace['trace'] ?? null) ? count($currentTrace['trace']) : 0,
            'nextTraceCount' => is_array($nextTrace['trace'] ?? null) ? count($nextTrace['trace']) : 0,
            'currentEmitted' => self::traceColumn($currentTrace, 'emitted'),
            'nextEmitted' => self::traceColumn($nextTrace, 'emitted'),
            'currentLimitRemaining' => self::lastTraceValue($currentTrace, 'limit_remaining'),
            'nextLimitRemaining' => self::lastTraceValue($nextTrace, 'limit_remaining'),
            'currentOffsetRemaining' => self::lastTraceValue($currentTrace, 'offset_remaining'),
            'nextOffsetRemaining' => self::lastTraceValue($nextTrace, 'offset_remaining'),
            'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'] ?? [], $nextTrace['dependencies'] ?? []))),
        ];
    }

    /**
     * @param array<string,mixed> $trace
     * @return list<mixed>
     */
    private static function traceColumn(array $trace, string $column): array
    {
        $rows = is_array($trace['trace'] ?? null) ? $trace['trace'] : [];

        return array_values(array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows));
    }

    /**
     * @param array<string,mixed> $trace
     */
    private static function lastTraceValue(array $trace, string $key): mixed
    {
        $rows = is_array($trace['trace'] ?? null) ? $trace['trace'] : [];
        if ($rows === []) {
            return null;
        }
        $last = $rows[count($rows) - 1];

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
            'firstAdmitted' => $limitedRows[0] ?? null,
            'lastAdmitted' => $limitedRows === [] ? null : $limitedRows[count($limitedRows) - 1],
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
     * @return list<string>
     */
    private static function newLabels(array $currentRows, array $nextRows): array
    {
        return array_values(array_diff(self::labels($nextRows), self::labels($currentRows)));
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
     * @param list<array<string,mixed>> $currentPreLimit
     * @param list<array<string,mixed>> $nextPreLimit
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     * @return list<string>
     */
    private static function changedTruncatedLabels(array $currentPreLimit, array $nextPreLimit, array $currentPlan, array $nextPlan): array
    {
        $current = self::labels(self::limitTrace($currentPreLimit, [], $currentPlan)['truncatedAfterLimit']);
        $next = self::labels(self::limitTrace($nextPreLimit, [], $nextPlan)['truncatedAfterLimit']);

        return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<array<string,mixed>> $currentPreLimit
     * @param list<array<string,mixed>> $nextPreLimit
     * @param array<string,mixed> $currentTrace
     * @param array<string,mixed> $nextTrace
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     * @return list<string>
     */
    private static function replanReasons(array $currentRows, array $nextRows, array $currentPreLimit, array $nextPreLimit, array $currentTrace, array $nextTrace, array $currentPlan, array $nextPlan): array
    {
        $reasons = [];
        if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
            $reasons[] = 'limited-compound-rowset-changed';
        }
        if (self::rowSignatures($currentPreLimit) !== self::rowSignatures($nextPreLimit)) {
            $reasons[] = 'prelimit-compound-rowset-changed';
        }
        if (($currentTrace['rows'] ?? []) !== ($nextTrace['rows'] ?? [])) {
            $reasons[] = 'recursive-cte-limit-offset-rowset';
        }
        if (in_array(false, self::traceColumn($currentTrace, 'emitted'), true)) {
            $reasons[] = 'recursive-cte-offset-skipped-anchor';
        }
        if (self::windowTerms($currentPlan) !== []) {
            $reasons[] = 'window-before-compound-select';
        }
        if (($currentPlan['compound']['limit'] ?? null) !== null) {
            $reasons[] = 'compound-final-limit-offset';
        }
        if (self::windowTerms($currentPlan) !== self::windowTerms($nextPlan)) {
            $reasons[] = 'window-plan-changed';
        }

        return $reasons;
    }
}
