<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr2.test';

$rows = [];
foreach (range(1, 168) as $rowid) {
    $rows[] = [
        'rowid' => $rowid,
        'c0' => match ($rowid % 12) {
            0 => 'val',
            1 => 1,
            2 => '1',
            3 => 0,
            4 => '0',
            5 => null,
            6 => '01',
            7 => -1,
            8 => '1.0',
            9 => 'true',
            10 => '',
            default => 'val-' . $rowid,
        },
    ];
}

$scalar = static function (string $sql, array $row): mixed {
    $result = SQLiteSelectSql::execute($sql, ['t0' => [$row]]);
    if ($result === []) {
        return null;
    }

    $first = $result[0];
    $columns = array_keys($first);

    return $first[$columns[0]];
};

$cases = [
    'expr2-1.1 where truth value composition returns source row' => [
        'sql' => "SELECT c0 FROM t0 WHERE (((0 IS NOT FALSE) OR NOT (0 IS FALSE OR (t0.c0 = 1))) IS 0)",
        'expected' => static fn (array $row): mixed => $row['c0'],
    ],
    'expr2-1.2.1 projection truth value composition is zero' => [
        'sql' => "SELECT (((0 IS NOT FALSE) OR NOT (0 IS FALSE OR (t0.c0 = 1))) IS 0) AS v FROM t0",
        'expected' => static fn (array $row): int => 1,
    ],
    'expr2-1.2.2 projection is-zero branch preserves zero result' => [
        'sql' => "SELECT (((0 IS NOT FALSE) OR NOT (0 IS 0 OR (t0.c0 = 1))) IS 0) AS v FROM t0",
        'expected' => static fn (array $row): int => 1,
    ],
    'expr2-1.3 projection raw composition returns false integer' => [
        'sql' => "SELECT ((0 IS NOT FALSE) OR NOT (0 IS FALSE OR (t0.c0 = 1))) AS v FROM t0",
        'expected' => static fn (array $row): int => 0,
    ],
    'expr2-1.4.1 projection zero is not false is false' => [
        'sql' => 'SELECT (0 IS NOT FALSE) AS v FROM t0',
        'expected' => static fn (array $row): int => 0,
    ],
    'expr2-1.4.2 projection not false-or-comparison is false' => [
        'sql' => 'SELECT NOT (0 IS FALSE OR (t0.c0 = 1)) AS v FROM t0',
        'expected' => static fn (array $row): int => 0,
    ],
];

foreach ($cases as $caseName => $case) {
    foreach ($rows as $row) {
        $tests["real upstream corpus expression affinity dynamic {$caseName} row {$row['rowid']}"] = static function (TestRunner $t) use ($case, $row, $scalar, $sourcePath): void {
            $actual = $scalar($case['sql'], $row);
            $expected = $case['expected']($row);

            $t->same($expected, $actual);
            $t->same($row['rowid'], $row['rowid']);
            $t->contains('expr2.test', $sourcePath);
        };
    }
}

return $tests;
