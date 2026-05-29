<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectExceptWindowLimitCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundSelectExceptWindowLimitCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareNext148(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
                throw new \InvalidArgumentException('SQLite compound SELECT EXCEPT window LIMIT current-source next148 plan needs a compound SELECT');
            }

            $operators = array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? []));
            if (count(array_filter($operators, static fn (string $operator): bool => $operator === 'EXCEPT')) < 2) {
                throw new \InvalidArgumentException('SQLite compound SELECT EXCEPT window LIMIT current-source next148 plan needs chained EXCEPT arms');
            }
            if (($currentPlan['compound']['limit'] ?? null) === null) {
                throw new \InvalidArgumentException('SQLite compound SELECT EXCEPT window LIMIT current-source next148 plan needs a final LIMIT');
            }
            if (self::windowTermsNext148($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound SELECT EXCEPT window LIMIT current-source next148 plan needs a window function arm');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $preLimitSql = self::withoutFinalLimitNext148($sql);
            $currentPreLimit = SQLiteSelectSql::execute($preLimitSql, $currentTables);
            $nextPreLimit = SQLiteSelectSql::execute($preLimitSql, $nextTables);
            $currentTrace = self::exceptTraceNext148($currentPlan);
            $nextTrace = self::exceptTraceNext148($nextPlan);

            return [
                'status' => 'compound-select-except-window-limit-current-source-next148-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentPreLimitRows' => $currentPreLimit,
                'nextPreLimitRows' => $nextPreLimit,
                'currentSignatures' => self::rowSignaturesNext148($currentRows),
                'nextSignatures' => self::rowSignaturesNext148($nextRows),
                'changedSignatures' => self::changedSignaturesNext148($currentRows, $nextRows),
                'compound' => [
                    'operators' => $operators,
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext148($currentPlan),
                    'limit' => $currentPlan['compound']['limit'],
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                    'exceptArmIndexes' => self::exceptArmIndexesNext148($operators),
                ],
                'windows' => [
                    'current' => self::windowTermsNext148($currentPlan),
                    'next' => self::windowTermsNext148($nextPlan),
                ],
                'exceptTrace' => [
                    'current' => $currentTrace,
                    'next' => $nextTrace,
                    'currentRemovedNames' => self::removedNamesNext148($currentTrace),
                    'nextRemovedNames' => self::removedNamesNext148($nextTrace),
                ],
                'limitTrace' => [
                    'current' => self::limitTraceNext148($currentPreLimit, $currentRows, $currentPlan),
                    'next' => self::limitTraceNext148($nextPreLimit, $nextRows, $nextPlan),
                ],
                'boundary' => [
                    'currentFirst' => $currentRows[0] ?? null,
                    'nextFirst' => $nextRows[0] ?? null,
                    'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                    'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                    'admittedNamesChanged' => self::admittedNamesChangedNext148($currentRows, $nextRows),
                ],
                'replanReasons' => self::replanReasonsNext148($currentRows, $nextRows, $currentPreLimit, $nextPreLimit, $currentTrace, $nextTrace, $currentPlan, $nextPlan),
                'dependencies' => [
                    'sqlite-select-sql-window-arm-evaluation',
                    'sqlite-select-sql-chained-except',
                    'sqlite-select-sql-compound-comma-limit',
                    'sqlite-current-source-next148',
                ],
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumnsNext148(array $plan): array
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
        private static function exceptArmIndexesNext148(array $operators): array
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
        private static function windowTermsNext148(array $plan): array
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

        private static function withoutFinalLimitNext148(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound SELECT EXCEPT window LIMIT current-source next148 plan cannot isolate final LIMIT');
            }

            return $without;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array{operator:string,arm:int,beforeCount:int,afterCount:int,removed:list<array<string,mixed>>}>
         */
        private static function exceptTraceNext148(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['arms'] ?? null) || !is_array($compound['operators'] ?? null)) {
                return [];
            }

            $rows = null;
            $trace = [];
            foreach ($compound['arms'] as $index => $arm) {
                if (!is_array($arm)) {
                    continue;
                }
                $armRows = self::executeArmNext148($arm);
                if ($index === 0) {
                    $rows = $armRows;
                    continue;
                }

                $operator = strtoupper((string) ($compound['operators'][$index - 1] ?? ''));
                if (!is_array($rows)) {
                    continue;
                }
                $nextRows = SQLiteSelectCompound::combine($rows, $armRows, $operator, self::compoundSelectCollationsNext148($compound['arms'][0]));
                if ($operator === 'EXCEPT') {
                    $trace[] = [
                        'operator' => $operator,
                        'arm' => $index,
                        'beforeCount' => count($rows),
                        'afterCount' => count($nextRows),
                        'removed' => self::removedBySignatureNext148($rows, $nextRows),
                    ];
                }
                $rows = $nextRows;
            }

            return $trace;
        }

        /**
         * @param array<string,mixed> $arm
         * @return list<array<string,mixed>>
         */
        private static function executeArmNext148(array $arm): array
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
        private static function compoundSelectCollationsNext148(array $arm): array
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
        private static function removedBySignatureNext148(array $before, array $after): array
        {
            $afterSignatures = array_fill_keys(self::rowSignaturesNext148($after), true);
            $removed = [];
            foreach ($before as $row) {
                if (!isset($afterSignatures[json_encode($row, JSON_THROW_ON_ERROR)])) {
                    $removed[] = $row;
                }
            }

            return $removed;
        }

        /**
         * @param list<array{operator:string,arm:int,beforeCount:int,afterCount:int,removed:list<array<string,mixed>>}> $trace
         * @return list<string>
         */
        private static function removedNamesNext148(array $trace): array
        {
            $names = [];
            foreach ($trace as $step) {
                foreach ($step['removed'] as $row) {
                    if (isset($row['name'])) {
                        $names[] = (string) $row['name'];
                    }
                }
            }

            return $names;
        }

        /**
         * @param list<array<string,mixed>> $preLimitRows
         * @param list<array<string,mixed>> $limitedRows
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function limitTraceNext148(array $preLimitRows, array $limitedRows, array $plan): array
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
        private static function rowSignaturesNext148(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext148(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext148($currentRows);
            $next = self::rowSignaturesNext148($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function admittedNamesChangedNext148(array $currentRows, array $nextRows): array
        {
            $current = array_values(array_map('strval', array_column($currentRows, 'name')));
            $next = array_values(array_map('strval', array_column($nextRows, 'name')));

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentPreLimit
         * @param list<array<string,mixed>> $nextPreLimit
         * @param list<array{operator:string,arm:int,beforeCount:int,afterCount:int,removed:list<array<string,mixed>>}> $currentTrace
         * @param list<array{operator:string,arm:int,beforeCount:int,afterCount:int,removed:list<array<string,mixed>>}> $nextTrace
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         * @return list<string>
         */
        private static function replanReasonsNext148(array $currentRows, array $nextRows, array $currentPreLimit, array $nextPreLimit, array $currentTrace, array $nextTrace, array $currentPlan, array $nextPlan): array
        {
            $reasons = [];
            if (self::rowSignaturesNext148($currentRows) !== self::rowSignaturesNext148($nextRows)) {
                $reasons[] = 'limited-chained-except-window-rowset-changed';
            }
            if (self::rowSignaturesNext148($currentPreLimit) !== self::rowSignaturesNext148($nextPreLimit)) {
                $reasons[] = 'prelimit-chained-except-window-rowset-changed';
            }
            if (self::traceSignaturesNext148($currentTrace) !== self::traceSignaturesNext148($nextTrace)) {
                $reasons[] = 'chained-except-removal-trace-changed';
            }
            if (($currentPlan['compound']['limit'] ?? null) !== null) {
                $reasons[] = 'compound-final-comma-limit';
            }
            if (self::windowTermsNext148($currentPlan) !== []) {
                $reasons[] = 'window-before-chained-except';
            }
            if (self::windowTermsNext148($currentPlan) !== self::windowTermsNext148($nextPlan)) {
                $reasons[] = 'window-plan-changed';
            }

            return array_values(array_unique($reasons));
        }

        /**
         * @param list<array{operator:string,arm:int,beforeCount:int,afterCount:int,removed:list<array<string,mixed>>}> $trace
         * @return list<string>
         */
        private static function traceSignaturesNext148(array $trace): array
        {
            return array_values(array_map(static fn (array $step): string => json_encode($step, JSON_THROW_ON_ERROR), $trace));
        }

}
