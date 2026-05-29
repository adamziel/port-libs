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
        public static function compareNext144(string $sql, array $currentTables, array $nextTables): array
        {
            if (stripos($sql, 'WITH RECURSIVE') === false) {
                throw new \InvalidArgumentException('SQLite compound recursive window ORDER current-source next144 needs WITH RECURSIVE SQL');
            }

            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound recursive window ORDER current-source next144 needs a compound SELECT');
            }
            if (self::windowTermsNext144($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound recursive window ORDER current-source next144 needs a window function arm');
            }
            if (self::orderColumnsNext144($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound recursive window ORDER current-source next144 needs final ORDER BY terms');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $traceSql = self::traceSqlNext144($sql);
            $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
            $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

            return [
                'status' => 'compound-select-recursive-window-order-current-source-next144-ready',
                'dependencies' => [
                    'sqlite-recursive-cte-queue-order-before-window-arm',
                    'sqlite-select-sql-window-arm-before-compound-final-order',
                    'sqlite-compound-select-final-order-current-source-next144',
                ],
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext144($currentPlan),
                    'orderDirections' => self::orderDirectionsNext144($currentPlan),
                    'limit' => $currentPlan['compound']['limit'] ?? null,
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                ],
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'changedSignatures' => self::changedSignaturesNext144($currentRows, $nextRows),
                'recursive' => [
                    'name' => $currentTrace['name'],
                    'columns' => $currentTrace['columns'],
                    'operator' => $currentTrace['operator'],
                    'currentVisitedLabels' => array_column($currentTrace['rows'], 'label'),
                    'nextVisitedLabels' => array_column($nextTrace['rows'], 'label'),
                    'currentQueueKeys' => self::queueKeysNext144($currentTrace['rows']),
                    'nextQueueKeys' => self::queueKeysNext144($nextTrace['rows']),
                    'currentAcceptedNextLabels' => self::acceptedNextLabelsNext144($currentTrace['trace']),
                    'nextAcceptedNextLabels' => self::acceptedNextLabelsNext144($nextTrace['trace']),
                    'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'], $nextTrace['dependencies']))),
                ],
                'windows' => [
                    'current' => self::windowTermsNext144($currentPlan),
                    'next' => self::windowTermsNext144($nextPlan),
                    'outputAliases' => self::windowAliasesNext144($currentPlan),
                ],
                'orderBoundary' => [
                    'currentFirst' => $currentRows[0] ?? null,
                    'nextFirst' => $nextRows[0] ?? null,
                    'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                    'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                    'currentCount' => count($currentRows),
                    'nextCount' => count($nextRows),
                ],
                'replanReasons' => self::replanReasonsNext144($currentRows, $nextRows, $currentTrace['rows'], $nextTrace['rows'], $currentPlan, $nextPlan),
            ];
        }

        private static function traceSqlNext144(string $sql): string
        {
            $sql = trim(rtrim(trim($sql), ';'));
            if (preg_match('/^(.*\))\s*SELECT\s+id\s*,\s*label\s*,\s*depth\s*,\s*queue_key\b/is', $sql, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound recursive window ORDER current-source next144 cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT id, label, depth, queue_key FROM option_walk';
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumnsNext144(array $plan): array
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
        private static function orderDirectionsNext144(array $plan): array
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
        private static function windowTermsNext144(array $plan): array
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
        private static function windowAliasesNext144(array $plan): array
        {
            return array_values(array_map(static fn (array $term): string => (string) $term['alias'], self::windowTermsNext144($plan)));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function queueKeysNext144(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => self::sqliteValueClassNext144($row['queue_key'] ?? null), $rows));
        }

        /**
         * @param list<array<string,mixed>> $trace
         * @return list<list<string>>
         */
        private static function acceptedNextLabelsNext144(array $trace): array
        {
            $labels = [];
            foreach ($trace as $entry) {
                $accepted = is_array($entry['accepted_next'] ?? null) ? $entry['accepted_next'] : [];
                $labels[] = array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? ''), $accepted));
            }

            return $labels;
        }

        private static function sqliteValueClassNext144(mixed $value): string
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
        private static function rowSignaturesNext144(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext144(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext144($currentRows);
            $next = self::rowSignaturesNext144($nextRows);

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
        private static function replanReasonsNext144(array $currentRows, array $nextRows, array $currentRecursiveRows, array $nextRecursiveRows, array $currentPlan, array $nextPlan): array
        {
            $reasons = [];
            if (self::rowSignaturesNext144($currentRows) !== self::rowSignaturesNext144($nextRows)) {
                $reasons[] = 'compound-recursive-window-rowset-changed';
            }
            if (array_column($currentRecursiveRows, 'label') !== array_column($nextRecursiveRows, 'label')) {
                $reasons[] = 'recursive-queue-order-before-window-changed';
            }
            if (self::queueKeysNext144($currentRecursiveRows) !== self::queueKeysNext144($nextRecursiveRows)) {
                $reasons[] = 'recursive-queue-storage-class-boundary-changed';
            }
            if (self::windowTermsNext144($currentPlan) !== []) {
                $reasons[] = 'window-arm-evaluated-before-compound-order';
            }
            if (self::orderColumnsNext144($currentPlan) !== self::orderColumnsNext144($nextPlan)) {
                $reasons[] = 'compound-final-order-plan-changed';
            } else {
                $reasons[] = 'compound-final-order-after-recursive-window';
            }

            return $reasons;
        }

}
