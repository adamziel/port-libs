<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectAffinityRecursiveOrderCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundSelectAffinityRecursiveOrderCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareNext140(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound-select affinity recursive-order current-source next140 needs a compound SELECT');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $traceSql = self::traceSqlNext140($sql);
            $currentTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $currentTables);
            $nextTrace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, $nextTables);

            return [
                'status' => 'compound-select-affinity-recursive-order-current-source-next140-ready',
                'dependencies' => [
                    'sqlite-recursive-cte-queue-order-storage-class',
                    'sqlite-compound-select-final-order-after-recursive-source',
                    'sqlite-current-source-next-recursive-boundary',
                ],
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext140($currentPlan),
                    'limit' => $currentPlan['compound']['limit'] ?? null,
                ],
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'recursive' => [
                    'currentRows' => $currentTrace['rows'],
                    'nextRows' => $nextTrace['rows'],
                    'currentVisitedNames' => array_column($currentTrace['rows'], 'name'),
                    'nextVisitedNames' => array_column($nextTrace['rows'], 'name'),
                    'currentAcceptedNextNames' => self::acceptedNextNamesNext140($currentTrace['trace']),
                    'nextAcceptedNextNames' => self::acceptedNextNamesNext140($nextTrace['trace']),
                    'currentSortClasses' => self::sortClassesNext140($currentTrace['rows'], 'sort_key'),
                    'nextSortClasses' => self::sortClassesNext140($nextTrace['rows'], 'sort_key'),
                    'traceCounts' => [
                        'current' => count($currentTrace['trace']),
                        'next' => count($nextTrace['trace']),
                    ],
                    'dependencies' => array_values(array_unique(array_merge($currentTrace['dependencies'], $nextTrace['dependencies']))),
                ],
                'changedSignatures' => self::changedSignaturesNext140($currentRows, $nextRows),
                'replanReasons' => self::replanReasonsNext140($currentRows, $nextRows, $currentTrace['rows'], $nextTrace['rows']),
            ];
        }

        private static function traceSqlNext140(string $sql): string
        {
            $sql = trim(rtrim(trim($sql), ';'));
            if (stripos($sql, 'WITH RECURSIVE') !== 0) {
                throw new \InvalidArgumentException('SQLite compound-select affinity recursive-order current-source next140 needs WITH RECURSIVE');
            }
            if (preg_match('/^(.*\))\s*SELECT\s+name\s*,\s*sort_key\b/is', $sql, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite compound-select affinity recursive-order current-source next140 cannot isolate recursive CTE');
            }

            return $match[1] . ' SELECT id, name, sort_key, depth FROM walk';
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumnsNext140(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['orderBy'] ?? null)) {
                return [];
            }

            return array_values(array_map(static fn (array $term): string => (string) ($term['column'] ?? ''), $compound['orderBy']));
        }

        /**
         * @param list<array<string,mixed>> $trace
         * @return list<list<string>>
         */
        private static function acceptedNextNamesNext140(array $trace): array
        {
            $names = [];
            foreach ($trace as $entry) {
                $accepted = is_array($entry['accepted_next'] ?? null) ? $entry['accepted_next'] : [];
                $names[] = array_values(array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $accepted));
            }

            return $names;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function sortClassesNext140(array $rows, string $column): array
        {
            return array_values(array_map(static fn (array $row): string => self::sqliteValueClassNext140($row[$column] ?? null), $rows));
        }

        private static function sqliteValueClassNext140(mixed $value): string
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
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext140(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext140($currentRows);
            $next = self::rowSignaturesNext140($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignaturesNext140(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentRecursiveRows
         * @param list<array<string,mixed>> $nextRecursiveRows
         * @return list<string>
         */
        private static function replanReasonsNext140(array $currentRows, array $nextRows, array $currentRecursiveRows, array $nextRecursiveRows): array
        {
            $reasons = [];
            if (self::rowSignaturesNext140($currentRows) !== self::rowSignaturesNext140($nextRows)) {
                $reasons[] = 'compound-final-rowset-changed';
            }
            if (array_column($currentRecursiveRows, 'name') !== array_column($nextRecursiveRows, 'name')) {
                $reasons[] = 'recursive-queue-order-boundary-changed';
            }
            if (self::sortClassesNext140($currentRecursiveRows, 'sort_key') !== self::sortClassesNext140($nextRecursiveRows, 'sort_key')) {
                $reasons[] = 'recursive-affinity-class-boundary-changed';
            }
            $reasons[] = 'compound-final-order-after-recursive-source';

            return $reasons;
        }

}
