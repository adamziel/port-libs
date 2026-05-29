<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareNext164(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            self::assertSupportedNext164($sql, $currentPlan, $nextPlan);

            $preLimitSql = self::withoutFinalLimitNext164($sql);
            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $currentTables);
            $nextPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $nextTables);
            $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($sql, $currentTables);
            $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($sql, $nextTables);

            return [
                'status' => 'compound-intersect-window-recursive-limit-current-source-next164-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentPreLimitRows' => $currentPreLimitRows,
                'nextPreLimitRows' => $nextPreLimitRows,
                'compound' => [
                    'operators' => self::operatorsNext164($currentPlan),
                    'armCount' => count($currentPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext164($currentPlan),
                    'limit' => $currentPlan['compound']['limit'] ?? null,
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                    'intersectArmIndex' => self::intersectArmIndexNext164($currentPlan),
                ],
                'windows' => [
                    'current' => self::windowTermsNext164($currentPlan),
                    'next' => self::windowTermsNext164($nextPlan),
                    'functions' => array_values(array_unique(array_column(self::windowTermsNext164($currentPlan), 'function'))),
                ],
                'recursive' => [
                    'name' => $currentTrace['name'] ?? null,
                    'columns' => $currentTrace['columns'] ?? [],
                    'operator' => $currentTrace['operator'] ?? null,
                    'currentRows' => $currentTrace['rows'] ?? [],
                    'nextRows' => $nextTrace['rows'] ?? [],
                    'currentTraceCount' => is_array($currentTrace['trace'] ?? null) ? count($currentTrace['trace']) : 0,
                    'nextTraceCount' => is_array($nextTrace['trace'] ?? null) ? count($nextTrace['trace']) : 0,
                    'currentLimitRemaining' => self::lastTraceValueNext164($currentTrace, 'limit_remaining'),
                    'nextLimitRemaining' => self::lastTraceValueNext164($nextTrace, 'limit_remaining'),
                    'dependencies' => array_values(array_unique(array_merge(
                        is_array($currentTrace['dependencies'] ?? null) ? $currentTrace['dependencies'] : [],
                        is_array($nextTrace['dependencies'] ?? null) ? $nextTrace['dependencies'] : [],
                    ))),
                ],
                'intersect' => [
                    'currentMatchedLabels' => self::labelsNext164($currentPreLimitRows),
                    'nextMatchedLabels' => self::labelsNext164($nextPreLimitRows),
                    'changedMatchedLabels' => self::changedLabelsNext164($currentPreLimitRows, $nextPreLimitRows),
                    'admittedLabels' => self::labelsNext164($nextRows),
                ],
                'yieldBoundary' => [
                    'current' => self::yieldBoundaryNext164($currentPreLimitRows, $currentRows, $currentPlan),
                    'next' => self::yieldBoundaryNext164($nextPreLimitRows, $nextRows, $nextPlan),
                ],
                'boundary' => [
                    'currentFirst' => $currentRows[0] ?? null,
                    'nextFirst' => $nextRows[0] ?? null,
                    'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                    'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                    'gainedLabels' => array_values(array_diff(self::labelsNext164($nextRows), self::labelsNext164($currentRows))),
                    'lostLabels' => array_values(array_diff(self::labelsNext164($currentRows), self::labelsNext164($nextRows))),
                ],
                'changedSignatures' => self::changedSignaturesNext164($currentRows, $nextRows),
                'replanReasons' => self::replanReasonsNext164($currentRows, $nextRows, $currentPreLimitRows, $nextPreLimitRows, $currentTrace),
                'dependencies' => [
                    'sqlite-recursive-queue-order-limit-before-intersect-next164',
                    'sqlite-window-arm-before-compound-intersect-next164',
                    'sqlite-compound-intersect-final-limit-yield-next164',
                ],
                'dependency_closure' => 'no new support component needed; this reuses lane-local SELECT SQL, recursive CTE queue ORDER/LIMIT, compound INTERSECT, window, and LIMIT/OFFSET execution',
            ];
        }

        /**
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         */
        private static function assertSupportedNext164(string $sql, array $currentPlan, array $nextPlan): void
        {
            if (stripos($sql, 'WITH RECURSIVE') === false) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT next164 needs WITH RECURSIVE SQL');
            }
            if (stripos($sql, 'ORDER BY 3') === false) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT next164 needs recursive queue ORDER BY');
            }
            if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT next164 needs a compound SELECT');
            }
            if (!in_array('INTERSECT', self::operatorsNext164($currentPlan), true)) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT next164 needs an INTERSECT arm');
            }
            if (($currentPlan['compound']['limit'] ?? null) === null || stripos($sql, ' OFFSET ') === false) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT next164 needs final LIMIT/OFFSET');
            }
            if (self::windowTermsNext164($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT next164 needs a window arm');
            }
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function operatorsNext164(array $plan): array
        {
            $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

            return array_values(array_map('strtoupper', is_array($compound['operators'] ?? null) ? $compound['operators'] : []));
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumnsNext164(array $plan): array
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
        private static function intersectArmIndexNext164(array $plan): ?int
        {
            foreach (self::operatorsNext164($plan) as $index => $operator) {
                if ($operator === 'INTERSECT') {
                    return $index + 1;
                }
            }

            return null;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function windowTermsNext164(array $plan): array
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

        private static function withoutFinalLimitNext164(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            $without = preg_replace('/\s+LIMIT\s+\d+\s+OFFSET\s+\d+\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT next164 cannot isolate final LIMIT/OFFSET');
            }

            return $without;
        }

        /**
         * @param array<string,mixed> $trace
         */
        private static function lastTraceValueNext164(array $trace, string $key): mixed
        {
            $rows = is_array($trace['trace'] ?? null) ? $trace['trace'] : [];
            $last = $rows === [] ? null : $rows[count($rows) - 1];

            return is_array($last) ? ($last[$key] ?? null) : null;
        }

        /**
         * @param list<array<string,mixed>> $preLimitRows
         * @param list<array<string,mixed>> $limitedRows
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function yieldBoundaryNext164(array $preLimitRows, array $limitedRows, array $plan): array
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
        private static function labelsNext164(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => isset($row['label']) && is_scalar($row['label']) ? (string) $row['label'] : '', $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedLabelsNext164(array $currentRows, array $nextRows): array
        {
            return array_values(array_merge(array_diff(self::labelsNext164($currentRows), self::labelsNext164($nextRows)), array_diff(self::labelsNext164($nextRows), self::labelsNext164($currentRows))));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignaturesNext164(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext164(array $currentRows, array $nextRows): array
        {
            return array_values(array_merge(array_diff(self::rowSignaturesNext164($currentRows), self::rowSignaturesNext164($nextRows)), array_diff(self::rowSignaturesNext164($nextRows), self::rowSignaturesNext164($currentRows))));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentPreLimitRows
         * @param list<array<string,mixed>> $nextPreLimitRows
         * @param array<string,mixed> $currentTrace
         * @return list<string>
         */
        private static function replanReasonsNext164(array $currentRows, array $nextRows, array $currentPreLimitRows, array $nextPreLimitRows, array $currentTrace): array
        {
            $reasons = ['recursive-queue-order-limit-before-intersect', 'window-before-compound-intersect', 'compound-intersect-before-final-limit'];
            if (self::rowSignaturesNext164($currentRows) !== self::rowSignaturesNext164($nextRows)) {
                $reasons[] = 'limited-compound-intersect-rowset-changed';
            }
            if (self::rowSignaturesNext164($currentPreLimitRows) !== self::rowSignaturesNext164($nextPreLimitRows)) {
                $reasons[] = 'prelimit-compound-intersect-rowset-changed';
            }
            if (self::lastTraceValueNext164($currentTrace, 'limit_remaining') === 0) {
                $reasons[] = 'recursive-order-limit-exhausted-before-intersect';
            }

            return $reasons;
        }

}
