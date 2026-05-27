<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWindowFunction
{
    /**
     * @param iterable<mixed> $orderKeys
     * @return list<int>
     */
    public static function rowNumber(iterable $orderKeys): array
    {
        return array_map(static fn (int $index): int => $index + 1, array_keys(self::rows($orderKeys)));
    }

    /**
     * @param iterable<mixed> $orderKeys
     * @return list<int>
     */
    public static function rank(iterable $orderKeys): array
    {
        $peerGroups = self::peerGroups($orderKeys);
        $ranks = [];
        $rank = 1;
        foreach ($peerGroups as $group) {
            foreach ($group as $_index) {
                $ranks[] = $rank;
            }
            $rank += count($group);
        }

        return $ranks;
    }

    /**
     * @param iterable<mixed> $orderKeys
     * @return list<int>
     */
    public static function denseRank(iterable $orderKeys): array
    {
        $peerGroups = self::peerGroups($orderKeys);
        $ranks = [];
        $rank = 1;
        foreach ($peerGroups as $group) {
            foreach ($group as $_index) {
                $ranks[] = $rank;
            }
            $rank++;
        }

        return $ranks;
    }

    /**
     * @param iterable<mixed> $orderKeys
     * @return list<float>
     */
    public static function percentRank(iterable $orderKeys): array
    {
        $rows = self::rows($orderKeys);
        $count = count($rows);
        if ($count === 0) {
            return [];
        }
        if ($count === 1) {
            return [0.0];
        }

        return array_map(static fn (int $rank): float => ($rank - 1) / ($count - 1), self::rank($rows));
    }

    /**
     * @param iterable<mixed> $orderKeys
     * @return list<float>
     */
    public static function cumeDist(iterable $orderKeys): array
    {
        $peerGroups = self::peerGroups($orderKeys);
        $count = array_sum(array_map('count', $peerGroups));
        if ($count === 0) {
            return [];
        }

        $result = [];
        $seen = 0;
        foreach ($peerGroups as $group) {
            $seen += count($group);
            $value = (float) ($seen / $count);
            foreach ($group as $_index) {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * @param iterable<mixed> $rows
     * @return list<int>
     */
    public static function ntile(iterable $rows, int $buckets): array
    {
        if ($buckets <= 0) {
            throw new \InvalidArgumentException('SQLite ntile() bucket count must be positive');
        }

        $values = self::rows($rows);
        $count = count($values);
        if ($count === 0) {
            return [];
        }

        $baseSize = intdiv($count, $buckets);
        $largerBuckets = $count % $buckets;
        $result = [];
        for ($bucket = 1; $bucket <= min($buckets, $count); $bucket++) {
            $size = $baseSize + ($bucket <= $largerBuckets ? 1 : 0);
            if ($size === 0) {
                continue;
            }
            array_push($result, ...array_fill(0, $size, $bucket));
        }

        return $result;
    }

    /**
     * @param iterable<mixed> $values
     * @return list<mixed>
     */
    public static function lag(iterable $values, int $offset = 1, mixed $default = null): array
    {
        return self::offsetValue($values, -$offset, $default, 'lag');
    }

    /**
     * @param iterable<mixed> $values
     * @return list<mixed>
     */
    public static function lead(iterable $values, int $offset = 1, mixed $default = null): array
    {
        return self::offsetValue($values, $offset, $default, 'lead');
    }

    /**
     * @param iterable<mixed> $values
     * @return list<mixed>
     */
    public static function firstValue(iterable $values): array
    {
        $rows = self::rows($values);
        if ($rows === []) {
            return [];
        }

        return array_fill(0, count($rows), $rows[0]);
    }

    /**
     * @param iterable<mixed> $values
     * @return list<mixed>
     */
    public static function lastValue(iterable $values): array
    {
        $rows = self::rows($values);
        if ($rows === []) {
            return [];
        }

        return array_fill(0, count($rows), $rows[count($rows) - 1]);
    }

    /**
     * @param iterable<mixed> $values
     * @return list<mixed>
     */
    public static function nthValue(iterable $values, int $nth): array
    {
        if ($nth <= 0) {
            throw new \InvalidArgumentException('SQLite nth_value() index must be positive');
        }

        $rows = self::rows($values);
        $value = $rows[$nth - 1] ?? null;

        return array_fill(0, count($rows), $value);
    }

    /**
     * @param iterable<mixed> $rows
     * @return array{rowNumber:list<int>,rank:list<int>,denseRank:list<int>,percentRank:list<float>,cumeDist:list<float>,ntile:list<int>}
     */
    public static function rankingSummary(iterable $rows, int $buckets): array
    {
        $orderKeys = self::rows($rows);

        return [
            'rowNumber' => self::rowNumber($orderKeys),
            'rank' => self::rank($orderKeys),
            'denseRank' => self::denseRank($orderKeys),
            'percentRank' => self::percentRank($orderKeys),
            'cumeDist' => self::cumeDist($orderKeys),
            'ntile' => self::ntile($orderKeys, $buckets),
        ];
    }

    /**
     * @param iterable<mixed> $values
     * @param iterable<mixed> $orderKeys
     * @param iterable<bool|int|float|string|null>|null $filters
     * @return list<array{count:int,sum:int|float|null,groupConcat:string|null,frame:list<int>}>
     */
    public static function aggregateRows(
        iterable $values,
        iterable $orderKeys,
        int $preceding,
        int $following,
        string $exclude = 'NO OTHERS',
        ?iterable $filters = null,
    ): array {
        return self::aggregateFrameRows($values, $orderKeys, 'ROWS', $preceding, $following, $exclude, $filters);
    }

    /**
     * @param iterable<mixed> $values
     * @param iterable<mixed> $orderKeys
     * @param iterable<bool|int|float|string|null>|null $filters
     * @return list<array{count:int,sum:int|float|null,groupConcat:string|null,frame:list<int>}>
     */
    public static function aggregateFrameRows(
        iterable $values,
        iterable $orderKeys,
        string $frameUnit,
        int $preceding,
        int $following,
        string $exclude = 'NO OTHERS',
        ?iterable $filters = null,
    ): array {
        if ($preceding < 0 || $following < 0) {
            throw new \InvalidArgumentException('SQLite window frame offsets must be non-negative');
        }

        $rows = self::rows($values);
        $keys = self::rows($orderKeys);
        if (count($rows) !== count($keys)) {
            throw new \InvalidArgumentException('SQLite window values and ORDER BY keys must have the same row count');
        }

        $filterRows = $filters === null ? null : self::rows($filters);
        if ($filterRows !== null && count($filterRows) !== count($rows)) {
            throw new \InvalidArgumentException('SQLite window FILTER values must have the same row count');
        }

        $excludeMode = strtoupper(trim($exclude));
        if (!in_array($excludeMode, ['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'], true)) {
            throw new \InvalidArgumentException('SQLite window EXCLUDE mode is not supported');
        }

        foreach ($keys as $key) {
            self::sortRank($key);
        }

        $unit = strtoupper(trim($frameUnit));
        if (!in_array($unit, ['ROWS', 'RANGE', 'GROUPS'], true)) {
            throw new \InvalidArgumentException('SQLite window frame unit is not supported');
        }
        if ($unit === 'RANGE') {
            foreach ($keys as $key) {
                if (!is_int($key) && !is_float($key) && !is_bool($key)) {
                    throw new \InvalidArgumentException('SQLite RANGE frame offsets require numeric ORDER BY keys');
                }
            }
        }

        $count = count($rows);
        $result = [];
        for ($index = 0; $index < $count; $index++) {
            $frameIndexes = self::frameIndexes($keys, $index, $preceding, $following, $unit);
            $frameIndexes = self::applyExclude($frameIndexes, $keys, $index, $excludeMode);
            if ($filterRows !== null) {
                $frameIndexes = array_values(array_filter(
                    $frameIndexes,
                    static fn (int $frameIndex): bool => self::sqlTruthy($filterRows[$frameIndex]),
                ));
            }

            $sum = null;
            $groupValues = [];
            foreach ($frameIndexes as $frameIndex) {
                $value = $rows[$frameIndex];
                if ($value !== null) {
                    if (!is_int($value) && !is_float($value) && !is_bool($value)) {
                        throw new \InvalidArgumentException('SQLite window sum() values must be numeric or NULL');
                    }
                    $sum = ($sum ?? 0) + (is_bool($value) ? (int) $value : $value);
                    $groupValues[] = self::valueText($value);
                }
            }

            $result[] = [
                'count' => count($frameIndexes),
                'sum' => $sum,
                'groupConcat' => $groupValues === [] ? null : implode(',', $groupValues),
                'frame' => $frameIndexes,
            ];
        }

        return $result;
    }

    /**
     * @param list<mixed> $orderKeys
     * @return list<int>
     */
    private static function frameIndexes(array $orderKeys, int $currentIndex, int $preceding, int $following, string $unit): array
    {
        $count = count($orderKeys);
        if ($count === 0) {
            return [];
        }

        if ($unit === 'ROWS') {
            $start = max(0, $currentIndex - $preceding);
            $end = min($count - 1, $currentIndex + $following);

            return range($start, $end);
        }

        if ($unit === 'RANGE') {
            $current = (float) $orderKeys[$currentIndex];
            $lower = $current - $preceding;
            $upper = $current + $following;
            $indexes = [];
            foreach ($orderKeys as $index => $key) {
                $value = (float) $key;
                if ($value >= $lower && $value <= $upper) {
                    $indexes[] = $index;
                }
            }

            return $indexes;
        }

        $groups = self::peerGroups($orderKeys);
        $groupByIndex = [];
        foreach ($groups as $groupIndex => $group) {
            foreach ($group as $rowIndex) {
                $groupByIndex[$rowIndex] = $groupIndex;
            }
        }

        $currentGroup = $groupByIndex[$currentIndex];
        $startGroup = max(0, $currentGroup - $preceding);
        $endGroup = min(count($groups) - 1, $currentGroup + $following);
        $indexes = [];
        for ($groupIndex = $startGroup; $groupIndex <= $endGroup; $groupIndex++) {
            array_push($indexes, ...$groups[$groupIndex]);
        }

        return $indexes;
    }

    /**
     * @param iterable<mixed> $values
     * @return list<mixed>
     */
    private static function offsetValue(iterable $values, int $relativeOffset, mixed $default, string $functionName): array
    {
        $offset = abs($relativeOffset);
        if ($offset <= 0) {
            throw new \InvalidArgumentException("SQLite {$functionName}() offset must be positive");
        }

        $rows = self::rows($values);
        $result = [];
        foreach (array_keys($rows) as $index) {
            $target = $index + $relativeOffset;
            $result[] = array_key_exists($target, $rows) ? $rows[$target] : $default;
        }

        return $result;
    }

    /**
     * @param list<int> $frameIndexes
     * @param list<mixed> $orderKeys
     * @return list<int>
     */
    private static function applyExclude(array $frameIndexes, array $orderKeys, int $currentIndex, string $excludeMode): array
    {
        if ($excludeMode === 'NO OTHERS') {
            return $frameIndexes;
        }

        return array_values(array_filter($frameIndexes, static function (int $frameIndex) use ($orderKeys, $currentIndex, $excludeMode): bool {
            $isCurrent = $frameIndex === $currentIndex;
            $isPeer = self::compareSqlValues($orderKeys[$frameIndex], $orderKeys[$currentIndex]) === 0;

            return match ($excludeMode) {
                'CURRENT ROW' => !$isCurrent,
                'GROUP' => !$isPeer,
                'TIES' => !$isPeer || $isCurrent,
                default => true,
            };
        }));
    }

    private static function sqlTruthy(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value != 0.0;
        }
        if (is_string($value)) {
            return (float) $value != 0.0;
        }

        throw new \InvalidArgumentException('SQLite window FILTER values must be scalar or NULL');
    }

    /**
     * @param iterable<mixed> $orderKeys
     * @return list<list<int>>
     */
    private static function peerGroups(iterable $orderKeys): array
    {
        $rows = self::rows($orderKeys);
        $groups = [];
        foreach ($rows as $index => $key) {
            self::sortRank($key);
            if ($index === 0 || self::compareSqlValues($rows[$index - 1], $key) !== 0) {
                $groups[] = [];
            }
            $groups[count($groups) - 1][] = $index;
        }

        return $groups;
    }

    /**
     * @param iterable<mixed> $rows
     * @return list<mixed>
     */
    private static function rows(iterable $rows): array
    {
        return array_values(is_array($rows) ? $rows : iterator_to_array($rows, false));
    }

    private static function compareSqlValues(mixed $left, mixed $right): int
    {
        $leftRank = self::sortRank($left);
        $rightRank = self::sortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        if ($left === null || $right === null) {
            return 0;
        }
        if ($left instanceof SQLiteBlobValue && $right instanceof SQLiteBlobValue) {
            return strcmp($left->bytes, $right->bytes);
        }
        if ((is_int($left) || is_float($left) || is_bool($left)) && (is_int($right) || is_float($right) || is_bool($right))) {
            return ((float) $left) <=> ((float) $right);
        }

        return strcmp(self::valueText($left), self::valueText($right));
    }

    private static function valueText(mixed $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return $value->bytes;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException('SQLite window ORDER BY values must be scalar, BLOB, or NULL');
    }

    private static function sortRank(mixed $value): int
    {
        return match (true) {
            $value === null => 0,
            is_int($value) || is_float($value) || is_bool($value) => 1,
            is_string($value) => 2,
            $value instanceof SQLiteBlobValue => 3,
            default => throw new \InvalidArgumentException('SQLite window ORDER BY values must be scalar, BLOB, or NULL'),
        };
    }
}
