<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundUnionLimitAffinityCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundUnionLimitAffinityCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareNext145(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            self::assertSupportedPlanNext145($currentPlan, $nextPlan);

            $allSql = self::withoutFinalLimitNext145(self::unionAllSqlNext145($sql));
            $unlimitedSql = self::withoutFinalLimitNext145($sql);
            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentAllRows = SQLiteSelectSql::execute($allSql, $currentTables);
            $nextAllRows = SQLiteSelectSql::execute($allSql, $nextTables);
            $currentDistinctRows = SQLiteSelectSql::execute($unlimitedSql, $currentTables);
            $nextDistinctRows = SQLiteSelectSql::execute($unlimitedSql, $nextTables);

            return [
                'status' => 'compound-union-limit-affinity-current-source-next145-ready',
                'dependencies' => [
                    'sqlite-compound-union-affinity-row-key',
                    'sqlite-compound-union-final-limit-boundary',
                    'sqlite-current-source-next-compound-boundary',
                ],
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'orderColumns' => self::orderColumnsNext145($currentPlan),
                    'limit' => $currentPlan['compound']['limit'],
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                ],
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentAllRows' => $currentAllRows,
                'nextAllRows' => $nextAllRows,
                'currentDistinctRows' => $currentDistinctRows,
                'nextDistinctRows' => $nextDistinctRows,
                'affinity' => [
                    'currentSkippedDuplicates' => self::skippedDuplicateRowsNext145($currentAllRows, $currentDistinctRows),
                    'nextSkippedDuplicates' => self::skippedDuplicateRowsNext145($nextAllRows, $nextDistinctRows),
                    'currentStorageClasses' => self::storageClassesNext145($currentDistinctRows),
                    'nextStorageClasses' => self::storageClassesNext145($nextDistinctRows),
                    'currentBoundaryClasses' => self::boundaryClassesNext145($currentRows),
                    'nextBoundaryClasses' => self::boundaryClassesNext145($nextRows),
                ],
                'limitTrace' => [
                    'current' => self::limitTraceNext145($currentDistinctRows, $currentRows, $currentPlan),
                    'next' => self::limitTraceNext145($nextDistinctRows, $nextRows, $nextPlan),
                ],
                'changedSignatures' => self::changedSignaturesNext145($currentRows, $nextRows),
                'replanReasons' => self::replanReasonsNext145($currentRows, $nextRows, $currentDistinctRows, $nextDistinctRows),
            ];
        }

        /**
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         */
        private static function assertSupportedPlanNext145(array $currentPlan, array $nextPlan): void
        {
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound UNION LIMIT affinity current-source next145 needs a compound SELECT');
            }
            $operators = array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? []));
            if ($operators !== ['UNION']) {
                throw new \InvalidArgumentException('SQLite compound UNION LIMIT affinity current-source next145 needs a single UNION operator');
            }
            if (($currentPlan['compound']['limit'] ?? null) === null) {
                throw new \InvalidArgumentException('SQLite compound UNION LIMIT affinity current-source next145 needs a final LIMIT');
            }
        }

        private static function unionAllSqlNext145(string $sql): string
        {
            $trimmed = trim(rtrim(trim($sql), ';'));
            $rewritten = preg_replace('/\bUNION\b(?!\s+ALL\b)/i', 'UNION ALL', $trimmed, 1);
            if (!is_string($rewritten) || $rewritten === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound UNION LIMIT affinity current-source next145 cannot isolate UNION');
            }

            return $rewritten;
        }

        private static function withoutFinalLimitNext145(string $sql): string
        {
            $trimmed = trim(rtrim(trim($sql), ';'));
            $without = preg_replace('/\s+LIMIT\s+\d+\s*(?:OFFSET\s+\d+)?\s*$/i', '', $trimmed);
            if (!is_string($without) || $without === $trimmed) {
                throw new \InvalidArgumentException('SQLite compound UNION LIMIT affinity current-source next145 cannot isolate final LIMIT');
            }

            return $without;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function orderColumnsNext145(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['orderBy'] ?? null)) {
                return [];
            }

            return array_values(array_map(static fn (array $term): string => (string) ($term['column'] ?? ''), $compound['orderBy']));
        }

        /**
         * @param list<array<string,mixed>> $allRows
         * @param list<array<string,mixed>> $distinctRows
         * @return list<array{row:array<string,mixed>,key:string,classes:list<string>}>
         */
        private static function skippedDuplicateRowsNext145(array $allRows, array $distinctRows): array
        {
            $remaining = [];
            foreach ($distinctRows as $row) {
                $remaining[self::rowKeyNext145($row)] = ($remaining[self::rowKeyNext145($row)] ?? 0) + 1;
            }

            $skipped = [];
            foreach ($allRows as $row) {
                $key = self::rowKeyNext145($row);
                if (($remaining[$key] ?? 0) > 0) {
                    --$remaining[$key];
                    continue;
                }
                $skipped[] = [
                    'row' => $row,
                    'key' => $key,
                    'classes' => self::rowClassesNext145($row),
                ];
            }

            return $skipped;
        }

        /**
         * @param list<array<string,mixed>> $preLimitRows
         * @param list<array<string,mixed>> $limitedRows
         * @param array<string,mixed> $plan
         * @return array<string,mixed>
         */
        private static function limitTraceNext145(array $preLimitRows, array $limitedRows, array $plan): array
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
                'firstAdmitted' => $limitedRows[0] ?? null,
                'lastAdmitted' => $limitedRows === [] ? null : $limitedRows[count($limitedRows) - 1],
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function storageClassesNext145(array $rows): array
        {
            $classes = [];
            foreach ($rows as $row) {
                foreach (self::rowClassesNext145($row) as $class) {
                    $classes[$class] = true;
                }
            }

            return array_keys($classes);
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return array{first:list<string>|null,last:list<string>|null}
         */
        private static function boundaryClassesNext145(array $rows): array
        {
            return [
                'first' => $rows === [] ? null : self::rowClassesNext145($rows[0]),
                'last' => $rows === [] ? null : self::rowClassesNext145($rows[count($rows) - 1]),
            ];
        }

        /**
         * @param array<string,mixed> $row
         * @return list<string>
         */
        private static function rowClassesNext145(array $row): array
        {
            return array_values(array_map(static fn (mixed $value): string => self::sqliteValueClassNext145($value), array_values($row)));
        }

        private static function rowKeyNext145(array $row): string
        {
            return SQLiteSelectCompound::rowValueKey(array_values($row));
        }

        private static function sqliteValueClassNext145(mixed $value): string
        {
            if ($value === null) {
                return 'null';
            }
            if (is_bool($value) || is_int($value) || is_float($value)) {
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
        private static function changedSignaturesNext145(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext145($currentRows);
            $next = self::rowSignaturesNext145($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignaturesNext145(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentDistinctRows
         * @param list<array<string,mixed>> $nextDistinctRows
         * @return list<string>
         */
        private static function replanReasonsNext145(array $currentRows, array $nextRows, array $currentDistinctRows, array $nextDistinctRows): array
        {
            $reasons = [];
            if (self::rowSignaturesNext145($currentDistinctRows) !== self::rowSignaturesNext145($nextDistinctRows)) {
                $reasons[] = 'union-distinct-rowset-changed';
            }
            if (self::rowSignaturesNext145($currentRows) !== self::rowSignaturesNext145($nextRows)) {
                $reasons[] = 'limited-union-boundary-changed';
            }
            if (self::storageClassesNext145($currentDistinctRows) !== self::storageClassesNext145($nextDistinctRows)) {
                $reasons[] = 'union-affinity-storage-classes-changed';
            }
            $reasons[] = 'compound-union-limit-after-affinity-distinct';

            return $reasons;
        }

}
