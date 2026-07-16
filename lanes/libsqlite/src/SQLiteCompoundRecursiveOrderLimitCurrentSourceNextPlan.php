<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundRecursiveOrderLimitCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundRecursiveOrderLimitCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareRecursiveOrderLimit(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound recursive ORDER/LIMIT current-source next146 needs a compound SELECT');
            }
            if (stripos(ltrim($sql), 'WITH RECURSIVE') !== 0) {
                throw new \InvalidArgumentException('SQLite compound recursive ORDER/LIMIT current-source next146 needs WITH RECURSIVE');
            }
            if (!is_array($currentPlan['compound']['orderBy'] ?? null) || !isset($currentPlan['compound']['limit'])) {
                throw new \InvalidArgumentException('SQLite compound recursive ORDER/LIMIT current-source next146 needs final compound ORDER BY and LIMIT');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $traceSql = self::traceSql($sql);
            $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
            $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

            return [
                'status' => 'compound-recursive-order-limit-current-source-next146-ready',
                'dependencies' => [
                    'sqlite-recursive-cte-priority-queue-order',
                    'sqlite-recursive-cte-queue-limit-before-compound',
                    'sqlite-compound-final-order-limit-after-current-source',
                    'sqlite-current-source-next-boundary',
                ],
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderBy' => $currentPlan['compound']['orderBy'],
                    'limit' => $currentPlan['compound']['limit'],
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                    'leftColumns' => self::leftColumns($currentPlan),
                ],
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentSignatures' => self::rowSignatures($currentRows),
                'nextSignatures' => self::rowSignatures($nextRows),
                'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
                'recursive' => [
                    'name' => $currentTrace['name'],
                    'columns' => $currentTrace['columns'],
                    'operator' => $currentTrace['operator'] ?? null,
                    'currentRows' => $currentTrace['rows'],
                    'nextRows' => $nextTrace['rows'],
                    'currentTraceCount' => count($currentTrace['trace']),
                    'nextTraceCount' => count($nextTrace['trace']),
                    'currentVisitOrder' => self::columnValues($currentTrace['rows'], 'name'),
                    'nextVisitOrder' => self::columnValues($nextTrace['rows'], 'name'),
                    'currentQueueAfter' => self::queueAfterNames($currentTrace['trace']),
                    'nextQueueAfter' => self::queueAfterNames($nextTrace['trace']),
                    'currentLimitRemaining' => self::lastTraceValue($currentTrace['trace'], 'limit_remaining'),
                    'nextLimitRemaining' => self::lastTraceValue($nextTrace['trace'], 'limit_remaining'),
                    'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'], $nextTrace['dependencies']))),
                ],
                'boundary' => [
                    'currentLabels' => self::columnValues($currentRows, 'name'),
                    'nextLabels' => self::columnValues($nextRows, 'name'),
                    'entered' => array_values(array_diff(self::rowSignatures($nextRows), self::rowSignatures($currentRows))),
                    'left' => array_values(array_diff(self::rowSignatures($currentRows), self::rowSignatures($nextRows))),
                ],
                'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentTrace['rows'], $nextTrace['rows']),
            ];
        }

        private static function traceSql(string $sql): string
        {
            $sql = trim(rtrim(trim($sql), ';'));
            if (stripos($sql, 'WITH RECURSIVE') !== 0) {
                throw new \InvalidArgumentException('SQLite compound recursive ORDER/LIMIT current-source next146 needs WITH RECURSIVE');
            }
            if (preg_match('/^(.*\))\s*SELECT\s+id\s*,\s*name\s*,\s*priority\b/is', $sql, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound recursive ORDER/LIMIT current-source next146 cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT id, name, priority, depth FROM ranked';
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function leftColumns(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            $arms = is_array($compound) && is_array($compound['arms'] ?? null) ? $compound['arms'] : [];
            $first = $arms[0] ?? null;
            $select = is_array($first) && is_array($first['select'] ?? null) ? $first['select'] : [];
            $columns = [];
            foreach ($select as $index => $term) {
                if (!is_array($term)) {
                    continue;
                }
                if (isset($term['alias']) && is_string($term['alias']) && $term['alias'] !== '') {
                    $columns[] = $term['alias'];
                    continue;
                }
                if (($term['type'] ?? null) === 'column' && isset($term['name']) && is_string($term['name']) && $term['name'] !== '') {
                    $name = $term['name'];
                    $columns[] = str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name;
                    continue;
                }
                $columns[] = 'expr' . ($index + 1);
            }

            return $columns;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<mixed>
         */
        private static function columnValues(array $rows, string $column): array
        {
            return array_values(array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows));
        }

        /**
         * @param list<array<string,mixed>> $trace
         * @return list<list<mixed>>
         */
        private static function queueAfterNames(array $trace): array
        {
            $queue = [];
            foreach ($trace as $entry) {
                $rows = is_array($entry['queue_after'] ?? null) ? $entry['queue_after'] : [];
                $queue[] = self::columnValues($rows, 'name');
            }

            return $queue;
        }

        /**
         * @param list<array<string,mixed>> $trace
         */
        private static function lastTraceValue(array $trace, string $key): mixed
        {
            $last = $trace[array_key_last($trace)] ?? null;

            return is_array($last) ? ($last[$key] ?? null) : null;
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
            return array_values(array_merge(
                array_diff(self::rowSignatures($currentRows), self::rowSignatures($nextRows)),
                array_diff(self::rowSignatures($nextRows), self::rowSignatures($currentRows)),
            ));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentRecursiveRows
         * @param list<array<string,mixed>> $nextRecursiveRows
         * @return list<string>
         */
        private static function replanReasons(array $currentRows, array $nextRows, array $currentRecursiveRows, array $nextRecursiveRows): array
        {
            $reasons = ['recursive-queue-order-limit-before-compound-tail'];
            if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
                $reasons[] = 'compound-final-limit-boundary-changed';
            }
            if (self::columnValues($currentRecursiveRows, 'name') !== self::columnValues($nextRecursiveRows, 'name')) {
                $reasons[] = 'recursive-priority-queue-visit-order-changed';
            }
            $reasons[] = 'compound-final-order-after-current-source';

            return $reasons;
        }

}
