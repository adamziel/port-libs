<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundCollationWindowCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundCollationWindowCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareNext136(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound collation window current-source next136 plan needs a compound SELECT');
            }

            $operators = array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? []));
            if (!in_array('UNION', $operators, true) && !in_array('INTERSECT', $operators, true)) {
                throw new \InvalidArgumentException('SQLite compound collation window current-source next136 plan needs a DISTINCT set operator');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentArmRows = self::armRowsNext136($currentPlan);
            $nextArmRows = self::armRowsNext136($nextPlan);
            $collations = self::leftArmCollationsNext136($currentPlan);

            return [
                'status' => 'compound-collation-window-current-source-next136-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentNames' => array_values(array_map(static fn (array $row): mixed => $row['name'] ?? null, $currentRows)),
                'nextNames' => array_values(array_map(static fn (array $row): mixed => $row['name'] ?? null, $nextRows)),
                'changedSignatures' => self::changedSignaturesNext136($currentRows, $nextRows),
                'compound' => [
                    'operators' => $operators,
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'leftCollations' => $collations,
                    'orderByCollations' => self::orderByCollationsNext136($currentPlan),
                    'currentDuplicateKeys' => self::duplicateKeysNext136($currentArmRows, $collations),
                    'nextDuplicateKeys' => self::duplicateKeysNext136($nextArmRows, $collations),
                    'currentSuppressedRows' => self::suppressedRowsNext136($currentArmRows, $currentRows, $collations),
                    'nextSuppressedRows' => self::suppressedRowsNext136($nextArmRows, $nextRows, $collations),
                ],
                'windows' => [
                    'current' => self::windowTermsNext136($currentPlan),
                    'next' => self::windowTermsNext136($nextPlan),
                    'aliases' => array_values(array_unique(array_merge(
                        array_column(self::windowTermsNext136($currentPlan), 'alias'),
                        array_column(self::windowTermsNext136($nextPlan), 'alias'),
                    ))),
                    'currentRowNumbers' => array_values(array_map(static fn (array $row): mixed => $row['rn'] ?? null, $currentRows)),
                    'nextRowNumbers' => array_values(array_map(static fn (array $row): mixed => $row['rn'] ?? null, $nextRows)),
                ],
                'replanReasons' => self::replanReasonsNext136($currentRows, $nextRows, $currentArmRows, $nextArmRows, $currentPlan, $nextPlan, $collations),
                'dependencies' => [
                    'sqlite-compound-left-collation-dedup',
                    'sqlite-window-arm-before-compound',
                    'sqlite-current-source-next136',
                ],
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function armRowsNext136(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['arms'] ?? null)) {
                return [];
            }

            $rows = [];
            foreach ($compound['arms'] as $arm) {
                if (is_array($arm)) {
                    array_push($rows, ...SQLiteSelectQuery::execute($arm));
                }
            }

            return $rows;
        }

        /**
         * @param array<string,mixed> $plan
         * @return array<string,string>
         */
        private static function leftArmCollationsNext136(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            $arm = is_array($compound) && is_array($compound['arms'] ?? null) ? ($compound['arms'][0] ?? null) : null;
            if (!is_array($arm) || !is_array($arm['select'] ?? null)) {
                return [];
            }

            $collations = [];
            foreach ($arm['select'] as $index => $term) {
                if (!is_array($term)) {
                    continue;
                }
                $column = isset($term['alias']) && is_string($term['alias']) && $term['alias'] !== '' ? $term['alias'] : 'expr' . ($index + 1);
                if (isset($term['collation']) && is_string($term['collation']) && $term['collation'] !== '') {
                    $collations[$column] = strtoupper($term['collation']);
                }
            }

            return $collations;
        }

        /**
         * @param array<string,mixed> $plan
         * @return array<string,string>
         */
        private static function orderByCollationsNext136(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['orderBy'] ?? null)) {
                return [];
            }

            $collations = [];
            foreach ($compound['orderBy'] as $term) {
                if (is_array($term) && isset($term['column'], $term['collation']) && is_string($term['column']) && is_string($term['collation'])) {
                    $collations[$term['column']] = strtoupper($term['collation']);
                }
            }

            return $collations;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @param array<string,string> $collations
         * @return list<string>
         */
        private static function duplicateKeysNext136(array $rows, array $collations): array
        {
            $counts = [];
            foreach ($rows as $row) {
                $key = self::rowKeyNext136($row, $collations);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }

            return array_values(array_keys(array_filter($counts, static fn (int $count): bool => $count > 1)));
        }

        /**
         * @param list<array<string,mixed>> $armRows
         * @param list<array<string,mixed>> $resultRows
         * @param array<string,string> $collations
         * @return list<array<string,mixed>>
         */
        private static function suppressedRowsNext136(array $armRows, array $resultRows, array $collations): array
        {
            $resultKeys = array_fill_keys(array_map(static fn (array $row): string => self::rowKeyNext136($row, $collations), $resultRows), true);
            $suppressed = [];
            $seen = [];
            foreach ($armRows as $row) {
                $key = self::rowKeyNext136($row, $collations);
                if (isset($seen[$key]) || !isset($resultKeys[$key])) {
                    $suppressed[] = $row;
                }
                $seen[$key] = true;
            }

            return $suppressed;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function windowTermsNext136(array $plan): array
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
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignaturesNext136(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext136(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext136($currentRows);
            $next = self::rowSignaturesNext136($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentArmRows
         * @param list<array<string,mixed>> $nextArmRows
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         * @param array<string,string> $collations
         * @return list<string>
         */
        private static function replanReasonsNext136(array $currentRows, array $nextRows, array $currentArmRows, array $nextArmRows, array $currentPlan, array $nextPlan, array $collations): array
        {
            $reasons = [];
            if (self::rowSignaturesNext136($currentRows) !== self::rowSignaturesNext136($nextRows)) {
                $reasons[] = 'compound-window-rowset-changed';
            }
            if ($collations !== []) {
                $reasons[] = 'compound-left-collation';
            }
            if (self::windowTermsNext136($currentPlan) !== []) {
                $reasons[] = 'window-before-compound-source';
            }
            if (self::duplicateKeysNext136($currentArmRows, $collations) !== self::duplicateKeysNext136($nextArmRows, $collations)) {
                $reasons[] = 'compound-dedup-keyset-changed';
            }
            if (self::windowTermsNext136($currentPlan) !== self::windowTermsNext136($nextPlan)) {
                $reasons[] = 'window-plan-changed';
            }

            return array_values(array_unique($reasons));
        }

        /**
         * @param array<string,mixed> $row
         * @param array<string,string> $collations
         */
        private static function rowKeyNext136(array $row, array $collations): string
        {
            $parts = [];
            foreach ($row as $column => $value) {
                $parts[] = $column . '=' . self::valueKeyNext136($value, $collations[$column] ?? 'BINARY');
            }

            return implode("\0", $parts);
        }

        private static function valueKeyNext136(mixed $value, string $collation): string
        {
            if ($value === null) {
                return 'null:';
            }
            if ($value instanceof SQLiteBlobValue) {
                return 'blob:' . $value->bytes;
            }
            if (is_int($value) || is_bool($value)) {
                return 'numeric:' . (int) $value;
            }
            if (is_float($value)) {
                return 'numeric:' . (is_finite($value) && floor($value) === $value ? sprintf('%.0F', $value) : sprintf('%.17G', $value));
            }
            if (is_string($value)) {
                $normalized = match (strtoupper($collation)) {
                    'NOCASE' => strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'),
                    'RTRIM' => rtrim($value, ' '),
                    default => $value,
                };

                return 'text:' . $normalized;
            }

            throw new \InvalidArgumentException('SQLite compound collation window values must be scalar, BLOB, or NULL');
        }

}
