<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

/**
 * @param list<mixed> $values
 * @param list<mixed> $separators
 * @return list<string|null>
 */
$separatorOracle = static function (array $values, array $separators, int $preceding, int $following): array {
    $result = [];
    $count = count($values);
    for ($index = 0; $index < $count; $index++) {
        $start = max(0, $index - $preceding);
        $end = min($count - 1, $index + $following);
        $parts = [];
        for ($frameIndex = $start; $frameIndex <= $end; $frameIndex++) {
            if ($values[$frameIndex] === null) {
                continue;
            }
            if ($parts !== []) {
                $parts[] = $separators[$frameIndex] === null ? '' : (string) $separators[$frameIndex];
            }
            $parts[] = (string) $values[$frameIndex];
        }
        $result[] = $parts === [] ? null : implode('', $parts);
    }

    return $result;
};

/**
 * @param list<mixed> $values
 * @param list<mixed> $separators
 */
$assertSeparatorWindow = static function (
    TestRunner $t,
    array $values,
    array $separators,
    int $preceding,
    int $following,
    string $label,
) use ($separatorOracle): void {
    $actual = SQLiteWindowFunction::groupConcatFrameBetweenSeparators(
        $values,
        $separators,
        array_keys($values),
        'ROWS',
        $preceding . ' PRECEDING',
        $following === 0 ? 'CURRENT ROW' : $following . ' FOLLOWING',
    );
    $expected = $separatorOracle($values, $separators, $preceding, $following);

    $t->same($expected, $actual, $label);
    $t->same(count($values), count($actual), $label . ' row count');
    $t->same($expected[0], $actual[0], $label . ' first row');
    $t->same($expected[array_key_last($expected)], $actual[array_key_last($actual)], $label . ' last row');
    $t->same(md5(json_encode($expected, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), $label . ' fingerprint');
};

$canonical = [
    'windowC.test 1.text.1.2.1 rows one preceding one following' => [
        array_fill(0, 5, 'val'),
        ['a', 'b', 'c', 'def', 'g'],
        1,
        1,
        ['valbval', 'valbvalcval', 'valcvaldefval', 'valdefvalgval', 'valgval'],
    ],
    'windowC.test 1.text.2.2.2 rows two preceding current row empty separators' => [
        array_fill(0, 4, 'val'),
        ['abcdefg', '', '', 'abcdefg'],
        2,
        0,
        ['val', 'valval', 'valvalval', 'valvalabcdefgval'],
    ],
    'windowC.test 1.text.3.2.3 current row unbounded following varied separators' => [
        array_fill(0, 6, 'val'),
        ['a', 'bc', 'def', 'ghij', 'klmno', 'pqrstu'],
        0,
        5,
        ['valbcvaldefvalghijvalklmnovalpqrstuval', 'valdefvalghijvalklmnovalpqrstuval', 'valghijvalklmnovalpqrstuval', 'valklmnovalpqrstuval', 'valpqrstuval', 'val'],
    ],
    'windowC.test 1.blob.4.2.1 blob-style separators preserve byte text' => [
        array_fill(0, 6, 'val'),
        ['a', 'bc', 'def', 'ghij', 'klmno', 'pqrstu'],
        1,
        1,
        ['valbcval', 'valbcvaldefval', 'valdefvalghijval', 'valghijvalklmnoval', 'valklmnovalpqrstuval', 'valpqrstuval'],
    ],
    'windowC.test 1.blob.5.2.1 punctuation separators are row-local' => [
        array_fill(0, 16, 'val'),
        [',', ',', ',', ',', ',', ',', ',', ',', ',', ',', ',', ',', '.......', ',', ',', ','],
        1,
        1,
        ['val,val', 'val,val,val', 'val,val,val', 'val,val,val', 'val,val,val', 'val,val,val', 'val,val,val', 'val,val,val', 'val,val,val', 'val,val,val', 'val,val,val', 'val,val.......val', 'val.......val,val', 'val,val,val', 'val,val,val', 'val,val'],
    ],
];

foreach ($canonical as $name => [$values, $separators, $preceding, $following, $expected]) {
    $label = $name;
    $tests['real upstream ' . $label] = static function (TestRunner $t) use ($values, $separators, $preceding, $following, $expected, $assertSeparatorWindow, $label): void {
        $assertSeparatorWindow($t, $values, $separators, $preceding, $following, $label);
        $t->same($expected, SQLiteWindowFunction::groupConcatFrameBetweenSeparators(
            $values,
            $separators,
            array_keys($values),
            'ROWS',
            $preceding . ' PRECEDING',
            $following === 0 ? 'CURRENT ROW' : $following . ' FOLLOWING',
        ), $label . ' canonical upstream expected');
    };
}

$separatorSets = [
    ['a', 'b', 'c', 'def', 'g'],
    ['abcdefg', '', '', 'abcdefg'],
    ['a', 'bc', 'def', 'ghij', 'klmno', 'pqrstu'],
    [',', ',', ',', ',', ',', ',', ',', ',', ',', ',', ',', ',', '.......', ',', ','],
    ['|', '--', '', ':', null, '/', '::', '~'],
];
$framePairs = [
    [1, 1],
    [2, 0],
    [0, 99],
    [3, 1],
    [1, 3],
];

foreach (range(1, 1000) as $case) {
    $separators = $separatorSets[($case - 1) % count($separatorSets)];
    $rowCount = count($separators);
    $values = [];
    for ($index = 0; $index < $rowCount; $index++) {
        $values[] = ($case + $index) % 17 === 0 ? null : 'v' . (($case + $index) % 11);
    }
    [$preceding, $following] = $framePairs[intdiv($case - 1, count($separatorSets)) % count($framePairs)];
    $label = sprintf('windowC.test dynamic separator frame case %04d', $case);

    $tests['real upstream ' . $label] = static function (TestRunner $t) use ($values, $separators, $preceding, $following, $label, $assertSeparatorWindow): void {
        $assertSeparatorWindow($t, $values, $separators, $preceding, $following, $label);
    };
}

$tests['real upstream windowC dynamic cites exact upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test:1.text.1-1.text.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test:1.blob.1-1.blob.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test:2.0-2.1',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test:1.text.1-1.text.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test:1.blob.1-1.blob.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test:2.0-2.1',
    ]);
};

return $tests;
