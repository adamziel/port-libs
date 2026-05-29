<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundWindowExceptAffinityCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundWindowExceptAffinityCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareWindowExceptAffinity(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound window EXCEPT affinity current-source next133 plan needs a compound SELECT');
            }

            $operators = array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? []));
            if (!in_array('EXCEPT', $operators, true)) {
                throw new \InvalidArgumentException('SQLite compound window EXCEPT affinity current-source next133 plan needs an EXCEPT arm');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);

            $currentRemoved = self::exceptRemovedRows($currentPlan, $currentTables);
            $nextRemoved = self::exceptRemovedRows($nextPlan, $nextTables);

            return [
                'status' => 'compound-window-except-affinity-current-source-next133-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentSignatures' => self::rowSignatures($currentRows),
                'nextSignatures' => self::rowSignatures($nextRows),
                'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
                'compound' => [
                    'operators' => $operators,
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumns($currentPlan),
                    'exceptArmIndexes' => self::exceptArmIndexes($operators),
                ],
                'windows' => [
                    'current' => self::windowTerms($currentPlan),
                    'next' => self::windowTerms($nextPlan),
                    'aliases' => array_values(array_unique(array_merge(
                        array_column(self::windowTerms($currentPlan), 'alias'),
                        array_column(self::windowTerms($nextPlan), 'alias'),
                    ))),
                ],
                'affinity' => [
                    'currentClasses' => self::valueClasses($currentRows),
                    'nextClasses' => self::valueClasses($nextRows),
                    'currentDuplicateClasses' => self::duplicateClasses($currentRows),
                    'nextDuplicateClasses' => self::duplicateClasses($nextRows),
                    'changedClasses' => self::changedValueClasses($currentRows, $nextRows),
                ],
                'except' => [
                    'currentRemoved' => $currentRemoved,
                    'nextRemoved' => $nextRemoved,
                ],
                'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPlan, $nextPlan, $currentRemoved, $nextRemoved),
                'dependencies' => [
                    'sqlite-compound-except-affinity',
                    'sqlite-window-arm-current-source',
                    'sqlite-select-sql-current-source-next133',
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
         * @param list<string> $operators
         * @return list<int>
         */
        private static function exceptArmIndexes(array $operators): array
        {
            $indexes = [];
            foreach ($operators as $index => $operator) {
                if ($operator === 'EXCEPT') {
                    $indexes[] = $index + 1;
                }
            }

            return $indexes;
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
         * @param array<string,mixed> $plan
         * @param array<string,list<array<string,mixed>>> $tables
         * @return list<array<string,mixed>>
         */
        private static function exceptRemovedRows(array $plan, array $tables): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['arms'] ?? null) || !is_array($compound['operators'] ?? null)) {
                return [];
            }

            $removed = [];
            $rows = null;
            foreach ($compound['arms'] as $index => $arm) {
                if (!is_array($arm)) {
                    continue;
                }
                $armRows = self::executeArm($arm);
                if ($index === 0) {
                    $rows = $armRows;
                    continue;
                }

                $operator = strtoupper((string) ($compound['operators'][$index - 1] ?? ''));
                if ($operator === 'EXCEPT' && is_array($rows)) {
                    $nextRows = SQLiteSelectCompound::combine($rows, $armRows, 'EXCEPT', self::compoundSelectCollations($compound['arms'][0]));
                    $removed = array_merge($removed, self::removedBySignature($rows, $nextRows));
                    $rows = $nextRows;
                    continue;
                }

                if (is_array($rows)) {
                    $rows = SQLiteSelectCompound::combine($rows, $armRows, $operator, self::compoundSelectCollations($compound['arms'][0]));
                }
            }

            return $removed;
        }

        /**
         * @param array<string,mixed> $arm
         * @return list<array<string,mixed>>
         */
        private static function executeArm(array $arm): array
        {
            $rows = SQLiteSelectQuery::execute($arm);
            $hidden = [];
            foreach (($arm['select'] ?? []) as $term) {
                if (is_array($term) && isset($term['hiddenOrderColumn']) && is_string($term['hiddenOrderColumn'])) {
                    $hidden[] = $term['hiddenOrderColumn'];
                }
            }
            if ($hidden === []) {
                return $rows;
            }

            return array_values(array_map(static function (array $row) use ($hidden): array {
                foreach ($hidden as $column) {
                    unset($row[$column]);
                }

                return $row;
            }, $rows));
        }

        /**
         * @param list<array<string,mixed>> $before
         * @param list<array<string,mixed>> $after
         * @return list<array<string,mixed>>
         */
        private static function removedBySignature(array $before, array $after): array
        {
            $afterSignatures = array_fill_keys(self::rowSignatures($after), true);
            $removed = [];
            foreach ($before as $row) {
                if (!isset($afterSignatures[json_encode($row, JSON_THROW_ON_ERROR)])) {
                    $removed[] = $row;
                }
            }

            return $removed;
        }

        /**
         * @param array<string,mixed> $arm
         * @return array<string,string>
         */
        private static function compoundSelectCollations(array $arm): array
        {
            if (!is_array($arm['select'] ?? null)) {
                return [];
            }

            $collations = [];
            foreach ($arm['select'] as $index => $term) {
                if (!is_array($term)) {
                    continue;
                }
                $column = isset($term['alias']) && is_string($term['alias']) && $term['alias'] !== '' ? $term['alias'] : 'expr' . ($index + 1);
                if (isset($term['collation']) && is_string($term['collation']) && $term['collation'] !== '') {
                    $collations[$column] = strtoupper($term['collation']);
                }
            }

            return $collations;
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
         * @param list<array<string,mixed>> $currentRemoved
         * @param list<array<string,mixed>> $nextRemoved
         * @return list<string>
         */
        private static function replanReasons(array $currentRows, array $nextRows, array $currentPlan, array $nextPlan, array $currentRemoved, array $nextRemoved): array
        {
            $reasons = [];
            if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
                $reasons[] = 'compound-except-rowset-changed';
            }
            if (self::windowTerms($currentPlan) !== []) {
                $reasons[] = 'compound-window-arm-source';
            }
            if (self::changedValueClasses($currentRows, $nextRows) !== []) {
                $reasons[] = 'affinity-class-changed';
            }
            if (self::rowSignatures($currentRemoved) !== self::rowSignatures($nextRemoved)) {
                $reasons[] = 'except-removal-set-changed';
            }

            return array_values(array_unique($reasons));
        }

}
