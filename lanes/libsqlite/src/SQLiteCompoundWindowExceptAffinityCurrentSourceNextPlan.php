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
        public static function compareNext133(string $sql, array $currentTables, array $nextTables): array
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

            $currentRemoved = self::exceptRemovedRowsNext133($currentPlan, $currentTables);
            $nextRemoved = self::exceptRemovedRowsNext133($nextPlan, $nextTables);

            return [
                'status' => 'compound-window-except-affinity-current-source-next133-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentSignatures' => self::rowSignaturesNext133($currentRows),
                'nextSignatures' => self::rowSignaturesNext133($nextRows),
                'changedSignatures' => self::changedSignaturesNext133($currentRows, $nextRows),
                'compound' => [
                    'operators' => $operators,
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext133($currentPlan),
                    'exceptArmIndexes' => self::exceptArmIndexesNext133($operators),
                ],
                'windows' => [
                    'current' => self::windowTermsNext133($currentPlan),
                    'next' => self::windowTermsNext133($nextPlan),
                    'aliases' => array_values(array_unique(array_merge(
                        array_column(self::windowTermsNext133($currentPlan), 'alias'),
                        array_column(self::windowTermsNext133($nextPlan), 'alias'),
                    ))),
                ],
                'affinity' => [
                    'currentClasses' => self::valueClassesNext133($currentRows),
                    'nextClasses' => self::valueClassesNext133($nextRows),
                    'currentDuplicateClasses' => self::duplicateClassesNext133($currentRows),
                    'nextDuplicateClasses' => self::duplicateClassesNext133($nextRows),
                    'changedClasses' => self::changedValueClassesNext133($currentRows, $nextRows),
                ],
                'except' => [
                    'currentRemoved' => $currentRemoved,
                    'nextRemoved' => $nextRemoved,
                ],
                'replanReasons' => self::replanReasonsNext133($currentRows, $nextRows, $currentPlan, $nextPlan, $currentRemoved, $nextRemoved),
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
        private static function orderColumnsNext133(array $plan): array
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
        private static function exceptArmIndexesNext133(array $operators): array
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
        private static function windowTermsNext133(array $plan): array
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
        private static function exceptRemovedRowsNext133(array $plan, array $tables): array
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
                $armRows = self::executeArmNext133($arm);
                if ($index === 0) {
                    $rows = $armRows;
                    continue;
                }

                $operator = strtoupper((string) ($compound['operators'][$index - 1] ?? ''));
                if ($operator === 'EXCEPT' && is_array($rows)) {
                    $nextRows = SQLiteSelectCompound::combine($rows, $armRows, 'EXCEPT', self::compoundSelectCollationsNext133($compound['arms'][0]));
                    $removed = array_merge($removed, self::removedBySignatureNext133($rows, $nextRows));
                    $rows = $nextRows;
                    continue;
                }

                if (is_array($rows)) {
                    $rows = SQLiteSelectCompound::combine($rows, $armRows, $operator, self::compoundSelectCollationsNext133($compound['arms'][0]));
                }
            }

            return $removed;
        }

        /**
         * @param array<string,mixed> $arm
         * @return list<array<string,mixed>>
         */
        private static function executeArmNext133(array $arm): array
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
        private static function removedBySignatureNext133(array $before, array $after): array
        {
            $afterSignatures = array_fill_keys(self::rowSignaturesNext133($after), true);
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
        private static function compoundSelectCollationsNext133(array $arm): array
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
        private static function rowSignaturesNext133(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext133(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext133($currentRows);
            $next = self::rowSignaturesNext133($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function duplicateClassesNext133(array $rows): array
        {
            $counts = [];
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    $key = self::sqliteValueClassNext133($value);
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
        private static function changedValueClassesNext133(array $currentRows, array $nextRows): array
        {
            $current = self::valueClassesNext133($currentRows);
            $next = self::valueClassesNext133($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function valueClassesNext133(array $rows): array
        {
            $classes = [];
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    $classes[self::sqliteValueClassNext133($value)] = true;
                }
            }

            return array_keys($classes);
        }

        private static function sqliteValueClassNext133(mixed $value): string
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
        private static function replanReasonsNext133(array $currentRows, array $nextRows, array $currentPlan, array $nextPlan, array $currentRemoved, array $nextRemoved): array
        {
            $reasons = [];
            if (self::rowSignaturesNext133($currentRows) !== self::rowSignaturesNext133($nextRows)) {
                $reasons[] = 'compound-except-rowset-changed';
            }
            if (self::windowTermsNext133($currentPlan) !== []) {
                $reasons[] = 'compound-window-arm-source';
            }
            if (self::changedValueClassesNext133($currentRows, $nextRows) !== []) {
                $reasons[] = 'affinity-class-changed';
            }
            if (self::rowSignaturesNext133($currentRemoved) !== self::rowSignaturesNext133($nextRemoved)) {
                $reasons[] = 'except-removal-set-changed';
            }

            return array_values(array_unique($reasons));
        }

}
