<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareNext161(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            self::assertSupportedNext161($sql, $currentPlan, $nextPlan);

            $preLimitSql = self::withoutFinalLimitNext161($sql);
            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $currentTables);
            $nextPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $nextTables);
            $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($sql, $currentTables);
            $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($sql, $nextTables);

            return [
                'status' => 'compound-except-window-recursive-limit-current-source-next161-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentPreLimitRows' => $currentPreLimitRows,
                'nextPreLimitRows' => $nextPreLimitRows,
                'compound' => [
                    'operators' => self::operatorsNext161($currentPlan),
                    'armCount' => count($currentPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext161($currentPlan),
                    'limit' => $currentPlan['compound']['limit'] ?? null,
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                    'exceptArmIndex' => self::exceptArmIndexNext161($currentPlan),
                ],
                'windows' => [
                    'current' => self::windowTermsNext161($currentPlan),
                    'next' => self::windowTermsNext161($nextPlan),
                    'functions' => array_values(array_unique(array_column(self::windowTermsNext161($currentPlan), 'function'))),
                ],
                'recursive' => [
                    'name' => $currentTrace['name'] ?? null,
                    'columns' => $currentTrace['columns'] ?? [],
                    'operator' => $currentTrace['operator'] ?? null,
                    'currentRows' => $currentTrace['rows'] ?? [],
                    'nextRows' => $nextTrace['rows'] ?? [],
                    'currentTraceCount' => is_array($currentTrace['trace'] ?? null) ? count($currentTrace['trace']) : 0,
                    'nextTraceCount' => is_array($nextTrace['trace'] ?? null) ? count($nextTrace['trace']) : 0,
                    'currentLimitRemaining' => self::lastTraceValueNext161($currentTrace, 'limit_remaining'),
                    'nextLimitRemaining' => self::lastTraceValueNext161($nextTrace, 'limit_remaining'),
                    'dependencies' => array_values(array_unique(array_merge(
                        is_array($currentTrace['dependencies'] ?? null) ? $currentTrace['dependencies'] : [],
                        is_array($nextTrace['dependencies'] ?? null) ? $nextTrace['dependencies'] : [],
                    ))),
                ],
                'except' => [
                    'currentExcludedLabels' => self::exceptLabelsNext161($currentPreLimitRows, $currentRows),
                    'nextExcludedLabels' => self::exceptLabelsNext161($nextPreLimitRows, $nextRows),
                    'changedExcludedLabels' => self::changedExceptLabelsNext161($currentPreLimitRows, $currentRows, $nextPreLimitRows, $nextRows),
                    'survivingSkipLabels' => self::survivingSkipLabelsNext161($currentRows, $nextRows),
                ],
                'yieldBoundary' => [
                    'current' => self::yieldBoundaryNext161($currentPreLimitRows, $currentRows, $currentPlan),
                    'next' => self::yieldBoundaryNext161($nextPreLimitRows, $nextRows, $nextPlan),
                ],
                'boundary' => [
                    'currentFirst' => $currentRows[0] ?? null,
                    'nextFirst' => $nextRows[0] ?? null,
                    'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                    'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                    'gainedLabels' => array_values(array_diff(self::labelsNext161($nextRows), self::labelsNext161($currentRows))),
                    'lostLabels' => array_values(array_diff(self::labelsNext161($currentRows), self::labelsNext161($nextRows))),
                ],
                'changedSignatures' => self::changedSignaturesNext161($currentRows, $nextRows),
                'replanReasons' => self::replanReasonsNext161($currentRows, $nextRows, $currentPreLimitRows, $nextPreLimitRows, $currentTrace),
                'dependencies' => [
                    'sqlite-recursive-cte-before-compound-except-next161',
                    'sqlite-window-arm-before-except-next161',
                    'sqlite-compound-except-final-limit-yield-next161',
                ],
                'dependency_closure' => 'no new support component needed; this reuses lane-local SELECT SQL, recursive CTE, compound EXCEPT, window, and LIMIT/OFFSET execution',
            ];
        }

        /**
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         */
        private static function assertSupportedNext161(string $sql, array $currentPlan, array $nextPlan): void
        {
            if (stripos($sql, 'WITH RECURSIVE') === false) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT next161 needs WITH RECURSIVE SQL');
            }
            if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT next161 needs a compound SELECT');
            }
            if (!in_array('EXCEPT', self::operatorsNext161($currentPlan), true)) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT next161 needs an EXCEPT arm');
            }
            if (($currentPlan['compound']['limit'] ?? null) === null || stripos($sql, ' OFFSET ') === false) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT next161 needs final LIMIT/OFFSET');
            }
            if (self::windowTermsNext161($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT next161 needs a window arm');
            }
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function operatorsNext161(array $plan): array
        {
            $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

            return array_values(array_map('strtoupper', is_array($compound['operators'] ?? null) ? $compound['operators'] : []));
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumnsNext161(array $plan): array
        {
            $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
            if (!is_array($compound['orderBy'] ?? null)) {
                return [];
            }

            return array_values(array_map(static fn (array $term): string => (string) ($term['column'] ?? ''), $compound['orderBy']));
        }

        /**
         * @param array<string,mixed> $plan
         */
        private static function exceptArmIndexNext161(array $plan): ?int
        {
            foreach (self::operatorsNext161($plan) as $index => $operator) {
                if ($operator === 'EXCEPT') {
                    return $index + 1;
                }
            }

            return null;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function windowTermsNext161(array $plan): array
        {
            $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
            $arms = is_array($compound['arms'] ?? null) ? $compound['arms'] : [];
            $windows = [];
            foreach ($arms as $armIndex => $arm) {
                $select = is_array($arm) && is_array($arm['select'] ?? null) ? $arm['select'] : [];
                foreach ($select as $selectIndex => $term) {
                    if (!is_array($term) || ($term['type'] ?? null) !== 'window') {
                        continue;
                    }
                    $windows[] = [
                        'arm' => $armIndex,
                        'selectIndex' => $selectIndex,
                        'alias' => isset($term['alias']) && is_string($term['alias']) ? $term['alias'] : 'expr' . ($selectIndex + 1),
                        'function' => (string) ($term['function'] ?? ''),
                        'argumentCount' => is_array($term['arguments'] ?? null) ? count($term['arguments']) : 0,
                        'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                    ];
                }
            }

            return $windows;
        }

        private static function withoutFinalLimitNext161(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            $without = preg_replace('/\s+LIMIT\s+\d+\s+OFFSET\s+\d+\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT next161 cannot isolate final LIMIT/OFFSET');
            }

            return $without;
        }

        /**
         * @param array<string,mixed> $trace
         */
        private static function lastTraceValueNext161(array $trace, string $key): mixed
        {
            $rows = is_array($trace['trace'] ?? null) ? $trace['trace'] : [];
            $last = $rows === [] ? null : $rows[count($rows) - 1];

            return is_array($last) ? ($last[$key] ?? null) : null;
        }

        /**
         * @param list<array<string,mixed>> $preLimitRows
         * @param list<array<string,mixed>> $limitedRows
         * @return list<string>
         */
        private static function exceptLabelsNext161(array $preLimitRows, array $limitedRows): array
        {
            return array_values(array_diff(self::labelsNext161($preLimitRows), self::labelsNext161($limitedRows)));
        }

        /**
         * @param list<array<string,mixed>> $currentPreLimitRows
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextPreLimitRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedExceptLabelsNext161(array $currentPreLimitRows, array $currentRows, array $nextPreLimitRows, array $nextRows): array
        {
            $current = self::exceptLabelsNext161($currentPreLimitRows, $currentRows);
            $next = self::exceptLabelsNext161($nextPreLimitRows, $nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function survivingSkipLabelsNext161(array $currentRows, array $nextRows): array
        {
            return array_values(array_filter(array_unique(array_merge(self::labelsNext161($currentRows), self::labelsNext161($nextRows))), static fn (string $label): bool => str_starts_with($label, 'skip_')));
        }

        /**
         * @param list<array<string,mixed>> $preLimitRows
         * @param list<array<string,mixed>> $limitedRows
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function yieldBoundaryNext161(array $preLimitRows, array $limitedRows, array $plan): array
        {
            $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
            $offset = isset($compound['offset']) && is_int($compound['offset']) ? $compound['offset'] : 0;
            $limit = isset($compound['limit']) && is_int($compound['limit']) ? $compound['limit'] : count($limitedRows);

            return [
                'offset' => $offset,
                'limit' => $limit,
                'preLimitCount' => count($preLimitRows),
                'yieldedCount' => count($limitedRows),
                'skippedBeforeOffset' => array_slice($preLimitRows, 0, $offset),
                'yielded' => $limitedRows,
                'truncatedAfterLimit' => array_slice($preLimitRows, $offset + $limit),
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function labelsNext161(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => isset($row['label']) && is_scalar($row['label']) ? (string) $row['label'] : '', $rows));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignaturesNext161(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext161(array $currentRows, array $nextRows): array
        {
            return array_values(array_merge(array_diff(self::rowSignaturesNext161($currentRows), self::rowSignaturesNext161($nextRows)), array_diff(self::rowSignaturesNext161($nextRows), self::rowSignaturesNext161($currentRows))));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentPreLimitRows
         * @param list<array<string,mixed>> $nextPreLimitRows
         * @param array<string,mixed> $currentTrace
         * @return list<string>
         */
        private static function replanReasonsNext161(array $currentRows, array $nextRows, array $currentPreLimitRows, array $nextPreLimitRows, array $currentTrace): array
        {
            $reasons = ['recursive-window-before-compound-except', 'compound-except-before-final-limit'];
            if (self::rowSignaturesNext161($currentRows) !== self::rowSignaturesNext161($nextRows)) {
                $reasons[] = 'limited-compound-except-rowset-changed';
            }
            if (self::rowSignaturesNext161($currentPreLimitRows) !== self::rowSignaturesNext161($nextPreLimitRows)) {
                $reasons[] = 'prelimit-compound-except-rowset-changed';
            }
            if (self::lastTraceValueNext161($currentTrace, 'limit_remaining') === 0) {
                $reasons[] = 'recursive-limit-exhausted-before-except';
            }

            return $reasons;
        }

    /* Variant formerly implemented by SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareNext170(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            self::assertSupportedNext170($sql, $currentPlan, $nextPlan);

            $preLimitSql = self::withoutFinalLimitNext170($sql);
            $traceSql = self::recursiveTraceSqlNext170($sql);
            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $currentTables);
            $nextPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $nextTables);
            $currentRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
            $nextRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

            return [
                'status' => 'compound-except-window-recursive-limit-current-source-next170-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentPreLimitRows' => $currentPreLimitRows,
                'nextPreLimitRows' => $nextPreLimitRows,
                'changedSignatures' => self::changedSignaturesNext170($currentRows, $nextRows),
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'exceptArmIndexes' => self::operatorIndexesNext170($currentPlan, 'EXCEPT'),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext170($currentPlan),
                    'limit' => $currentPlan['compound']['limit'],
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                ],
                'windows' => [
                    'current' => self::windowTermsNext170($currentPlan),
                    'next' => self::windowTermsNext170($nextPlan),
                ],
                'recursive' => self::recursiveSummaryNext170($currentRecursive, $nextRecursive),
                'exceptTrace' => self::exceptTraceNext170($currentPreLimitRows, $nextPreLimitRows, $currentRows, $nextRows),
                'limitTrace' => [
                    'current' => self::limitTraceNext170($currentPreLimitRows, $currentRows, $currentPlan),
                    'next' => self::limitTraceNext170($nextPreLimitRows, $nextRows, $nextPlan),
                ],
                'boundary' => self::boundaryDeltaNext170($currentRows, $nextRows),
                'replanReasons' => self::replanReasonsNext170($currentRows, $nextRows, $currentPreLimitRows, $nextPreLimitRows, $currentRecursive, $nextRecursive),
                'dependencies' => [
                    'sqlite-select-sql-recursive-offset-exhaustion-next170',
                    'sqlite-select-sql-window-before-except-next170',
                    'sqlite-select-sql-compound-except-tail-next170',
                    'sqlite-select-sql-compound-final-limit-next170',
                    'sqlite-current-source-next170',
                ],
                'dependency_closure' => 'no new support component needed; next170 reuses lane-local recursive CTE queue tracing, window row-array evaluation, EXCEPT compound reduction, and final LIMIT/OFFSET execution',
            ];
        }

        /**
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         */
        private static function assertSupportedNext170(string $sql, array $currentPlan, array $nextPlan): void
        {
            if (stripos($sql, 'WITH RECURSIVE') === false) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 needs WITH RECURSIVE SQL');
            }
            if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 needs a compound SELECT');
            }
            $operators = array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? []));
            if (!in_array('EXCEPT', $operators, true)) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 needs an EXCEPT arm');
            }
            if (($currentPlan['compound']['limit'] ?? null) === null || ($currentPlan['compound']['offset'] ?? null) === null) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 needs final LIMIT/OFFSET');
            }
            if (stripos($sql, ' OFFSET ') === false) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 needs recursive OFFSET exhaustion');
            }
            if (self::windowTermsNext170($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 needs a window function arm');
            }
        }

        private static function recursiveTraceSqlNext170(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT * FROM ' . $match[2];
        }

        private static function withoutFinalLimitNext170(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound EXCEPT window recursive LIMIT current-source next170 cannot isolate final LIMIT');
            }

            return $without;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<int>
         */
        private static function operatorIndexesNext170(array $plan, string $operator): array
        {
            $indexes = [];
            foreach (($plan['compound']['operators'] ?? []) as $index => $candidate) {
                if (strtoupper((string) $candidate) === $operator) {
                    $indexes[] = $index + 1;
                }
            }

            return $indexes;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumnsNext170(array $plan): array
        {
            $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
            if (!is_array($compound['orderBy'] ?? null)) {
                return [];
            }

            return array_values(array_map(static fn (array $term): string => (string) ($term['column'] ?? ''), $compound['orderBy']));
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function windowTermsNext170(array $plan): array
        {
            $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
            $arms = is_array($compound['arms'] ?? null) ? $compound['arms'] : [];
            $windows = [];
            foreach ($arms as $armIndex => $arm) {
                $select = is_array($arm) && is_array($arm['select'] ?? null) ? $arm['select'] : [];
                foreach ($select as $selectIndex => $term) {
                    if (!is_array($term) || ($term['type'] ?? null) !== 'window') {
                        continue;
                    }
                    $windows[] = [
                        'arm' => $armIndex,
                        'selectIndex' => $selectIndex,
                        'alias' => isset($term['alias']) && is_string($term['alias']) ? $term['alias'] : 'expr' . ($selectIndex + 1),
                        'function' => (string) ($term['function'] ?? ''),
                        'argumentCount' => is_array($term['arguments'] ?? null) ? count($term['arguments']) : 0,
                        'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                        'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                    ];
                }
            }

            return $windows;
        }

        /**
         * @param array<string,mixed> $currentTrace
         * @param array<string,mixed> $nextTrace
         * @return array<string,mixed>
         */
        private static function recursiveSummaryNext170(array $currentTrace, array $nextTrace): array
        {
            return [
                'name' => $currentTrace['name'] ?? null,
                'columns' => $currentTrace['columns'] ?? [],
                'operator' => $currentTrace['operator'] ?? null,
                'currentRows' => $currentTrace['rows'] ?? [],
                'nextRows' => $nextTrace['rows'] ?? [],
                'currentSkippedLabels' => self::traceLabelsNext170($currentTrace['trace'] ?? [], false),
                'currentEmittedLabels' => self::traceLabelsNext170($currentTrace['trace'] ?? [], true),
                'nextSkippedLabels' => self::traceLabelsNext170($nextTrace['trace'] ?? [], false),
                'nextEmittedLabels' => self::traceLabelsNext170($nextTrace['trace'] ?? [], true),
                'currentTraceCount' => is_array($currentTrace['trace'] ?? null) ? count($currentTrace['trace']) : 0,
                'nextTraceCount' => is_array($nextTrace['trace'] ?? null) ? count($nextTrace['trace']) : 0,
                'currentLimitRemaining' => self::lastTraceValueNext170($currentTrace['trace'] ?? [], 'limit_remaining'),
                'currentOffsetRemaining' => self::lastTraceValueNext170($currentTrace['trace'] ?? [], 'offset_remaining'),
                'nextLimitRemaining' => self::lastTraceValueNext170($nextTrace['trace'] ?? [], 'limit_remaining'),
                'nextOffsetRemaining' => self::lastTraceValueNext170($nextTrace['trace'] ?? [], 'offset_remaining'),
                'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'] ?? [], $nextTrace['dependencies'] ?? []))),
            ];
        }

        /**
         * @param list<array<string,mixed>> $trace
         * @return list<string>
         */
        private static function traceLabelsNext170(array $trace, bool $emitted): array
        {
            $labels = [];
            foreach ($trace as $step) {
                if (!is_array($step) || (bool) ($step['emitted'] ?? false) !== $emitted) {
                    continue;
                }
                $current = $step['current'] ?? null;
                if (is_array($current) && isset($current['label'])) {
                    $labels[] = (string) $current['label'];
                }
            }

            return $labels;
        }

        /**
         * @param list<array<string,mixed>> $trace
         */
        private static function lastTraceValueNext170(array $trace, string $key): ?int
        {
            $last = $trace === [] ? null : $trace[count($trace) - 1];
            $value = is_array($last) ? ($last[$key] ?? null) : null;

            return is_int($value) ? $value : null;
        }

        /**
         * @param list<array<string,mixed>> $currentPreLimit
         * @param list<array<string,mixed>> $nextPreLimit
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return array<string,mixed>
         */
        private static function exceptTraceNext170(array $currentPreLimit, array $nextPreLimit, array $currentRows, array $nextRows): array
        {
            return [
                'currentPreLimitLabels' => array_values(array_map('strval', array_column($currentPreLimit, 'label'))),
                'nextPreLimitLabels' => array_values(array_map('strval', array_column($nextPreLimit, 'label'))),
                'currentAdmittedLabels' => array_values(array_map('strval', array_column($currentRows, 'label'))),
                'nextAdmittedLabels' => array_values(array_map('strval', array_column($nextRows, 'label'))),
                'removedBeforeLimit' => array_values(array_diff(
                    array_map('strval', array_column($nextPreLimit, 'label')),
                    array_map('strval', array_column($nextRows, 'label')),
                )),
            ];
        }

        /**
         * @param list<array<string,mixed>> $preLimitRows
         * @param list<array<string,mixed>> $limitedRows
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function limitTraceNext170(array $preLimitRows, array $limitedRows, array $plan): array
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
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return array<string,mixed>
         */
        private static function boundaryDeltaNext170(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext170($currentRows);
            $next = self::rowSignaturesNext170($nextRows);

            return [
                'currentFirst' => $currentRows[0] ?? null,
                'nextFirst' => $nextRows[0] ?? null,
                'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                'gainedRows' => array_values(array_diff($next, $current)),
                'lostRows' => array_values(array_diff($current, $next)),
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignaturesNext170(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext170(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext170($currentRows);
            $next = self::rowSignaturesNext170($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentPreLimit
         * @param list<array<string,mixed>> $nextPreLimit
         * @param array<string,mixed> $currentRecursive
         * @param array<string,mixed> $nextRecursive
         * @return list<string>
         */
        private static function replanReasonsNext170(array $currentRows, array $nextRows, array $currentPreLimit, array $nextPreLimit, array $currentRecursive, array $nextRecursive): array
        {
            $reasons = [
                'recursive-offset-exhaustion-before-window-arm',
                'window-before-except-compound-tail',
                'compound-final-limit-offset',
            ];
            if (self::rowSignaturesNext170($currentRows) !== self::rowSignaturesNext170($nextRows)) {
                $reasons[] = 'limited-except-window-rowset-changed';
            }
            if (self::rowSignaturesNext170($currentPreLimit) !== self::rowSignaturesNext170($nextPreLimit)) {
                $reasons[] = 'prelimit-except-window-rowset-changed';
            }
            if (self::traceLabelsNext170($currentRecursive['trace'] ?? [], false) !== [] || self::traceLabelsNext170($nextRecursive['trace'] ?? [], false) !== []) {
                $reasons[] = 'recursive-offset-skipped-anchor';
            }

            return array_values(array_unique($reasons));
        }

}
