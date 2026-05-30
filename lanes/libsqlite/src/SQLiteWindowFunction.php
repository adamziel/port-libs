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
     * @param iterable<mixed> $values
     * @param iterable<int|float|string> $nthValues
     * @param iterable<mixed>|null $orderKeys
     * @return list<mixed>
     */
    public static function nthValueByRow(
        iterable $values,
        iterable $nthValues,
        ?iterable $orderKeys = null,
        string $frameUnit = 'ROWS',
        string $startBoundary = 'UNBOUNDED PRECEDING',
        string $endBoundary = 'CURRENT ROW',
    ): array {
        $rows = self::rows($values);
        $nthRows = self::rows($nthValues);
        $keys = $orderKeys === null ? array_keys($rows) : self::rows($orderKeys);
        if (count($rows) !== count($nthRows) || count($rows) !== count($keys)) {
            throw new \InvalidArgumentException('SQLite nth_value() values, indexes, and ORDER BY keys must have the same row count');
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

        $start = self::parseFrameBoundary($startBoundary);
        $end = self::parseFrameBoundary($endBoundary);
        $result = [];
        foreach (array_keys($rows) as $index) {
            $nth = self::nthIndexValue($nthRows[$index]);
            $frameIndexes = self::frameIndexesBetween($keys, $index, $unit, $start, $end);
            $target = $frameIndexes[$nth - 1] ?? null;
            $result[] = $target === null ? null : $rows[$target];
        }

        return $result;
    }

    /**
     * @param iterable<mixed> $values
     * @param iterable<mixed> $orderKeys
     * @return list<mixed>
     */
    public static function valueFrameValues(
        string $function,
        iterable $values,
        iterable $orderKeys,
        string $frameUnit,
        int|float $preceding,
        int|float $following,
        string $exclude = 'NO OTHERS',
        ?int $nth = null,
    ): array {
        $function = strtolower($function);
        if (!in_array($function, ['first_value', 'last_value', 'nth_value'], true)) {
            throw new \InvalidArgumentException("SQLite window value function {$function} is not supported");
        }
        if ($function === 'nth_value' && ($nth === null || $nth <= 0)) {
            throw new \InvalidArgumentException('SQLite nth_value() index must be positive');
        }
        if ($preceding < 0 || $following < 0) {
            throw new \InvalidArgumentException('SQLite window frame offsets must be non-negative');
        }

        $rows = self::rows($values);
        $keys = self::rows($orderKeys);
        if (count($rows) !== count($keys)) {
            throw new \InvalidArgumentException('SQLite window values and ORDER BY keys must have the same row count');
        }

        $excludeMode = strtoupper(trim($exclude));
        if (!in_array($excludeMode, ['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'], true)) {
            throw new \InvalidArgumentException('SQLite window EXCLUDE mode is not supported');
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

        $result = [];
        foreach (array_keys($rows) as $index) {
            $frameIndexes = self::frameIndexes($keys, $index, $preceding, $following, $unit);
            $frameIndexes = self::applyExclude($frameIndexes, $keys, $index, $excludeMode);

            $target = match ($function) {
                'first_value' => $frameIndexes[0] ?? null,
                'last_value' => $frameIndexes === [] ? null : $frameIndexes[count($frameIndexes) - 1],
                'nth_value' => $frameIndexes[($nth ?? 1) - 1] ?? null,
                default => null,
            };
            $result[] = $target === null ? null : $rows[$target];
        }

        return $result;
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
        int|float $preceding,
        int|float $following,
        string $exclude = 'NO OTHERS',
        ?iterable $filters = null,
        string $separator = ',',
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
        int|float $preceding,
        int|float $following,
        string $exclude = 'NO OTHERS',
        ?iterable $filters = null,
        string $separator = ',',
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
     * @param iterable<mixed> $values
     * @param iterable<mixed> $orderKeys
     * @return list<mixed>
     */
    public static function aggregateFrameValues(
        string $function,
        iterable $values,
        iterable $orderKeys,
        string $frameUnit,
        int|float $preceding,
        int|float $following,
        string $exclude = 'NO OTHERS',
        ?iterable $filters = null,
        string $separator = ',',
    ): array {
        $function = strtolower($function);
        if (!in_array($function, ['count', 'sum', 'total', 'avg', 'min', 'max', 'group_concat'], true)) {
            throw new \InvalidArgumentException("SQLite window aggregate {$function} is not supported");
        }
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

        $result = [];
        foreach (array_keys($rows) as $index) {
            $frameIndexes = self::frameIndexes($keys, $index, $preceding, $following, $unit);
            $frameIndexes = self::applyExclude($frameIndexes, $keys, $index, $excludeMode);
            if ($filterRows !== null) {
                $frameIndexes = array_values(array_filter(
                    $frameIndexes,
                    static fn (int $frameIndex): bool => self::sqlTruthy($filterRows[$frameIndex]),
                ));
            }

            $values = array_map(static fn (int $frameIndex): mixed => $rows[$frameIndex], $frameIndexes);
            $result[] = match ($function) {
                'count' => count(array_filter($values, static fn (mixed $value): bool => $value !== null)),
                'sum' => self::sumFrameValues($values),
                'total' => self::totalFrameValues($values),
                'avg' => self::avgFrameValues($values),
                'min' => self::minMaxFrameValues($values, true),
                'max' => self::minMaxFrameValues($values, false),
                'group_concat' => self::groupConcatFrameValues($values, $separator),
            };
        }

        return $result;
    }

    /**
     * @param iterable<mixed> $values
     * @param iterable<mixed> $orderKeys
     * @param iterable<bool|int|float|string|null>|null $filters
     * @return list<mixed>
     */
    public static function aggregateFrameBetweenValues(
        string $function,
        iterable $values,
        iterable $orderKeys,
        string $frameUnit,
        string $startBoundary,
        string $endBoundary,
        string $exclude = 'NO OTHERS',
        ?iterable $filters = null,
        string $separator = ',',
    ): array {
        $function = strtolower($function);
        if (!in_array($function, ['count', 'sum', 'total', 'avg', 'min', 'max', 'group_concat'], true)) {
            throw new \InvalidArgumentException("SQLite window aggregate {$function} is not supported");
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

        $unit = strtoupper(trim($frameUnit));
        if (!in_array($unit, ['ROWS', 'RANGE', 'GROUPS'], true)) {
            throw new \InvalidArgumentException('SQLite window frame unit is not supported');
        }
        $start = self::parseFrameBoundary($startBoundary);
        $end = self::parseFrameBoundary($endBoundary);
        $result = [];
        foreach (array_keys($rows) as $index) {
            $frameIndexes = self::frameIndexesBetween($keys, $index, $unit, $start, $end);
            $frameIndexes = self::applyExclude($frameIndexes, $keys, $index, $excludeMode);
            if ($filterRows !== null) {
                $frameIndexes = array_values(array_filter(
                    $frameIndexes,
                    static fn (int $frameIndex): bool => self::sqlTruthy($filterRows[$frameIndex]),
                ));
            }

            $frameValues = array_map(static fn (int $frameIndex): mixed => $rows[$frameIndex], $frameIndexes);
            $result[] = match ($function) {
                'count' => count(array_filter($frameValues, static fn (mixed $value): bool => $value !== null)),
                'sum' => self::sumFrameValues($frameValues),
                'total' => self::totalFrameValues($frameValues),
                'avg' => self::avgFrameValues($frameValues),
                'min' => self::minMaxFrameValues($frameValues, true),
                'max' => self::minMaxFrameValues($frameValues, false),
                'group_concat' => self::groupConcatFrameValues($frameValues, $separator),
            };
        }

        return $result;
    }

    /**
     * @param iterable<mixed> $values
     * @param iterable<mixed> $orderKeys
     * @param iterable<bool|int|float|string|null>|null $filters
     * @return list<array{count:int,sum:int|float|null,groupConcat:string|null,frame:list<int>}>
     */
    public static function aggregateFrameBetweenRows(
        iterable $values,
        iterable $orderKeys,
        string $frameUnit,
        string $startBoundary,
        string $endBoundary,
        string $exclude = 'NO OTHERS',
        ?iterable $filters = null,
    ): array {
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

        $start = self::parseFrameBoundary($startBoundary);
        $end = self::parseFrameBoundary($endBoundary);
        $result = [];
        foreach (array_keys($rows) as $index) {
            $frameIndexes = self::frameIndexesBetween($keys, $index, $unit, $start, $end);
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
    private static function frameIndexes(array $orderKeys, int $currentIndex, int|float $preceding, int|float $following, string $unit): array
    {
        $count = count($orderKeys);
        if ($count === 0) {
            return [];
        }

        if ($unit === 'ROWS') {
            if (!self::isIntegerOffset($preceding) || !self::isIntegerOffset($following)) {
                throw new \InvalidArgumentException('SQLite ROWS frame offsets must be integers');
            }
            $precedingRows = (int) $preceding;
            $followingRows = (int) $following;
            $start = max(0, $currentIndex - $precedingRows);
            $end = min($count - 1, $currentIndex + $followingRows);

            return range($start, $end);
        }

        if ($unit === 'RANGE') {
            $current = (float) $orderKeys[$currentIndex];
            $lower = $current - $preceding;
            $upper = $current + $following;
            $indexes = [];
            foreach ($orderKeys as $index => $key) {
                $value = (float) $key;
                if ($value >= $lower - 1.0e-12 && $value <= $upper + 1.0e-12) {
                    $indexes[] = $index;
                }
            }

            return $indexes;
        }

        if (!self::isIntegerOffset($preceding) || !self::isIntegerOffset($following)) {
            throw new \InvalidArgumentException('SQLite GROUPS frame offsets must be integers');
        }
        $precedingGroups = (int) $preceding;
        $followingGroups = (int) $following;

        $groups = self::peerGroups($orderKeys);
        $groupByIndex = [];
        foreach ($groups as $groupIndex => $group) {
            foreach ($group as $rowIndex) {
                $groupByIndex[$rowIndex] = $groupIndex;
            }
        }

        $currentGroup = $groupByIndex[$currentIndex];
        $startGroup = max(0, $currentGroup - $precedingGroups);
        $endGroup = min(count($groups) - 1, $currentGroup + $followingGroups);
        $indexes = [];
        for ($groupIndex = $startGroup; $groupIndex <= $endGroup; $groupIndex++) {
            array_push($indexes, ...$groups[$groupIndex]);
        }

        return $indexes;
    }

    /**
     * @param list<mixed> $orderKeys
     * @param array{type:string,offset:int|float|null} $start
     * @param array{type:string,offset:int|float|null} $end
     * @return list<int>
     */
    private static function frameIndexesBetween(array $orderKeys, int $currentIndex, string $unit, array $start, array $end): array
    {
        $count = count($orderKeys);
        if ($count === 0) {
            return [];
        }

        if ($unit === 'ROWS') {
            $startIndex = self::rowBoundaryIndex($currentIndex, $count, $start, true);
            $endIndex = self::rowBoundaryIndex($currentIndex, $count, $end, false);
            if ($startIndex > $endIndex || $endIndex < 0 || $startIndex > $count - 1) {
                return [];
            }

            return range(max(0, $startIndex), min($count - 1, $endIndex));
        }

        if ($unit === 'RANGE') {
            if (!is_int($orderKeys[$currentIndex]) && !is_float($orderKeys[$currentIndex]) && !is_bool($orderKeys[$currentIndex])) {
                $groups = self::peerGroups($orderKeys);
                $groupByIndex = [];
                foreach ($groups as $groupIndex => $group) {
                    foreach ($group as $rowIndex) {
                        $groupByIndex[$rowIndex] = $groupIndex;
                    }
                }

                $currentGroup = $groupByIndex[$currentIndex];
                $startGroup = self::rowBoundaryIndex($currentGroup, count($groups), $start, true);
                $endGroup = self::rowBoundaryIndex($currentGroup, count($groups), $end, false);
                if ($startGroup > $endGroup) {
                    return [];
                }

                $indexes = [];
                for ($groupIndex = max(0, $startGroup); $groupIndex <= min(count($groups) - 1, $endGroup); $groupIndex++) {
                    array_push($indexes, ...$groups[$groupIndex]);
                }

                return $indexes;
            }

            $current = (float) $orderKeys[$currentIndex];
            $lower = self::rangeBoundaryValue($current, $start, true);
            $upper = self::rangeBoundaryValue($current, $end, false);
            if ($lower > $upper) {
                return [];
            }

            $indexes = [];
            foreach ($orderKeys as $index => $key) {
                $value = (float) $key;
                if ($value >= $lower - 1.0e-12 && $value <= $upper + 1.0e-12) {
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
        $startGroup = self::rowBoundaryIndex($currentGroup, count($groups), $start, true);
        $endGroup = self::rowBoundaryIndex($currentGroup, count($groups), $end, false);
        if ($startGroup > $endGroup) {
            return [];
        }

        $indexes = [];
        for ($groupIndex = max(0, $startGroup); $groupIndex <= min(count($groups) - 1, $endGroup); $groupIndex++) {
            array_push($indexes, ...$groups[$groupIndex]);
        }

        return $indexes;
    }

    /**
     * @return array{type:string,offset:int|float|null}
     */
    private static function parseFrameBoundary(string $boundary): array
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $boundary) ?? $boundary));
        if ($normalized === 'UNBOUNDED PRECEDING' || $normalized === 'UNBOUNDED FOLLOWING' || $normalized === 'CURRENT ROW') {
            return ['type' => $normalized, 'offset' => null];
        }
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?) (PRECEDING|FOLLOWING)$/', $normalized, $match) === 1) {
            $number = str_contains($match[1], '.') ? (float) $match[1] : (int) $match[1];

            return ['type' => $match[2], 'offset' => $number];
        }

        throw new \InvalidArgumentException("SQLite window frame boundary is not supported: {$boundary}");
    }

    /**
     * @param array{type:string,offset:int|float|null} $boundary
     */
    private static function rowBoundaryIndex(int $currentIndex, int $count, array $boundary, bool $isStart): int
    {
        return match ($boundary['type']) {
            'UNBOUNDED PRECEDING' => 0,
            'UNBOUNDED FOLLOWING' => $count - 1,
            'CURRENT ROW' => $currentIndex,
            'PRECEDING' => $currentIndex - self::integerBoundaryOffset($boundary),
            'FOLLOWING' => $currentIndex + self::integerBoundaryOffset($boundary),
            default => $isStart ? $count : -1,
        };
    }

    /**
     * @param array{type:string,offset:int|float|null} $boundary
     */
    private static function rangeBoundaryValue(float $current, array $boundary, bool $isStart): float
    {
        return match ($boundary['type']) {
            'UNBOUNDED PRECEDING' => -INF,
            'UNBOUNDED FOLLOWING' => INF,
            'CURRENT ROW' => $current,
            'PRECEDING' => $current - self::numericBoundaryOffset($boundary),
            'FOLLOWING' => $current + self::numericBoundaryOffset($boundary),
            default => $isStart ? INF : -INF,
        };
    }

    /**
     * @param array{type:string,offset:int|float|null} $boundary
     */
    private static function integerBoundaryOffset(array $boundary): int
    {
        $offset = $boundary['offset'];
        if (!is_int($offset) && (!is_float($offset) || floor($offset) !== $offset)) {
            throw new \InvalidArgumentException('SQLite ROWS and GROUPS frame offsets must be integers');
        }

        return (int) $offset;
    }

    /**
     * @param array{type:string,offset:int|float|null} $boundary
     */
    private static function numericBoundaryOffset(array $boundary): int|float
    {
        $offset = $boundary['offset'];
        if (!is_int($offset) && !is_float($offset)) {
            throw new \InvalidArgumentException('SQLite RANGE frame offsets must be numeric');
        }

        return $offset;
    }

    private static function nthIndexValue(mixed $value): int
    {
        if (is_string($value) && preg_match('/^[+-]?[0-9]+$/', $value) === 1) {
            $value = (int) $value;
        }
        if (!is_int($value) && (!is_float($value) || floor($value) !== $value)) {
            throw new \InvalidArgumentException('SQLite nth_value() index must be an integer');
        }

        $nth = (int) $value;
        if ($nth <= 0) {
            throw new \InvalidArgumentException('SQLite nth_value() index must be positive');
        }

        return $nth;
    }

    private static function isIntegerOffset(int|float $offset): bool
    {
        return is_int($offset) || floor($offset) === $offset;
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
        if (is_array($left) && is_array($right)) {
            $count = min(count($left), count($right));
            for ($index = 0; $index < $count; $index++) {
                $comparison = self::compareSqlValues($left[$index] ?? null, $right[$index] ?? null);
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return count($left) <=> count($right);
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

    /**
     * @param list<mixed> $values
     */
    private static function sumFrameValues(array $values): int|float|null
    {
        $sum = null;
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            if (!is_int($value) && !is_float($value) && !is_bool($value)) {
                throw new \InvalidArgumentException('SQLite window sum() values must be numeric or NULL');
            }
            $sum = ($sum ?? 0) + (is_bool($value) ? (int) $value : $value);
        }

        return $sum;
    }

    /**
     * @param list<mixed> $values
     */
    private static function avgFrameValues(array $values): ?float
    {
        $sum = self::sumFrameValues($values);
        if ($sum === null) {
            return null;
        }

        $count = count(array_filter($values, static fn (mixed $value): bool => $value !== null));

        return $count === 0 ? null : $sum / $count;
    }

    /**
     * @param list<mixed> $values
     */
    private static function totalFrameValues(array $values): float
    {
        $sum = self::sumFrameValues($values);

        return $sum === null ? 0.0 : (float) $sum;
    }

    /**
     * @param list<mixed> $values
     */
    private static function minMaxFrameValues(array $values, bool $minimum): mixed
    {
        $selected = null;
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            if (!is_int($value) && !is_float($value) && !is_string($value) && !is_bool($value)) {
                throw new \InvalidArgumentException('SQLite window min()/max() values must be scalar or NULL');
            }
            if ($selected === null || ($minimum ? $value < $selected : $value > $selected)) {
                $selected = $value;
            }
        }

        return $selected;
    }

    /**
     * @param list<mixed> $values
     */
    private static function groupConcatFrameValues(array $values, string $separator = ','): ?string
    {
        $text = [];
        foreach ($values as $value) {
            if ($value !== null) {
                $text[] = self::valueText($value);
            }
        }

        return $text === [] ? null : implode($separator, $text);
    }

    private static function sortRank(mixed $value): int
    {
        return match (true) {
            $value === null => 0,
            is_int($value) || is_float($value) || is_bool($value) => 1,
            is_string($value) => 2,
            $value instanceof SQLiteBlobValue => 3,
            is_array($value) => 4,
            default => throw new \InvalidArgumentException('SQLite window ORDER BY values must be scalar, BLOB, or NULL'),
        };
    }
}
