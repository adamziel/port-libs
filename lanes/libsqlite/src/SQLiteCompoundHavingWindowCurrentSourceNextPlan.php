<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundHavingWindowCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundHavingWindowCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @return array<string,mixed>
         */
        public static function compareHavingWindow(string $sql, array $currentTables, array $nextTables): array
        {
            $currentPlan = SQLiteSelectSql::plan($sql, $currentTables);
            $nextPlan = SQLiteSelectSql::plan($sql, $nextTables);
            if (!isset($currentPlan['compound'], $nextPlan['compound']) || !is_array($currentPlan['compound']) || !is_array($nextPlan['compound'])) {
                throw new \InvalidArgumentException('SQLite compound HAVING window current-source next128 requires a compound SELECT');
            }

            $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
            $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
            $currentHaving = self::havingTermsHavingWindow($currentPlan);
            $nextHaving = self::havingTermsHavingWindow($nextPlan);
            $currentWindows = self::windowTermsHavingWindow($currentPlan);
            $nextWindows = self::windowTermsHavingWindow($nextPlan);

            return [
                'status' => 'compound-having-window-current-source-next128',
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentSignatures' => self::rowSignaturesHavingWindow($currentRows),
                'nextSignatures' => self::rowSignaturesHavingWindow($nextRows),
                'changedSignatures' => self::changedSignaturesHavingWindow($currentRows, $nextRows),
                'compound' => [
                    'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                    'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                    'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                    'orderColumns' => array_values(array_map(
                        static fn (array $term): string => (string) ($term['column'] ?? ''),
                        is_array($currentPlan['compound']['orderBy'] ?? null) ? $currentPlan['compound']['orderBy'] : [],
                    )),
                ],
                'having' => [
                    'current' => $currentHaving,
                    'next' => $nextHaving,
                    'arms' => array_values(array_unique(array_map(static fn (array $term): int => (int) $term['arm'], $currentHaving))),
                    'correlatedArms' => self::correlatedHavingArmsHavingWindow($currentHaving),
                ],
                'windows' => [
                    'current' => $currentWindows,
                    'next' => $nextWindows,
                    'aliases' => self::windowAliasesHavingWindow($currentWindows),
                ],
                'replanReasons' => self::replanReasonsHavingWindow($currentRows, $nextRows, $currentHaving, $nextHaving, $currentWindows, $nextWindows),
                'dependencies' => [
                    'sqlite-select-compound-current-source',
                    'sqlite-select-having-aggregate-current-source',
                    'sqlite-window-current-source',
                    'sqlite-compound-having-window-current-source-next128',
                ],
            ];
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function havingTermsHavingWindow(array $plan): array
        {
            $compound = $plan['compound'] ?? null;
            if (!is_array($compound) || !is_array($compound['arms'] ?? null)) {
                return [];
            }

            $terms = [];
            foreach ($compound['arms'] as $armIndex => $arm) {
                if (!is_array($arm) || !is_array($arm['groupBy'] ?? null) || !is_array($arm['groupBy']['having'] ?? null)) {
                    continue;
                }
                $having = $arm['groupBy']['having'];
                $terms[] = [
                    'arm' => $armIndex,
                    'type' => (string) ($having['type'] ?? 'predicate'),
                    'groupColumns' => is_array($arm['groupBy']['columns'] ?? null) ? array_values($arm['groupBy']['columns']) : [],
                    'valueColumn' => (string) ($arm['groupBy']['valueColumn'] ?? ''),
                    'correlated' => self::expressionReferencesQualifiedColumnHavingWindow($having) || self::expressionHasSubqueryHavingWindow($having),
                ];
            }

            return $terms;
        }

        /**
         * @param array<string,mixed> $plan
         * @return list<array<string,mixed>>
         */
        private static function windowTermsHavingWindow(array $plan): array
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
                        'hasFilter' => is_array($term['filter'] ?? null),
                        'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                        'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                        'frameUnit' => is_array($term['frame'] ?? null) ? (string) ($term['frame']['unit'] ?? '') : null,
                    ];
                }
            }

            return $windows;
        }

        /**
         * @param list<array<string,mixed>> $terms
         * @return list<int>
         */
        private static function correlatedHavingArmsHavingWindow(array $terms): array
        {
            $arms = [];
            foreach ($terms as $term) {
                if (($term['correlated'] ?? false) === true) {
                    $arms[] = (int) $term['arm'];
                }
            }

            return array_values(array_unique($arms));
        }

        /**
         * @param list<array<string,mixed>> $windows
         * @return list<string>
         */
        private static function windowAliasesHavingWindow(array $windows): array
        {
            $aliases = [];
            foreach ($windows as $window) {
                if (isset($window['alias']) && is_string($window['alias'])) {
                    $aliases[] = $window['alias'];
                }
            }

            return $aliases;
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<string>
         */
        private static function rowSignaturesHavingWindow(array $rows): array
        {
            return array_values(array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @return list<string>
         */
        private static function changedSignaturesHavingWindow(array $currentRows, array $nextRows): array
        {
            $current = self::rowSignaturesHavingWindow($currentRows);
            $next = self::rowSignaturesHavingWindow($nextRows);

            return array_values(array_merge(array_diff($current, $next), array_diff($next, $current)));
        }

        /**
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,mixed>> $nextRows
         * @param list<array<string,mixed>> $currentHaving
         * @param list<array<string,mixed>> $nextHaving
         * @param list<array<string,mixed>> $currentWindows
         * @param list<array<string,mixed>> $nextWindows
         * @return list<string>
         */
        private static function replanReasonsHavingWindow(array $currentRows, array $nextRows, array $currentHaving, array $nextHaving, array $currentWindows, array $nextWindows): array
        {
            $reasons = [];
            if (self::rowSignaturesHavingWindow($currentRows) !== self::rowSignaturesHavingWindow($nextRows)) {
                $reasons[] = 'compound-rowset-changed';
            }
            if ($currentHaving !== []) {
                $reasons[] = 'having-aggregate-source';
            }
            if (self::correlatedHavingArmsHavingWindow($currentHaving) !== []) {
                $reasons[] = 'correlated-having-source';
            }
            if (self::rowSignaturesHavingWindow($currentHaving) !== self::rowSignaturesHavingWindow($nextHaving)) {
                $reasons[] = 'having-plan-changed';
            }
            if (self::rowSignaturesHavingWindow($currentWindows) !== self::rowSignaturesHavingWindow($nextWindows)) {
                $reasons[] = 'window-plan-changed';
            }

            return $reasons;
        }

        private static function expressionReferencesQualifiedColumnHavingWindow(mixed $expression): bool
        {
            if (!is_array($expression)) {
                return false;
            }
            if (isset($expression['column']) && is_string($expression['column']) && str_contains($expression['column'], '.')) {
                return true;
            }
            if (isset($expression['name']) && is_string($expression['name']) && str_contains($expression['name'], '.')) {
                return true;
            }
            foreach ($expression as $value) {
                if (self::expressionReferencesQualifiedColumnHavingWindow($value)) {
                    return true;
                }
            }

            return false;
        }

        private static function expressionHasSubqueryHavingWindow(mixed $expression): bool
        {
            if (!is_array($expression)) {
                return false;
            }
            if (is_callable($expression['subquery'] ?? null) || is_callable($expression['valuesSubquery'] ?? null)) {
                return true;
            }
            foreach ($expression as $value) {
                if (self::expressionHasSubqueryHavingWindow($value)) {
                    return true;
                }
            }

            return false;
        }

}
