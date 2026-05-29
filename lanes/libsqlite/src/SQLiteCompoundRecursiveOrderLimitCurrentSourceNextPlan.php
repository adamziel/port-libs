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
        public static function compareNext146(string $sql, array $currentTables, array $nextTables): array
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
            $traceSql = self::traceSqlNext146($sql);
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
                    'leftColumns' => self::leftColumnsNext146($currentPlan),
                ],
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentSignatures' => self::rowSignaturesNext146($currentRows),
                'nextSignatures' => self::rowSignaturesNext146($nextRows),
                'changedSignatures' => self::changedSignaturesNext146($currentRows, $nextRows),
                'recursive' => [
                    'name' => $currentTrace['name'],
                    'columns' => $currentTrace['columns'],
                    'operator' => $currentTrace['operator'] ?? null,
                    'currentRows' => $currentTrace['rows'],
                    'nextRows' => $nextTrace['rows'],
                    'currentTraceCount' => count($currentTrace['trace']),
                    'nextTraceCount' => count($nextTrace['trace']),
                    'currentVisitOrder' => self::columnValuesNext146($currentTrace['rows'], 'name'),
                    'nextVisitOrder' => self::columnValuesNext146($nextTrace['rows'], 'name'),
                    'currentQueueAfter' => self::queueAfterNamesNext146($currentTrace['trace']),
                    'nextQueueAfter' => self::queueAfterNamesNext146($nextTrace['trace']),
                    'currentLimitRemaining' => self::lastTraceValueNext146($currentTrace['trace'], 'limit_remaining'),
                    'nextLimitRemaining' => self::lastTraceValueNext146($nextTrace['trace'], 'limit_remaining'),
                    'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'], $nextTrace['dependencies']))),
                ],
                'boundary' => [
                    'currentLabels' => self::columnValuesNext146($currentRows, 'name'),
                    'nextLabels' => self::columnValuesNext146($nextRows, 'name'),
                    'entered' => array_values(array_diff(self::rowSignaturesNext146($nextRows), self::rowSignaturesNext146($currentRows))),
                    'left' => array_values(array_diff(self::rowSignaturesNext146($currentRows), self::rowSignaturesNext146($nextRows))),
                ],
                'replanReasons' => self::replanReasonsNext146($currentRows, $nextRows, $currentTrace['rows'], $nextTrace['rows']),
            ];
        }

        private static function traceSqlNext146(string $sql): string
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
        private static function leftColumnsNext146(array $plan): array
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
        private static function columnValuesNext146(array $rows, string $column): array
        {
            return array_values(array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows));
        }

        /**
         * @param list<array<string,mixed>> $trace
         * @return list<list<mixed>>
         */
        private static function queueAfterNamesNext146(array $trace): array
        {
            $queue = [];
            foreach ($trace as $entry) {
                $rows = is_array($entry['queue_after'] ?? null) ? $entry['queue_after'] : [];
                $queue[] = self::columnValuesNext146($rows, 'name');
            }

            return $queue;
        }

        /**
         * @param list<array<string,mixed>> $trace
         */
        private static function lastTraceValueNext146(array $trace, string $key): mixed
        {
            $last = $trace[array_key_last($trace)] ?? null;

            return is_array($last) ? ($last[$key] ?? null) : null;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignaturesNext146(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext146(array $currentRows, array $nextRows): array
        {
            return array_values(array_merge(
                array_diff(self::rowSignaturesNext146($currentRows), self::rowSignaturesNext146($nextRows)),
                array_diff(self::rowSignaturesNext146($nextRows), self::rowSignaturesNext146($currentRows)),
            ));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentRecursiveRows
         * @param list<array<string,mixed>> $nextRecursiveRows
         * @return list<string>
         */
        private static function replanReasonsNext146(array $currentRows, array $nextRows, array $currentRecursiveRows, array $nextRecursiveRows): array
        {
            $reasons = ['recursive-queue-order-limit-before-compound-tail'];
            if (self::rowSignaturesNext146($currentRows) !== self::rowSignaturesNext146($nextRows)) {
                $reasons[] = 'compound-final-limit-boundary-changed';
            }
            if (self::columnValuesNext146($currentRecursiveRows, 'name') !== self::columnValuesNext146($nextRecursiveRows, 'name')) {
                $reasons[] = 'recursive-priority-queue-visit-order-changed';
            }
            $reasons[] = 'compound-final-order-after-current-source';

            return $reasons;
        }

}
