<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<array<string,mixed>> $expected
 */
$assertRows = static function (TestRunner $t, string $sql, array $tables, array $expected, string $scenario): void {
    $actual = SQLiteSelectSql::execute($sql, $tables);

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'row count for ' . $scenario);
    $t->same(
        $expected === [] ? [] : array_keys($expected[0]),
        $actual === [] ? [] : array_keys($actual[0]),
        'result column names for ' . $scenario,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint for ' . $scenario,
    );
};

$tests['real upstream select1.test select1-6.4 cites mixed-case expression source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test';

    $t->true(is_file($source), 'hydrated upstream select1.test is available');
    $text = file_get_contents($source);
    $t->contains('do_test select1-6.4', $text);
    $t->contains('SELECT f1+F2 as xyzzy FROM test1 ORDER BY f2', $text);
    $t->contains('do_test select1-6.5', $text);
    $t->contains('SELECT test1.f1+F2 FROM test1 ORDER BY f2', $text);
    $t->contains('do_test select1-6.6', $text);
    $t->contains('SELECT test1.f1+F2, t1 FROM test1, test2', $text);
    $t->contains('do_test select1-6.9.1', $text);
};

for ($seed = 0; $seed < 1250; $seed++) {
    $first = 11 + $seed;
    $second = $first + 22 + ($seed % 9);
    $third = $second + 3;
    $direction = $seed % 2 === 0 ? 'ASC' : 'DESC';
    $leftColumn = match ($seed % 4) {
        0 => 'f1',
        1 => 'F1',
        2 => 'test1.f1',
        default => 'TEST1.F1',
    };
    $rightColumn = match ($seed % 5) {
        0 => 'f2',
        1 => 'F2',
        2 => 'test1.f2',
        3 => 'TEST1.F2',
        default => 'TeSt1.F2',
    };
    $orderColumn = $seed % 3 === 0 ? 'F2' : ($seed % 3 === 1 ? 'test1.F2' : 'f2');
    $sql = sprintf(
        'SELECT %s+%s AS mixed_total FROM test1 ORDER BY %s %s',
        $leftColumn,
        $rightColumn,
        $orderColumn,
        $direction
    );
    $rows = [
        ['f1' => $first, 'f2' => $second],
        ['f1' => $first + 3, 'f2' => $third],
    ];
    $expectedRows = $rows;
    usort($expectedRows, static fn (array $left, array $right): int => $direction === 'DESC'
        ? ($right['f2'] <=> $left['f2'])
        : ($left['f2'] <=> $right['f2']));
    $expected = array_map(
        static fn (array $row): array => ['mixed_total' => $row['f1'] + $row['f2']],
        $expectedRows
    );

    $tests[sprintf('real upstream select1.test select1-6.4 dynamic mixed-case expression seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertRows, $sql, $rows, $expected): void {
            $assertRows($t, $sql, ['test1' => $rows], $expected, 'select1-6.4');
        };
}

for ($seed = 0; $seed < 250; $seed++) {
    $leftRows = [
        ['f1' => 10 + $seed, 'f2' => 20 + $seed],
        ['f1' => 30 + $seed, 'f2' => 40 + $seed],
    ];
    $rightRows = [
        ['t1' => 'abc' . ($seed % 7), 't2' => 'xyz' . $seed],
    ];
    $sql = $seed % 2 === 0
        ? 'SELECT test1.f1+F2 AS total, t1 FROM test1, test2 ORDER BY F2'
        : 'SELECT TEST1.F1+test1.F2 AS total, test2.T1 FROM test1, test2 ORDER BY test1.f2 DESC';
    $expectedRows = $leftRows;
    usort($expectedRows, static fn (array $left, array $right): int => $seed % 2 === 0
        ? ($left['f2'] <=> $right['f2'])
        : ($right['f2'] <=> $left['f2']));
    $expected = array_map(
        static fn (array $row): array => [
            'total' => $row['f1'] + $row['f2'],
            $seed % 2 === 0 ? 't1' : 'test2.T1' => $rightRows[0]['t1'],
        ],
        $expectedRows
    );

    $tests[sprintf('real upstream select1.test select1-6.6 dynamic joined mixed-case expression seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertRows, $sql, $leftRows, $rightRows, $expected): void {
            $assertRows($t, $sql, ['test1' => $leftRows, 'test2' => $rightRows], $expected, 'select1-6.6');
        };
}

return $tests;
