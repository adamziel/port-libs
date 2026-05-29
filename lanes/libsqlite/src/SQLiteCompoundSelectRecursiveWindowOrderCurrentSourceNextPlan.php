<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compare(string $sql, array $currentTables, array $nextTables): array
        {
            if (stripos($sql, 'WITH RECURSIVE') === false) {
                throw new \InvalidArgumentException('SQLite compound recursive window ORDER current-source needs WITH RECURSIVE SQL');
            }

            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound recursive window ORDER current-source needs a compound SELECT');
            }
            if (self::windowTerms($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound recursive window ORDER current-source needs a window function arm');
            }
            if (self::orderColumns($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound recursive window ORDER current-source needs final ORDER BY terms');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $traceSql = self::traceSql($sql);
            $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
            $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

            return [
                'status' => 'compound-select-recursive-window-order-current-source-ready',
                'dependencies' => [
                    'sqlite-recursive-cte-queue-order-before-window-arm',
                    'sqlite-select-sql-window-arm-before-compound-final-order',
                    'sqlite-compound-select-final-order-current-source',
                ],
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumns($currentPlan),
                    'orderDirections' => self::orderDirections($currentPlan),
                    'limit' => $currentPlan['compound']['limit'] ?? null,
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                ],
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
                'recursive' => [
                    'name' => $currentTrace['name'],
                    'columns' => $currentTrace['columns'],
                    'operator' => $currentTrace['operator'],
                    'currentVisitedLabels' => array_column($currentTrace['rows'], 'label'),
                    'nextVisitedLabels' => array_column($nextTrace['rows'], 'label'),
                    'currentQueueKeys' => self::queueKeys($currentTrace['rows']),
                    'nextQueueKeys' => self::queueKeys($nextTrace['rows']),
                    'currentAcceptedNextLabels' => self::acceptedNextLabels($currentTrace['trace']),
                    'nextAcceptedNextLabels' => self::acceptedNextLabels($nextTrace['trace']),
                    'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'], $nextTrace['dependencies']))),
                ],
                'windows' => [
                    'current' => self::windowTerms($currentPlan),
                    'next' => self::windowTerms($nextPlan),
                    'outputAliases' => self::windowAliases($currentPlan),
                ],
                'orderBoundary' => [
                    'currentFirst' => $currentRows[0] ?? null,
                    'nextFirst' => $nextRows[0] ?? null,
                    'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                    'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                    'currentCount' => count($currentRows),
                    'nextCount' => count($nextRows),
                ],
                'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentTrace['rows'], $nextTrace['rows'], $currentPlan, $nextPlan),
            ];
        }

        private static function traceSql(string $sql): string
        {
            $sql = trim(rtrim(trim($sql), ';'));
            if (preg_match('/^(.*\))\s*SELECT\s+id\s*,\s*label\s*,\s*depth\s*,\s*queue_key\b/is', $sql, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound recursive window ORDER current-source cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT id, label, depth, queue_key FROM option_walk';
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
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderDirections(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['orderBy'] ?? null)) {
                return [];
            }

            return array_values(array_map(static fn (array $term): string => (string) ($term['direction'] ?? 'ASC'), $compound['orderBy']));
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
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function windowAliases(array $plan): array
        {
            return array_values(array_map(static fn (array $term): string => (string) $term['alias'], self::windowTerms($plan)));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function queueKeys(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => self::sqliteValueClass($row['queue_key'] ?? null), $rows));
        }

        /**
         * @param list<array<string,mixed>> $trace
         * @return list<list<string>>
         */
        private static function acceptedNextLabels(array $trace): array
        {
            $labels = [];
            foreach ($trace as $entry) {
                $accepted = is_array($entry['accepted_next'] ?? null) ? $entry['accepted_next'] : [];
                $labels[] = array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? ''), $accepted));
            }

            return $labels;
        }

        private static function sqliteValueClass(mixed $value): string
        {
            if ($value === null) {
                return 'null';
            }
            if (is_int($value) || is_float($value) || is_bool($value)) {
                return 'numeric:' . (string) (0 + $value);
            }
            if ($value instanceof SQLiteBlobValue) {
                return 'blob:' . bin2hex($value->bytes);
            }

            return get_debug_type($value) . ':' . (string) $value;
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
         * @param list<array<string,mixed>> $currentRecursiveRows
         * @param list<array<string,mixed>> $nextRecursiveRows
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         * @return list<string>
         */
        private static function replanReasons(array $currentRows, array $nextRows, array $currentRecursiveRows, array $nextRecursiveRows, array $currentPlan, array $nextPlan): array
        {
            $reasons = [];
            if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
                $reasons[] = 'compound-recursive-window-rowset-changed';
            }
            if (array_column($currentRecursiveRows, 'label') !== array_column($nextRecursiveRows, 'label')) {
                $reasons[] = 'recursive-queue-order-before-window-changed';
            }
            if (self::queueKeys($currentRecursiveRows) !== self::queueKeys($nextRecursiveRows)) {
                $reasons[] = 'recursive-queue-storage-class-boundary-changed';
            }
            if (self::windowTerms($currentPlan) !== []) {
                $reasons[] = 'window-arm-evaluated-before-compound-order';
            }
            if (self::orderColumns($currentPlan) !== self::orderColumns($nextPlan)) {
                $reasons[] = 'compound-final-order-plan-changed';
            } else {
                $reasons[] = 'compound-final-order-after-recursive-window';
            }

            return $reasons;
        }

}
