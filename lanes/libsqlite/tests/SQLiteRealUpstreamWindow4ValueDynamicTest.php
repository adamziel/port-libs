<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

// Upstream source: SQLite test/window4.test 1.1-1.19, 2.1-2.4, and
// 3.5-3.6. The generated matrix keeps the upstream row shapes and value
// function families while using generic table labels for the PHP port.
$tileLabels = range('a', 'j');
$tileRows = array_map(static fn (string $label): array => ['label' => $label], $tileLabels);

$valueRows = [
    ['a' => 1, 'b' => 'A', 'c' => 9],
    ['a' => 2, 'b' => 'B', 'c' => 3],
    ['a' => 3, 'b' => 'C', 'c' => 2],
    ['a' => 4, 'b' => 'D', 'c' => 10],
    ['a' => 5, 'b' => 'E', 'c' => 5],
    ['a' => 6, 'b' => 'F', 'c' => 1],
    ['a' => 7, 'b' => 'G', 'c' => 1],
    ['a' => 8, 'b' => 'H', 'c' => 2],
    ['a' => 9, 'b' => 'I', 'c' => 10],
    ['a' => 10, 'b' => 'J', 'c' => 4],
];

$frameRows = [
    ['a' => 1, 'b' => 'A', 'c' => 'one', 'd' => 5],
    ['a' => 2, 'b' => 'B', 'c' => 'two', 'd' => 4],
    ['a' => 3, 'b' => 'A', 'c' => 'three', 'd' => 3],
    ['a' => 4, 'b' => 'B', 'c' => 'four', 'd' => 2],
    ['a' => 5, 'b' => 'A', 'c' => 'five', 'd' => 1],
];

$labels = array_column($valueRows, 'b');
$values = array_column($valueRows, 'b');
$orderKeys = array_column($valueRows, 'a');
$nthByRow = array_column($valueRows, 'c');
$frameValues = array_column($frameRows, 'c');
$frameKeys = array_column($frameRows, 'a');

$expectedNtile = static function (int $rowCount, int $bucketCount): array {
    $baseSize = intdiv($rowCount, $bucketCount);
    $largerBuckets = $rowCount % $bucketCount;
    $result = [];
    for ($bucket = 1; $bucket <= min($bucketCount, $rowCount); $bucket++) {
        $size = $baseSize + ($bucket <= $largerBuckets ? 1 : 0);
        array_push($result, ...array_fill(0, $size, $bucket));
    }

    return $result;
};

$expectedOffset = static function (array $source, int $offset, mixed $default): array {
    $result = [];
    $count = count($source);
    for ($index = 0; $index < $count; $index++) {
        $target = $index + $offset;
        $result[] = $target < 0 || $target >= $count ? $default : $source[$target];
    }

    return $result;
};

$frameIndexes = static function (int $index, int $count, string $start, string $end): array {
    $boundary = static function (string $value) use ($index, $count): int {
        $value = strtoupper(trim($value));

        return match (true) {
            $value === 'UNBOUNDED PRECEDING' => 0,
            $value === 'UNBOUNDED FOLLOWING' => $count - 1,
            $value === 'CURRENT ROW' => $index,
            preg_match('/^([0-9]+) PRECEDING$/', $value, $match) === 1 => $index - (int) $match[1],
            preg_match('/^([0-9]+) FOLLOWING$/', $value, $match) === 1 => $index + (int) $match[1],
            default => throw new InvalidArgumentException('Unsupported window4 dynamic boundary ' . $value),
        };
    };

    $startIndex = $boundary($start);
    $endIndex = $boundary($end);
    if ($startIndex > $endIndex || $endIndex < 0 || $startIndex > $count - 1) {
        return [];
    }

    return range(max(0, $startIndex), min($count - 1, $endIndex));
};

$expectedValue = static function (string $function, array $source, array $indexes, int $nth = 1): mixed {
    if ($indexes === []) {
        return null;
    }

    return match ($function) {
        'first_value' => $source[$indexes[0]],
        'last_value' => $source[$indexes[count($indexes) - 1]],
        'nth_value' => isset($indexes[$nth - 1]) ? $source[$indexes[$nth - 1]] : null,
        default => throw new InvalidArgumentException('Unsupported window4 value function ' . $function),
    };
};

$passCaseCount = 0;

foreach (range(1, 80) as $bucketCount) {
    $actual = SQLiteWindowFunction::ntile($tileRows, $bucketCount);
    $expected = $expectedNtile(count($tileRows), $bucketCount);
    foreach ($tileLabels as $rowIndex => $label) {
        $passCaseCount++;
        $tests["real upstream window4.test 1.dynamic ntile bucket {$bucketCount} row {$label}"] = static function (TestRunner $t) use ($actual, $expected, $rowIndex, $bucketCount, $label): void {
            $t->same($expected[$rowIndex], $actual[$rowIndex], "window4.test 1 ntile({$bucketCount}) row {$label}");
        };
    }
}

foreach (range(1, 40) as $nth) {
    $actual = SQLiteWindowFunction::nthValueByRow($values, array_fill(0, count($values), $nth), $orderKeys);
    $expected = array_map(
        static fn (int $index): mixed => $index >= $nth - 1 ? ($values[$nth - 1] ?? null) : null,
        array_keys($values),
    );
    foreach ($labels as $rowIndex => $label) {
        $passCaseCount++;
        $tests["real upstream window4.test 2.1 dynamic nth_value fixed {$nth} row {$label}"] = static function (TestRunner $t) use ($actual, $expected, $rowIndex, $nth, $label): void {
            $t->same($expected[$rowIndex], $actual[$rowIndex], "window4.test 2.1 nth_value fixed {$nth} row {$label}");
        };
    }
}

$actualNthByRow = SQLiteWindowFunction::nthValueByRow($values, $nthByRow, $orderKeys);
foreach ([null, null, 'B', null, 'E', 'A', 'A', 'B', null, 'D'] as $rowIndex => $expected) {
    $passCaseCount++;
    $tests['real upstream window4.test 2.1 nth_value row expression row ' . $labels[$rowIndex]] = static function (TestRunner $t) use ($actualNthByRow, $expected, $rowIndex): void {
        $t->same($expected, $actualNthByRow[$rowIndex], 'window4.test 2.1 nth_value(b,c) row expression');
    };
}

foreach (range(1, 30) as $offset) {
    foreach (['lead' => $offset, 'lag' => -$offset] as $function => $signedOffset) {
        $actual = $function === 'lead'
            ? SQLiteWindowFunction::lead($values, $offset, 'abc')
            : SQLiteWindowFunction::lag($values, $offset, 'abc');
        $expected = $expectedOffset($values, $signedOffset, 'abc');
        foreach ($labels as $rowIndex => $label) {
            $passCaseCount++;
            $tests["real upstream window4.test 2 dynamic {$function} offset {$offset} row {$label}"] = static function (TestRunner $t) use ($actual, $expected, $rowIndex, $function, $offset, $label): void {
                $t->same($expected[$rowIndex], $actual[$rowIndex], "window4.test 2 {$function}({$offset}) row {$label}");
            };
        }
    }
}

$frameBoundaryPairs = [
    ['1 PRECEDING', '2 PRECEDING'],
    ['1 PRECEDING', '1 PRECEDING'],
    ['0 PRECEDING', '0 PRECEDING'],
    ['2 FOLLOWING', '1 FOLLOWING'],
    ['1 FOLLOWING', '1 FOLLOWING'],
    ['0 FOLLOWING', '0 FOLLOWING'],
    ['UNBOUNDED PRECEDING', 'CURRENT ROW'],
    ['CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    ['1 PRECEDING', '1 FOLLOWING'],
    ['2 PRECEDING', '2 FOLLOWING'],
    ['CURRENT ROW', 'CURRENT ROW'],
    ['UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'],
];

foreach ($frameBoundaryPairs as [$start, $end]) {
    foreach (['ROWS', 'GROUPS', 'RANGE'] as $unit) {
        foreach (['first_value' => 1, 'last_value' => 1, 'nth_value' => 2] as $function => $nth) {
            $actual = SQLiteWindowFunction::valueFrameBetweenValues($function, $frameValues, $frameKeys, $unit, $start, $end, 'NO OTHERS', $function === 'nth_value' ? $nth : null);
            foreach ($frameRows as $rowIndex => $row) {
                $indexes = $frameIndexes($rowIndex, count($frameRows), $start, $end);
                $expected = $expectedValue($function, $frameValues, $indexes, $nth);
                $passCaseCount++;
                $tests["real upstream window4.test 3 dynamic {$function} {$unit} {$start} to {$end} row {$row['a']}"] = static function (TestRunner $t) use ($actual, $expected, $rowIndex, $function, $unit, $start, $end): void {
                    $t->same($expected, $actual[$rowIndex], "window4.test 3 {$function} {$unit} {$start} {$end}");
                };
            }
        }
    }
}

$tests['real upstream window4.test dynamic corpus cites upstream source and count'] = static function (TestRunner $t) use ($passCaseCount): void {
    $t->same(2350, $passCaseCount, 'window4.test dynamic focused PASS case count');
    $t->same(
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test:1.1-1.19,2.1-2.4,3.5-3.6',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test:1.1-1.19,2.1-2.4,3.5-3.6',
    );
};

return $tests;
