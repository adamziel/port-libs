<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareRecursiveAffinityWindow(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source plan needs a compound SELECT');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);

            return [
                'status' => 'compound-recursive-affinity-window-current-source-ready',
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
                'recursive' => self::recursiveNodeWeightSummary($sql, $currentTables, $nextTables),
                'affinity' => [
                    'currentDuplicateClasses' => self::duplicateValueClasses($currentRows),
                    'nextDuplicateClasses' => self::duplicateValueClasses($nextRows),
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
        private static function recursiveNodeWeightSummary(string $sql, array $currentTables, array $nextTables): array
        {
            $traceSql = self::traceNodeWeightSql($sql);
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

        private static function traceNodeWeightSql(string $sql): string
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
        private static function duplicateValueClasses(array $rows): array
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

    /* Variant formerly implemented by SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareRecursiveUnionSourceBoundary(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source source-boundary plan needs a compound SELECT');
            }
            if (!str_starts_with(strtoupper(ltrim($sql)), 'WITH RECURSIVE')) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source source-boundary plan needs WITH RECURSIVE');
            }
            if (self::windowTermsForSourceBoundary($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source source-boundary plan needs a window function arm');
            }
            if (!in_array('UNION', array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])), true)) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source source-boundary plan needs a DISTINCT UNION operator');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);

            return [
                'status' => 'compound-recursive-affinity-window-current-source-source-boundary-ready',
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
                    'leftColumns' => self::leftColumns($currentPlan),
                ],
                'windows' => [
                    'current' => self::windowTermsForSourceBoundary($currentPlan),
                    'next' => self::windowTermsForSourceBoundary($nextPlan),
                ],
                'recursive' => self::recursiveSourceBoundarySummary($sql, $currentTables, $nextTables),
                'affinity' => [
                    'currentKeyClasses' => self::columnClasses($currentRows, 'key_value'),
                    'nextKeyClasses' => self::columnClasses($nextRows, 'key_value'),
                    'currentDuplicateKeys' => self::duplicateColumnClasses($currentRows, 'key_value'),
                    'nextDuplicateKeys' => self::duplicateColumnClasses($nextRows, 'key_value'),
                    'changedKeyClasses' => self::changedColumnClasses($currentRows, $nextRows, 'key_value'),
                ],
                'sourceDelta' => self::sourceDelta($currentRows, $nextRows),
                'replanReasons' => self::replanReasonsForSourceBoundary($currentRows, $nextRows, $currentPlan, $nextPlan),
                'dependencies' => [
                    'sqlite-recursive-cte-union-affinity-dedup',
                    'sqlite-window-arm-before-compound-union',
                    'sqlite-compound-left-column-name-retention',
                    'sqlite-current-source-next-rowset-boundary',
                ],
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function leftColumns(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            $arms = is_array($compound) && is_array($compound['arms'] ?? null) ? $compound['arms'] : [];
            $first = $arms[0] ?? null;
            $select = is_array($first) && is_array($first['select'] ?? null) ? $first['select'] : [];
            $columns = [];
            foreach ($select as $index => $term) {
                if (!is_array($term)) {
                    continue;
                }
                if (isset($term['alias']) && is_string($term['alias']) && $term['alias'] !== '') {
                    $columns[] = $term['alias'];
                    continue;
                }
                if (($term['type'] ?? null) === 'column' && isset($term['name']) && is_string($term['name']) && $term['name'] !== '') {
                    $name = $term['name'];
                    $columns[] = str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name;
                    continue;
                }
                $columns[] = 'expr' . ($index + 1);
            }

            return $columns;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function windowTermsForSourceBoundary(array $plan): array
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

        /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        private static function recursiveSourceBoundarySummary(string $sql, array $currentTables, array $nextTables): array
        {
            $traceSql = self::traceSourceBoundarySql($sql);
            $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
            $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

            return [
                'name' => $currentTrace['name'],
                'columns' => $currentTrace['columns'],
                'operator' => $currentTrace['operator'] ?? null,
                'currentRows' => $currentTrace['rows'],
                'nextRows' => $nextTrace['rows'],
                'currentSkipped' => array_values(array_map(static fn (array $row): array => $row['row'], $currentTrace['skipped'])),
                'nextSkipped' => array_values(array_map(static fn (array $row): array => $row['row'], $nextTrace['skipped'])),
                'currentTraceCount' => count($currentTrace['trace']),
                'nextTraceCount' => count($nextTrace['trace']),
                'currentLimitRemaining' => $currentTrace['limitRemaining'] ?? null,
                'nextLimitRemaining' => $nextTrace['limitRemaining'] ?? null,
                'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'], $nextTrace['dependencies']))),
            ];
        }

        private static function traceSourceBoundarySql(string $sql): string
        {
            $sql = trim(rtrim(trim($sql), ';'));
            if (!str_starts_with(strtoupper($sql), 'WITH RECURSIVE')) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source source-boundary plan needs WITH RECURSIVE');
            }
            if (preg_match('/^(.*\))\s*SELECT\s+item_id\s+AS\s+id\b/is', $sql, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source source-boundary plan cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT item_id, key_value, source FROM option_walk';
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function columnClasses(array $rows, string $column): array
        {
            $classes = [];
            foreach ($rows as $row) {
                if (array_key_exists($column, $row)) {
                    $classes[self::sqliteValueClass($row[$column])] = true;
                }
            }

            return array_keys($classes);
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function duplicateColumnClasses(array $rows, string $column): array
        {
            $counts = [];
            foreach ($rows as $row) {
                if (!array_key_exists($column, $row)) {
                    continue;
                }
                $key = self::sqliteValueClass($row[$column]);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }

            return array_values(array_keys(array_filter($counts, static fn (int $count): bool => $count > 1)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedColumnClasses(array $currentRows, array $nextRows, string $column): array
        {
            $current = self::columnClasses($currentRows, $column);
            $next = self::columnClasses($nextRows, $column);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return array{currentSources:array<string,int>,nextSources:array<string,int>,newSources:list<string>,removedSources:list<string>}
         */
        private static function sourceDelta(array $currentRows, array $nextRows): array
        {
            $currentSources = self::sourceCounts($currentRows);
            $nextSources = self::sourceCounts($nextRows);

            return [
                'currentSources' => $currentSources,
                'nextSources' => $nextSources,
                'newSources' => array_values(array_diff(array_keys($nextSources), array_keys($currentSources))),
                'removedSources' => array_values(array_diff(array_keys($currentSources), array_keys($nextSources))),
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return array<string,int>
         */
        private static function sourceCounts(array $rows): array
        {
            $counts = [];
            foreach ($rows as $row) {
                $source = isset($row['source']) && is_scalar($row['source']) ? (string) $row['source'] : '';
                $counts[$source] = ($counts[$source] ?? 0) + 1;
            }
            ksort($counts);

            return $counts;
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         * @return list<string>
         */
        private static function replanReasonsForSourceBoundary(array $currentRows, array $nextRows, array $currentPlan, array $nextPlan): array
        {
            $reasons = [];
            if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
                $reasons[] = 'compound-rowset-changed';
            }
            if (self::windowTermsForSourceBoundary($currentPlan) !== []) {
                $reasons[] = 'window-before-compound-union';
            }
            if (self::changedColumnClasses($currentRows, $nextRows, 'key_value') !== []) {
                $reasons[] = 'affinity-key-class-changed';
            }
            if (self::sourceDelta($currentRows, $nextRows)['newSources'] !== []) {
                $reasons[] = 'current-next-source-boundary-changed';
            }
            if (self::windowTermsForSourceBoundary($currentPlan) !== self::windowTermsForSourceBoundary($nextPlan)) {
                $reasons[] = 'window-plan-changed';
            }

            return $reasons;
        }

}
