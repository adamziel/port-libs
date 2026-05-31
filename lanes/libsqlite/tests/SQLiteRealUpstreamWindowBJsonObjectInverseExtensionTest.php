<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonAggregate;

$tests = [];

$objectRows = [
    ['a', 1, 1],
    ['b', 2, 2],
    ['c', 3, 3],
    ['d', 4, 4],
    ['f', 5, 5],
    ['g', 6, 6],
    ['h', 7, 7],
];

$jsonObjectWindowOracle = static function (array $rows, callable $labelForRow): array {
    $result = [];
    $count = count($rows);
    for ($index = 0; $index < $count; $index++) {
        $object = [];
        for ($frame = max(0, $index - 1); $frame <= min($count - 1, $index + 1); $frame++) {
            $label = $labelForRow($rows[$frame]);
            if ($label !== null) {
                $object[(string) $label] = $rows[$frame][1];
            }
        }
        $result[] = json_encode((object) $object, JSON_UNESCAPED_SLASHES);
    }

    return $result;
};

$buildWindowRows = static function (array $rows, callable $labelForRow): array {
    $windowRows = [];
    foreach ($rows as $row) {
        $windowRows[] = [$labelForRow($row), $row[1], $row[2]];
    }

    return $windowRows;
};

$upstreamCases = [
    '3.11 labels greater than four with suffix' => [
        static fn (array $row): ?string => $row[2] > 4 ? $row[0] . '@' : null,
        ['{}', '{}', '{}', '{"f@":5}', '{"f@":5,"g@":6}', '{"f@":5,"g@":6,"h@":7}', '{"g@":6,"h@":7}'],
    ],
    '3.12 all labels remain visible' => [
        static fn (array $row): ?string => $row[0],
        ['{"a":1,"b":2}', '{"a":1,"b":2,"c":3}', '{"b":2,"c":3,"d":4}', '{"c":3,"d":4,"f":5}', '{"d":4,"f":5,"g":6}', '{"f":5,"g":6,"h":7}', '{"g":6,"h":7}'],
    ],
    '3.13 interior labels omit first and last frame edges' => [
        static fn (array $row): ?string => ($row[2] > 1 && $row[2] < 7) ? $row[0] : null,
        ['{"b":2}', '{"b":2,"c":3}', '{"b":2,"c":3,"d":4}', '{"c":3,"d":4,"f":5}', '{"d":4,"f":5,"g":6}', '{"f":5,"g":6}', '{"g":6}'],
    ],
    '3.15 only outer labels remain visible' => [
        static fn (array $row): ?string => ($row[2] < 2 || $row[2] > 6) ? $row[0] : null,
        ['{"a":1}', '{"a":1}', '{}', '{}', '{}', '{"h":7}', '{"h":7}'],
    ],
];

foreach ($upstreamCases as $name => [$labelForRow, $expected]) {
    $tests['real upstream windowB.test ' . $name . ' full vector'] = static function (TestRunner $t) use ($objectRows, $buildWindowRows, $jsonObjectWindowOracle, $labelForRow, $expected, $name): void {
        $windowRows = $buildWindowRows($objectRows, $labelForRow);
        $actual = SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit($windowRows, 'ROWS', 1, 1);
        $t->same($expected, $jsonObjectWindowOracle($objectRows, $labelForRow), 'windowB.test ' . $name . ' independent oracle');
        $t->same($expected, $actual, 'windowB.test ' . $name . ' native output');
    };

    foreach ($expected as $rowIndex => $json) {
        $tests['real upstream windowB.test ' . $name . ' row ' . ($rowIndex + 1)] = static function (TestRunner $t) use ($objectRows, $buildWindowRows, $labelForRow, $rowIndex, $json, $name): void {
            $actual = SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit($buildWindowRows($objectRows, $labelForRow), 'ROWS', 1, 1);
            $t->same($json, $actual[$rowIndex], 'windowB.test ' . $name . ' row ' . ($rowIndex + 1));
        };
    }
}

for ($case = 0; $case < 1200; $case++) {
    $labelMode = $case % 6;
    $suffix = match (intdiv($case, 6) % 4) {
        0 => '',
        1 => '@',
        2 => '#',
        default => '_x',
    };
    $offset = intdiv($case, 24) % 5;
    $rows = array_map(
        static fn (array $row): array => [$row[0], $row[1] + $offset, $row[2]],
        $objectRows,
    );

    $labelForRow = static function (array $row) use ($labelMode, $suffix): ?string {
        $id = $row[2];
        $base = $row[0] . $suffix;

        return match ($labelMode) {
            0 => $id > 4 ? $base : null,
            1 => $base,
            2 => ($id > 1 && $id < 7) ? $base : null,
            3 => ($id < 2 || $id > 6) ? $base : null,
            4 => ($id > 2 && $id < 6) ? $base : null,
            default => $id !== 1 ? $base : null,
        };
    };

    $testName = 'real upstream windowB.test dynamic json object inverse key filter case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $tests[$testName] = static function (TestRunner $t) use ($case, $rows, $buildWindowRows, $jsonObjectWindowOracle, $labelForRow): void {
        $expected = $jsonObjectWindowOracle($rows, $labelForRow);
        $actual = SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit($buildWindowRows($rows, $labelForRow), 'ROWS', 1, 1);
        foreach ($expected as $rowIndex => $json) {
            $t->same($json, $actual[$rowIndex], "windowB.test 3.11-3.15 dynamic case {$case} row {$rowIndex}");
        }
    };
}

$tests['real upstream windowB json object inverse extension cites exact upstream source'] = static function (TestRunner $t): void {
    $t->same(
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test:3.11',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test:3.12',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test:3.13',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test:3.15',
        ],
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test:3.11',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test:3.12',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test:3.13',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test:3.15',
        ],
    );
};

return $tests;
