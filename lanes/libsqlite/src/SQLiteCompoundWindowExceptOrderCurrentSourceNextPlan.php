<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundWindowExceptOrderCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compare(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound window EXCEPT order current-source plan needs a compound SELECT');
            }
            $operators = array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? []));
            if (!in_array('EXCEPT', $operators, true)) {
                throw new \InvalidArgumentException('SQLite compound window EXCEPT order current-source plan needs an EXCEPT arm');
            }
            if (!isset($currentPlan['compound']['orderBy']) || !is_array($currentPlan['compound']['orderBy']) || $currentPlan['compound']['orderBy'] === []) {
                throw new \InvalidArgumentException('SQLite compound window EXCEPT order current-source plan needs a final ORDER BY');
            }
            if (self::windowTerms($currentPlan) === []) {
                throw new \InvalidArgumentException('SQLite compound window EXCEPT order current-source plan needs a window function arm');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $preOrderSql = self::withoutFinalOrder($sql);
            $currentPreOrder = SQLiteSelectSql::execute($preOrderSql, $currentTables);
            $nextPreOrder = SQLiteSelectSql::execute($preOrderSql, $nextTables);

            return [
                'status' => 'compound-window-except-order-current-source-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentPreOrderRows' => $currentPreOrder,
                'nextPreOrderRows' => $nextPreOrder,
                'currentSignatures' => self::rowSignatures($currentRows),
                'nextSignatures' => self::rowSignatures($nextRows),
                'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
                'compound' => [
                    'operators' => $operators,
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumns($currentPlan),
                    'orderDirections' => self::orderDirections($currentPlan),
                ],
                'windows' => [
                    'current' => self::windowTerms($currentPlan),
                    'next' => self::windowTerms($nextPlan),
                ],
                'exceptTrace' => [
                    'currentRemoved' => self::removedByExcept($currentPreOrder, $currentTables['wp_options'] ?? []),
                    'nextRemoved' => self::removedByExcept($nextPreOrder, $nextTables['wp_options'] ?? []),
                    'currentPreOrderNames' => array_column($currentPreOrder, 'name'),
                    'nextPreOrderNames' => array_column($nextPreOrder, 'name'),
                    'currentOrderedNames' => array_column($currentRows, 'name'),
                    'nextOrderedNames' => array_column($nextRows, 'name'),
                ],
                'boundary' => [
                    'currentFirst' => $currentRows[0] ?? null,
                    'nextFirst' => $nextRows[0] ?? null,
                    'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                    'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                    'rankShiftNames' => self::rankShiftNames($currentRows, $nextRows),
                ],
                'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPreOrder, $nextPreOrder, $currentPlan, $nextPlan),
                'dependencies' => [
                    'sqlite-select-sql-window-arm-evaluation',
                    'sqlite-select-sql-compound-except',
                    'sqlite-select-sql-compound-final-order',
                    'sqlite-current-source-next-rowset-boundary',
                ],
            ];
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

            return array_values(array_map(
                static fn (array $term): string => (string) ($term['column'] ?? ''),
                $compound['orderBy'],
            ));
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

            return array_values(array_map(
                static fn (array $term): string => (string) ($term['direction'] ?? 'ASC'),
                $compound['orderBy'],
            ));
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

        private static function withoutFinalOrder(string $sql): string
        {
            $trimmed = rtrim(trim($sql), ';');
            $offset = self::topLevelKeywordOffset($trimmed, 'ORDER BY');
            if ($offset === null) {
                throw new \InvalidArgumentException('SQLite compound window EXCEPT order current-source plan cannot isolate final ORDER BY');
            }

            return rtrim(substr($trimmed, 0, $offset));
        }

        private static function topLevelKeywordOffset(string $sql, string $keyword): ?int
        {
            $length = strlen($sql);
            $keywordLength = strlen($keyword);
            $depth = 0;
            $quote = false;
            $last = null;
            for ($i = 0; $i < $length; $i++) {
                $char = $sql[$i];
                if ($char === "'") {
                    if ($quote && ($sql[$i + 1] ?? null) === "'") {
                        $i++;
                        continue;
                    }
                    $quote = !$quote;
                    continue;
                }
                if ($quote) {
                    continue;
                }
                if ($char === '(') {
                    $depth++;
                    continue;
                }
                if ($char === ')') {
                    $depth--;
                    continue;
                }
                if ($depth !== 0) {
                    continue;
                }
                if (strncasecmp(substr($sql, $i), $keyword, $keywordLength) !== 0) {
                    continue;
                }
                $before = $i === 0 ? '' : $sql[$i - 1];
                $after = $sql[$i + $keywordLength] ?? '';
                if (($before === '' || !preg_match('/[A-Za-z0-9_]/', $before)) && ($after === '' || !preg_match('/[A-Za-z0-9_]/', $after))) {
                    $last = $i;
                }
            }

            return $last;
        }

        /**
         * @param list<array<string,mixed>> $preOrderRows
         * @param list<array<string,mixed>> $sourceRows
         * @return list<string>
         */
        private static function removedByExcept(array $preOrderRows, array $sourceRows): array
        {
            $remaining = array_flip(array_column($preOrderRows, 'name'));
            $removed = [];
            foreach ($sourceRows as $row) {
                $name = isset($row['option_name']) ? (string) $row['option_name'] : '';
                if ($name !== '' && !isset($remaining[$name])) {
                    $removed[] = $name;
                }
            }

            return array_values(array_unique($removed));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function rankShiftNames(array $currentRows, array $nextRows): array
        {
            $current = [];
            foreach ($currentRows as $row) {
                if (isset($row['name'], $row['source_rank'])) {
                    $current[(string) $row['name']] = $row['source_rank'];
                }
            }

            $shifted = [];
            foreach ($nextRows as $row) {
                $name = isset($row['name']) ? (string) $row['name'] : '';
                if ($name !== '' && array_key_exists($name, $current) && $current[$name] !== ($row['source_rank'] ?? null)) {
                    $shifted[] = $name;
                }
            }

            return $shifted;
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
         * @param list<array<string,mixed>> $currentPreOrder
         * @param list<array<string,mixed>> $nextPreOrder
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         * @return list<string>
         */
        private static function replanReasons(array $currentRows, array $nextRows, array $currentPreOrder, array $nextPreOrder, array $currentPlan, array $nextPlan): array
        {
            $reasons = [];
            if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
                $reasons[] = 'ordered-except-rowset-changed';
            }
            if (self::rowSignatures($currentPreOrder) !== self::rowSignatures($nextPreOrder)) {
                $reasons[] = 'preorder-except-rowset-changed';
            }
            if (self::windowTerms($currentPlan) !== []) {
                $reasons[] = 'window-before-except';
            }
            if (self::orderColumns($currentPlan) !== []) {
                $reasons[] = 'compound-final-order';
            }
            if (self::windowTerms($currentPlan) !== self::windowTerms($nextPlan)) {
                $reasons[] = 'window-plan-changed';
            }

            return $reasons;
        }

}
