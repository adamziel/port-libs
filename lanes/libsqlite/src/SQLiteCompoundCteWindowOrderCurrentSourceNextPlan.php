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
        public static function compareNext134(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound CTE window ORDER current-source next134 plan needs a compound SELECT');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentWindows = self::windowTermsNext134($currentPlan);
            $nextWindows = self::windowTermsNext134($nextPlan);

            return [
                'status' => 'compound-cte-window-order-current-source-next134-ready',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentSignatures' => self::rowSignaturesNext134($currentRows),
                'nextSignatures' => self::rowSignaturesNext134($nextRows),
                'changedSignatures' => self::changedSignaturesNext134($currentRows, $nextRows),
                'compound' => [
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'orderColumns' => self::orderColumnsNext134($currentPlan),
                    'orderDirections' => self::orderDirectionsNext134($currentPlan),
                    'limit' => $currentPlan['compound']['limit'] ?? null,
                    'offset' => $currentPlan['compound']['offset'] ?? 0,
                ],
                'cte' => [
                    'current' => self::cteNamesNext134($currentPlan),
                    'next' => self::cteNamesNext134($nextPlan),
                    'materialized' => self::materializedNamesNext134($sql),
                ],
                'windows' => [
                    'current' => $currentWindows,
                    'next' => $nextWindows,
                    'orderedAliases' => self::orderedAliasesNext134($currentWindows),
                ],
                'orderBoundary' => self::orderBoundaryNext134($currentRows, $nextRows),
                'replanReasons' => self::replanReasonsNext134($currentRows, $nextRows, $currentPlan, $nextPlan, $currentWindows, $nextWindows),
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
        private static function orderColumnsNext134(array $plan): array
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
        private static function orderDirectionsNext134(array $plan): array
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
        private static function cteNamesNext134(array $plan): array
        {
            $names = is_array($plan['with'] ?? null) ? $plan['with'] : [];

            return array_values(array_map(static fn (mixed $name): string => (string) $name, $names));
        }

        /**
         * @return list<string>
         */
        private static function materializedNamesNext134(string $sql): array
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
        private static function windowTermsNext134(array $plan): array
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
        private static function orderedAliasesNext134(array $windows): array
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
        private static function orderBoundaryNext134(array $currentRows, array $nextRows): array
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
        private static function rowSignaturesNext134(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesNext134(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesNext134($currentRows);
            $next = self::rowSignaturesNext134($nextRows);

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
        private static function replanReasonsNext134(array $currentRows, array $nextRows, array $currentPlan, array $nextPlan, array $currentWindows, array $nextWindows): array
        {
            $reasons = [];
            if (self::rowSignaturesNext134($currentRows) !== self::rowSignaturesNext134($nextRows)) {
                $reasons[] = 'compound-cte-rowset-changed';
            }
            if (self::cteNamesNext134($currentPlan) !== []) {
                $reasons[] = 'cte-materialized-source';
            }
            if (self::orderedAliasesNext134($currentWindows) !== []) {
                $reasons[] = 'window-order-source';
            }
            if (self::orderColumnsNext134($currentPlan) !== []) {
                $reasons[] = 'compound-final-order';
            }
            if (self::cteNamesNext134($currentPlan) !== self::cteNamesNext134($nextPlan) || self::rowSignaturesNext134($currentWindows) !== self::rowSignaturesNext134($nextWindows)) {
                $reasons[] = 'plan-shape-changed';
            }

            return $reasons;
        }

}
