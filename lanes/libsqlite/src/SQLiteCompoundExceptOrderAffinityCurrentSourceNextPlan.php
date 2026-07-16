<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundExceptOrderAffinityCurrentSourceNextPlan. */

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
                throw new \InvalidArgumentException('SQLite compound EXCEPT order affinity current-source plan needs a compound SELECT');
            }

            $operators = array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? []));
            if (!in_array('EXCEPT', $operators, true)) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT order affinity current-source plan needs an EXCEPT arm');
            }
            if (!is_array($currentPlan['compound']['orderBy'] ?? null) || $currentPlan['compound']['orderBy'] === []) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT order affinity current-source plan needs a tail ORDER BY');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentPreOrder = self::preOrderRows($currentPlan);
            $nextPreOrder = self::preOrderRows($nextPlan);

            return [
                'status' => 'compound-except-order-affinity-current-source-next-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentSignatures' => self::rowSignatures($currentRows),
                'nextSignatures' => self::rowSignatures($nextRows),
                'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
                'compound' => [
                    'operators' => $operators,
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'exceptArmIndexes' => self::exceptArmIndexes($operators),
                    'orderBy' => self::orderTerms($currentPlan),
                    'leftCollations' => self::leftArmCollations($currentPlan),
                ],
                'except' => [
                    'currentRemoved' => self::exceptRemovedRows($currentPlan),
                    'nextRemoved' => self::exceptRemovedRows($nextPlan),
                ],
                'orderTrace' => [
                    'currentPreOrder' => $currentPreOrder,
                    'nextPreOrder' => $nextPreOrder,
                    'currentKeys' => self::orderKeys($currentRows, $currentPlan),
                    'nextKeys' => self::orderKeys($nextRows, $nextPlan),
                    'currentPreOrderKeys' => self::orderKeys($currentPreOrder, $currentPlan),
                    'nextPreOrderKeys' => self::orderKeys($nextPreOrder, $nextPlan),
                ],
                'affinity' => [
                    'currentClasses' => self::valueClasses($currentRows),
                    'nextClasses' => self::valueClasses($nextRows),
                    'currentRemovedClasses' => self::valueClasses(self::exceptRemovedRows($currentPlan)),
                    'nextRemovedClasses' => self::valueClasses(self::exceptRemovedRows($nextPlan)),
                    'changedClasses' => self::changedValueClasses($currentRows, $nextRows),
                ],
                'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPlan, $nextPlan),
                'dependencies' => [
                    'sqlite-compound-except-affinity',
                    'sqlite-select-sql-compound-tail-order',
                    'sqlite-select-result-storage-class-order',
                    'sqlite-current-source-next',
                ],
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function preOrderRows(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound)) {
                return [];
            }

            $withoutOrder = $plan;
            unset($withoutOrder['compound']['orderBy'], $withoutOrder['compound']['limit'], $withoutOrder['compound']['offset']);

            return SQLiteSelectSql::executeCompoundPlanForDiagnostics($withoutOrder);
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
        private static function orderTerms(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['orderBy'] ?? null)) {
                return [];
            }

            return array_values($compound['orderBy']);
        }

        /**
         * @param array<string,mixed> $plan
         * @return array<string,string>
         */
        private static function leftArmCollations(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            $arm = is_array($compound) && is_array($compound['arms'] ?? null) ? ($compound['arms'][0] ?? null) : null;
            if (!is_array($arm) || !is_array($arm['select'] ?? null)) {
                return [];
            }

            $collations = [];
            foreach ($arm['select'] as $index => $term) {
                if (!is_array($term) || !isset($term['collation']) || !is_string($term['collation'])) {
                    continue;
                }
                $column = isset($term['alias']) && is_string($term['alias']) && $term['alias'] !== '' ? $term['alias'] : 'expr' . ($index + 1);
                $collations[$column] = strtoupper($term['collation']);
            }

            return $collations;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function exceptRemovedRows(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['arms'] ?? null) || !is_array($compound['operators'] ?? null)) {
                return [];
            }

            $removed = [];
            $rows = null;
            $columns = null;
            foreach ($compound['arms'] as $index => $arm) {
                if (!is_array($arm)) {
                    continue;
                }
                $armRows = SQLiteSelectQuery::execute($arm);
                if ($columns === null) {
                    $columns = self::outputColumns($arm);
                }
                $armRows = self::renameRows($armRows, $columns);
                if ($index === 0) {
                    $rows = $armRows;
                    continue;
                }

                $operator = strtoupper((string) ($compound['operators'][$index - 1] ?? ''));
                if (!is_array($rows)) {
                    continue;
                }
                $nextRows = SQLiteSelectCompound::combine($rows, $armRows, $operator, self::leftArmCollations($plan));
                if ($operator === 'EXCEPT') {
                    $removed = array_merge($removed, self::removedRows($rows, $nextRows));
                }
                $rows = $nextRows;
            }

            return $removed;
        }

        /**
         * @param array<string,mixed> $arm
         * @return list<string>
         */
        private static function outputColumns(array $arm): array
        {
            $columns = [];
            foreach (($arm['select'] ?? []) as $index => $term) {
                if (!is_array($term)) {
                    continue;
                }
                if (isset($term['alias']) && is_string($term['alias']) && $term['alias'] !== '') {
                    $columns[] = $term['alias'];
                } elseif (($term['type'] ?? null) === 'column' && isset($term['name']) && is_string($term['name'])) {
                    $name = $term['name'];
                    $columns[] = str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name;
                } else {
                    $columns[] = 'expr' . ($index + 1);
                }
            }

            return $columns;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @param list<string> $columns
         * @return list<array<string,mixed>>
         */
        private static function renameRows(array $rows, array $columns): array
        {
            return array_values(array_map(static function (array $row) use ($columns): array {
                $renamed = array_combine($columns, array_values($row));
                if (!is_array($renamed)) {
                    throw new \InvalidArgumentException('SQLite compound EXCEPT order affinity current-source row width mismatch');
                }

                return $renamed;
            }, $rows));
        }

        /**
         * @param list<array<string,mixed>> $before
         * @param list<array<string,mixed>> $after
         * @return list<array<string,mixed>>
         */
        private static function removedRows(array $before, array $after): array
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
         * @param list<array<string,mixed>> $rows
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderKeys(array $rows, array $plan): array
        {
            $keys = [];
            foreach ($rows as $row) {
                $parts = [];
                foreach (self::orderTerms($plan) as $term) {
                    $column = (string) ($term['column'] ?? '');
                    $parts[] = $column . '=' . self::sqliteValueClass($row[$column] ?? null)
                        . ':dir=' . strtoupper((string) ($term['direction'] ?? 'ASC'))
                        . ':collate=' . strtoupper((string) ($term['collation'] ?? 'BINARY'))
                        . ':nulls=' . strtoupper((string) ($term['nulls'] ?? ''));
                }
                $keys[] = implode('|', $parts);
            }

            return $keys;
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

        private static function sqliteValueClass(mixed $value): string
        {
            if ($value === null) {
                return 'null';
            }
            if (is_int($value) || is_float($value)) {
                return 'numeric:' . (string) (is_float($value) && floor($value) === $value ? (int) $value : $value);
            }
            if (is_string($value)) {
                return 'text:' . $value;
            }
            if (is_bool($value)) {
                return 'numeric:' . ($value ? '1' : '0');
            }

            return 'blob';
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
                $reasons[] = 'compound-except-order-rowset-changed';
            }
            if (self::orderTerms($currentPlan) !== []) {
                $reasons[] = 'compound-tail-order-by';
            }
            if (self::changedValueClasses($currentRows, $nextRows) !== []) {
                $reasons[] = 'order-affinity-class-changed';
            }
            if (self::exceptRemovedRows($currentPlan) !== self::exceptRemovedRows($nextPlan)) {
                $reasons[] = 'except-removal-set-changed';
            }
            if (self::orderTerms($currentPlan) !== self::orderTerms($nextPlan)) {
                $reasons[] = 'order-plan-changed';
            }

            return $reasons;
        }

}
