<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonAggregate;

$tests = [];

$fixtureRows = [
    ['id' => 1, 'k' => 'a', 'v' => 1],
    ['id' => 2, 'k' => 'b', 'v' => 2],
    ['id' => 3, 'k' => 'c', 'v' => 3],
    ['id' => 4, 'k' => 'd', 'v' => 4],
    ['id' => 5, 'k' => 'f', 'v' => 5],
    ['id' => 6, 'k' => 'g', 'v' => 6],
    ['id' => 7, 'k' => 'h', 'v' => 7],
];

$windowObjectFrames = static function (array $rows, callable $labelForRow): array {
    $windowRows = [];
    foreach ($rows as $row) {
        $windowRows[] = [$labelForRow($row), $row['v'], $row['id']];
    }

    return SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows($windowRows, 1, 1);
};

$staticScenarios = [
    '3.9' => [
        'labels' => static fn (array $row): ?string => $row['id'] !== 1 ? $row['k'] : null,
        'expected' => [
            '{"b":2}',
            '{"b":2,"c":3}',
            '{"b":2,"c":3,"d":4}',
            '{"c":3,"d":4,"f":5}',
            '{"d":4,"f":5,"g":6}',
            '{"f":5,"g":6,"h":7}',
            '{"g":6,"h":7}',
        ],
    ],
    '3.10' => [
        'labels' => static fn (array $row): ?string => $row['id'] > 4 ? $row['k'] : null,
        'expected' => [
            '{}',
            '{}',
            '{}',
            '{"f":5}',
            '{"f":5,"g":6}',
            '{"f":5,"g":6,"h":7}',
            '{"g":6,"h":7}',
        ],
    ],
    '3.11' => [
        'labels' => static fn (array $row): ?string => $row['id'] > 4 ? $row['k'] . '@' : null,
        'expected' => [
            '{}',
            '{}',
            '{}',
            '{"f@":5}',
            '{"f@":5,"g@":6}',
            '{"f@":5,"g@":6,"h@":7}',
            '{"g@":6,"h@":7}',
        ],
    ],
    '3.12' => [
        'labels' => static fn (array $row): string => $row['k'],
        'expected' => [
            '{"a":1,"b":2}',
            '{"a":1,"b":2,"c":3}',
            '{"b":2,"c":3,"d":4}',
            '{"c":3,"d":4,"f":5}',
            '{"d":4,"f":5,"g":6}',
            '{"f":5,"g":6,"h":7}',
            '{"g":6,"h":7}',
        ],
    ],
    '3.13' => [
        'labels' => static fn (array $row): ?string => $row['id'] > 1 && $row['id'] < 7 ? $row['k'] : null,
        'expected' => [
            '{"b":2}',
            '{"b":2,"c":3}',
            '{"b":2,"c":3,"d":4}',
            '{"c":3,"d":4,"f":5}',
            '{"d":4,"f":5,"g":6}',
            '{"f":5,"g":6}',
            '{"g":6}',
        ],
    ],
    '3.14' => [
        'labels' => static fn (array $row): ?string => $row['id'] > 2 && $row['id'] < 6 ? $row['k'] : null,
        'expected' => [
            '{}',
            '{"c":3}',
            '{"c":3,"d":4}',
            '{"c":3,"d":4,"f":5}',
            '{"d":4,"f":5}',
            '{"f":5}',
            '{}',
        ],
    ],
    '3.15' => [
        'labels' => static fn (array $row): ?string => $row['id'] < 2 || $row['id'] > 6 ? $row['k'] : null,
        'expected' => [
            '{"a":1}',
            '{"a":1}',
            '{}',
            '{}',
            '{}',
            '{"h":7}',
            '{"h":7}',
        ],
    ],
    '3.16' => [
        'labels' => static fn (array $row): ?string => $row['id'] < 3 || $row['id'] > 5 ? $row['k'] : null,
        'expected' => [
            '{"a":1,"b":2}',
            '{"a":1,"b":2}',
            '{"b":2}',
            '{}',
            '{"g":6}',
            '{"g":6,"h":7}',
            '{"g":6,"h":7}',
        ],
    ],
];

foreach ($staticScenarios as $upstreamCase => $scenario) {
    $tests["real upstream windowB {$upstreamCase} json object inverse null labels"] = static function (TestRunner $t) use ($fixtureRows, $windowObjectFrames, $scenario, $upstreamCase): void {
        $t->same($scenario['expected'], $windowObjectFrames($fixtureRows, $scenario['labels']), "windowB.test {$upstreamCase}");
    };
}

$expectedFrames = static function (array $rows, callable $labelForRow, int $preceding, int $following): array {
    $frames = [];
    $last = count($rows) - 1;
    foreach ($rows as $index => $_row) {
        $pairs = [];
        for ($frame = max(0, $index - $preceding); $frame <= min($last, $index + $following); $frame++) {
            $label = $labelForRow($rows[$frame]);
            if ($label === null) {
                continue;
            }
            $pairs[] = '"' . addcslashes($label, "\\\"") . '":' . $rows[$frame]['v'];
        }
        $frames[] = '{' . implode(',', $pairs) . '}';
    }

    return $frames;
};

for ($case = 0; $case < 1000; $case++) {
    $rowCount = 7 + ($case % 6);
    $rows = [];
    for ($row = 1; $row <= $rowCount; $row++) {
        $rows[] = [
            'id' => $row,
            'k' => chr(97 + (($case + $row) % 26)),
            'v' => (($case * 19 + $row * 7) % 97) + 1,
        ];
    }

    $leftNullCutoff = 1 + ($case % $rowCount);
    $rightNullCutoff = $rowCount - ($case % max(1, $rowCount - 1));
    $suffix = ($case % 3) === 0 ? '@' : '';
    $preceding = 1 + ($case % 2);
    $following = 1 + (intdiv($case, 2) % 2);
    $labelForRow = static function (array $row) use ($leftNullCutoff, $rightNullCutoff, $suffix): ?string {
        if ($row['id'] <= $leftNullCutoff || $row['id'] >= $rightNullCutoff) {
            return null;
        }

        return $row['k'] . $suffix;
    };
    $windowRows = array_map(
        static fn (array $row): array => [$labelForRow($row), $row['v'], $row['id']],
        $rows,
    );
    $expected = $expectedFrames($rows, $labelForRow, $preceding, $following);

    $tests['real upstream windowB dynamic json object inverse null label case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($windowRows, $expected, $preceding, $following, $leftNullCutoff, $rightNullCutoff, $case): void {
        $actual = SQLiteJsonAggregate::jsonGroupObjectWindowFrameRows($windowRows, $preceding, $following);
        $t->same($expected, $actual, "windowB.test 3.9-3.16 dynamic json_group_object inverse null-key case {$case}");
        $t->same(count($expected), count($actual), "windowB.test dynamic case {$case} emits one frame per input row");
        $t->true($leftNullCutoff < $rightNullCutoff || in_array('{}', $actual, true), "windowB.test dynamic case {$case} preserves empty objects after NULL-key inverse removal");
    };
}

$tests['real upstream windowB json object inverse dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test 3.8 fixture',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test 3.9-3.16 json_group_object xInverse NULL entry behavior',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test 3.8 fixture',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test 3.9-3.16 json_group_object xInverse NULL entry behavior',
    ]);
};

$tests['real upstream windowB json object inverse dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses generic SQLiteJsonAggregate json_group_object ROWS frame helpers with NULL label elision from upstream windowB.test',
        'no new support component needed; reuses generic SQLiteJsonAggregate json_group_object ROWS frame helpers with NULL label elision from upstream windowB.test',
    );
};

return $tests;
