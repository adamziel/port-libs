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
        public static function compareNext129(string $sql, array $currentTables, array $nextTables): array
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
                'currentSignatures' => self::rowSignaturesNext129($currentRows),
                'nextSignatures' => self::rowSignaturesNext129($nextRows),
                'changedSignatures' => self::changedSignaturesNext129($currentRows, $nextRows),
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext129($currentPlan),
                ],
                'windows' => [
                    'current' => self::windowTermsNext129($currentPlan),
                    'next' => self::windowTermsNext129($nextPlan),
                ],
                'recursive' => self::recursiveSummaryNext129($sql, $currentTables, $nextTables),
                'affinity' => [
                    'currentDuplicateClasses' => self::duplicateClassesNext129($currentRows),
                    'nextDuplicateClasses' => self::duplicateClassesNext129($nextRows),
                    'changedClasses' => self::changedValueClassesNext129($currentRows, $nextRows),
                ],
                'replanReasons' => self::replanReasonsNext129($currentRows, $nextRows, $currentPlan, $nextPlan),
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumnsNext129(array $plan): array
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
        private static function windowTermsNext129(array $plan): array
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
        private static function recursiveSummaryNext129(string $sql, array $currentTables, array $nextTables): array
        {
            $traceSql = self::traceSqlNext129($sql);
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

        private static function traceSqlNext129(string $sql): string
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
        private static function rowSignaturesNext129(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext129(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext129($currentRows);
            $next = self::rowSignaturesNext129($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function duplicateClassesNext129(array $rows): array
        {
            $counts = [];
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    $key = self::sqliteValueClassNext129($value);
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
        private static function changedValueClassesNext129(array $currentRows, array $nextRows): array
        {
            $current = self::valueClassesNext129($currentRows);
            $next = self::valueClassesNext129($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function valueClassesNext129(array $rows): array
        {
            $classes = [];
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    $classes[self::sqliteValueClassNext129($value)] = true;
                }
            }

            return array_keys($classes);
        }

        private static function sqliteValueClassNext129(mixed $value): string
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
        private static function replanReasonsNext129(array $currentRows, array $nextRows, array $currentPlan, array $nextPlan): array
        {
            $reasons = [];
            if (self::rowSignaturesNext129($currentRows) !== self::rowSignaturesNext129($nextRows)) {
                $reasons[] = 'compound-rowset-changed';
            }
            if (self::windowTermsNext129($currentPlan) !== []) {
                $reasons[] = 'compound-window-source';
            }
            if (self::changedValueClassesNext129($currentRows, $nextRows) !== []) {
                $reasons[] = 'affinity-class-changed';
            }
            if (self::windowTermsNext129($currentPlan) !== self::windowTermsNext129($nextPlan)) {
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
        public static function compareNext142(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source next142 plan needs a compound SELECT');
            }
            if (!str_starts_with(strtoupper(ltrim($sql)), 'WITH RECURSIVE')) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source next142 plan needs WITH RECURSIVE');
            }
            if (self::windowTermsNext142($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source next142 plan needs a window function arm');
            }
            if (!in_array('UNION', array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])), true)) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source next142 plan needs a DISTINCT UNION operator');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);

            return [
                'status' => 'compound-recursive-affinity-window-current-source-next142-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentSignatures' => self::rowSignaturesNext142($currentRows),
                'nextSignatures' => self::rowSignaturesNext142($nextRows),
                'changedSignatures' => self::changedSignaturesNext142($currentRows, $nextRows),
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext142($currentPlan),
                    'leftColumns' => self::leftColumnsNext142($currentPlan),
                ],
                'windows' => [
                    'current' => self::windowTermsNext142($currentPlan),
                    'next' => self::windowTermsNext142($nextPlan),
                ],
                'recursive' => self::recursiveSummaryNext142($sql, $currentTables, $nextTables),
                'affinity' => [
                    'currentKeyClasses' => self::columnClassesNext142($currentRows, 'key_value'),
                    'nextKeyClasses' => self::columnClassesNext142($nextRows, 'key_value'),
                    'currentDuplicateKeys' => self::duplicateColumnClassesNext142($currentRows, 'key_value'),
                    'nextDuplicateKeys' => self::duplicateColumnClassesNext142($nextRows, 'key_value'),
                    'changedKeyClasses' => self::changedColumnClassesNext142($currentRows, $nextRows, 'key_value'),
                ],
                'sourceDelta' => self::sourceDeltaNext142($currentRows, $nextRows),
                'replanReasons' => self::replanReasonsNext142($currentRows, $nextRows, $currentPlan, $nextPlan),
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
        private static function orderColumnsNext142(array $plan): array
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
         * @return list<string>
         */
        private static function leftColumnsNext142(array $plan): array
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
        private static function windowTermsNext142(array $plan): array
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
        private static function recursiveSummaryNext142(string $sql, array $currentTables, array $nextTables): array
        {
            $traceSql = self::traceSqlNext142($sql);
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

        private static function traceSqlNext142(string $sql): string
        {
            $sql = trim(rtrim(trim($sql), ';'));
            if (!str_starts_with(strtoupper($sql), 'WITH RECURSIVE')) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source next142 plan needs WITH RECURSIVE');
            }
            if (preg_match('/^(.*\))\s*SELECT\s+item_id\s+AS\s+id\b/is', $sql, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity window current-source next142 plan cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT item_id, key_value, source FROM option_walk';
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignaturesNext142(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext142(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext142($currentRows);
            $next = self::rowSignaturesNext142($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function columnClassesNext142(array $rows, string $column): array
        {
            $classes = [];
            foreach ($rows as $row) {
                if (array_key_exists($column, $row)) {
                    $classes[self::sqliteValueClassNext142($row[$column])] = true;
                }
            }

            return array_keys($classes);
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function duplicateColumnClassesNext142(array $rows, string $column): array
        {
            $counts = [];
            foreach ($rows as $row) {
                if (!array_key_exists($column, $row)) {
                    continue;
                }
                $key = self::sqliteValueClassNext142($row[$column]);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }

            return array_values(array_keys(array_filter($counts, static fn (int $count): bool => $count > 1)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedColumnClassesNext142(array $currentRows, array $nextRows, string $column): array
        {
            $current = self::columnClassesNext142($currentRows, $column);
            $next = self::columnClassesNext142($nextRows, $column);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return array{currentSources:array<string,int>,nextSources:array<string,int>,newSources:list<string>,removedSources:list<string>}
         */
        private static function sourceDeltaNext142(array $currentRows, array $nextRows): array
        {
            $currentSources = self::sourceCountsNext142($currentRows);
            $nextSources = self::sourceCountsNext142($nextRows);

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
        private static function sourceCountsNext142(array $rows): array
        {
            $counts = [];
            foreach ($rows as $row) {
                $source = isset($row['source']) && is_scalar($row['source']) ? (string) $row['source'] : '';
                $counts[$source] = ($counts[$source] ?? 0) + 1;
            }
            ksort($counts);

            return $counts;
        }

        private static function sqliteValueClassNext142(mixed $value): string
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
        private static function replanReasonsNext142(array $currentRows, array $nextRows, array $currentPlan, array $nextPlan): array
        {
            $reasons = [];
            if (self::rowSignaturesNext142($currentRows) !== self::rowSignaturesNext142($nextRows)) {
                $reasons[] = 'compound-rowset-changed';
            }
            if (self::windowTermsNext142($currentPlan) !== []) {
                $reasons[] = 'window-before-compound-union';
            }
            if (self::changedColumnClassesNext142($currentRows, $nextRows, 'key_value') !== []) {
                $reasons[] = 'affinity-key-class-changed';
            }
            if (self::sourceDeltaNext142($currentRows, $nextRows)['newSources'] !== []) {
                $reasons[] = 'current-next-source-boundary-changed';
            }
            if (self::windowTermsNext142($currentPlan) !== self::windowTermsNext142($nextPlan)) {
                $reasons[] = 'window-plan-changed';
            }

            return $reasons;
        }

}
