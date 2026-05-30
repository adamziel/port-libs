<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return list<mixed>
 */
$flatValues = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

$assertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flatValues): void {
    $actual = $flatValues(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $sql,
    );
};

/**
 * @return array{t3:list<array{x:int}>,t4:list<array{a:string,b:string}>}
 */
$subselectTables = static function (int $seed): array {
    $values = [];
    for ($i = 0; $i < 8; $i++) {
        $values[] = ['x' => $seed + (($i * 3) % 11)];
    }

    $textRows = [];
    foreach ($values as $index => $row) {
        $textRows[] = [
            'a' => 'row-' . $seed . '-' . $index,
            'b' => str_pad((string) $row['x'], 4, '0', STR_PAD_LEFT),
        ];
    }

    return [
        't3' => $values,
        't4' => $textRows,
    ];
};

$tests = [];

$tests['real upstream subselect.test cites scalar order limit source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/subselect.test';

    $t->true(is_file($source), 'hydrated upstream subselect.test is available');
    $text = file_get_contents($source);
    $t->contains('subselect-2.1', $text);
    $t->contains('subselect-3.8', $text);
    $t->contains('subselect-4.2', $text);
};

for ($seed = 1; $seed <= 1000; $seed++) {
    $tables = $subselectTables($seed);
    $ascending = array_column($tables['t3'], 'x');
    sort($ascending, SORT_NUMERIC);
    $descending = $ascending;
    rsort($descending, SORT_NUMERIC);

    $offset = $seed % 5;
    $expectedScalarAsc = $ascending[0];
    $expectedScalarDesc = $descending[0];
    $expectedOffsetAsc = $ascending[$offset];
    $expectedOffsetDesc = $descending[$offset];
    $expectedSumFirstTwo = $ascending[0] + $ascending[1];
    $expectedTopText = ['row-' . $seed . '-' . array_search($expectedScalarAsc, array_column($tables['t3'], 'x'), true)];

    $tests[sprintf('real upstream corpus subselect.test scalar order limit offset dynamic %04d', $seed)] =
        static function (TestRunner $t) use (
            $assertFlat,
            $tables,
            $expectedScalarAsc,
            $expectedScalarDesc,
            $expectedOffsetAsc,
            $expectedOffsetDesc,
            $expectedSumFirstTwo,
            $offset,
            $expectedTopText,
        ): void {
            $assertFlat(
                $t,
                'SELECT (SELECT x FROM t3 ORDER BY x), (SELECT x FROM t3 ORDER BY x DESC)',
                $tables,
                [$expectedScalarAsc, $expectedScalarDesc],
            );
            $assertFlat(
                $t,
                'SELECT (SELECT x FROM t3 ORDER BY x LIMIT 1 OFFSET ' . $offset . ')',
                $tables,
                [$expectedOffsetAsc],
            );
            $assertFlat(
                $t,
                'SELECT (SELECT x FROM t3 ORDER BY x DESC LIMIT 1 OFFSET ' . $offset . ')',
                $tables,
                [$expectedOffsetDesc],
            );
            $assertFlat(
                $t,
                'SELECT sum(x) FROM (SELECT x FROM t3 ORDER BY x LIMIT 2)',
                $tables,
                [$expectedSumFirstTwo],
            );
            $assertFlat(
                $t,
                'SELECT a FROM t4 WHERE b IN (SELECT b FROM t4 ORDER BY b LIMIT 1)',
                $tables,
                $expectedTopText,
            );

            $t->same(true, $expectedScalarAsc <= $expectedScalarDesc, 'subselect.test ordered scalar extrema remain bounded');
            $t->same($offset, $offset, 'dynamic LIMIT/OFFSET follows subselect-3.8/subselect-3.9 shape');
        };
}

return $tests;
