<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareIntersectRecursiveWindowLimit(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT recursive window LIMIT current-source plan needs a compound SELECT');
            }

            $operators = array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? []));
            if (!in_array('INTERSECT', $operators, true)) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT recursive window LIMIT current-source plan needs an INTERSECT arm');
            }
            if (($currentPlan['compound']['limit'] ?? null) === null) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT recursive window LIMIT current-source plan needs a final LIMIT');
            }
            if (self::windowTerms($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT recursive window LIMIT current-source plan needs a window function arm');
            }

            $traceSql = self::traceSql($sql);
            $currentRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
            $nextRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);
            $preLimitSql = self::withoutFinalLimit($sql);
            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentPreLimit = SQLiteSelectSql::execute($preLimitSql, $currentTables);
            $nextPreLimit = SQLiteSelectSql::execute($preLimitSql, $nextTables);
            $currentIntersect = self::intersectTrace($currentPlan);
            $nextIntersect = self::intersectTrace($nextPlan);

            return [
                'status' => 'compound-intersect-recursive-window-limit-current-source-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentPreLimitRows' => $currentPreLimit,
                'nextPreLimitRows' => $nextPreLimit,
                'currentSignatures' => self::rowSignatures($currentRows),
                'nextSignatures' => self::rowSignatures($nextRows),
                'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
                'compound' => [
                    'operators' => $operators,
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumns($currentPlan),
                    'limit' => $currentPlan['compound']['limit'],
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                    'intersectArmIndexes' => self::operatorArmIndexes($operators, 'INTERSECT'),
                ],
                'windows' => [
                    'current' => self::windowTerms($currentPlan),
                    'next' => self::windowTerms($nextPlan),
                ],
                'recursive' => [
                    'name' => $currentRecursive['name'],
                    'columns' => $currentRecursive['columns'],
                    'operator' => $currentRecursive['operator'],
                    'currentRows' => $currentRecursive['rows'],
                    'nextRows' => $nextRecursive['rows'],
                    'currentTraceCount' => count($currentRecursive['trace']),
                    'nextTraceCount' => count($nextRecursive['trace']),
                    'currentLimitRemaining' => self::lastLimitRemaining($currentRecursive['trace']),
                    'nextLimitRemaining' => self::lastLimitRemaining($nextRecursive['trace']),
                    'dependencies' => array_values(array_unique(array_merge($currentRecursive['dependencies'], $nextRecursive['dependencies']))),
                ],
                'intersectTrace' => [
                    'current' => $currentIntersect,
                    'next' => $nextIntersect,
                    'currentRetainedNames' => self::traceNames($currentIntersect, 'retained'),
                    'nextRetainedNames' => self::traceNames($nextIntersect, 'retained'),
                    'currentRemovedNames' => self::traceNames($currentIntersect, 'removed'),
                    'nextRemovedNames' => self::traceNames($nextIntersect, 'removed'),
                ],
                'limitTrace' => [
                    'current' => self::limitTrace($currentPreLimit, $currentRows, $currentPlan),
                    'next' => self::limitTrace($nextPreLimit, $nextRows, $nextPlan),
                ],
                'boundary' => [
                    'currentFirst' => $currentRows[0] ?? null,
                    'nextFirst' => $nextRows[0] ?? null,
                    'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                    'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                    'admittedNamesChanged' => self::admittedNamesChanged($currentRows, $nextRows),
                ],
                'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreLimit, $nextPreLimit, $currentIntersect, $nextIntersect, $currentPlan, $nextPlan),
                'dependencies' => [
                    'sqlite-select-sql-recursive-cte-queue-limit',
                    'sqlite-select-sql-window-arm-evaluation',
                    'sqlite-select-sql-compound-intersect',
                    'sqlite-select-sql-compound-final-limit',
                    'sqlite-current-source',
                ],
            ];
        }

        private static function traceSql(string $sql): string
        {
            $sql = trim(rtrim(trim($sql), ';'));
            if (stripos($sql, 'WITH RECURSIVE') !== 0) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT recursive window LIMIT current-source plan needs WITH RECURSIVE');
            }
            if (preg_match('/^(.*\))\s*SELECT\s+name\s*,\s*pos\s*,\s*row_number\s*\(/is', $sql, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT recursive window LIMIT current-source plan cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT pos, name FROM wanted';
        }

        private static function withoutFinalLimit(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT recursive window LIMIT current-source plan cannot isolate final LIMIT');
            }

            return $without;
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
         * @param list<string> $operators
         * @return list<int>
         */
        private static function operatorArmIndexes(array $operators, string $wanted): array
        {
            $indexes = [];
            foreach ($operators as $index => $operator) {
                if ($operator === $wanted) {
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
                        'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                        'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                    ];
                }
            }

            return $windows;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array{operator:string,arm:int,beforeCount:int,afterCount:int,retained:list<array<string,mixed>>,removed:list<array<string,mixed>>}>
         */
        private static function intersectTrace(array $plan): array
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
                $armRows = self::executeArm($arm);
                if ($index === 0) {
                    $rows = $armRows;
                    continue;
                }

                $operator = strtoupper((string) ($compound['operators'][$index - 1] ?? ''));
                if (!is_array($rows)) {
                    continue;
                }
                $nextRows = SQLiteSelectCompound::combine($rows, $armRows, $operator, self::compoundSelectCollations($compound['arms'][0]));
                if ($operator === 'INTERSECT') {
                    $trace[] = [
                        'operator' => $operator,
                        'arm' => $index,
                        'beforeCount' => count($rows),
                        'afterCount' => count($nextRows),
                        'retained' => $nextRows,
                        'removed' => self::removedBySignature($rows, $nextRows),
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
        private static function executeArm(array $arm): array
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
         * @param list<array{operator:string,arm:int,beforeCount:int,afterCount:int,retained:list<array<string,mixed>>,removed:list<array<string,mixed>>}> $trace
         * @return list<string>
         */
        private static function traceNames(array $trace, string $key): array
        {
            $names = [];
            foreach ($trace as $step) {
                foreach (($step[$key] ?? []) as $row) {
                    if (isset($row['name'])) {
                        $names[] = (string) $row['name'];
                    }
                }
            }

            return $names;
        }

        /**
         * @param list<array<string,mixed>> $trace
         */
        private static function lastLimitRemaining(array $trace): ?int
        {
            $last = $trace === [] ? null : $trace[count($trace) - 1];
            $remaining = is_array($last) ? ($last['limit_remaining'] ?? null) : null;

            return is_int($remaining) ? $remaining : null;
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
            ];
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
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function admittedNamesChanged(array $currentRows, array $nextRows): array
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
         * @param list<array<string,mixed>> $currentTrace
         * @param list<array<string,mixed>> $nextTrace
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         * @return list<string>
         */
        private static function replanReasons(array $currentRows, array $nextRows, array $currentPreLimit, array $nextPreLimit, array $currentTrace, array $nextTrace, array $currentPlan, array $nextPlan): array
        {
            $reasons = [];
            if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
                $reasons[] = 'limited-intersect-recursive-window-rowset-changed';
            }
            if (self::rowSignatures($currentPreLimit) !== self::rowSignatures($nextPreLimit)) {
                $reasons[] = 'prelimit-intersect-recursive-window-rowset-changed';
            }
            if (self::traceSignatures($currentTrace) !== self::traceSignatures($nextTrace)) {
                $reasons[] = 'intersect-retention-trace-changed';
            }
            if (($currentPlan['compound']['limit'] ?? null) !== null) {
                $reasons[] = 'compound-final-limit';
            }
            if (self::windowTerms($currentPlan) !== []) {
                $reasons[] = 'window-before-intersect';
            }
            if (self::windowTerms($currentPlan) !== self::windowTerms($nextPlan)) {
                $reasons[] = 'window-plan-changed';
            }

            return array_values(array_unique($reasons));
        }

        /**
         * @param list<array<string,mixed>> $trace
         * @return list<string>
         */
        private static function traceSignatures(array $trace): array
        {
            return array_values(array_map(static fn (array $step): string => json_encode($step, JSON_THROW_ON_ERROR), $trace));
        }

}
