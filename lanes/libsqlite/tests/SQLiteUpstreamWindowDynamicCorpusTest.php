<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$rows = [
    [10, 89], [11, 81], [12, 96], [13, 59], [14, 38], [15, 68], [16, 39], [17, 62],
    [18, 91], [19, 46], [20, 6], [21, 99], [22, 97], [23, 27], [24, 46], [25, 78],
    [26, 54], [27, 97], [28, 8], [29, 67], [30, 29], [31, 93], [32, 84], [33, 77],
    [34, 23], [35, 16], [36, 16], [37, 93], [38, 65], [39, 35], [40, 47], [41, 7],
    [42, 86], [43, 74], [44, 61], [45, 91], [46, 85], [47, 24], [48, 85], [49, 43],
    [50, 59], [51, 12], [52, 32], [53, 56], [54, 3], [55, 91], [56, 22], [57, 90],
    [58, 55], [59, 15], [60, 28], [61, 89], [62, 25], [63, 47], [64, 1], [65, 56],
    [66, 40], [67, 43], [68, 56], [69, 16], [70, 75], [71, 36], [72, 89], [73, 98],
    [74, 76], [75, 81], [76, 4], [77, 94], [78, 42], [79, 30], [80, 78], [81, 33],
    [82, 29], [83, 53], [84, 63], [85, 2], [86, 87], [87, 37], [88, 80], [89, 84],
    [90, 72], [91, 41], [92, 9], [93, 61], [94, 73], [95, 95], [96, 65], [97, 13],
    [98, 58], [99, 96], [100, 98], [101, 1], [102, 21], [103, 74], [104, 65], [105, 35],
    [106, 5], [107, 73], [108, 11], [109, 51], [110, 87], [111, 41], [112, 12], [113, 8],
    [114, 20], [115, 31], [116, 31], [117, 15], [118, 95], [119, 22], [120, 73],
    [121, 79], [122, 88], [123, 34], [124, 8], [125, 11], [126, 49], [127, 34],
    [128, 90], [129, 59], [130, 96], [131, 60], [132, 55], [133, 75], [134, 77],
    [135, 44], [136, 2], [137, 7], [138, 85], [139, 57], [140, 74], [141, 29], [142, 70],
    [143, 59], [144, 19], [145, 39], [146, 26], [147, 26], [148, 47], [149, 80],
    [150, 90], [151, 36], [152, 58], [153, 47], [154, 9], [155, 72], [156, 72], [157, 66],
    [158, 33], [159, 93], [160, 75], [161, 64], [162, 81], [163, 9], [164, 23], [165, 37],
    [166, 13], [167, 12], [168, 14], [169, 62], [170, 91], [171, 36], [172, 91],
    [173, 33], [174, 15], [175, 34], [176, 36], [177, 99], [178, 3], [179, 95], [180, 69],
    [181, 58], [182, 52], [183, 30], [184, 50], [185, 84], [186, 10], [187, 84],
    [188, 33], [189, 21], [190, 39], [191, 44], [192, 58], [193, 30], [194, 38],
    [195, 34], [196, 83], [197, 27], [198, 82], [199, 17], [200, 7],
];

$aValues = array_column($rows, 0);
$bValues = array_column($rows, 1);
$evenFilters = array_map(static fn (int $value): bool => ($value % 2) === 0, $bValues);
$bOrderedRows = $rows;
usort($bOrderedRows, static fn (array $left, array $right): int => [$left[1], $left[0]] <=> [$right[1], $right[0]]);
$bOrderedAValues = array_column($bOrderedRows, 0);
$bOrderedBValues = array_column($bOrderedRows, 1);
$bOrderedEvenFilters = array_map(static fn (int $value): bool => ($value % 2) === 0, $bOrderedBValues);

$frameRows = static function (array $keys, int $index, string $unit, string $start, string $end): array {
    $count = count($keys);
    $peerGroup = static function (int $row) use ($keys): array {
        $start = $row;
        while ($start > 0 && $keys[$start - 1] === $keys[$row]) {
            $start--;
        }
        $end = $row;
        $last = count($keys) - 1;
        while ($end < $last && $keys[$end + 1] === $keys[$row]) {
            $end++;
        }

        return [$start, $end];
    };
    $groups = static function () use ($keys): array {
        $result = [];
        for ($i = 0, $count = count($keys); $i < $count;) {
            $start = $i;
            $key = $keys[$i];
            while ($i + 1 < $count && $keys[$i + 1] === $key) {
                $i++;
            }
            $result[] = [$start, $i, $key];
            $i++;
        }

        return $result;
    };
    $offset = static function (string $boundary): int|float|null {
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?) PRECEDING$/', $boundary, $match) === 1) {
            return str_contains($match[1], '.') ? (float) $match[1] : (int) $match[1];
        }
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?) FOLLOWING$/', $boundary, $match) === 1) {
            $value = str_contains($match[1], '.') ? (float) $match[1] : (int) $match[1];
            return -$value;
        }

        return null;
    };

    if ($unit === 'ROWS') {
        $startIndex = match ($start) {
            'UNBOUNDED PRECEDING' => 0,
            'CURRENT ROW' => $index,
            default => max(0, $index - (int) $offset($start)),
        };
        $endIndex = match ($end) {
            'UNBOUNDED FOLLOWING' => $count - 1,
            'CURRENT ROW' => $index,
            default => min($count - 1, $index + abs((int) $offset($end))),
        };
    } elseif ($unit === 'RANGE') {
        [$peerStart, $peerEnd] = $peerGroup($index);
        $current = $keys[$index];
        $startIndex = $peerStart;
        $endIndex = $peerEnd;
        if ($start === 'UNBOUNDED PRECEDING') {
            $startIndex = 0;
        } elseif ($start !== 'CURRENT ROW') {
            $lower = $current - (float) $offset($start);
            while ($startIndex > 0 && $keys[$startIndex - 1] >= $lower) {
                $startIndex--;
            }
        }
        if ($end === 'UNBOUNDED FOLLOWING') {
            $endIndex = $count - 1;
        } elseif ($end !== 'CURRENT ROW') {
            $upper = $current + abs((float) $offset($end));
            while ($endIndex + 1 < $count && $keys[$endIndex + 1] <= $upper) {
                $endIndex++;
            }
        }
    } else {
        $allGroups = $groups();
        $groupIndex = 0;
        foreach ($allGroups as $i => [$startRow, $endRow]) {
            if ($index >= $startRow && $index <= $endRow) {
                $groupIndex = $i;
                break;
            }
        }
        $startGroup = match ($start) {
            'UNBOUNDED PRECEDING' => 0,
            'CURRENT ROW' => $groupIndex,
            default => max(0, $groupIndex - (int) $offset($start)),
        };
        $endGroup = match ($end) {
            'UNBOUNDED FOLLOWING' => count($allGroups) - 1,
            'CURRENT ROW' => $groupIndex,
            default => min(count($allGroups) - 1, $groupIndex + abs((int) $offset($end))),
        };
        $startIndex = $allGroups[$startGroup][0];
        $endIndex = $allGroups[$endGroup][1];
    }

    return $startIndex > $endIndex ? [] : range($startIndex, $endIndex);
};

$applyExclude = static function (array $indexes, array $keys, int $index, string $exclude): array {
    if ($exclude === 'NO OTHERS') {
        return $indexes;
    }

    return array_values(array_filter($indexes, static function (int $candidate) use ($keys, $index, $exclude): bool {
        if ($exclude === 'CURRENT ROW') {
            return $candidate !== $index;
        }
        if ($exclude === 'GROUP') {
            return $keys[$candidate] !== $keys[$index];
        }
        if ($exclude === 'TIES') {
            return $candidate === $index || $keys[$candidate] !== $keys[$index];
        }

        return true;
    }));
};

$oracle = static function (
    string $function,
    array $values,
    array $keys,
    string $unit,
    string $start,
    string $end,
    string $exclude = 'NO OTHERS',
    ?array $filters = null,
    ?int $nth = null,
) use ($frameRows, $applyExclude): array {
    $result = [];
    foreach (array_keys($values) as $index) {
        $indexes = $frameRows($keys, $index, $unit, $start, $end);
        $indexes = $applyExclude($indexes, $keys, $index, $exclude);
        if ($filters !== null) {
            $indexes = array_values(array_filter($indexes, static fn (int $candidate): bool => $filters[$candidate]));
        }
        $frame = array_map(static fn (int $candidate): mixed => $values[$candidate], $indexes);
        $result[] = match ($function) {
            'count' => count(array_filter($frame, static fn (mixed $value): bool => $value !== null)),
            'sum' => $frame === [] ? null : array_sum($frame),
            'total' => (float) array_sum($frame),
            'avg' => $frame === [] ? null : (float) (array_sum($frame) / count($frame)),
            'min' => $frame === [] ? null : min($frame),
            'max' => $frame === [] ? null : max($frame),
            'group_concat' => $frame === [] ? null : implode('.', array_map('strval', $frame)),
            'first_value' => $frame[0] ?? null,
            'last_value' => $frame === [] ? null : $frame[count($frame) - 1],
            'nth_value' => $frame[($nth ?? 1) - 1] ?? null,
            default => throw new RuntimeException('Unexpected upstream window function ' . $function),
        };
    }

    return $result;
};

$scenarios = [
    ['window3.1.1.2.1 max over a range unbounded current', 'max', $bValues, $aValues, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    ['window3.1.1.2.2 min over a range unbounded current', 'min', $bValues, $aValues, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    ['window3.1.1.6.1 count over a range unbounded current', 'count', $bValues, $aValues, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    ['window3.1.1.9 sum over a rows one preceding current', 'sum', $bValues, $aValues, 'ROWS', '1 PRECEDING', 'CURRENT ROW'],
    ['window3.1.1.10 avg over a rows current one following', 'avg', $bValues, $aValues, 'ROWS', 'CURRENT ROW', '1 FOLLOWING'],
    ['window3.1.1.11 group concat over a rows current two following', 'group_concat', $aValues, $aValues, 'ROWS', 'CURRENT ROW', '2 FOLLOWING'],
    ['window3.1.1.12 max over b range current 10 following', 'max', $bOrderedAValues, $bOrderedBValues, 'RANGE', 'CURRENT ROW', '10 FOLLOWING'],
    ['window3.1.1.13 min over b range 10 preceding current', 'min', $bOrderedAValues, $bOrderedBValues, 'RANGE', '10 PRECEDING', 'CURRENT ROW'],
    ['window3.1.1.14 sum over b groups current following', 'sum', $bOrderedBValues, $bOrderedBValues, 'GROUPS', 'CURRENT ROW', '1 FOLLOWING'],
    ['window3.1.1.15 count over b groups preceding current', 'count', $bOrderedBValues, $bOrderedBValues, 'GROUPS', '1 PRECEDING', 'CURRENT ROW'],
    ['window3.1.2.2 max over b groups exclude current row', 'max', $bOrderedAValues, $bOrderedBValues, 'GROUPS', 'CURRENT ROW', '1 FOLLOWING', 'CURRENT ROW'],
    ['window3.1.2.3 min over b groups exclude group', 'min', $bOrderedAValues, $bOrderedBValues, 'GROUPS', '1 PRECEDING', 'CURRENT ROW', 'GROUP'],
    ['window3.1.2.4 count over b range exclude ties', 'count', $bOrderedBValues, $bOrderedBValues, 'RANGE', 'CURRENT ROW', '10 FOLLOWING', 'TIES'],
    ['window3.1.2.5 sum over b range filtered even', 'sum', $bOrderedBValues, $bOrderedBValues, 'RANGE', 'CURRENT ROW', '10 FOLLOWING', 'NO OTHERS', $bOrderedEvenFilters],
    ['window3.1.2.6 group concat over b groups filtered even exclude ties', 'group_concat', $bOrderedAValues, $bOrderedBValues, 'GROUPS', 'CURRENT ROW', '1 FOLLOWING', 'TIES', $bOrderedEvenFilters],
    ['window3.1.3.1 first value over a rows two preceding current', 'first_value', $bValues, $aValues, 'ROWS', '2 PRECEDING', 'CURRENT ROW'],
    ['window3.1.3.2 last value over a rows current two following', 'last_value', $bValues, $aValues, 'ROWS', 'CURRENT ROW', '2 FOLLOWING'],
    ['window3.1.3.3 nth value over b groups current following', 'nth_value', $bOrderedAValues, $bOrderedBValues, 'GROUPS', 'CURRENT ROW', '1 FOLLOWING', 'NO OTHERS', null, 3],
    ['window3.1.3.4 total over b range current following filtered', 'total', $bOrderedBValues, $bOrderedBValues, 'RANGE', 'CURRENT ROW', '2 FOLLOWING', 'NO OTHERS', $bOrderedEvenFilters],
    ['window3.1.3.5 avg over b groups preceding following filtered', 'avg', $bOrderedBValues, $bOrderedBValues, 'GROUPS', '1 PRECEDING', '1 FOLLOWING', 'NO OTHERS', $bOrderedEvenFilters],
];

foreach ($scenarios as $scenario) {
    [$name, $function, $values, $keys, $unit, $start, $end] = array_slice($scenario, 0, 7);
    $exclude = $scenario[7] ?? 'NO OTHERS';
    $filters = $scenario[8] ?? null;
    $nth = $scenario[9] ?? null;

    $tests['real upstream window dynamic ' . $name] = static function (TestRunner $t) use ($function, $values, $keys, $unit, $start, $end, $exclude, $filters, $nth, $oracle): void {
        $expected = $oracle($function, $values, $keys, $unit, $start, $end, $exclude, $filters, $nth);
        $actual = in_array($function, ['first_value', 'last_value', 'nth_value'], true)
            ? SQLiteWindowFunction::valueFrameBetweenValues($function, $values, $keys, $unit, $start, $end, $exclude, $nth, $filters)
            : SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, $unit, $start, $end, $exclude, $filters, '.');

        foreach ($expected as $index => $value) {
            $t->same($value, $actual[$index]);
        }
    };
}

return $tests;
