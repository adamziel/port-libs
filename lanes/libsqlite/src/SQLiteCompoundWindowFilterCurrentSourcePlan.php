<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundWindowFilterCurrentSourcePlan
{
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
            throw new \InvalidArgumentException('SQLite compound window current-source plan needs a compound SELECT');
        }

        $currentRows = SQLiteSelectSql::execute($sql, $currentTables);
        $nextRows = SQLiteSelectSql::execute($sql, $nextTables);
        $currentWindows = self::windowTerms($currentPlan);
        $nextWindows = self::windowTerms($nextPlan);

        return [
            'status' => 'compound-window-filter-current-source-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'currentSignatures' => self::rowSignatures($currentRows),
            'nextSignatures' => self::rowSignatures($nextRows),
            'changedSignatures' => self::changedSignatures($currentRows, $nextRows),
            'compound' => [
                'currentArms' => count($currentPlan['compound']['arms'] ?? []),
                'nextArms' => count($nextPlan['compound']['arms'] ?? []),
                'operators' => array_values(array_map('strtoupper', $currentPlan['compound']['operators'] ?? [])),
                'orderColumns' => array_values(array_map(
                    static fn (array $term): string => (string) ($term['column'] ?? ''),
                    is_array($currentPlan['compound']['orderBy'] ?? null) ? $currentPlan['compound']['orderBy'] : [],
                )),
            ],
            'windows' => [
                'current' => $currentWindows,
                'next' => $nextWindows,
                'filteredAliases' => self::filteredAliases($currentWindows),
                'correlatedFilters' => self::correlatedFilters($currentWindows),
            ],
            'replanReasons' => self::replanReasons($currentRows, $nextRows, $currentWindows, $nextWindows),
        ];
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
                    'hasFilter' => is_array($term['filter'] ?? null),
                    'filterCorrelated' => self::expressionReferencesQualifiedColumn($term['filter'] ?? null) || self::expressionHasSubquery($term['filter'] ?? null),
                    'partitionCount' => is_array($term['partitionBy'] ?? null) ? count($term['partitionBy']) : 0,
                    'orderCount' => is_array($term['orderBy'] ?? null) ? count($term['orderBy']) : 0,
                    'frameUnit' => is_array($term['frame'] ?? null) ? (string) ($term['frame']['unit'] ?? '') : null,
                ];
            }
        }

        return $windows;
    }

    /**
     * @param list<array<string,mixed>> $windows
     * @return list<string>
     */
    private static function filteredAliases(array $windows): array
    {
        $aliases = [];
        foreach ($windows as $window) {
            if (($window['hasFilter'] ?? false) === true && isset($window['alias']) && is_string($window['alias'])) {
                $aliases[] = $window['alias'];
            }
        }

        return $aliases;
    }

    /**
     * @param list<array<string,mixed>> $windows
     * @return list<string>
     */
    private static function correlatedFilters(array $windows): array
    {
        $aliases = [];
        foreach ($windows as $window) {
            if (($window['filterCorrelated'] ?? false) === true && isset($window['alias']) && is_string($window['alias'])) {
                $aliases[] = $window['alias'];
            }
        }

        return $aliases;
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
     * @param list<array<string,mixed>> $currentWindows
     * @param list<array<string,mixed>> $nextWindows
     * @return list<string>
     */
    private static function replanReasons(array $currentRows, array $nextRows, array $currentWindows, array $nextWindows): array
    {
        $reasons = [];
        if (self::rowSignatures($currentRows) !== self::rowSignatures($nextRows)) {
            $reasons[] = 'compound-rowset-changed';
        }
        if (self::filteredAliases($currentWindows) !== []) {
            $reasons[] = 'window-filter-source';
        }
        if (self::correlatedFilters($currentWindows) !== []) {
            $reasons[] = 'correlated-window-filter-source';
        }
        if (self::rowSignatures($currentWindows) !== self::rowSignatures($nextWindows)) {
            $reasons[] = 'window-plan-changed';
        }

        return $reasons;
    }

    private static function expressionReferencesQualifiedColumn(mixed $expression): bool
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
            if (self::expressionReferencesQualifiedColumn($value)) {
                return true;
            }
        }

        return false;
    }

    private static function expressionHasSubquery(mixed $expression): bool
    {
        if (!is_array($expression)) {
            return false;
        }
        if (is_callable($expression['subquery'] ?? null) || is_callable($expression['valuesSubquery'] ?? null)) {
            return true;
        }
        foreach ($expression as $value) {
            if (self::expressionHasSubquery($value)) {
                return true;
            }
        }

        return false;
    }
}
