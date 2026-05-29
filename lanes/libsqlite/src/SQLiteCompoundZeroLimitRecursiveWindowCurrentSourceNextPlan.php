<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundZeroLimitRecursiveWindowCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundZeroLimitRecursiveWindowCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareNext174(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            self::assertSupportedNext174($sql, $currentPlan, $nextPlan);

            $preLimitSql = self::withoutFinalLimitNext174($sql);
            $traceSql = self::recursiveTraceSqlNext174($sql);
            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentPreLimit = SQLiteSelectSql::execute($preLimitSql, $currentTables);
            $nextPreLimit = SQLiteSelectSql::execute($preLimitSql, $nextTables);
            $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
            $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

            return [
                'status' => 'compound-zero-limit-recursive-window-current-source-next174-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentPreLimitRows' => $currentPreLimit,
                'nextPreLimitRows' => $nextPreLimit,
                'suppressedSignatures' => [
                    'current' => self::rowSignaturesNext174($currentPreLimit),
                    'next' => self::rowSignaturesNext174($nextPreLimit),
                ],
                'changedSuppressedSignatures' => self::changedSignaturesNext174($currentPreLimit, $nextPreLimit),
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext174($currentPlan),
                    'limit' => $currentPlan['compound']['limit'],
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                    'zeroLimitSuppressesRows' => $currentRows === [] && $nextRows === [] && $currentPreLimit !== [] && $nextPreLimit !== [],
                ],
                'windows' => [
                    'current' => self::windowTermsNext174($currentPlan),
                    'next' => self::windowTermsNext174($nextPlan),
                ],
                'recursive' => self::recursiveSummaryNext174($currentTrace, $nextTrace),
                'limitTrace' => [
                    'current' => self::limitTraceNext174($currentPreLimit, $currentRows, $currentPlan),
                    'next' => self::limitTraceNext174($nextPreLimit, $nextRows, $nextPlan),
                ],
                'sourceDelta' => self::sourceDeltaNext174($currentPreLimit, $nextPreLimit),
                'replanReasons' => self::replanReasonsNext174($currentRows, $nextRows, $currentPreLimit, $nextPreLimit, $currentTrace, $nextTrace, $currentPlan),
                'dependencies' => [
                    'sqlite-select-sql-recursive-cte-limit-next174',
                    'sqlite-select-sql-window-before-compound-limit-zero-next174',
                    'sqlite-select-sql-compound-final-limit-zero-next174',
                    'sqlite-current-source-next174',
                ],
                'dependency_closure' => 'no new support component needed; next174 reuses lane-local recursive CTE tracing, SELECT SQL compound execution, window row-array evaluation, and final LIMIT/OFFSET result trimming',
            ];
        }

        /**
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         */
        private static function assertSupportedNext174(string $sql, array $currentPlan, array $nextPlan): void
        {
            if (stripos($sql, 'WITH RECURSIVE') === false) {
                throw new \InvalidArgumentException('SQLite compound zero LIMIT recursive window current-source next174 needs WITH RECURSIVE SQL');
            }
            if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
                throw new \InvalidArgumentException('SQLite compound zero LIMIT recursive window current-source next174 needs a compound SELECT');
            }
            if (($currentPlan['compound']['limit'] ?? null) !== 0 || ($nextPlan['compound']['limit'] ?? null) !== 0) {
                throw new \InvalidArgumentException('SQLite compound zero LIMIT recursive window current-source next174 needs final LIMIT 0');
            }
            if (self::windowTermsNext174($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound zero LIMIT recursive window current-source next174 needs a window function arm');
            }
        }

        private static function recursiveTraceSqlNext174(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound zero LIMIT recursive window current-source next174 cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT * FROM ' . $match[2];
        }

        private static function withoutFinalLimitNext174(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            $without = preg_replace('/\s+LIMIT\s+0\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound zero LIMIT recursive window current-source next174 cannot isolate final LIMIT 0');
            }

            return $without;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumnsNext174(array $plan): array
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
        private static function windowTermsNext174(array $plan): array
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
        private static function recursiveSummaryNext174(array $currentTrace, array $nextTrace): array
        {
            return [
                'name' => $currentTrace['name'] ?? null,
                'columns' => $currentTrace['columns'] ?? [],
                'operator' => $currentTrace['operator'] ?? null,
                'currentRows' => $currentTrace['rows'] ?? [],
                'nextRows' => $nextTrace['rows'] ?? [],
                'currentTraceCount' => is_array($currentTrace['trace'] ?? null) ? count($currentTrace['trace']) : 0,
                'nextTraceCount' => is_array($nextTrace['trace'] ?? null) ? count($nextTrace['trace']) : 0,
                'currentLimitRemaining' => self::lastTraceValueNext174($currentTrace, 'limit_remaining'),
                'nextLimitRemaining' => self::lastTraceValueNext174($nextTrace, 'limit_remaining'),
                'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'] ?? [], $nextTrace['dependencies'] ?? []))),
            ];
        }

        /**
         * @param array<string,mixed> $trace
         */
        private static function lastTraceValueNext174(array $trace, string $key): ?int
        {
            $rows = is_array($trace['trace'] ?? null) ? $trace['trace'] : [];
            $last = $rows === [] ? null : $rows[count($rows) - 1];
            $value = is_array($last) ? ($last[$key] ?? null) : null;

            return is_int($value) ? $value : null;
        }

        /**
         * @param list<array<string,mixed>> $preLimitRows
         * @param list<array<string,mixed>> $limitedRows
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function limitTraceNext174(array $preLimitRows, array $limitedRows, array $plan): array
        {
            $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
            $offset = isset($compound['offset']) && is_int($compound['offset']) ? $compound['offset'] : 0;

            return [
                'preLimitCount' => count($preLimitRows),
                'acceptedCount' => count($limitedRows),
                'suppressedCount' => count($preLimitRows),
                'offsetIgnoredByZeroLimit' => $offset,
                'firstSuppressed' => $preLimitRows[0] ?? null,
                'lastSuppressed' => $preLimitRows === [] ? null : $preLimitRows[count($preLimitRows) - 1],
            ];
        }

        /**
         * @param list<array<string,mixed>> $currentPreLimit
         * @param list<array<string,mixed>> $nextPreLimit
         * @return array<string,mixed>
         */
        private static function sourceDeltaNext174(array $currentPreLimit, array $nextPreLimit): array
        {
            $currentLabels = self::labelsNext174($currentPreLimit);
            $nextLabels = self::labelsNext174($nextPreLimit);

            return [
                'currentLabels' => $currentLabels,
                'nextLabels' => $nextLabels,
                'suppressedAddedLabels' => array_values(array_diff($nextLabels, $currentLabels)),
                'suppressedRemovedLabels' => array_values(array_diff($currentLabels, $nextLabels)),
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function labelsNext174(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? $row['name'] ?? ''), $rows));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignaturesNext174(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext174(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext174($currentRows);
            $next = self::rowSignaturesNext174($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentPreLimit
         * @param list<array<string,mixed>> $nextPreLimit
         * @param array<string,mixed> $currentTrace
         * @param array<string,mixed> $nextTrace
         * @param array<string,mixed> $currentPlan
         * @return list<string>
         */
        private static function replanReasonsNext174(array $currentRows, array $nextRows, array $currentPreLimit, array $nextPreLimit, array $currentTrace, array $nextTrace, array $currentPlan): array
        {
            $reasons = ['compound-final-limit-zero-suppressed-output'];
            if ($currentRows === [] && $nextRows === []) {
                $reasons[] = 'current-next-visible-rowset-empty';
            }
            if (self::rowSignaturesNext174($currentPreLimit) !== self::rowSignaturesNext174($nextPreLimit)) {
                $reasons[] = 'suppressed-prelimit-rowset-changed';
            }
            if (($currentTrace['rows'] ?? []) !== ($nextTrace['rows'] ?? [])) {
                $reasons[] = 'recursive-current-next-rowset-compared';
            }
            if (self::windowTermsNext174($currentPlan) !== []) {
                $reasons[] = 'window-evaluated-before-final-limit-zero';
            }

            return array_values(array_unique($reasons));
        }

}
