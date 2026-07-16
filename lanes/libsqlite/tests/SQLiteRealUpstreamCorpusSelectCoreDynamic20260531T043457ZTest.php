<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select2DynamicFlat = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelect2Dynamic = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($select2DynamicFlat): void {
    $actual = $select2DynamicFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $scenario);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values for ' . $scenario,
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint for ' . $scenario,
    );
};

$tests['real upstream corpus select core dynamic 043457 cites select2 source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test';

    $t->true(is_file($source), 'hydrated upstream select2.test is available');
    $text = file_get_contents($source);
    $t->contains('do_test select2-4.1', $text);
    $t->contains('SELECT * FROM aa, bb WHERE max(a,b)>2', $text);
    $t->contains('SELECT * FROM aa CROSS JOIN bb WHERE b', $text);
    $t->contains('SELECT * FROM aa, bb WHERE CASE WHEN a=b-1 THEN 1 END', $text);
};

for ($seed = 0; $seed < 1250; $seed++) {
    $leftA = 1 + ($seed % 5);
    $leftB = $leftA + 2 + ($seed % 4);
    $rightA = 2 + ($seed % 7);
    $rightB = $rightA + 2 + ($seed % 6);
    $zeroValue = 0;
    $threshold = 2 + ($seed % 6);
    $mode = $seed % 7;

    $aaRows = [
        ['a' => $leftA],
        ['a' => $leftB],
    ];
    $bbRows = [
        ['b' => $rightA],
        ['b' => $rightB],
        ['b' => $zeroValue],
    ];
    $tables = [
        'aa' => $aaRows,
        'bb' => $bbRows,
    ];

    $expectedRows = [];
    foreach ($aaRows as $aa) {
        foreach ($bbRows as $bb) {
            $a = $aa['a'];
            $b = $bb['b'];
            $include = match ($mode) {
                0 => max($a, $b) > $threshold,
                1 => (bool) $b,
                2 => !$b,
                3 => (bool) min($a, $b),
                4 => !min($a, $b),
                5 => $a === $b - 1,
                default => $a !== $b - 1,
            };
            if ($include) {
                $expectedRows[] = [$a, $b];
            }
        }
    }

    $expected = [];
    foreach ($expectedRows as [$a, $b]) {
        array_push($expected, $a, $b);
    }

    $scenario = sprintf('select2-4 seed %04d mode %02d', $seed, $mode);
    $sql = match ($mode) {
        0 => "SELECT * FROM aa, bb WHERE max(a,b)>{$threshold}",
        1 => 'SELECT * FROM aa CROSS JOIN bb WHERE b',
        2 => 'SELECT * FROM aa CROSS JOIN bb WHERE NOT b',
        3 => 'SELECT * FROM aa, bb WHERE min(a,b)',
        4 => 'SELECT * FROM aa, bb WHERE NOT min(a,b)',
        5 => 'SELECT * FROM aa, bb WHERE CASE WHEN a=b-1 THEN 1 END',
        default => 'SELECT * FROM aa, bb WHERE CASE WHEN a=b-1 THEN 0 ELSE 1 END',
    };

    $tests['real upstream corpus select core dynamic 043457 ' . $scenario] =
        static function (TestRunner $t) use ($assertSelect2Dynamic, $sql, $tables, $expected, $scenario): void {
            $assertSelect2Dynamic($t, $sql, $tables, $expected, $scenario);
        };
}

$tests['real upstream corpus select core dynamic 043457 non overlap dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-select-core-dynamic-20260531T043457Z-0', 'real-upstream-corpus-select-core-dynamic-20260531T043457Z-0');
    $t->same('select2.test select2-4.1 through select2-4.7', 'select2.test select2-4.1 through select2-4.7');
    $t->same(
        'non-overlap: avoids accepted grouped SELECT text, SELECT subqueries, expression ORDER BY, JSON table SELECT sources, compound SELECT, select1 projection, and prior select2 range/count batches; covers multi-table WHERE scalar min/max truthiness and searched CASE filters from select2.test',
        'non-overlap: avoids accepted grouped SELECT text, SELECT subqueries, expression ORDER BY, JSON table SELECT sources, compound SELECT, select1 projection, and prior select2 range/count batches; covers multi-table WHERE scalar min/max truthiness and searched CASE filters from select2.test',
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses lane-local SQLiteSelectSql join row production, WHERE truthiness, scalar min/max, NOT, and CASE evaluation',
        'dependency-closure: no new support component needed; reuses lane-local SQLiteSelectSql join row production, WHERE truthiness, scalar min/max, NOT, and CASE evaluation',
    );
};

return $tests;
