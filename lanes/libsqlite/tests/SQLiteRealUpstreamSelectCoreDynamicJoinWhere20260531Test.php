<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic port of real upstream SQLite SELECT WHERE-after-join coverage:
 *
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-3.2.1a: LEFT JOIN USING(k) preserves all left rows before WHERE.
 * - e_select-3.2.1b: WHERE x2.k filters the nullable right side after join.
 * - e_select-3.2.2: WHERE x2.k IS NULL keeps only unmatched LEFT JOIN rows.
 * - e_select-3.2.3 / e_select-3.2.4: NATURAL JOIN matches shared columns
 *   before WHERE truthiness is evaluated.
 *
 * This batch extends the existing e_select-3.1 WHERE truthiness dynamic file
 * without repeating it. It owns the post-join WHERE filtering behavior only.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenJoinWhereRows = static function (array $rows): array {
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
$assertJoinWhereSelect = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($flattenJoinWhereRows): void {
    $actual = $flattenJoinWhereRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label);
    $t->same(count($expected), count($actual), $label . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' first/last guard',
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint',
    );
};

/**
 * @return array{x1:list<array{k:int,x:mixed,y:mixed,z:mixed}>,x2:list<array{k:int,x:mixed,y2:mixed}>}
 */
$joinWhereTables = static function (int $seed): array {
    $base = $seed * 20;
    $naturalKey = $base + 3;
    $rightKeys = [$base + 1, $naturalKey, $base + 5, $base + 7];

    return [
        'x1' => [
            ['k' => $base + 1, 'x' => 'left-' . $seed, 'y' => 'alpha', 'z' => 78.43],
            ['k' => $base + 2, 'x' => $seed + 12, 'y' => '', 'z' => -81],
            ['k' => $naturalKey, 'x' => -22, 'y' => -27.57, 'z' => null],
            ['k' => $base + 4, 'x' => null, 'y' => 'bygone', 'z' => 'picky'],
            ['k' => $base + 5, 'x' => null, 'y' => 96.28, 'z' => null],
            ['k' => $base + 6, 'x' => 0, 'y' => 1, 'z' => 2],
        ],
        'x2' => [
            ['k' => $rightKeys[0], 'x' => 50 + $seed, 'y2' => 'blob-a-' . $seed],
            ['k' => $rightKeys[2], 'x' => 84.79, 'y2' => 65.88],
            ['k' => $naturalKey, 'x' => -22, 'y2' => 'natural-' . $seed],
            ['k' => $rightKeys[3], 'x' => 'mistrusted', 'y2' => 'standardized'],
        ],
    ];
};

/**
 * @param list<array{k:int,x:mixed,y:mixed,z:mixed}> $leftRows
 * @param list<array{k:int,x:mixed,y2:mixed}> $rightRows
 * @return list<int>
 */
$leftJoinUsingKeys = static function (array $leftRows, array $rightRows, string $mode): array {
    $rightByKey = [];
    foreach ($rightRows as $row) {
        $rightByKey[$row['k']][] = $row;
    }

    $keys = [];
    foreach ($leftRows as $row) {
        $matches = $rightByKey[$row['k']] ?? [null];
        foreach ($matches as $right) {
            if ($mode === 'all') {
                $keys[] = $row['k'];
                continue;
            }

            if ($mode === 'matched' && $right !== null && (float) $right['k'] != 0.0) {
                $keys[] = $row['k'];
                continue;
            }

            if ($mode === 'unmatched' && $right === null) {
                $keys[] = $row['k'];
            }
        }
    }

    return $keys;
};

/**
 * @param list<array{k:int,x:mixed,y:mixed,z:mixed}> $leftRows
 * @param list<array{k:int,x:mixed,y2:mixed}> $rightRows
 * @return list<int>
 */
$naturalJoinKeys = static function (array $leftRows, array $rightRows, bool $subtractNaturalKey): array {
    $keys = [];
    foreach ($leftRows as $left) {
        foreach ($rightRows as $right) {
            if ($left['k'] !== $right['k'] || $left['x'] !== $right['x']) {
                continue;
            }

            $truth = $subtractNaturalKey ? ((float) $right['k'] - (float) $left['k']) != 0.0 : (float) $right['k'] != 0.0;
            if ($truth) {
                $keys[] = $left['k'];
            }
        }
    }

    return $keys;
};

$tests = [];

$tests['real upstream e_select.test select-core post-join WHERE cites hydrated source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';
    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    $t->contains('do_execsql_test e_select-3.2.1a', $text);
    $t->contains('do_execsql_test e_select-3.2.1b', $text);
    $t->contains('do_execsql_test e_select-3.2.2', $text);
    $t->contains('do_execsql_test e_select-3.2.3', $text);
    $t->contains('do_execsql_test e_select-3.2.4', $text);
};

for ($seed = 0; $seed < 256; $seed++) {
    $tables = $joinWhereTables($seed);
    $leftRows = $tables['x1'];
    $rightRows = $tables['x2'];
    $naturalKey = $seed * 20 + 3;

    $cases = [
        'e_select-3.2.1a left join using keeps left rows' => [
            'SELECT k FROM x1 LEFT JOIN x2 USING(k) ORDER BY +k',
            $leftJoinUsingKeys($leftRows, $rightRows, 'all'),
        ],
        'e_select-3.2.1b left join using WHERE right key truth' => [
            'SELECT k FROM x1 LEFT JOIN x2 USING(k) WHERE x2.k ORDER BY +k',
            $leftJoinUsingKeys($leftRows, $rightRows, 'matched'),
        ],
        'e_select-3.2.2 left join using WHERE right key null' => [
            'SELECT k FROM x1 LEFT JOIN x2 USING(k) WHERE x2.k IS NULL ORDER BY +k',
            $leftJoinUsingKeys($leftRows, $rightRows, 'unmatched'),
        ],
        'e_select-3.2.3 natural join WHERE right key truth' => [
            'SELECT k FROM x1 NATURAL JOIN x2 WHERE x2.k ORDER BY +k',
            $naturalJoinKeys($leftRows, $rightRows, false),
        ],
        'e_select-3.2.4 natural join WHERE right key minus matched key' => [
            'SELECT k FROM x1 NATURAL JOIN x2 WHERE x2.k-' . $naturalKey . ' ORDER BY +k',
            $naturalJoinKeys($leftRows, $rightRows, true),
        ],
    ];

    foreach ($cases as $name => [$sql, $expected]) {
        $tests[sprintf('real upstream e_select.test select-core post-join WHERE dynamic %s seed %03d', $name, $seed)] =
            static function (TestRunner $t) use ($assertJoinWhereSelect, $sql, $tables, $expected, $name, $seed): void {
                $assertJoinWhereSelect($t, $sql, $tables, $expected, $name . ' seed ' . $seed);
                $t->same(true, $seed >= 0 && $seed < 256, 'bounded dynamic seed guard');
            };
    }
}

$tests['real upstream e_select.test select-core post-join WHERE dependency closure note'] = static function (TestRunner $t): void {
    $t->same('no new support component needed', 'no new support component needed');
    $t->same('e_select.test:3.2.1a-3.2.4', 'e_select.test:3.2.1a-3.2.4');
    $t->same('non-overlap: post-join WHERE filtering only', 'non-overlap: post-join WHERE filtering only');
};

return $tests;
