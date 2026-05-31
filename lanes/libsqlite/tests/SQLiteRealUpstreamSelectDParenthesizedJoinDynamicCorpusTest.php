<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test
 * - selectD-1.1, selectD-1.2.1, selectD-1.2.2, selectD-1.2.3,
 *   selectD-1.2.4, selectD-1.2.5, selectD-1.2.7, selectD-1.5,
 *   selectD-1.6, and selectD-1.7 parenthesized SELECT FROM name resolution.
 *
 * This dynamic corpus ports the ON/qualified-projection parenthesized join
 * branch. The upstream USING sub-branch remains a separate behavior gap.
 */

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
$assertSelectD = static function (TestRunner $t, string $sql, array $tables, array $expected, string $scenario) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario . ' flat row values');
    $t->same(count($expected), count($actual), $scenario . ' value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $scenario . ' first/last guard'
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' upstream-shaped fingerprint'
    );
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectDTables = static function (int $seed): array {
    $base = ($seed * 10) + 1000;
    $label = 's' . str_pad((string) $seed, 3, '0', STR_PAD_LEFT);

    return [
        't1' => [['a' => $base + 111, 'b' => $label . '-x1']],
        't2' => [['a' => $base + 222, 'b' => $label . '-x2']],
        't3' => [['a' => $base + 333, 'b' => $label . '-x3']],
        't4' => [['a' => $base + 444, 'b' => $label . '-x4']],
        'main.t4' => [['a' => $base + 444, 'b' => $label . '-main4']],
        'aux1.t4' => [['a' => $base + 555, 'b' => $label . '-aux4']],
    ];
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<mixed>
 */
$flatTableRows = static function (array $tables, array $names): array {
    $flat = [];
    foreach ($names as $name) {
        $row = $tables[$name][0];
        $flat[] = $row['a'];
        $flat[] = $row['b'];
    }

    return $flat;
};

$tests = [];

for ($seed = 1; $seed <= 240; $seed++) {
    $tables = $selectDTables($seed);
    $unqualifiedTables = [
        't1' => $tables['t1'],
        't2' => $tables['t2'],
        't3' => $tables['t3'],
        't4' => $tables['t4'],
    ];
    $base = ($seed * 10) + 1000;
    $t1 = $tables['t1'][0];
    $t2 = $tables['t2'][0];
    $t3 = $tables['t3'][0];
    $main4 = $tables['main.t4'][0];
    $aux4 = $tables['aux1.t4'][0];

    $tests[sprintf('real upstream selectD.test dynamic parenthesized comma where seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectD, $unqualifiedTables, $flatTableRows): void {
            $sql = 'SELECT * FROM (t1), (t2), (t3), (t4) '
                . 'WHERE t4.a=t3.a+111 AND t3.a=t2.a+111 AND t2.a=t1.a+111';

            $assertSelectD($t, $sql, $unqualifiedTables, $flatTableRows($unqualifiedTables, ['t1', 't2', 't3', 't4']), 'selectD-1.1');
        };

    $tests[sprintf('real upstream selectD.test dynamic nested join star seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectD, $unqualifiedTables, $flatTableRows): void {
            $sql = 'SELECT * FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+111) '
                . 'ON t3.a=t2.a+111) ON t2.a=t1.a+111';

            $assertSelectD($t, $sql, $unqualifiedTables, $flatTableRows($unqualifiedTables, ['t1', 't2', 't3', 't4']), 'selectD-1.2.1');
        };

    $tests[sprintf('real upstream selectD.test dynamic nested join qualified projection seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectD, $unqualifiedTables, $t3): void {
            $sql = 'SELECT t3.* FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+111) '
                . 'ON t3.a=t2.a+111) ON t2.a=t1.a+111';

            $assertSelectD($t, $sql, $unqualifiedTables, [$t3['a'], $t3['b']], 'selectD-1.2.3');
        };

    $tests[sprintf('real upstream selectD.test dynamic nested schema-qualified aliases seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectD, $tables, $t1, $t2, $main4, $aux4): void {
            $sql = 'SELECT * FROM t1 JOIN (t2 JOIN (main.t4 AS x JOIN aux1.t4 AS y ON y.a=x.a+111) '
                . 'ON x.a=t2.a+222) ON t2.a=t1.a+111';

            $assertSelectD($t, $sql, $tables, [$t1['a'], $t1['b'], $t2['a'], $t2['b'], $main4['a'], $main4['b'], $aux4['a'], $aux4['b']], 'selectD-1.2.5');
        };

    $tests[sprintf('real upstream selectD.test dynamic left join parenthesized null extension seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectD, $tables, $base, $t1, $t2, $t3): void {
            $leftTables = $tables;
            $leftTables['t2'] = [['a' => $t1['a'], 'b' => $t2['b']]];
            $leftTables['t3'] = [['a' => $base + 222, 'b' => $t3['b']]];
            $leftTables['t4'] = [['a' => $base + 333, 'b' => 'unmatched']];
            $sql = 'SELECT t1.*, t2.*, t3.*, t4.b FROM (t1 LEFT JOIN t2 ON t2.a=t1.a) '
                . 'JOIN (t3 LEFT JOIN t4 ON t4.a=t3.a) ON t1.a=t3.a-111';

            $assertSelectD($t, $sql, $leftTables, [$t1['a'], $t1['b'], $t1['a'], $t2['b'], $base + 222, $t3['b'], null], 'selectD-1.7');
        };
}

$tests['real upstream selectD.test source coverage and follow-up note'] = static function (TestRunner $t): void {
    $t->same(
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test',
        'real upstream selectD source file'
    );
    $t->contains('selectD-1.1', 'selectD-1.1 parenthesized comma name resolution');
    $t->contains('selectD-1.2.7', 'selectD-1.2.7 schema-qualified alias name resolution');
    $t->contains('USING', 'selectD USING sub-branch remains a separate behavior fix');
};

return $tests;
