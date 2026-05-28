<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext181Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext178Plan::compare($sql, $currentTables, $nextTables);
        $operators = is_array($base['compound']['operators'] ?? null) ? $base['compound']['operators'] : [];
        if (!in_array('UNION', $operators, true)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next181 needs a UNION distinct arm');
        }

        $currentPreLimit = is_array($base['currentPreLimitRows'] ?? null) ? $base['currentPreLimitRows'] : [];
        $nextPreLimit = is_array($base['nextPreLimitRows'] ?? null) ? $base['nextPreLimitRows'] : [];
        $currentRows = is_array($base['currentRows'] ?? null) ? $base['currentRows'] : [];
        $nextRows = is_array($base['nextRows'] ?? null) ? $base['nextRows'] : [];

        return [
            'status' => 'compound-select-window-recursive-limit-current-source-next181-ready',
            'currentRows' => $currentRows,
            'nextRows' => $nextRows,
            'compound' => $base['compound'],
            'windows' => $base['windows'],
            'recursive' => $base['recursive'],
            'yieldTape' => [
                'current' => self::yieldTape($currentPreLimit, $currentRows),
                'next' => self::yieldTape($nextPreLimit, $nextRows),
                'changedLabels' => self::changedLabels($currentRows, $nextRows),
                'suppressedDuplicateLabels' => [
                    'current' => self::suppressedLabels($currentPreLimit),
                    'next' => self::suppressedLabels($nextPreLimit),
                ],
            ],
            'limitTrace' => $base['limitTrace'],
            'sourceClasses' => $base['sourceClasses'],
            'boundary' => $base['boundary'],
            'replanReasons' => array_values(array_unique(array_merge(
                is_array($base['replanReasons'] ?? null) ? $base['replanReasons'] : [],
                self::yieldReplanReasons($currentPreLimit, $nextPreLimit, $currentRows, $nextRows),
            ))),
            'dependencies' => [
                'sqlite-select-sql-recursive-limit-offset-next181',
                'sqlite-select-sql-window-before-union-distinct-next181',
                'sqlite-select-sql-union-distinct-yield-tape-next181',
                'sqlite-select-sql-compound-final-limit-next181',
                'sqlite-current-source-next181',
            ],
            'dependency_closure' => 'no new support component needed; next181 reuses lane-local recursive CTE tracing, window execution, UNION distinct duplicate suppression, and final LIMIT/OFFSET execution',
        ];
    }

    /**
     * @param list<array<string,mixed>> $preLimitRows
     * @param list<array<string,mixed>> $limitedRows
     * @return list<array<string,mixed>>
     */
    private static function yieldTape(array $preLimitRows, array $limitedRows): array
    {
        $limited = array_flip(self::rowSignatures($limitedRows));
        $seen = [];
        $tape = [];
        foreach ($preLimitRows as $index => $row) {
            $signature = json_encode($row, JSON_THROW_ON_ERROR);
            $duplicate = isset($seen[$signature]);
            $seen[$signature] = true;
            $label = (string) ($row['label'] ?? '');
            $source = str_starts_with($label, 'seed') ? 'recursive' : 'table';
            $tape[] = [
                'index' => $index,
                'label' => $label,
                'source' => $source,
                'duplicateSuppressed' => $duplicate,
                'admittedByFinalLimit' => isset($limited[$signature]),
                'bucket' => isset($row['bucket']) && is_int($row['bucket']) ? $row['bucket'] : null,
            ];
        }

        return $tape;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function suppressedLabels(array $rows): array
    {
        $seen = [];
        $suppressed = [];
        foreach ($rows as $row) {
            $signature = json_encode($row, JSON_THROW_ON_ERROR);
            if (isset($seen[$signature])) {
                $suppressed[] = (string) ($row['label'] ?? '');
            }
            $seen[$signature] = true;
        }

        return $suppressed;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function changedLabels(array $currentRows, array $nextRows): array
    {
        $current = self::rowSignatures($currentRows);
        $next = self::rowSignatures($nextRows);
        $changed = array_merge(array_diff($current, $next), array_diff($next, $current));

        return array_values(array_unique(array_map(static function (string $signature): string {
            $row = json_decode($signature, true, 512, JSON_THROW_ON_ERROR);

            return is_array($row) ? (string) ($row['label'] ?? '') : '';
        }, $changed)));
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
     * @param list<array<string,mixed>> $currentPreLimit
     * @param list<array<string,mixed>> $nextPreLimit
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<string>
     */
    private static function yieldReplanReasons(array $currentPreLimit, array $nextPreLimit, array $currentRows, array $nextRows): array
    {
        $reasons = ['union-distinct-yield-tape'];
        if (self::suppressedLabels($currentPreLimit) !== [] || self::suppressedLabels($nextPreLimit) !== []) {
            $reasons[] = 'union-duplicate-suppression-visible';
        }
        if (self::changedLabels($currentRows, $nextRows) !== []) {
            $reasons[] = 'current-next-yield-boundary-changed';
        }
        if (count($nextPreLimit) > count($currentPreLimit)) {
            $reasons[] = 'next-source-prelimit-rowset-expanded';
        }

        return $reasons;
    }
}
