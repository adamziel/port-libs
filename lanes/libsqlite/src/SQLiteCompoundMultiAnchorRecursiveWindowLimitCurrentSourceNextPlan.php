<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundMultiAnchorRecursiveWindowLimitCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundMultiAnchorRecursiveWindowLimitCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compare(string $sql, array $currentTables, array $nextTables): array
        {
            if (stripos($sql, 'WITH RECURSIVE') === false) {
                throw new \InvalidArgumentException('SQLite compound multi-anchor recursive window LIMIT needs WITH RECURSIVE SQL');
            }
            if (preg_match('/\bVALUES\b.*\b(?:INTERSECT|EXCEPT)\b.*\bUNION(?:\s+ALL)?\b/is', $sql) !== 1) {
                throw new \InvalidArgumentException('SQLite compound multi-anchor recursive window LIMIT needs compound non-recursive anchor arms');
            }

            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
                throw new \InvalidArgumentException('SQLite compound multi-anchor recursive window LIMIT needs a trailing compound SELECT');
            }
            if (($currentPlan['compound']['limit'] ?? null) === null) {
                throw new \InvalidArgumentException('SQLite compound multi-anchor recursive window LIMIT needs a final LIMIT');
            }
            if (self::windowTerms($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound multi-anchor recursive window LIMIT needs a window function arm');
            }

            $traceSql = self::recursiveTraceSql($sql);
            $currentRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
            $nextRecursive = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);
            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentPreLimit = SQLiteSelectSql::execute(self::withoutFinalLimit($sql), $currentTables);
            $nextPreLimit = SQLiteSelectSql::execute(self::withoutFinalLimit($sql), $nextTables);

            return [
                'status' => 'compound-multi-anchor-recursive-window-limit-current-source-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentPreLimitRows' => $currentPreLimit,
                'nextPreLimitRows' => $nextPreLimit,
                'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumns($currentPlan),
                    'limit' => $currentPlan['compound']['limit'],
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                ],
                'windows' => [
                    'current' => self::windowTerms($currentPlan),
                    'next' => self::windowTerms($nextPlan),
                ],
                'recursive' => self::recursiveSummary($currentRecursive, $nextRecursive),
                'limitTrace' => [
                    'current' => self::limitTrace($currentPreLimit, $currentRows, $currentPlan),
                    'next' => self::limitTrace($nextPreLimit, $nextRows, $nextPlan),
                ],
                'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreLimit, $nextPreLimit, $currentRecursive, $nextRecursive),
                'dependencies' => [
                    'sqlite-recursive-cte-compound-anchor-arms',
                    'sqlite-recursive-cte-order-limit-queue',
                    'sqlite-select-sql-window-arm-evaluation',
                    'sqlite-select-sql-compound-final-limit',
                    'sqlite-current-source',
                ],
            ];
        }

        private static function recursiveTraceSql(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound multi-anchor recursive window LIMIT cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT * FROM ' . $match[2];
        }

        private static function withoutFinalLimit(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:,\s*\d+|OFFSET\s+\d+)?\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound multi-anchor recursive window LIMIT cannot isolate final LIMIT');
            }

            return $without;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumns(array $plan): array
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
        private static function windowTerms(array $plan): array
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

        /**
         * @param array<string,mixed> $currentTrace
         * @param array<string,mixed> $nextTrace
         * @return array<string,mixed>
         */
        private static function recursiveSummary(array $currentTrace, array $nextTrace): array
        {
            return [
                'name' => $currentTrace['name'] ?? null,
                'columns' => $currentTrace['columns'] ?? [],
                'operator' => $currentTrace['operator'] ?? null,
                'currentRows' => $currentTrace['rows'] ?? [],
                'nextRows' => $nextTrace['rows'] ?? [],
                'currentTraceCount' => is_array($currentTrace['trace'] ?? null) ? count($currentTrace['trace']) : 0,
                'nextTraceCount' => is_array($nextTrace['trace'] ?? null) ? count($nextTrace['trace']) : 0,
                'currentGeneratedLabels' => self::traceGeneratedLabels($currentTrace),
                'nextGeneratedLabels' => self::traceGeneratedLabels($nextTrace),
                'currentSkippedLabels' => self::skippedLabels($currentTrace),
                'nextSkippedLabels' => self::skippedLabels($nextTrace),
                'currentLimitRemaining' => self::lastTraceValue($currentTrace, 'limit_remaining'),
                'nextLimitRemaining' => self::lastTraceValue($nextTrace, 'limit_remaining'),
                'currentOffsetRemaining' => self::lastTraceValue($currentTrace, 'offset_remaining'),
                'nextOffsetRemaining' => self::lastTraceValue($nextTrace, 'offset_remaining'),
                'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'] ?? [], $nextTrace['dependencies'] ?? []))),
            ];
        }

        /**
         * @param array<string,mixed> $trace
         * @return list<string>
         */
        private static function traceGeneratedLabels(array $trace): array
        {
            $labels = [];
            foreach (($trace['trace'] ?? []) as $step) {
                if (!is_array($step)) {
                    continue;
                }
                foreach (($step['accepted_next'] ?? []) as $row) {
                    if (is_array($row) && isset($row['label'])) {
                        $labels[] = (string) $row['label'];
                    }
                }
            }

            return $labels;
        }

        /**
         * @param array<string,mixed> $trace
         * @return list<string>
         */
        private static function skippedLabels(array $trace): array
        {
            $labels = [];
            foreach (($trace['skipped'] ?? []) as $step) {
                $row = is_array($step) ? ($step['row'] ?? null) : null;
                if (is_array($row) && isset($row['label'])) {
                    $labels[] = (string) $row['label'];
                }
            }

            return $labels;
        }

        /**
         * @param array<string,mixed> $trace
         */
        private static function lastTraceValue(array $trace, string $key): mixed
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
         * @param list<array<string,mixed>> $currentPreLimit
         * @param list<array<string,mixed>> $nextPreLimit
         * @param array<string,mixed> $currentRecursive
         * @param array<string,mixed> $nextRecursive
         * @return list<string>
         */
        private static function replanReasons(array $currentRows, array $nextRows, array $currentPreLimit, array $nextPreLimit, array $currentRecursive, array $nextRecursive): array
        {
            $reasons = [];
            if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
                $reasons[] = 'limited-compound-rowset-changed';
            }
            if (self::rowSignatures($currentPreLimit) !== self::rowSignatures($nextPreLimit)) {
                $reasons[] = 'prelimit-compound-rowset-changed';
            }
            if (($currentRecursive['rows'] ?? []) !== ($nextRecursive['rows'] ?? [])) {
                $reasons[] = 'recursive-multi-anchor-rowset-changed';
            }
            if (($currentRecursive['skipped'] ?? []) !== [] || ($nextRecursive['skipped'] ?? []) !== []) {
                $reasons[] = 'recursive-union-cycle-dedup';
            }
            $reasons[] = 'compound-anchor-before-recursive-arm';
            $reasons[] = 'window-before-compound-final-limit';

            return array_values(array_unique($reasons));
        }

}
