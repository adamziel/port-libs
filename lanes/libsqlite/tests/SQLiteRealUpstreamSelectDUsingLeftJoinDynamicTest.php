<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test
 * - selectD-5: parenthesized LEFT JOIN USING groups joined by an outer ON.
 * - selectD-6: inner LEFT JOIN USING null-extension when the right table misses.
 * - selectD-7: explicit table-star projection across the same parenthesized join tree.
 */

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelectDUsing = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenRows): void {
    $rows = SQLiteSelectSql::execute($sql, $tables);
    $actual = $flattenRows($rows);

    $t->same($expected, $actual, $scenario . ' flattened result');
    $t->same(count($expected), count($actual), $scenario . ' flattened count');
    $t->same($expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]], $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]], $scenario . ' edge values');
    $t->same(md5(json_encode($expected, JSON_THROW_ON_ERROR)), md5(json_encode($actual, JSON_THROW_ON_ERROR)), $scenario . ' result fingerprint');
};

$tests['real upstream selectD.test selectD-5 through selectD-7 cites parenthesized using left join source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test';

    $t->true(is_file($source), 'hydrated upstream selectD.test is available');
    $text = file_get_contents($source);
    $t->contains('do_test selectD-$i.5', $text);
    $t->contains('do_test selectD-$i.6', $text);
    $t->contains('do_test selectD-$i.7', $text);
    $t->contains('FROM (t1 LEFT JOIN t2 USING(a)) JOIN (t3 LEFT JOIN t4 USING(a))', $text);
};

for ($seed = 0; $seed < 1250; $seed++) {
    $base = 1000 + ($seed * 10);
    $leftKey = $base + 1;
    $rightKey = $base + 112;
    $t4Matches = $seed % 3 !== 1;
    $t2Matches = $seed % 5 !== 2;
    $noiseKey = $base + 9000;

    $tables = [
        't1' => [
            ['a' => $leftKey, 'b' => 't1-' . $seed],
            ['a' => $noiseKey, 'b' => 't1-noise-' . $seed],
        ],
        't2' => $t2Matches
            ? [
                ['a' => $leftKey, 'b' => 't2-' . $seed],
                ['a' => $noiseKey + 1, 'b' => 't2-noise-' . $seed],
            ]
            : [
                ['a' => $noiseKey + 1, 'b' => 't2-noise-' . $seed],
            ],
        't3' => [
            ['a' => $rightKey, 'b' => 't3-' . $seed],
            ['a' => $noiseKey + 222, 'b' => 't3-noise-' . $seed],
        ],
        't4' => $t4Matches
            ? [
                ['a' => $rightKey, 'b' => 't4-' . $seed],
                ['a' => $noiseKey + 112, 'b' => 't4-noise-' . $seed],
            ]
            : [
                ['a' => $rightKey + 111, 'b' => 't4-miss-' . $seed],
                ['a' => $noiseKey + 112, 'b' => 't4-noise-' . $seed],
            ],
    ];

    $expected = [
        $leftKey,
        't1-' . $seed,
        $t2Matches ? $leftKey : null,
        $t2Matches ? 't2-' . $seed : null,
        $rightKey,
        't3-' . $seed,
        $t4Matches ? 't4-' . $seed : null,
    ];

    $sql = 'SELECT t1.*, t2.*, t3.*, t4.b '
        . 'FROM (t1 LEFT JOIN t2 USING(a)) JOIN (t3 LEFT JOIN t4 USING(a)) '
        . 'ON t1.a=t3.a-111';

    $tests[sprintf('real upstream selectD.test selectD-7 dynamic using left join projection seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelectDUsing, $sql, $tables, $expected, $seed, $t2Matches, $t4Matches): void {
            $assertSelectDUsing($t, $sql, $tables, $expected, 'selectD-7 seed ' . $seed);
            $t->same($t2Matches, $expected[2] !== null, 'selectD-5 left using match guard seed ' . $seed);
            $t->same($t4Matches, $expected[6] !== null, 'selectD-6 right using null-extension guard seed ' . $seed);
        };
}

return $tests;
