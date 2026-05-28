<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext186Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables): array
    {
        self::assertCommaLimitSql($sql);

        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext183Plan::compare(self::offsetLimitSql($sql), $currentTables, $nextTables);
        $currentRows = self::rows($base, 'currentRows');
        $nextRows = self::rows($base, 'nextRows');
        $currentPreLimit = self::rows($base, 'currentPreLimitRows');
        $nextPreLimit = self::rows($base, 'nextPreLimitRows');

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next186-ready';
        $base['compound']['commaLimit'] = self::commaLimit($sql);
        $base['sourceBoundary'] = [
            'currentAdmittedLabels' => self::labels($currentRows),
            'nextAdmittedLabels' => self::labels($nextRows),
            'currentSkippedLabels' => self::labels(array_slice($currentPreLimit, 0, self::offset($base))),
            'nextSkippedLabels' => self::labels(array_slice($nextPreLimit, 0, self::offset($base))),
            'currentTruncatedLabels' => self::labels(array_slice($currentPreLimit, self::offset($base) + self::limit($base))),
            'nextTruncatedLabels' => self::labels(array_slice($nextPreLimit, self::offset($base) + self::limit($base))),
            'addedAdmittedLabels' => self::changedLabels($currentRows, $nextRows, true),
            'removedAdmittedLabels' => self::changedLabels($currentRows, $nextRows, false),
            'nextAutoloadWindowLabels' => self::autoloadLabels($nextPreLimit),
            'nextRecursiveLabels' => self::recursiveLabels($nextPreLimit),
        ];
        $base['replanReasons'] = array_values(array_unique(array_merge(
            is_array($base['replanReasons'] ?? null) ? $base['replanReasons'] : [],
            [
                'compound-tail-comma-limit-current-source-next186',
                'window-rank-dense-rank-before-distinct-union-next186',
                'recursive-offset-source-boundary-next186',
                'wordpress-autoload-option-rank-replans-limit-window-next186',
            ],
        )));
        $base['dependencies'] = [
            'sqlite-select-sql-recursive-limit-offset-next186',
            'sqlite-select-sql-compound-comma-limit-next186',
            'sqlite-select-sql-window-rank-dense-rank-next186',
            'sqlite-current-source-next186',
        ];
        $base['dependency_closure'] = 'no new support component needed; next186 reuses native recursive CTE LIMIT/OFFSET, compound UNION ALL/UNION, rank/dense_rank window evaluation, ORDER BY, and comma-form tail LIMIT execution';

        return $base;
    }

    private static function assertCommaLimitSql(string $sql): void
    {
        if (preg_match('/\s+LIMIT\s+\d+\s*,\s*\d+\s*;?\s*$/i', trim($sql)) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next186 needs comma-form final LIMIT offset,count');
        }
        if (preg_match('/\brank\s*\(/i', $sql) !== 1 || preg_match('/\bdense_rank\s*\(/i', $sql) !== 1) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next186 needs rank() and dense_rank() window arms');
        }
    }

    private static function offsetLimitSql(string $sql): string
    {
        $limit = self::commaLimit($sql);
        $converted = preg_replace(
            '/\s+LIMIT\s+\d+\s*,\s*\d+\s*;?\s*$/i',
            ' LIMIT ' . $limit['count'] . ' OFFSET ' . $limit['offset'],
            rtrim(trim($sql), ';'),
        );
        if (!is_string($converted)) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT current-source next186 cannot normalize comma LIMIT');
        }

        return $converted;
    }

    /**
     * @param array<string,mixed> $summary
     * @return list<array<string,mixed>>
     */
    private static function rows(array $summary, string $key): array
    {
        return is_array($summary[$key] ?? null) ? array_values(array_filter(
            $summary[$key],
            static fn (mixed $row): bool => is_array($row),
        )) : [];
    }

    /**
     * @return array{offset:int,count:int}
     */
    private static function commaLimit(string $sql): array
    {
        if (preg_match('/\s+LIMIT\s+(\d+)\s*,\s*(\d+)\s*;?\s*$/i', trim($sql), $match) !== 1) {
            return ['offset' => 0, 'count' => 0];
        }

        return ['offset' => (int) $match[1], 'count' => (int) $match[2]];
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
     * @return list<string>
     */
    private static function autoloadLabels(array $rows): array
    {
        return array_values(array_filter(
            self::labels($rows),
            static fn (string $label): bool => !str_starts_with($label, 'seed'),
        ));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function recursiveLabels(array $rows): array
    {
        return array_values(array_filter(
            self::labels($rows),
            static fn (string $label): bool => str_starts_with($label, 'seed'),
        ));
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
