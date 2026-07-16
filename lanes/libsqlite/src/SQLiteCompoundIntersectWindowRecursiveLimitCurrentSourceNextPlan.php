<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNextPlan
{
    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareIntersectWindowRecursiveLimit(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            self::assertSupportedIntersectWindowRecursiveLimit($sql, $currentPlan, $nextPlan);

            $preLimitSql = self::withoutFinalLimit($sql);
            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $currentTables);
            $nextPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $nextTables);
            $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($sql, $currentTables);
            $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($sql, $nextTables);

            return [
                'status' => 'compound-intersect-window-recursive-limit-current-source-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentPreLimitRows' => $currentPreLimitRows,
                'nextPreLimitRows' => $nextPreLimitRows,
                'compound' => [
                    'operators' => self::compoundOperators($currentPlan),
                    'armCount' => count($currentPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::compoundOrderColumns($currentPlan),
                    'limit' => $currentPlan['compound']['limit'] ?? null,
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                    'intersectArmIndex' => self::intersectArmIndex($currentPlan),
                ],
                'windows' => [
                    'current' => self::windowTerms($currentPlan),
                    'next' => self::windowTerms($nextPlan),
                    'functions' => array_values(array_unique(array_column(self::windowTerms($currentPlan), 'function'))),
                ],
                'recursive' => [
                    'name' => $currentTrace['name'] ?? null,
                    'columns' => $currentTrace['columns'] ?? [],
                    'operator' => $currentTrace['operator'] ?? null,
                    'currentRows' => $currentTrace['rows'] ?? [],
                    'nextRows' => $nextTrace['rows'] ?? [],
                    'currentTraceCount' => is_array($currentTrace['trace'] ?? null) ? count($currentTrace['trace']) : 0,
                    'nextTraceCount' => is_array($nextTrace['trace'] ?? null) ? count($nextTrace['trace']) : 0,
                    'currentLimitRemaining' => self::lastTraceValue($currentTrace, 'limit_remaining'),
                    'nextLimitRemaining' => self::lastTraceValue($nextTrace, 'limit_remaining'),
                    'dependencies' => array_values(array_unique(array_merge(
                        is_array($currentTrace['dependencies'] ?? null) ? $currentTrace['dependencies'] : [],
                        is_array($nextTrace['dependencies'] ?? null) ? $nextTrace['dependencies'] : [],
                    ))),
                ],
                'intersect' => [
                    'currentMatchedLabels' => self::rowLabels($currentPreLimitRows),
                    'nextMatchedLabels' => self::rowLabels($nextPreLimitRows),
                    'changedMatchedLabels' => self::changedRowLabels($currentPreLimitRows, $nextPreLimitRows),
                    'admittedLabels' => self::rowLabels($nextRows),
                ],
                'yieldBoundary' => [
                    'current' => self::yieldBoundary($currentPreLimitRows, $currentRows, $currentPlan),
                    'next' => self::yieldBoundary($nextPreLimitRows, $nextRows, $nextPlan),
                ],
                'boundary' => [
                    'currentFirst' => $currentRows[0] ?? null,
                    'nextFirst' => $nextRows[0] ?? null,
                    'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                    'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                    'gainedLabels' => array_values(array_diff(self::rowLabels($nextRows), self::rowLabels($currentRows))),
                    'lostLabels' => array_values(array_diff(self::rowLabels($currentRows), self::rowLabels($nextRows))),
                ],
                'changedSignatures' => self::changedRowSignatures($currentRows, $nextRows),
                'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreLimitRows, $nextPreLimitRows, $currentTrace),
                'dependencies' => [
                    'sqlite-recursive-queue-order-limit-before-intersect',
                    'sqlite-window-arm-before-compound-intersect',
                    'sqlite-compound-intersect-final-limit-yield',
                ],
                'dependency_closure' => 'no new support component needed; this reuses lane-local SELECT SQL, recursive CTE queue ORDER/LIMIT, compound INTERSECT, window, and LIMIT/OFFSET execution',
            ];
        }

        /**
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         */
        private static function assertSupportedIntersectWindowRecursiveLimit(string $sql, array $currentPlan, array $nextPlan): void
        {
            if (stripos($sql, 'WITH RECURSIVE') === false) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT needs WITH RECURSIVE SQL');
            }
            if (stripos($sql, 'ORDER BY 3') === false) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT needs recursive queue ORDER BY');
            }
            if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT needs a compound SELECT');
            }
            if (!in_array('INTERSECT', self::compoundOperators($currentPlan), true)) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT needs an INTERSECT arm');
            }
            if (($currentPlan['compound']['limit'] ?? null) === null || stripos($sql, ' OFFSET ') === false) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT needs final LIMIT/OFFSET');
            }
            if (self::windowTerms($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT needs a window arm');
            }
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function compoundOperators(array $plan): array
        {
            $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

            return array_values(array_map('strtoupper', is_array($compound['operators'] ?? null) ? $compound['operators'] : []));
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function compoundOrderColumns(array $plan): array
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
        private static function intersectArmIndex(array $plan): ?int
        {
            foreach (self::compoundOperators($plan) as $index => $operator) {
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
        private static function windowTerms(array $plan): array
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

        private static function withoutFinalLimit(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            $without = preg_replace('/\s+LIMIT\s+\d+\s+OFFSET\s+\d+\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT window recursive LIMIT cannot isolate final LIMIT/OFFSET');
            }

            return $without;
        }

        /**
         * @param array<string,mixed> $trace
         */
        private static function lastTraceValue(array $trace, string $key): mixed
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
        private static function yieldBoundary(array $preLimitRows, array $limitedRows, array $plan): array
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
        private static function rowLabels(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => isset($row['label']) && is_scalar($row['label']) ? (string) $row['label'] : '', $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedRowLabels(array $currentRows, array $nextRows): array
        {
            return array_values(array_merge(array_diff(self::rowLabels($currentRows), self::rowLabels($nextRows)), array_diff(self::rowLabels($nextRows), self::rowLabels($currentRows))));
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
        private static function changedRowSignatures(array $currentRows, array $nextRows): array
        {
            return array_values(array_merge(array_diff(self::rowSignatures($currentRows), self::rowSignatures($nextRows)), array_diff(self::rowSignatures($nextRows), self::rowSignatures($currentRows))));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentPreLimitRows
         * @param list<array<string,mixed>> $nextPreLimitRows
         * @param array<string,mixed> $currentTrace
         * @return list<string>
         */
        private static function replanReasons(array $currentRows, array $nextRows, array $currentPreLimitRows, array $nextPreLimitRows, array $currentTrace): array
        {
            $reasons = ['recursive-queue-order-limit-before-intersect', 'window-before-compound-intersect', 'compound-intersect-before-final-limit'];
            if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
                $reasons[] = 'limited-compound-intersect-rowset-changed';
            }
            if (self::rowSignatures($currentPreLimitRows) !== self::rowSignatures($nextPreLimitRows)) {
                $reasons[] = 'prelimit-compound-intersect-rowset-changed';
            }
            if (self::lastTraceValue($currentTrace, 'limit_remaining') === 0) {
                $reasons[] = 'recursive-order-limit-exhausted-before-intersect';
            }

            return $reasons;
        }

}
