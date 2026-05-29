<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundRecursiveLimitWindowCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundRecursiveLimitWindowCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareNext135(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound recursive limit window current-source plan needs a compound SELECT');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);

            return [
                'status' => 'compound-recursive-limit-window-current-source-next135-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentSignatures' => self::rowSignaturesNext135($currentRows),
                'nextSignatures' => self::rowSignaturesNext135($nextRows),
                'changedSignatures' => self::changedSignaturesNext135($currentRows, $nextRows),
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext135($currentPlan),
                    'limit' => $currentPlan['compound']['limit'] ?? null,
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                ],
                'windows' => [
                    'current' => self::windowTermsNext135($currentPlan),
                    'next' => self::windowTermsNext135($nextPlan),
                ],
                'recursive' => self::recursiveSummaryNext135($sql, $currentTables, $nextTables),
                'limitBoundary' => self::limitBoundaryNext135($currentRows, $nextRows),
                'replanReasons' => self::replanReasonsNext135($currentRows, $nextRows, $currentPlan, $nextPlan),
                'dependencies' => [
                    'sqlite-recursive-cte-queue-limit',
                    'sqlite-select-sql-compound-tail-limit',
                    'sqlite-select-sql-window-current-following-frame',
                    'sqlite-select-sql-current-source-next-rowset',
                ],
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumnsNext135(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['orderBy'] ?? null)) {
                return [];
            }

            return array_values(array_map(
                static fn (array $term): string => (string) ($term['column'] ?? ''),
                $compound['orderBy'],
            ));
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function windowTermsNext135(array $plan): array
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
                        'exclude' => isset($frame['exclude']) ? (string) $frame['exclude'] : null,
                    ];
                }
            }

            return $windows;
        }

        /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        private static function recursiveSummaryNext135(string $sql, array $currentTables, array $nextTables): array
        {
            $traceSql = self::traceSqlNext135($sql);
            $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
            $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

            return [
                'name' => $currentTrace['name'],
                'columns' => $currentTrace['columns'],
                'currentRows' => $currentTrace['rows'],
                'nextRows' => $nextTrace['rows'],
                'currentTraceCount' => count($currentTrace['trace']),
                'nextTraceCount' => count($nextTrace['trace']),
                'currentLimitRemaining' => self::lastTraceValueNext135($currentTrace['trace'], 'limit_remaining'),
                'nextLimitRemaining' => self::lastTraceValueNext135($nextTrace['trace'], 'limit_remaining'),
                'currentLastQueueAfter' => self::lastTraceValueNext135($currentTrace['trace'], 'queue_after'),
                'nextLastQueueAfter' => self::lastTraceValueNext135($nextTrace['trace'], 'queue_after'),
                'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'], $nextTrace['dependencies']))),
            ];
        }

        private static function traceSqlNext135(string $sql): string
        {
            $sql = trim(rtrim(trim($sql), ';'));
            if (stripos($sql, 'WITH RECURSIVE') !== 0) {
                throw new \InvalidArgumentException('SQLite compound recursive limit window current-source plan needs WITH RECURSIVE');
            }
            if (preg_match('/^(.*\))\s*SELECT\s+id\s*,\s*label\s*,/is', $sql, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound recursive limit window current-source plan cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT id, label, depth, weight FROM wanted';
        }

        /**
         * @param list<array<string,mixed>> $trace
         */
        private static function lastTraceValueNext135(array $trace, string $key): mixed
        {
            if ($trace === []) {
                return null;
            }

            return $trace[count($trace) - 1][$key] ?? null;
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return array{currentCount:int,nextCount:int,currentFirst:array<string,mixed>|null,nextFirst:array<string,mixed>|null,currentLast:array<string,mixed>|null,nextLast:array<string,mixed>|null}
         */
        private static function limitBoundaryNext135(array $currentRows, array $nextRows): array
        {
            return [
                'currentCount' => count($currentRows),
                'nextCount' => count($nextRows),
                'currentFirst' => $currentRows[0] ?? null,
                'nextFirst' => $nextRows[0] ?? null,
                'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignaturesNext135(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext135(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext135($currentRows);
            $next = self::rowSignaturesNext135($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         * @return list<string>
         */
        private static function replanReasonsNext135(array $currentRows, array $nextRows, array $currentPlan, array $nextPlan): array
        {
            $reasons = [];
            if (self::rowSignaturesNext135($currentRows) !== self::rowSignaturesNext135($nextRows)) {
                $reasons[] = 'recursive-limited-compound-rowset-changed';
            }
            if (self::windowTermsNext135($currentPlan) !== []) {
                $reasons[] = 'compound-window-current-following-source';
            }
            if (($currentPlan['compound']['limit'] ?? null) !== null) {
                $reasons[] = 'compound-tail-limit';
            }
            if (self::windowTermsNext135($currentPlan) !== self::windowTermsNext135($nextPlan)) {
                $reasons[] = 'window-plan-changed';
            }

            return $reasons;
        }

}
