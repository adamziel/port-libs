<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWindowSortReusePlan
{
    /**
     * @param list<array{name?:string,columns:list<string>}> $indexes
     * @param list<array{label?:string,partitionBy?:list<string>,orderBy?:list<string>,frameUnit?:string,frameStart?:string,frameEnd?:string}> $windows
     * @return array{
     *   source:string,
     *   requiredOrderByCount:int,
     *   reusedSortCount:int,
     *   indexes:list<array{name:string,columns:list<string>}>,
     *   windows:list<array{index:int,label:string,partitionBy:list<string>,orderBy:list<string>,sortKey:list<string>,sortSignature:string,frameUnit:string,frameStart:string,frameEnd:string,satisfiedByIndex:?string,requiresOrderBy:bool,reusesSort:?int}>,
     *   requiredSorts:list<array{sortIndex:int,sortKey:list<string>,sortSignature:string,windowIndexes:list<int>}>
     * }
     */
    public static function plan(array $indexes, array $windows): array
    {
        $normalizedIndexes = self::normalizeIndexes($indexes);
        $plannedWindows = [];
        $requiredSorts = [];
        $sortIndexesBySignature = [];

        foreach ($windows as $windowIndex => $window) {
            if (!is_array($window)) {
                throw new \InvalidArgumentException('SQLite window sort reuse windows must be arrays');
            }

            $partitionBy = self::normalizeTerms($window['partitionBy'] ?? [], 'window partition');
            $orderBy = self::normalizeTerms($window['orderBy'] ?? [], 'window order');
            $sortKey = [...$partitionBy, ...$orderBy];
            $sortSignature = implode('|', $sortKey);
            $satisfiedByIndex = self::indexSatisfyingSortKey($normalizedIndexes, $sortKey);
            $requiresOrderBy = $sortKey !== [] && $satisfiedByIndex === null;
            $reusesSort = null;

            if ($requiresOrderBy) {
                if (!array_key_exists($sortSignature, $sortIndexesBySignature)) {
                    $sortIndexesBySignature[$sortSignature] = count($requiredSorts);
                    $requiredSorts[] = [
                        'sortIndex' => count($requiredSorts),
                        'sortKey' => $sortKey,
                        'sortSignature' => $sortSignature,
                        'windowIndexes' => [],
                    ];
                }

                $reusesSort = $sortIndexesBySignature[$sortSignature];
                $requiredSorts[$reusesSort]['windowIndexes'][] = $windowIndex;
            }

            $plannedWindows[] = [
                'index' => $windowIndex,
                'label' => self::normalizeLabel($window['label'] ?? ''),
                'partitionBy' => $partitionBy,
                'orderBy' => $orderBy,
                'sortKey' => $sortKey,
                'sortSignature' => $sortSignature,
                'frameUnit' => self::normalizeFrameUnit($window['frameUnit'] ?? 'RANGE'),
                'frameStart' => self::normalizeFrameBoundary($window['frameStart'] ?? 'UNBOUNDED PRECEDING'),
                'frameEnd' => self::normalizeFrameBoundary($window['frameEnd'] ?? 'CURRENT ROW'),
                'satisfiedByIndex' => $satisfiedByIndex,
                'requiresOrderBy' => $requiresOrderBy,
                'reusesSort' => $reusesSort,
            ];
        }

        return [
            'source' => 'window1.test 23.1-23.6 planner window sorter reuse',
            'requiredOrderByCount' => count($requiredSorts),
            'reusedSortCount' => array_sum(array_map(static fn (array $sort): int => max(0, count($sort['windowIndexes']) - 1), $requiredSorts)),
            'indexes' => $normalizedIndexes,
            'windows' => $plannedWindows,
            'requiredSorts' => $requiredSorts,
        ];
    }

    /**
     * @param list<array{name?:string,columns:list<string>}> $indexes
     * @return list<array{name:string,columns:list<string>}>
     */
    private static function normalizeIndexes(array $indexes): array
    {
        if (!array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite window sort reuse indexes must be a list');
        }

        $normalized = [];
        foreach ($indexes as $index => $candidate) {
            if (!is_array($candidate)) {
                throw new \InvalidArgumentException('SQLite window sort reuse index entries must be arrays');
            }
            $name = self::normalizeLabel($candidate['name'] ?? 'index' . $index);
            if ($name === '') {
                throw new \InvalidArgumentException('SQLite window sort reuse index names must be non-empty');
            }
            $columns = self::normalizeTerms($candidate['columns'] ?? [], 'index columns');
            if ($columns === []) {
                throw new \InvalidArgumentException('SQLite window sort reuse indexes must include columns');
            }
            foreach ($columns as $column) {
                if (!self::isSimpleColumn($column)) {
                    throw new \InvalidArgumentException('SQLite window sort reuse index columns must be simple column names');
                }
            }

            $normalized[] = [
                'name' => $name,
                'columns' => $columns,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $terms
     * @return list<string>
     */
    private static function normalizeTerms(mixed $terms, string $label): array
    {
        if (!is_array($terms) || !array_is_list($terms)) {
            throw new \InvalidArgumentException("SQLite window sort reuse {$label} must be a list");
        }

        $normalized = [];
        foreach ($terms as $term) {
            if (!is_string($term)) {
                throw new \InvalidArgumentException("SQLite window sort reuse {$label} entries must be strings");
            }
            $value = preg_replace('/\s+/', '', strtolower(trim($term)));
            $value = $value === null ? strtolower(trim($term)) : $value;
            if ($value === '') {
                throw new \InvalidArgumentException("SQLite window sort reuse {$label} entries must be non-empty");
            }
            $normalized[] = $value;
        }

        return $normalized;
    }

    /**
     * @param list<array{name:string,columns:list<string>}> $indexes
     * @param list<string> $sortKey
     */
    private static function indexSatisfyingSortKey(array $indexes, array $sortKey): ?string
    {
        if ($sortKey === []) {
            return null;
        }
        foreach ($sortKey as $term) {
            if (!self::isSimpleColumn($term)) {
                return null;
            }
        }

        foreach ($indexes as $index) {
            if (array_slice($index['columns'], 0, count($sortKey)) === $sortKey) {
                return $index['name'];
            }
        }

        return null;
    }

    private static function normalizeFrameUnit(mixed $unit): string
    {
        if (!is_string($unit)) {
            throw new \InvalidArgumentException('SQLite window sort reuse frame unit must be a string');
        }
        $normalized = strtoupper(trim($unit));
        if (!in_array($normalized, ['ROWS', 'RANGE', 'GROUPS'], true)) {
            throw new \InvalidArgumentException('SQLite window sort reuse frame unit must be ROWS, RANGE, or GROUPS');
        }

        return $normalized;
    }

    private static function normalizeFrameBoundary(mixed $boundary): string
    {
        if (!is_string($boundary)) {
            throw new \InvalidArgumentException('SQLite window sort reuse frame boundaries must be strings');
        }
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $boundary) ?? $boundary));
        if ($normalized === '') {
            throw new \InvalidArgumentException('SQLite window sort reuse frame boundaries must be non-empty');
        }

        return $normalized;
    }

    private static function normalizeLabel(mixed $label): string
    {
        if (!is_string($label) && !is_int($label)) {
            throw new \InvalidArgumentException('SQLite window sort reuse labels must be strings');
        }

        return trim((string) $label);
    }

    private static function isSimpleColumn(string $term): bool
    {
        return preg_match('/^[a-z_][a-z0-9_]*$/i', $term) === 1;
    }
}
