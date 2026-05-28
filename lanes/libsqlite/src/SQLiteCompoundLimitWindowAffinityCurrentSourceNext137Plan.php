<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundLimitWindowAffinityCurrentSourceNext137Plan
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
        if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
            throw new \InvalidArgumentException('SQLite compound LIMIT window affinity current-source plan needs a compound SELECT');
        }
        if (($currentPlan['compound']['limit'] ?? null) === null) {
            throw new \InvalidArgumentException('SQLite compound LIMIT window affinity current-source plan needs a final LIMIT');
        }
        if (self::windowTerms($currentPlan) === []) {
            throw new \InvalidArgumentException('SQLite compound LIMIT window affinity current-source plan needs a window function arm');
        }

        $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
        $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
        $currentPreLimit = SQLiteSelectSql::execute(self::withoutFinalLimit($sql), $currentTables);
        $nextPreLimit = SQLiteSelectSql::execute(self::withoutFinalLimit($sql), $nextTables);

        return [
            'status' => 'compound-limit-window-affinity-current-source-next137-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
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
            'limitTrace' => [
                'current' => self::limitTrace($currentPreLimit, $currentRows, $currentPlan),
                'next' => self::limitTrace($nextPreLimit, $nextRows, $nextPlan),
            ],
            'affinity' => [
                'currentClasses' => self::valueClasses($currentRows),
                'nextClasses' => self::valueClasses($nextRows),
                'currentPreLimitClasses' => self::valueClasses($currentPreLimit),
                'nextPreLimitClasses' => self::valueClasses($nextPreLimit),
                'changedClasses' => self::changedValueClasses($currentRows, $nextRows),
                'boundaryClasses' => self::boundaryClasses($currentRows, $nextRows),
            ],
            'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreLimit, $nextPreLimit, $currentPlan, $nextPlan),
            'dependencies' => [
                'sqlite-select-sql-compound-final-limit',
                'sqlite-select-sql-window-arm-evaluation',
                'sqlite-compound-affinity-storage-class-comparison',
                'sqlite-current-source-next-rowset-boundary',
            ],
        ];
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

        return array_values(array_map(
            static fn (array $term): string => (string) ($term['column'] ?? ''),
            $compound['orderBy'],
        ));
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    private static function windowTerms(array $plan): array
    {
        $compound = $plan['compound'] ?? null;
        if (!is_array($compound) || !is_array($compound['arms'] ?? null)) {
            return [];
        }

        $windows = [];
        foreach ($compound['arms'] as $armIndex => $arm) {
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
                    'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                    'frameUnit' => isset($frame['unit']) ? (string) $frame['unit'] : null,
                    'preceding' => $frame['preceding'] ?? null,
                    'following' => $frame['following'] ?? null,
                    'exclude' => isset($frame['exclude']) ? (string) $frame['exclude'] : null,
                ];
            }
        }

        return $windows;
    }

    private static function withoutFinalLimit(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:OFFSET\s+\d+)?\s*$/i', '', $trimmed);
        if (!is_string($without) || $without === $trimmed) {
            throw new \InvalidArgumentException('SQLite compound LIMIT window affinity current-source plan cannot isolate final LIMIT');
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
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function valueClasses(array $rows): array
    {
        $classes = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $classes[self::sqliteValueClass($value)] = true;
            }
        }

        return array_keys($classes);
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedValueClasses(array $currentRows, array $nextRows): array
    {
        $current = self::valueClasses($currentRows);
        $next = self::valueClasses($nextRows);

        return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array{currentLast:string|null,nextLast:string|null}
     */
    private static function boundaryClasses(array $currentRows, array $nextRows): array
    {
        $currentLast = $currentRows === [] ? null : self::sqliteValueClass($currentRows[count($currentRows) - 1]['class_value'] ?? null);
        $nextLast = $nextRows === [] ? null : self::sqliteValueClass($nextRows[count($nextRows) - 1]['class_value'] ?? null);

        return ['currentLast' => $currentLast, 'nextLast' => $nextLast];
    }

    private static function sqliteValueClass(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_int($value) || is_float($value)) {
            return 'numeric:' . (string) (0 + $value);
        }
        if ($value instanceof SQLiteBlobValue) {
            return 'blob:' . bin2hex($value->bytes);
        }

        return get_debug_type($value) . ':' . (string) $value;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<array<string,mixed>> $currentPreLimit
     * @param list<array<string,mixed>> $nextPreLimit
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     * @return list<string>
     */
    private static function replanReasons(array $currentRows, array $nextRows, array $currentPreLimit, array $nextPreLimit, array $currentPlan, array $nextPlan): array
    {
        $reasons = [];
        if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
            $reasons[] = 'limited-compound-rowset-changed';
        }
        if (self::rowSignatures($currentPreLimit) !== self::rowSignatures($nextPreLimit)) {
            $reasons[] = 'prelimit-compound-rowset-changed';
        }
        if (($currentPlan['compound']['limit'] ?? null) !== null) {
            $reasons[] = 'compound-final-limit';
        }
        if (self::windowTerms($currentPlan) !== []) {
            $reasons[] = 'compound-window-arm-source';
        }
        if (self::changedValueClasses($currentRows, $nextRows) !== []) {
            $reasons[] = 'affinity-class-boundary-changed';
        }
        if (self::windowTerms($currentPlan) !== self::windowTerms($nextPlan)) {
            $reasons[] = 'window-plan-changed';
        }

        return $reasons;
    }
}
