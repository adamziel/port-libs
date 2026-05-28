<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundRecursiveAffinityWindowCurrentSourceNext129Plan
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
            throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source plan needs a compound SELECT');
        }

        $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
        $nextRows = SQLiteSelectSql::execute($sql, $nextTables);

        return [
            'status' => 'compound-recursive-affinity-window-current-source-next129-ready',
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
            ],
            'windows' => [
                'current' => self::windowTerms($currentPlan),
                'next' => self::windowTerms($nextPlan),
            ],
            'recursive' => self::recursiveSummary($sql, $currentTables, $nextTables),
            'affinity' => [
                'currentDuplicateClasses' => self::duplicateClasses($currentRows),
                'nextDuplicateClasses' => self::duplicateClasses($nextRows),
                'changedClasses' => self::changedValueClasses($currentRows, $nextRows),
            ],
            'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPlan, $nextPlan),
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
                $windows[] = [
                    'arm' => $armIndex,
                    'selectIndex' => $selectIndex,
                    'alias' => isset($term['alias']) && is_string($term['alias']) ? $term['alias'] : 'expr' . ($selectIndex + 1),
                    'function' => (string) ($term['function'] ?? ''),
                    'hasFilter' => is_array($term['filter'] ?? null),
                    'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                    'frameUnit' => is_array($term['frame'] ?? null) ? (string) ($term['frame']['unit'] ?? '') : null,
                ];
            }
        }

        return $windows;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @return array<string,mixed>
     */
    private static function recursiveSummary(string $sql, array $currentTables, array $nextTables): array
    {
        $traceSql = self::traceSql($sql);
        $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
        $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

        return [
            'name' => $currentTrace['name'],
            'columns' => $currentTrace['columns'],
            'currentRows' => $currentTrace['rows'],
            'nextRows' => $nextTrace['rows'],
            'currentSkipped' => array_values(array_map(static fn (array $row): array => $row['row'], $currentTrace['skipped'])),
            'nextSkipped' => array_values(array_map(static fn (array $row): array => $row['row'], $nextTrace['skipped'])),
            'currentTraceCount' => count($currentTrace['trace']),
            'nextTraceCount' => count($nextTrace['trace']),
            'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'], $nextTrace['dependencies']))),
        ];
    }

    private static function traceSql(string $sql): string
    {
        $sql = trim(rtrim(trim($sql), ';'));
        $with = stripos($sql, 'WITH RECURSIVE');
        if ($with !== 0) {
            throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source plan needs WITH RECURSIVE');
        }

        if (preg_match('/^(.*\))\s*SELECT\s+node\s+AS\s+id\b/is', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source plan cannot isolate recursive CTE');
        }

        return $match[1] . ' SELECT node, weight FROM wanted';
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
    private static function duplicateClasses(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $key = self::sqliteValueClass($value);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return array_values(array_keys(array_filter($counts, static fn (int $count): bool => $count > 1)));
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
     * @param array<string,mixed> $currentPlan
     * @param array<string,mixed> $nextPlan
     * @return list<string>
     */
    private static function replanReasons(array $currentRows, array $nextRows, array $currentPlan, array $nextPlan): array
    {
        $reasons = [];
        if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
            $reasons[] = 'compound-rowset-changed';
        }
        if (self::windowTerms($currentPlan) !== []) {
            $reasons[] = 'compound-window-source';
        }
        if (self::changedValueClasses($currentRows, $nextRows) !== []) {
            $reasons[] = 'affinity-class-changed';
        }
        if (self::windowTerms($currentPlan) !== self::windowTerms($nextPlan)) {
            $reasons[] = 'window-plan-changed';
        }

        return $reasons;
    }
}
