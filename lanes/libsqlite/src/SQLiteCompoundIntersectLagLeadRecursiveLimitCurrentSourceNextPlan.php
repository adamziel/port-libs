<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan
{

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareIntersectLagLeadRecursiveLimit(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            self::assertIntersectLagLeadRecursiveLimitSupported($sql, $currentPlan, $nextPlan);

            $preLimitSql = self::withoutFinalLimit($sql);
            $traceSql = self::recursiveTraceSql($sql);
            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $currentTables);
            $nextPreLimitRows = SQLiteSelectSql::execute($preLimitSql, $nextTables);
            $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
            $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

            return [
                'status' => 'compound-intersect-lag-lead-recursive-limit-current-source-recursive-limit-ready',
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
                    'currentSkippedLabels' => self::traceLabels($currentTrace, false),
                    'currentEmittedLabels' => self::traceLabels($currentTrace, true),
                    'nextEmittedLabels' => self::traceLabels($nextTrace, true),
                    'currentLimitRemaining' => self::lastTraceValue($currentTrace, 'limit_remaining'),
                    'currentOffsetRemaining' => self::lastTraceValue($currentTrace, 'offset_remaining'),
                    'dependencies' => array_values(array_unique(array_merge(
                        is_array($currentTrace['dependencies'] ?? null) ? $currentTrace['dependencies'] : [],
                        is_array($nextTrace['dependencies'] ?? null) ? $nextTrace['dependencies'] : [],
                    ))),
                ],
                'intersect' => [
                    'currentMatchedLabels' => self::labels($currentPreLimitRows),
                    'nextMatchedLabels' => self::labels($nextPreLimitRows),
                    'changedMatchedLabels' => self::changedLabels($currentPreLimitRows, $nextPreLimitRows),
                    'currentMarkers' => self::markers($currentPreLimitRows),
                    'nextMarkers' => self::markers($nextPreLimitRows),
                ],
                'leadDiagnostics' => [
                    'current' => self::leadRows($currentTables),
                    'next' => self::leadRows($nextTables),
                ],
                'limitTrace' => [
                    'current' => self::limitTrace($currentPreLimitRows, $currentRows, $currentPlan),
                    'next' => self::limitTrace($nextPreLimitRows, $nextRows, $nextPlan),
                ],
                'boundary' => [
                    'currentFirst' => $currentRows[0] ?? null,
                    'nextFirst' => $nextRows[0] ?? null,
                    'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                    'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                    'gainedLabels' => array_values(array_diff(self::labels($nextRows), self::labels($currentRows))),
                    'lostLabels' => array_values(array_diff(self::labels($currentRows), self::labels($nextRows))),
                ],
                'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
                'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreLimitRows, $nextPreLimitRows, $currentTrace, $currentPlan),
                'dependencies' => [
                    'sqlite-recursive-limit-offset-before-intersect-recursive-limit',
                    'sqlite-window-lag-lead-before-compound-intersect-recursive-limit',
                    'sqlite-current-source-limit-boundary-recursive-limit',
                ],
                'dependency_closure' => 'no new support component needed; recursive-limit reuses lane-local SELECT SQL recursive CTE LIMIT/OFFSET, lag/lead window evaluation, INTERSECT, ORDER BY, and final LIMIT helpers',
            ];
        }

        /**
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         */
        private static function assertIntersectLagLeadRecursiveLimitSupported(string $sql, array $currentPlan, array $nextPlan): void
        {
            if (stripos($sql, 'WITH RECURSIVE') === false) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT lag/lead recursive LIMIT recursive-limit needs WITH RECURSIVE SQL');
            }
            if (!is_array($currentPlan['compound'] ?? null) || !is_array($nextPlan['compound'] ?? null)) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT lag/lead recursive LIMIT recursive-limit needs a compound SELECT');
            }
            if (!in_array('INTERSECT', self::compoundOperators($currentPlan), true)) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT lag/lead recursive LIMIT recursive-limit needs INTERSECT');
            }
            if (($currentPlan['compound']['limit'] ?? null) === null || preg_match('/\s+LIMIT\s+\d+\s+OFFSET\s+\d+\s*$/i', rtrim(trim($sql), ';')) !== 1) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT lag/lead recursive LIMIT recursive-limit needs final LIMIT/OFFSET');
            }
            if (preg_match('/\bLIMIT\s+\d+\s+OFFSET\s+\d+/i', self::recursiveTraceSql($sql)) !== 1) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT lag/lead recursive LIMIT recursive-limit needs recursive LIMIT/OFFSET');
            }
            $functions = array_map('strtolower', array_column(self::windowTerms($currentPlan), 'function'));
            if (!in_array('lag', $functions, true)) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT lag/lead recursive LIMIT recursive-limit needs lag() compound arms');
            }
        }

        private static function recursiveTraceSql(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            if (preg_match('/^(WITH\s+RECURSIVE\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s+AS\s*\(.*\))\s*SELECT\s+/is', $trimmed, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT lag/lead recursive LIMIT recursive-limit cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT * FROM ' . $match[2];
        }

        private static function withoutFinalLimit(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            $without = preg_replace('/\s+LIMIT\s+\d+\s+OFFSET\s+\d+\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound INTERSECT lag/lead recursive LIMIT recursive-limit cannot isolate final LIMIT');
            }

            return $without;
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

        /**
         * @param array<string,mixed> $trace
         * @return list<string>
         */
        private static function traceLabels(array $trace, bool $emitted): array
        {
            $rows = is_array($trace['trace'] ?? null) ? $trace['trace'] : [];
            $labels = [];
            foreach ($rows as $step) {
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
        private static function limitTrace(array $preLimitRows, array $limitedRows, array $plan): array
        {
            $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
            $offset = isset($compound['offset']) && is_int($compound['offset']) ? $compound['offset'] : 0;
            $limit = isset($compound['limit']) && is_int($compound['limit']) ? $compound['limit'] : count($limitedRows);

            return [
                'offset' => $offset,
                'limit' => $limit,
                'preLimitCount' => count($preLimitRows),
                'admittedCount' => count($limitedRows),
                'skippedBeforeOffset' => array_slice($preLimitRows, 0, $offset),
                'admitted' => $limitedRows,
                'truncatedAfterLimit' => array_slice($preLimitRows, $offset + $limit),
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function labels(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => isset($row['label']) && is_scalar($row['label']) ? (string) $row['label'] : '', $rows));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function markers(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => isset($row['marker']) && is_scalar($row['marker']) ? (string) $row['marker'] : '', $rows));
        }

        /**
         * @param array<string,list<array<string,mixed>>> $tables
         * @return list<array<string,mixed>>
         */
        private static function leadRows(array $tables): array
        {
            if (!isset($tables['wp_options'])) {
                return [];
            }

            return SQLiteSelectSql::execute(
                "SELECT option_id AS id, option_name AS label, lead(option_name, 1, 'tail') OVER (ORDER BY weight DESC, option_id) AS lead_marker FROM wp_options ORDER BY id",
                $tables,
            );
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedLabels(array $currentRows, array $nextRows): array
        {
            return array_values(array_merge(array_diff(self::labels($nextRows), self::labels($currentRows)), array_diff(self::labels($currentRows), self::labels($nextRows))));
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
            return array_values(array_unique(array_merge(
                array_diff(self::rowSignatures($nextRows), self::rowSignatures($currentRows)),
                array_diff(self::rowSignatures($currentRows), self::rowSignatures($nextRows)),
            )));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentPreLimitRows
         * @param list<array<string,mixed>> $nextPreLimitRows
         * @param array<string,mixed> $currentTrace
         * @param array<string,mixed> $currentPlan
         * @return list<string>
         */
        private static function replanReasons(array $currentRows, array $nextRows, array $currentPreLimitRows, array $nextPreLimitRows, array $currentTrace, array $currentPlan): array
        {
            $reasons = [
                'recursive-limit-offset-before-intersect',
                'lag-lead-window-before-compound-intersect',
                'compound-intersect-before-final-limit',
                'compound-tail-limit-offset',
            ];
            if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
                $reasons[] = 'limited-intersect-rowset-changed';
            }
            if (self::rowSignatures($currentPreLimitRows) !== self::rowSignatures($nextPreLimitRows)) {
                $reasons[] = 'prelimit-intersect-rowset-changed';
            }
            if (self::traceLabels($currentTrace, false) !== []) {
                $reasons[] = 'recursive-offset-skipped-anchor';
            }
            if (count(array_unique(array_column(self::windowTerms($currentPlan), 'function'))) > 1) {
                $reasons[] = 'mixed-lag-lead-window-functions';
            }

            return $reasons;
        }

}
