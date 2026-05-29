<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectRecursiveAffinityLimitPlan
{

    /*  */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareRecursiveAffinityLimit(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            self::assertSupported($sql, $currentPlan, $nextPlan);

            $unlimitedSql = self::withoutFinalLimit($sql);
            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentUnlimitedRows = SQLiteSelectSql::execute($unlimitedSql, $currentTables);
            $nextUnlimitedRows = SQLiteSelectSql::execute($unlimitedSql, $nextTables);
            $currentRecursive = SQLiteSelectSql::recursiveCteCycleTrace(self::traceSql($sql), $currentTables);
            $nextRecursive = SQLiteSelectSql::recursiveCteCycleTrace(self::traceSql($sql), $nextTables);

            return [
                'status' => 'compound-select-recursive-affinity-limit-ready',
                'dependencies' => [
                    'sqlite-recursive-cte-union-affinity-dedup',
                    'sqlite-compound-select-final-limit-after-union',
                    'sqlite-compound-select-left-column-name-affinity',
                ],
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumns($currentPlan),
                    'leftColumns' => self::leftColumns($currentPlan),
                    'limit' => $currentPlan['compound']['limit'] ?? null,
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                ],
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentUnlimitedRows' => $currentUnlimitedRows,
                'nextUnlimitedRows' => $nextUnlimitedRows,
                'recursive' => [
                    'name' => $currentRecursive['name'],
                    'columns' => $currentRecursive['columns'],
                    'operator' => $currentRecursive['operator'] ?? null,
                    'currentRows' => $currentRecursive['rows'],
                    'nextRows' => $nextRecursive['rows'],
                    'currentSkipped' => self::skippedRows($currentRecursive),
                    'nextSkipped' => self::skippedRows($nextRecursive),
                    'currentTraceCount' => count($currentRecursive['trace']),
                    'nextTraceCount' => count($nextRecursive['trace']),
                    'dependencies' => array_values(array_unique(array_merge($currentRecursive['dependencies'], $nextRecursive['dependencies']))),
                ],
                'affinity' => [
                    'currentKeyClasses' => self::columnClasses($currentUnlimitedRows, 'key_value'),
                    'nextKeyClasses' => self::columnClasses($nextUnlimitedRows, 'key_value'),
                    'currentDuplicateClasses' => self::duplicateColumnClasses($currentUnlimitedRows, 'key_value'),
                    'nextDuplicateClasses' => self::duplicateColumnClasses($nextUnlimitedRows, 'key_value'),
                    'changedKeyClasses' => self::changedColumnClasses($currentUnlimitedRows, $nextUnlimitedRows, 'key_value'),
                ],
                'limitTrace' => [
                    'current' => self::limitTrace($currentUnlimitedRows, $currentRows, $currentPlan),
                    'next' => self::limitTrace($nextUnlimitedRows, $nextRows, $nextPlan),
                ],
                'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
                'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentUnlimitedRows, $nextUnlimitedRows),
            ];
        }

        /**
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         */
        private static function assertSupported(string $sql, array $currentPlan, array $nextPlan): void
        {
            if (!str_starts_with(strtoupper(ltrim($sql)), 'WITH RECURSIVE')) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity limit needs WITH RECURSIVE');
            }
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity limit needs a compound SELECT');
            }
            $operators = array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? []));
            if ($operators !== ['UNION']) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity limit needs a DISTINCT UNION operator');
            }
            if (($currentPlan['compound']['limit'] ?? null) === null) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity limit needs a final LIMIT');
            }
        }

        private static function withoutFinalLimit(string $sql): string
        {
            $trimmed = trim(rtrim(trim($sql), ';'));
            $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:OFFSET\s+\d+)?\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity limit cannot isolate final LIMIT');
            }

            return $without;
        }

        private static function traceSql(string $sql): string
        {
            $trimmed = trim(rtrim(trim($sql), ';'));
            if (preg_match('/^(.*\))\s*SELECT\s+item_id\s+AS\s+id\b/is', $trimmed, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound recursive affinity limit cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT item_id, key_value, source FROM option_walk';
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
         * @param array<string,mixed> $trace
         * @return list<array<string,mixed>>
         */
        private static function skippedRows(array $trace): array
        {
            $skipped = [];
            foreach ($trace['skipped'] ?? [] as $row) {
                if (is_array($row) && is_array($row['row'] ?? null)) {
                    $skipped[] = $row['row'];
                }
            }

            return $skipped;
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
                if (array_key_exists($column, $row)) {
                    $key = self::sqliteValueClass($row[$column]);
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
        private static function changedColumnClasses(array $currentRows, array $nextRows, string $column): array
        {
            return array_values(array_merge(
                array_diff(self::columnClasses($currentRows, $column), self::columnClasses($nextRows, $column)),
                array_diff(self::columnClasses($nextRows, $column), self::columnClasses($currentRows, $column)),
            ));
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
            return array_values(array_merge(
                array_diff(self::rowSignatures($currentRows), self::rowSignatures($nextRows)),
                array_diff(self::rowSignatures($nextRows), self::rowSignatures($currentRows)),
            ));
        }

        private static function sqliteValueClass(mixed $value): string
        {
            if ($value === null) {
                return 'null';
            }
            if (is_bool($value) || is_int($value) || is_float($value)) {
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
         * @param list<array<string,mixed>> $currentUnlimitedRows
         * @param list<array<string,mixed>> $nextUnlimitedRows
         * @return list<string>
         */
        private static function replanReasons(array $currentRows, array $nextRows, array $currentUnlimitedRows, array $nextUnlimitedRows): array
        {
            $reasons = ['compound-recursive-final-limit'];
            if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
                $reasons[] = 'limited-rowset-boundary-changed';
            }
            if (self::rowSignatures($currentUnlimitedRows) !== self::rowSignatures($nextUnlimitedRows)) {
                $reasons[] = 'recursive-union-source-rowset-changed';
            }
            if (self::columnClasses($currentUnlimitedRows, 'key_value') !== self::columnClasses($nextUnlimitedRows, 'key_value')) {
                $reasons[] = 'affinity-storage-classes-changed';
            }

            return $reasons;
        }

}
