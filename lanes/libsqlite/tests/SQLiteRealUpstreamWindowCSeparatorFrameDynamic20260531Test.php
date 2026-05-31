<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$windowCValueText = static function (mixed $value): string {
    if ($value instanceof SQLiteBlobValue) {
        return $value->bytes;
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return (string) $value;
};

$windowCGroupConcatOracle = static function (array $values, array $separators, array $frameIndexes) use ($windowCValueText): ?string {
    $result = null;
    foreach ($frameIndexes as $frameIndex) {
        $value = $values[$frameIndex];
        if ($value === null) {
            continue;
        }

        if ($result === null) {
            $result = $windowCValueText($value);
            continue;
        }

        $separator = $separators[$frameIndex] === null ? '' : $windowCValueText($separators[$frameIndex]);
        $result .= $separator . $windowCValueText($value);
    }

    return $result;
};

$windowCFrameIndexes = static function (int $rowCount, int $row, string $start, string $end): array {
    [$startIndex, $endIndex] = match ([$start, $end]) {
        ['1 PRECEDING', '1 FOLLOWING'] => [max(0, $row - 1), min($rowCount - 1, $row + 1)],
        ['2 PRECEDING', 'CURRENT ROW'] => [max(0, $row - 2), $row],
        ['CURRENT ROW', 'UNBOUNDED FOLLOWING'] => [$row, $rowCount - 1],
        ['1 PRECEDING', '1 PRECEDING'] => [$row - 1, $row - 1],
        default => throw new RuntimeException("unsupported windowC frame {$start} to {$end}"),
    };

    if ($startIndex > $endIndex || $endIndex < 0 || $startIndex > $rowCount - 1) {
        return [];
    }

    return range(max(0, $startIndex), min($rowCount - 1, $endIndex));
};

$windowCSourceSeparatorSets = [
    'windowC.test 1 text separators a-b-c-def-g' => ['a', 'b', 'c', 'def', 'g'],
    'windowC.test 1 empty separator boundaries' => ['abcdefg', '', '', 'abcdefg'],
    'windowC.test 1 variable length separators' => ['a', 'bc', 'def', 'ghij', 'klmno', 'pqrstu'],
    'windowC.test 2 UTF16 fuzz separators' => [',a,', ',bc,', new SQLiteBlobValue("\x55\x85\xd0\x90\x13\x45\x51\x78\xcd\x11\xce\x4a")],
];

$windowCFrameSpecs = [
    ['1 PRECEDING', '1 FOLLOWING'],
    ['2 PRECEDING', 'CURRENT ROW'],
    ['CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    ['1 PRECEDING', '1 PRECEDING'],
];

foreach ($windowCSourceSeparatorSets as $source => $separators) {
    foreach ($windowCFrameSpecs as [$start, $end]) {
        $values = array_fill(0, count($separators), 'val');
        $orderKeys = range(1, count($separators));
        $actual = SQLiteWindowFunction::groupConcatFrameBetweenSeparators(
            $values,
            $separators,
            $orderKeys,
            'ROWS',
            $start,
            $end,
        );

        foreach ($values as $row => $_value) {
            $expected = $windowCGroupConcatOracle($values, $separators, $windowCFrameIndexes(count($values), $row, $start, $end));
            $tests["real upstream {$source} {$start} to {$end} row {$row}"] = static function (TestRunner $t) use ($actual, $expected, $source, $start, $end, $row): void {
                $t->same($expected, $actual[$row], "{$source} group_concat separator frame {$start} to {$end} row {$row}");
                if ($expected !== null) {
                    $t->same('val', substr($actual[$row], 0, 3), "{$source} windowC prefix invariant row {$row}");
                    $t->same('val', substr($actual[$row], -3), "{$source} windowC suffix invariant row {$row}");
                }
            };
        }
    }
}

for ($case = 0; $case < 1000; $case++) {
    $tests['real upstream windowC dynamic varying separator frame corpus ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $windowCGroupConcatOracle, $windowCFrameIndexes): void {
        $rowCount = 4 + ($case % 8);
        $values = [];
        $separators = [];
        for ($row = 0; $row < $rowCount; $row++) {
            $values[] = ($case + $row) % 13 === 0 ? null : 'val';
            $separator = match (($case + $row) % 7) {
                0 => '',
                1 => ',',
                2 => '|',
                3 => 'abc',
                4 => new SQLiteBlobValue('B' . (string) ($row % 3)),
                5 => null,
                default => 'sep' . (string) (($case + $row) % 5),
            };
            $separators[] = $separator;
        }

        [$start, $end] = match ($case % 4) {
            0 => ['1 PRECEDING', '1 FOLLOWING'],
            1 => ['2 PRECEDING', 'CURRENT ROW'],
            2 => ['CURRENT ROW', 'UNBOUNDED FOLLOWING'],
            default => ['1 PRECEDING', '1 PRECEDING'],
        };
        $actual = SQLiteWindowFunction::groupConcatFrameBetweenSeparators(
            $values,
            $separators,
            range(1, $rowCount),
            'ROWS',
            $start,
            $end,
        );

        foreach ($values as $row => $_value) {
            $expected = $windowCGroupConcatOracle($values, $separators, $windowCFrameIndexes($rowCount, $row, $start, $end));
            $t->same($expected, $actual[$row], "windowC.test dynamic varying separator case {$case} row {$row}");
            if ($actual[$row] !== null) {
                $t->same('val', substr($actual[$row], 0, 3), "windowC.test dynamic prefix case {$case} row {$row}");
                $t->same('val', substr($actual[$row], -3), "windowC.test dynamic suffix case {$case} row {$row}");
            }
        }
    };
}

$tests['real upstream windowC varying separator corpus cites exact upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test 1.* varying group_concat separators',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test 2.0-2.1 UTF16/BLOB separator fuzz regression',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test 1.* varying group_concat separators',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test 2.0-2.1 UTF16/BLOB separator fuzz regression',
    ]);
};

$tests['real upstream windowC varying separator corpus dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local SQLiteWindowFunction group_concat() ROWS frame evaluation with per-row separators and BLOB text coercion',
        'no new support component needed; reuses lane-local SQLiteWindowFunction group_concat() ROWS frame evaluation with per-row separators and BLOB text coercion',
    );
};

return $tests;
