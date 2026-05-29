<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundCteWindowOrderCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundCteWindowOrderCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareCteWindowOrder(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound CTE window ORDER current-source plan needs a compound SELECT');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentWindows = self::windowTerms($currentPlan);
            $nextWindows = self::windowTerms($nextPlan);

            return [
                'status' => 'compound-cte-window-order-current-source-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentSignatures' => self::rowSignatures($currentRows),
                'nextSignatures' => self::rowSignatures($nextRows),
                'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumns($currentPlan),
                    'orderDirections' => self::orderDirections($currentPlan),
                    'limit' => $currentPlan['compound']['limit'] ?? null,
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                ],
                'cte' => [
                    'current' => self::cteNames($currentPlan),
                    'next' => self::cteNames($nextPlan),
                    'materialized' => self::materializedNames($sql),
                ],
                'windows' => [
                    'current' => $currentWindows,
                    'next' => $nextWindows,
                    'orderedAliases' => self::orderedAliases($currentWindows),
                ],
                'orderBoundary' => self::orderBoundary($currentRows, $nextRows),
                'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentPlan, $nextPlan, $currentWindows, $nextWindows),
                'dependencies' => [
                    'sqlite-select-sql-with-materialized-cte',
                    'sqlite-select-sql-compound-cte-arms',
                    'sqlite-select-sql-window-order-from-cte',
                    'sqlite-select-sql-compound-final-order',
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
                static fn (array $term): string => isset($term['direction']) ? (string) $term['direction'] : 'ASC',
                $compound['orderBy'],
            ));
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<string>
         */
        private static function cteNames(array $plan): array
        {
            $names = is_array($plan['with'] ?? null) ? $plan['with'] : [];

            return array_values(array_map(static fn (mixed $name): string => (string) $name, $names));
        }

        /**
         * @return list<string>
         */
        private static function materializedNames(string $sql): array
        {
            if (preg_match_all('/\b([A-Za-z_][A-Za-z0-9_]*)\s*(?:\([^)]*\))?\s+AS\s+MATERIALIZED\s*\(/i', $sql, $matches) !== false && isset($matches[1])) {
                return array_values(array_map(static fn (string $name): string => strtolower($name), $matches[1]));
            }

            return [];
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
         * @param list<array<string,mixed>> $windows
         * @return list<string>
         */
        private static function orderedAliases(array $windows): array
        {
            $aliases = [];
            foreach ($windows as $window) {
                if (($window['orderCount'] ?? 0) > 0 && isset($window['alias']) && is_string($window['alias'])) {
                    $aliases[] = $window['alias'];
                }
            }

            return $aliases;
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return array<string,mixed>
         */
        private static function orderBoundary(array $currentRows, array $nextRows): array
        {
            return [
                'currentFirst' => $currentRows[0] ?? null,
                'nextFirst' => $nextRows[0] ?? null,
                'currentLast' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1],
                'nextLast' => $nextRows === [] ? null : $nextRows[count($nextRows) - 1],
                'currentCount' => count($currentRows),
                'nextCount' => count($nextRows),
            ];
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
         * @param array<string,mixed> $currentPlan
         * @param array<string,mixed> $nextPlan
         * @param list<array<string,mixed>> $currentWindows
         * @param list<array<string,mixed>> $nextWindows
         * @return list<string>
         */
        private static function replanReasons(array $currentRows, array $nextRows, array $currentPlan, array $nextPlan, array $currentWindows, array $nextWindows): array
        {
            $reasons = [];
            if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
                $reasons[] = 'compound-cte-rowset-changed';
            }
            if (self::cteNames($currentPlan) !== []) {
                $reasons[] = 'cte-materialized-source';
            }
            if (self::orderedAliases($currentWindows) !== []) {
                $reasons[] = 'window-order-source';
            }
            if (self::orderColumns($currentPlan) !== []) {
                $reasons[] = 'compound-final-order';
            }
            if (self::cteNames($currentPlan) !== self::cteNames($nextPlan) || self::rowSignatures($currentWindows) !== self::rowSignatures($nextWindows)) {
                $reasons[] = 'plan-shape-changed';
            }

            return $reasons;
        }

}
