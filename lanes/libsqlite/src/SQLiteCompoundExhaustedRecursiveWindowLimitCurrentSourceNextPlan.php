<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareNext166(string $sql, array $currentTables, array $nextTables): array
        {
            if (stripos($sql, 'WITH RECURSIVE') === false) {
                throw new \InvalidArgumentException('SQLite compound exhausted recursive window LIMIT next166 needs WITH RECURSIVE SQL');
            }
            if (preg_match('/\bLIMIT\s+0\b/i', $sql) !== 1) {
                throw new \InvalidArgumentException('SQLite compound exhausted recursive window LIMIT next166 needs a recursive LIMIT 0 queue');
            }

            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
                throw new \InvalidArgumentException('SQLite compound exhausted recursive window LIMIT next166 needs a compound SELECT');
            }
            if (($currentPlan['compound']['limit'] ?? null) === null) {
                throw new \InvalidArgumentException('SQLite compound exhausted recursive window LIMIT next166 needs a final LIMIT');
            }
            if (self::windowTermsNext166($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound exhausted recursive window LIMIT next166 needs a window function arm');
            }

            $traceSql = self::recursiveTraceSqlNext166($sql);
            $currentRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
            $nextRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);
            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentPreLimit = SQLiteSelectSql::execute(self::withoutFinalLimitNext166($sql), $currentTables);
            $nextPreLimit = SQLiteSelectSql::execute(self::withoutFinalLimitNext166($sql), $nextTables);

            return [
                'status' => 'compound-exhausted-recursive-window-limit-current-source-next166-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentPreLimitRows' => $currentPreLimit,
                'nextPreLimitRows' => $nextPreLimit,
                'changedSignatures' => self::changedSignaturesNext166($currentRows, $nextRows),
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext166($currentPlan),
                    'limit' => $currentPlan['compound']['limit'],
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                ],
                'windows' => [
                    'current' => self::windowTermsNext166($currentPlan),
                    'next' => self::windowTermsNext166($nextPlan),
                ],
                'recursive' => self::recursiveSummaryNext166($currentRecursive, $nextRecursive),
                'limitTrace' => [
                    'current' => self::limitTraceNext166($currentPreLimit, $currentRows, $currentPlan),
                    'next' => self::limitTraceNext166($nextPreLimit, $nextRows, $nextPlan),
                ],
                'replanReasons' => self::replanReasonsNext166($currentRows, $nextRows, $currentPreLimit, $nextPreLimit, $currentRecursive, $nextRecursive),
                'dependencies' => [
                    'sqlite-recursive-cte-limit-zero-exhaustion',
                    'sqlite-select-sql-window-arm-evaluation',
                    'sqlite-select-sql-compound-final-limit',
                    'sqlite-current-source-next166',
                ],
            ];
        }

        private static function recursiveTraceSqlNext166(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound exhausted recursive window LIMIT next166 cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT * FROM ' . $match[2];
        }

        private static function withoutFinalLimitNext166(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound exhausted recursive window LIMIT next166 cannot isolate final LIMIT');
            }

            return $without;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumnsNext166(array $plan): array
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
        private static function windowTermsNext166(array $plan): array
        {
            $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
            $arms = is_array($compound['arms'] ?? null) ? $compound['arms'] : [];
            $windows = [];
            foreach ($arms as $armIndex => $arm) {
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
         * @param array<string,mixed> $currentTrace
         * @param array<string,mixed> $nextTrace
         * @return array<string,mixed>
         */
        private static function recursiveSummaryNext166(array $currentTrace, array $nextTrace): array
        {
            return [
                'name' => $currentTrace['name'] ?? null,
                'columns' => $currentTrace['columns'] ?? [],
                'operator' => $currentTrace['operator'] ?? null,
                'currentRows' => $currentTrace['rows'] ?? [],
                'nextRows' => $nextTrace['rows'] ?? [],
                'currentTraceCount' => is_array($currentTrace['trace'] ?? null) ? count($currentTrace['trace']) : 0,
                'nextTraceCount' => is_array($nextTrace['trace'] ?? null) ? count($nextTrace['trace']) : 0,
                'currentFirstTrace' => self::firstTraceNext166($currentTrace),
                'nextFirstTrace' => self::firstTraceNext166($nextTrace),
                'currentLimitRemaining' => self::lastTraceValueNext166($currentTrace, 'limit_remaining'),
                'nextLimitRemaining' => self::lastTraceValueNext166($nextTrace, 'limit_remaining'),
                'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'] ?? [], $nextTrace['dependencies'] ?? []))),
            ];
        }

        /**
         * @param array<string,mixed> $trace
         * @return array<string,mixed>|null
         */
        private static function firstTraceNext166(array $trace): ?array
        {
            $rows = is_array($trace['trace'] ?? null) ? $trace['trace'] : [];

            return isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
        }

        /**
         * @param array<string,mixed> $trace
         */
        private static function lastTraceValueNext166(array $trace, string $key): mixed
        {
            $rows = is_array($trace['trace'] ?? null) ? $trace['trace'] : [];
            if ($rows === []) {
                return null;
            }
            $last = $rows[count($rows) - 1];

            return is_array($last) ? ($last[$key] ?? null) : null;
        }

        /**
         * @param list<array<string,mixed>> $preLimitRows
         * @param list<array<string,mixed>> $limitedRows
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function limitTraceNext166(array $preLimitRows, array $limitedRows, array $plan): array
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
        private static function rowSignaturesNext166(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext166(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext166($currentRows);
            $next = self::rowSignaturesNext166($nextRows);

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
        private static function replanReasonsNext166(array $currentRows, array $nextRows, array $currentPreLimit, array $nextPreLimit, array $currentRecursive, array $nextRecursive): array
        {
            $reasons = ['recursive-limit-zero-exhausted-before-window-arm'];
            if (self::rowSignaturesNext166($currentRows) !== self::rowSignaturesNext166($nextRows)) {
                $reasons[] = 'limited-compound-rowset-changed';
            }
            if (self::rowSignaturesNext166($currentPreLimit) !== self::rowSignaturesNext166($nextPreLimit)) {
                $reasons[] = 'prelimit-compound-rowset-changed';
            }
            if (($currentRecursive['rows'] ?? []) === [] && ($nextRecursive['rows'] ?? []) === []) {
                $reasons[] = 'recursive-arm-empty-in-current-and-next';
            }
            $reasons[] = 'window-before-compound-final-limit';

            return array_values(array_unique($reasons));
        }

}
