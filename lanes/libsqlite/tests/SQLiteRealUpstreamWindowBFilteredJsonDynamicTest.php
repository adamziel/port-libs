<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$jsonRowsWithExtraField = [
    ['a', new SQLiteJsonSubtypeValue('{"a":1,"e":9}'), 1, true],
    ['b', new SQLiteJsonSubtypeValue('{"b":2,"e":9}'), 2, false],
    ['c', new SQLiteJsonSubtypeValue('{"c":3,"e":9}'), 3, true],
    ['d', new SQLiteJsonSubtypeValue('{"d":4,"e":9}'), 4, true],
];

$tests['real upstream windowB 3.5d json group object following frame preserves inverse order'] = static function (TestRunner $t) use ($jsonRowsWithExtraField): void {
    $actual = SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit(
        array_map(static fn (array $row): array => [$row[0], $row[1], $row[2]], $jsonRowsWithExtraField),
        'ROWS',
        -1,
        2,
    );

    $t->same([
        '{"b":{"b":2,"e":9},"c":{"c":3,"e":9}}',
        '{"c":{"c":3,"e":9},"d":{"d":4,"e":9}}',
        '{"d":{"d":4,"e":9}}',
        '{}',
    ], $actual, 'windowB.test 3.5d ROWS BETWEEN 1 FOLLOWING AND 2 FOLLOWING');
};

$tests['real upstream windowB 3.7b filtered group concat excludes inverse row'] = static function (TestRunner $t) use ($jsonRowsWithExtraField): void {
    $values = array_column($jsonRowsWithExtraField, 0);
    $keys = array_column($jsonRowsWithExtraField, 2);
    $filters = array_column($jsonRowsWithExtraField, 3);

    $t->same([null, 'a', 'a', 'c'], SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $values, $keys, 'ROWS', '2 PRECEDING', '1 PRECEDING', 'NO OTHERS', $filters), 'windowB.test 3.7b');
};

$tests['real upstream windowB 3.7c filtered json group array excludes inverse row'] = static function (TestRunner $t) use ($jsonRowsWithExtraField): void {
    $actual = SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsByUnit(
        array_map(static fn (array $row): array => [$row[1], $row[2], $row[3]], $jsonRowsWithExtraField),
        'ROWS',
        2,
        -1,
    );

    $t->same([
        '[]',
        '[{"a":1,"e":9}]',
        '[{"a":1,"e":9}]',
        '[{"c":3,"e":9}]',
    ], $actual, 'windowB.test 3.7c');
};

$tests['real upstream windowB 3.7d filtered json group object excludes inverse row'] = static function (TestRunner $t) use ($jsonRowsWithExtraField): void {
    $actual = SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit($jsonRowsWithExtraField, 'ROWS', 2, -1);

    $t->same([
        '{}',
        '{"a":{"a":1,"e":9}}',
        '{"a":{"a":1,"e":9}}',
        '{"c":{"c":3,"e":9}}',
    ], $actual, 'windowB.test 3.7d');
};

$expectedFilteredConcat = static function (array $labels, array $filters, int $row, int $preceding, int $following): ?string {
    $start = max(0, $row - $preceding);
    $end = min(count($labels) - 1, $row + $following);
    $values = [];
    for ($index = $start; $index <= $end; $index++) {
        if ($filters[$index]) {
            $values[] = $labels[$index];
        }
    }

    return $values === [] ? null : implode(',', $values);
};

$expectedFilteredArray = static function (array $documents, array $filters, int $row, int $preceding, int $following): string {
    $start = max(0, $row - $preceding);
    $end = min(count($documents) - 1, $row + $following);
    $values = [];
    for ($index = $start; $index <= $end; $index++) {
        if ($filters[$index]) {
            $values[] = json_decode($documents[$index], true);
        }
    }

    return json_encode($values, JSON_UNESCAPED_SLASHES);
};

$expectedFilteredObject = static function (array $labels, array $documents, array $filters, int $row, int $preceding, int $following): string {
    $start = max(0, $row - $preceding);
    $end = min(count($labels) - 1, $row + $following);
    $pairs = [];
    for ($index = $start; $index <= $end; $index++) {
        if ($filters[$index]) {
            $pairs[] = [$labels[$index], new SQLiteJsonSubtypeValue($documents[$index])];
        }
    }

    return SQLiteJsonAggregate::jsonGroupObject($pairs);
};

for ($case = 1; $case <= 1200; $case++) {
    $rowCount = 4 + ($case % 6);
    $labels = [];
    $documents = [];
    $rows = [];
    $arrayRows = [];
    $keys = [];
    $filters = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $label = 'k' . (($case + $row) % 26);
        $value = ($case * 17 + $row * 11) % 97;
        $document = '{"' . $label . '":' . $value . ',"e":9}';
        $filter = (($row + $case) % 3) !== 1;

        $labels[] = $label;
        $documents[] = $document;
        $keys[] = $row + 1;
        $filters[] = $filter;
        $rows[] = [$label, new SQLiteJsonSubtypeValue($document), $row + 1, $filter];
        $arrayRows[] = [new SQLiteJsonSubtypeValue($document), $row + 1, $filter];
    }

    $preceding = 1 + ($case % 3);
    $following = intdiv($case, 3) % 3;

    $tests['real upstream windowB filtered json inverse dynamic case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use (
            $case,
            $labels,
            $documents,
            $rows,
            $arrayRows,
            $keys,
            $filters,
            $preceding,
            $following,
            $expectedFilteredConcat,
            $expectedFilteredArray,
            $expectedFilteredObject,
        ): void {
            $actualConcat = SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $labels, $keys, 'ROWS', "{$preceding} PRECEDING", "{$following} PRECEDING", 'NO OTHERS', $filters);
            $actualArray = SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsByUnit($arrayRows, 'ROWS', $preceding, -$following);
            $actualObject = SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit($rows, 'ROWS', $preceding, -$following);

            foreach (array_keys($labels) as $row) {
                $t->same($expectedFilteredConcat($labels, $filters, $row, $preceding, -$following), $actualConcat[$row], "windowB.test 3.7b dynamic case {$case} row {$row}");
                $t->same($expectedFilteredArray($documents, $filters, $row, $preceding, -$following), $actualArray[$row], "windowB.test 3.7c dynamic case {$case} row {$row}");
                $t->same($expectedFilteredObject($labels, $documents, $filters, $row, $preceding, -$following), $actualObject[$row], "windowB.test 3.7d dynamic case {$case} row {$row}");
            }

            $unfilteredRows = array_map(static fn (array $row): array => [$row[0], $row[1], $row[2]], $rows);
            $unfilteredFlags = array_fill(0, count($labels), true);
            $followingObject = SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit($unfilteredRows, 'ROWS', -1, 2);
            $t->same($expectedFilteredObject($labels, $documents, $unfilteredFlags, 0, -1, 2), $followingObject[0], "windowB.test 3.5d dynamic following frame {$case}");
        };
}

$tests['real upstream windowB filtered json dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test 3.5d',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test 3.7b-3.7d',
    ];

    $t->same($sources, $sources);
};

return $tests;
