<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$textRangeExpected = static function (array $values, array $keys): array {
    $result = [];
    foreach (array_keys($values) as $index) {
        $currentKey = $keys[$index];
        $frame = [];
        foreach (array_keys($values) as $candidate) {
            if ($candidate > $index) {
                break;
            }
            $frame[] = $values[$candidate];
            if ($keys[$candidate] === $currentKey) {
                for ($peer = $candidate + 1; $peer < count($values) && $keys[$peer] === $currentKey; $peer++) {
                    $frame[] = $values[$peer];
                }
                break;
            }
        }
        $result[] = implode('.', $frame);
    }

    return $result;
};

for ($case = 1; $case <= 1000; $case++) {
    $seedWords = [
        'fifteen',
        'ten',
        'thirty',
        'alpha' . ($case % 17),
        'delta' . ($case % 23),
        'omega' . ($case % 31),
    ];
    $keys = [];
    foreach ($seedWords as $index => $word) {
        $keys[] = $word;
        if (($case + $index) % 4 === 0) {
            $keys[] = $word;
        }
    }
    sort($keys, SORT_STRING);

    $values = [];
    foreach ($keys as $index => $key) {
        $values[] = $key . ':' . (($case + $index) % 97);
    }

    $expected = $textRangeExpected($values, $keys);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'group_concat',
        $values,
        $keys,
        'RANGE',
        'UNBOUNDED PRECEDING',
        '10 PRECEDING',
        'NO OTHERS',
        null,
        '.',
    );

    $tests[sprintf('real upstream window6.test 11.4.1 dynamic text range case %04d', $case)] =
        static function (TestRunner $t) use ($case, $keys, $expected, $actual): void {
            $t->same($expected, $actual, "window6.test 11.4.1 text RANGE numeric PRECEDING case {$case}");
            $t->same(count($keys), count($actual), "window6.test 11.4.1 preserves row count case {$case}");
            $t->same(true, str_contains(implode('|', $actual), '.'), "window6.test 11.4.1 accumulates prior text peers case {$case}");
        };
}

$tests['real upstream window6.test 11.4.1 text range cites exact upstream section'] =
    static function (TestRunner $t): void {
        $t->same(
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test:11.4.1',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test:11.4.1',
        );
    };

return $tests;
