<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundWindowExceptLimitCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundWindowExceptLimitCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareWindowExceptLimit(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound window EXCEPT LIMIT current-source next141 plan needs a compound SELECT');
            }

            $operators = array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? []));
            if (!in_array('EXCEPT', $operators, true)) {
                throw new \InvalidArgumentException('SQLite compound window EXCEPT LIMIT current-source next141 plan needs an EXCEPT arm');
            }
            if (($currentPlan['compound']['limit'] ?? null) === null) {
                throw new \InvalidArgumentException('SQLite compound window EXCEPT LIMIT current-source next141 plan needs a final LIMIT');
            }
            if (self::windowTermsWindowExceptLimit($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound window EXCEPT LIMIT current-source next141 plan needs a window function arm');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $preLimitSql = self::withoutFinalLimitWindowExceptLimit($sql);
            $currentPreLimit = SQLiteSelectSql::execute($preLimitSql, $currentTables);
            $nextPreLimit = SQLiteSelectSql::execute($preLimitSql, $nextTables);
            $currentRemoved = self::exceptRemovedRowsWindowExceptLimit($currentPlan);
            $nextRemoved = self::exceptRemovedRowsWindowExceptLimit($nextPlan);

            return [
                'status' => 'compound-window-except-limit-current-source-next141-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentSignatures' => self::rowSignaturesWindowExceptLimit($currentRows),
                'nextSignatures' => self::rowSignaturesWindowExceptLimit($nextRows),
                'changedSignatures' => self::changedSignaturesWindowExceptLimit($currentRows, $nextRows),
                'compound' => [
                    'operators' => $operators,
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsWindowExceptLimit($currentPlan),
                    'limit' => $currentPlan['compound']['limit'],
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                    'exceptArmIndexes' => self::exceptArmIndexesWindowExceptLimit($operators),
                ],
                'windows' => [
                    'current' => self::windowTermsWindowExceptLimit($currentPlan),
                    'next' => self::windowTermsWindowExceptLimit($nextPlan),
                ],
                'except' => [
                    'currentRemoved' => $currentRemoved,
                    'nextRemoved' => $nextRemoved,
                    'currentRemovedClasses' => self::valueClassesWindowExceptLimit($currentRemoved),
                    'nextRemovedClasses' => self::valueClassesWindowExceptLimit($nextRemoved),
                ],
                'limitTrace' => [
                    'current' => self::limitTraceWindowExceptLimit($currentPreLimit, $currentRows, $currentPlan),
                    'next' => self::limitTraceWindowExceptLimit($nextPreLimit, $nextRows, $nextPlan),
                ],
                'affinity' => [
                    'currentClasses' => self::valueClassesWindowExceptLimit($currentRows),
                    'nextClasses' => self::valueClassesWindowExceptLimit($nextRows),
                    'changedClasses' => self::changedValueClassesWindowExceptLimit($currentRows, $nextRows),
                    'boundaryClasses' => self::boundaryClassesWindowExceptLimit($currentRows, $nextRows),
                ],
                'replanReasons' => self::replanReasonsWindowExceptLimit($currentRows, $nextRows, $currentPreLimit, $nextPreLimit, $currentPlan, $nextPlan, $currentRemoved, $nextRemoved),
                'dependencies' => [
                    'sqlite-compound-except-affinity',
                    'sqlite-select-sql-window-arm-evaluation',
                    'sqlite-select-sql-compound-final-limit',
                    'sqlite-current-source-next141',
                ],
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumnsWindowExceptLimit(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['orderBy'] ?? null)) {
                return [];
            }

            return array_values(array_map(static fn (array $term): string => (string) ($term['column'] ?? ''), $compound['orderBy']));
        }

        /**
         * @param list<string> $operators
         * @return list<int>
         */
        private static function exceptArmIndexesWindowExceptLimit(array $operators): array
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
        private static function windowTermsWindowExceptLimit(array $plan): array
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
                    ];
                }
            }

            return $windows;
        }

        private static function withoutFinalLimitWindowExceptLimit(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound window EXCEPT LIMIT current-source next141 plan cannot isolate final LIMIT');
            }

            return $without;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function exceptRemovedRowsWindowExceptLimit(array $plan): array
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
                $armRows = self::executeArmWindowExceptLimit($arm);
                if ($index === 0) {
                    $rows = $armRows;
                    continue;
                }

                $operator = strtoupper((string) ($compound['operators'][$index - 1] ?? ''));
                if ($operator === 'EXCEPT' && is_array($rows)) {
                    $nextRows = SQLiteSelectCompound::combine($rows, $armRows, 'EXCEPT', self::compoundSelectCollationsWindowExceptLimit($compound['arms'][0]));
                    $removed = array_merge($removed, self::removedBySignatureWindowExceptLimit($rows, $nextRows));
                    $rows = $nextRows;
                    continue;
                }
                if (is_array($rows)) {
                    $rows = SQLiteSelectCompound::combine($rows, $armRows, $operator, self::compoundSelectCollationsWindowExceptLimit($compound['arms'][0]));
                }
            }

            return $removed;
        }

        /**
         * @param array<string,mixed> $arm
         * @return list<array<string,mixed>>
         */
        private static function executeArmWindowExceptLimit(array $arm): array
        {
            $rows = SQLiteSelectQuery::execute($arm);
            $hidden = [];
            foreach (($arm['select'] ?? []) as $term) {
                if (is_array($term) && isset($term['hiddenOrderColumn']) && is_string($term['hiddenOrderColumn'])) {
                    $hidden[] = $term['hiddenOrderColumn'];
                }
            }
            foreach ($rows as &$row) {
                foreach ($hidden as $column) {
                    unset($row[$column]);
                }
            }
            unset($row);

            return $rows;
        }

        /**
         * @param array<string,mixed> $arm
         * @return array<string,string>
         */
        private static function compoundSelectCollationsWindowExceptLimit(array $arm): array
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
         * @param list<array<string,mixed>> $before
         * @param list<array<string,mixed>> $after
         * @return list<array<string,mixed>>
         */
        private static function removedBySignatureWindowExceptLimit(array $before, array $after): array
        {
            $afterSignatures = array_fill_keys(self::rowSignaturesWindowExceptLimit($after), true);
            $removed = [];
            foreach ($before as $row) {
                if (!isset($afterSignatures[json_encode($row, JSON_THROW_ON_ERROR)])) {
                    $removed[] = $row;
                }
            }

            return $removed;
        }

        /**
         * @param list<array<string,mixed>> $preLimitRows
         * @param list<array<string,mixed>> $limitedRows
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function limitTraceWindowExceptLimit(array $preLimitRows, array $limitedRows, array $plan): array
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
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignaturesWindowExceptLimit(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesWindowExceptLimit(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesWindowExceptLimit($currentRows);
            $next = self::rowSignaturesWindowExceptLimit($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function valueClassesWindowExceptLimit(array $rows): array
        {
            $classes = [];
            foreach ($rows as $row) {
                foreach ($row as $value) {
                    $classes[self::sqliteValueClassWindowExceptLimit($value)] = true;
                }
            }

            return array_keys($classes);
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedValueClassesWindowExceptLimit(array $currentRows, array $nextRows): array
        {
            $current = self::valueClassesWindowExceptLimit($currentRows);
            $next = self::valueClassesWindowExceptLimit($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return array{currentLast:string|null,nextLast:string|null}
         */
        private static function boundaryClassesWindowExceptLimit(array $currentRows, array $nextRows): array
        {
            $currentLast = $currentRows === [] ? null : self::sqliteValueClassWindowExceptLimit($currentRows[count($currentRows) - 1]['class_value'] ?? null);
            $nextLast = $nextRows === [] ? null : self::sqliteValueClassWindowExceptLimit($nextRows[count($nextRows) - 1]['class_value'] ?? null);

            return ['currentLast' => $currentLast, 'nextLast' => $nextLast];
        }

        private static function sqliteValueClassWindowExceptLimit(mixed $value): string
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
         * @param list<array<string,mixed>> $currentRemoved
         * @param list<array<string,mixed>> $nextRemoved
         * @return list<string>
         */
        private static function replanReasonsWindowExceptLimit(array $currentRows, array $nextRows, array $currentPreLimit, array $nextPreLimit, array $currentPlan, array $nextPlan, array $currentRemoved, array $nextRemoved): array
        {
            $reasons = [];
            if (self::rowSignaturesWindowExceptLimit($currentRows) !== self::rowSignaturesWindowExceptLimit($nextRows)) {
                $reasons[] = 'limited-except-window-rowset-changed';
            }
            if (self::rowSignaturesWindowExceptLimit($currentPreLimit) !== self::rowSignaturesWindowExceptLimit($nextPreLimit)) {
                $reasons[] = 'prelimit-except-window-rowset-changed';
            }
            if (self::rowSignaturesWindowExceptLimit($currentRemoved) !== self::rowSignaturesWindowExceptLimit($nextRemoved)) {
                $reasons[] = 'except-removal-set-changed';
            }
            if (($currentPlan['compound']['limit'] ?? null) !== null) {
                $reasons[] = 'compound-final-limit';
            }
            if (self::windowTermsWindowExceptLimit($currentPlan) !== []) {
                $reasons[] = 'compound-window-arm-source';
            }
            if (self::changedValueClassesWindowExceptLimit($currentRows, $nextRows) !== []) {
                $reasons[] = 'affinity-class-boundary-changed';
            }
            if (self::windowTermsWindowExceptLimit($currentPlan) !== self::windowTermsWindowExceptLimit($nextPlan)) {
                $reasons[] = 'window-plan-changed';
            }

            return array_values(array_unique($reasons));
        }

}
