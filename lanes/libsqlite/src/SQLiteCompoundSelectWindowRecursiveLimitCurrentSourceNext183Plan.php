<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext183Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext178Plan::compare($sql, $currentTables, $nextTables);
        $currentRows = is_array($base['currentRows'] ?? null) ? $base['currentRows'] : [];
        $nextRows = is_array($base['nextRows'] ?? null) ? $base['nextRows'] : [];
        $currentPreLimit = is_array($base['currentPreLimitRows'] ?? null) ? $base['currentPreLimitRows'] : [];
        $nextPreLimit = is_array($base['nextPreLimitRows'] ?? null) ? $base['nextPreLimitRows'] : [];

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next183-ready';
        $base['dependencies'] = [
            'sqlite-select-sql-recursive-limit-offset-next183',
            'sqlite-select-sql-compound-window-tail-limit-next183',
            'sqlite-select-sql-current-source-boundary-next183',
            'sqlite-current-source-next183',
        ];
        $base['tailWindowLimit'] = [
            'currentLabels' => self::labels($currentRows),
            'nextLabels' => self::labels($nextRows),
            'currentPreLimitLabels' => self::labels($currentPreLimit),
            'nextPreLimitLabels' => self::labels($nextPreLimit),
            'currentSkippedLabels' => self::labels(array_slice($currentPreLimit, 0, self::offset($base))),
            'nextSkippedLabels' => self::labels(array_slice($nextPreLimit, 0, self::offset($base))),
            'currentTruncatedLabels' => self::labels(array_slice($currentPreLimit, self::offset($base) + self::limit($base))),
            'nextTruncatedLabels' => self::labels(array_slice($nextPreLimit, self::offset($base) + self::limit($base))),
            'gainedLabels' => self::changedLabels($currentRows, $nextRows, true),
            'lostLabels' => self::changedLabels($currentRows, $nextRows, false),
            'windowMetrics' => self::metrics($nextPreLimit),
        ];
        $base['replanReasons'] = array_values(array_unique(array_merge(
            is_array($base['replanReasons'] ?? null) ? $base['replanReasons'] : [],
            [
                'compound-tail-window-limit-current-source-next183',
                'recursive-offset-window-arm-before-union-distinct-next183',
                'wordpress-option-boundary-replans-final-limit-next183',
            ],
        )));
        $base['dependency_closure'] = 'no new support component needed; next183 reuses native SELECT SQL recursive CTE LIMIT/OFFSET, window lag/lead, compound UNION ALL/UNION, ORDER BY, and final LIMIT/OFFSET execution';

        return $base;
    }

    /**
     * @param array<string,mixed> $summary
     */
    private static function limit(array $summary): int
    {
        $compound = is_array($summary['compound'] ?? null) ? $summary['compound'] : [];

        return is_int($compound['limit'] ?? null) ? $compound['limit'] : 0;
    }

    /**
     * @param array<string,mixed> $summary
     */
    private static function offset(array $summary): int
    {
        $compound = is_array($summary['compound'] ?? null) ? $summary['compound'] : [];

        return is_int($compound['offset'] ?? null) ? $compound['offset'] : 0;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function labels(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? ''), $rows));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int>
     */
    private static function metrics(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => (int) ($row['metric'] ?? 0), $rows));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedLabels(array $currentRows, array $nextRows, bool $gained): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[json_encode($row, JSON_THROW_ON_ERROR)] = (string) ($row['label'] ?? '');
        }
        $next = [];
        foreach ($nextRows as $row) {
            $next[json_encode($row, JSON_THROW_ON_ERROR)] = (string) ($row['label'] ?? '');
        }

        return array_values(array_unique($gained ? array_diff($next, $current) : array_diff($current, $next)));
    }
}
