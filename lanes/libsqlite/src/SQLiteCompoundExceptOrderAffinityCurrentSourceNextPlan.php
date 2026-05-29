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
        public static function compareNext138(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT order affinity current-source next138 plan needs a compound SELECT');
            }

            $operators = array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? []));
            if (!in_array('EXCEPT', $operators, true)) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT order affinity current-source next138 plan needs an EXCEPT arm');
            }
            if (!is_array($currentPlan['compound']['orderBy'] ?? null) || $currentPlan['compound']['orderBy'] === []) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT order affinity current-source next138 plan needs a tail ORDER BY');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentPreOrder = self::preOrderRowsNext138($currentPlan);
            $nextPreOrder = self::preOrderRowsNext138($nextPlan);

            return [
                'status' => 'compound-except-order-affinity-current-source-next138-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentSignatures' => self::rowSignaturesNext138($currentRows),
                'nextSignatures' => self::rowSignaturesNext138($nextRows),
                'changedSignatures' => self::changedSignaturesNext138($currentRows, $nextRows),
                'compound' => [
                    'operators' => $operators,
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'exceptArmIndexes' => self::exceptArmIndexesNext138($operators),
                    'orderBy' => self::orderTermsNext138($currentPlan),
                    'leftCollations' => self::leftArmCollationsNext138($currentPlan),
                ],
                'except' => [
                    'currentRemoved' => self::exceptRemovedRowsNext138($currentPlan),
                    'nextRemoved' => self::exceptRemovedRowsNext138($nextPlan),
                ],
                'orderTrace' => [
                    'currentPreOrder' => $currentPreOrder,
                    'nextPreOrder' => $nextPreOrder,
                    'currentKeys' => self::orderKeysNext138($currentRows, $currentPlan),
                    'nextKeys' => self::orderKeysNext138($nextRows, $nextPlan),
                    'currentPreOrderKeys' => self::orderKeysNext138($currentPreOrder, $currentPlan),
                    'nextPreOrderKeys' => self::orderKeysNext138($nextPreOrder, $nextPlan),
                ],
                'affinity' => [
                    'currentClasses' => self::valueClassesNext138($currentRows),
                    'nextClasses' => self::valueClassesNext138($nextRows),
                    'currentRemovedClasses' => self::valueClassesNext138(self::exceptRemovedRowsNext138($currentPlan)),
                    'nextRemovedClasses' => self::valueClassesNext138(self::exceptRemovedRowsNext138($nextPlan)),
                    'changedClasses' => self::changedValueClassesNext138($currentRows, $nextRows),
                ],
                'replanReasons' => self::replanReasonsNext138($currentRows, $nextRows, $currentPlan, $nextPlan),
                'dependencies' => [
                    'sqlite-compound-except-affinity',
                    'sqlite-select-sql-compound-tail-order',
                    'sqlite-select-result-storage-class-order',
                    'sqlite-current-source-next138',
                ],
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function preOrderRowsNext138(array $plan): array
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
        private static function exceptArmIndexesNext138(array $operators): array
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
        private static function orderTermsNext138(array $plan): array
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
        private static function leftArmCollationsNext138(array $plan): array
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
        private static function exceptRemovedRowsNext138(array $plan): array
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
                    $columns = self::outputColumnsNext138($arm);
                }
                $armRows = self::renameRowsNext138($armRows, $columns);
                if ($index === 0) {
                    $rows = $armRows;
                    continue;
                }

                $operator = strtoupper((string) ($compound['operators'][$index - 1] ?? ''));
                if (!is_array($rows)) {
                    continue;
                }
                $nextRows = SQLiteSelectCompound::combine($rows, $armRows, $operator, self::leftArmCollationsNext138($plan));
                if ($operator === 'EXCEPT') {
                    $removed = array_merge($removed, self::removedRowsNext138($rows, $nextRows));
                }
                $rows = $nextRows;
            }

            return $removed;
        }

        /**
         * @param array<string,mixed> $arm
         * @return list<string>
         */
        private static function outputColumnsNext138(array $arm): array
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
        private static function renameRowsNext138(array $rows, array $columns): array
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
        private static function removedRowsNext138(array $before, array $after): array
        {
            $afterSignatures = array_fill_keys(self::rowSignaturesNext138($after), true);
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
        private static function orderKeysNext138(array $rows, array $plan): array
        {
            $keys = [];
            foreach ($rows as $row) {
                $parts = [];
                foreach (self::orderTermsNext138($plan) as $term) {
                    $column = (string) ($term['column'] ?? '');
                    $parts[] = $column . '=' . self::sqliteValueClassNext138($row[$column] ?? null)
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
        private static function rowSignaturesNext138(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext138(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext138($currentRows);
            $next = self::rowSignaturesNext138($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function valueClassesNext138(array $rows): array
        {
            $classes = [];
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    $classes[self::sqliteValueClassNext138($value)] = true;
                }
            }

            return array_keys($classes);
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedValueClassesNext138(array $currentRows, array $nextRows): array
        {
            $current = self::valueClassesNext138($currentRows);
            $next = self::valueClassesNext138($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        private static function sqliteValueClassNext138(mixed $value): string
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
        private static function replanReasonsNext138(array $currentRows, array $nextRows, array $currentPlan, array $nextPlan): array
        {
            $reasons = [];
            if (self::rowSignaturesNext138($currentRows) !== self::rowSignaturesNext138($nextRows)) {
                $reasons[] = 'compound-except-order-rowset-changed';
            }
            if (self::orderTermsNext138($currentPlan) !== []) {
                $reasons[] = 'compound-tail-order-by';
            }
            if (self::changedValueClassesNext138($currentRows, $nextRows) !== []) {
                $reasons[] = 'order-affinity-class-changed';
            }
            if (self::exceptRemovedRowsNext138($currentPlan) !== self::exceptRemovedRowsNext138($nextPlan)) {
                $reasons[] = 'except-removal-set-changed';
            }
            if (self::orderTermsNext138($currentPlan) !== self::orderTermsNext138($nextPlan)) {
                $reasons[] = 'order-plan-changed';
            }

            return $reasons;
        }

}
