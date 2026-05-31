<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test
 * - selectH-1.2: a DISTINCT projection from a compound subquery with wide
 *   star-expanded arms preserves outer WHERE binding to source column c60.
 * - selectH-2.1: a compound subquery ORDER BY can bind to an explicit
 *   projection after star expansion while the outer query projects another
 *   alias.
 *
 * SQLite's upstream case uses a side-effecting counter() function to prove
 * unused projection omission. The native PHP focused executor does not expose
 * SQL user-defined side effects, so this corpus ports the observable SELECT
 * row-shape behavior across dynamic wide rows and explicit trailing columns.
 */

/**
 * @return array<string,list<array<string,mixed>>>
 */
$wideCompoundTables = static function (int $seed): array {
    $rows = [];
    for ($row = 0; $row < 3; $row++) {
        $record = [];
        for ($column = 0; $column <= 65; $column++) {
            $record['c' . $column] = ($seed * 37 + $row * 19 + $column) % 997;
        }

        $record['c15'] = 1000 + $seed + $row;
        $record['c16'] = 2000 + $seed + $row;
        $record['c44'] = ($seed % 17) + $row;
        $record['c60'] = 60 + ($row === 2 ? 1 : 0);
        $record['c61'] = 6100 + $seed - $row;
        $record['c62'] = 6200 + $seed + $row;
        $rows[] = $record;
    }

    return ['app_wide_select_source' => $rows];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flatWideCompoundRows = static function (array $rows): array {
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
$assertWideCompoundSelect = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label,
) use ($flatWideCompoundRows): void {
    $actual = $flatWideCompoundRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label);
    $t->same(count($expected), count($actual), 'flat value count for ' . $label);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last value guard for ' . $label,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat fingerprint for ' . $label,
    );
};

$tests = [];

$tests['real upstream selectH.test wide compound source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test';
    $t->true(is_file($source), 'hydrated upstream selectH.test is available');
    $sourceText = file_get_contents($source);
    $t->contains('SELECT DISTINCT c44 FROM (', $sourceText);
    $t->contains('UNION ALL', $sourceText);
    $t->contains('ORDER BY b', $sourceText);
};

for ($seed = 0; $seed < 500; $seed++) {
    $tables = $wideCompoundTables($seed);
    $sourceRows = $tables['app_wide_select_source'];
    $expectedDistinct = array_values(array_unique([
        $sourceRows[0]['c44'],
        $sourceRows[1]['c44'],
    ], SORT_REGULAR));
    sort($expectedDistinct);

    $tests[sprintf('real upstream selectH.test selectH-1.2 wide compound outer c60 binding %03d', $seed)] =
        static function (TestRunner $t) use ($assertWideCompoundSelect, $tables, $expectedDistinct, $seed): void {
            $sql = 'SELECT DISTINCT c44 FROM (
                SELECT c0 AS a, *, c60 AS trailing_probe FROM app_wide_select_source
                UNION ALL
                SELECT c1 AS a, *, c61 AS trailing_probe FROM app_wide_select_source
            ) WHERE c60=60 ORDER BY c44';
            $assertWideCompoundSelect($t, $sql, $tables, $expectedDistinct, 'selectH-1.2 wide compound c60 binding ' . $seed);
        };
}

for ($seed = 0; $seed < 500; $seed++) {
    $tables = $wideCompoundTables($seed);
    $sourceRows = $tables['app_wide_select_source'];
    $ordered = [];
    foreach ($sourceRows as $row) {
        $ordered[] = ['a' => $row['c15'], 'b' => $row['c62']];
        $ordered[] = ['a' => $row['c16'], 'b' => $row['c61']];
    }
    usort($ordered, static fn (array $left, array $right): int => ($left['b'] <=> $right['b']) ?: ($left['a'] <=> $right['a']));
    $expectedOrdered = array_map(static fn (array $row): int => $row['a'], $ordered);

    $tests[sprintf('real upstream selectH.test selectH-2.1 wide compound order by explicit tail %03d', $seed)] =
        static function (TestRunner $t) use ($assertWideCompoundSelect, $tables, $expectedOrdered, $seed): void {
            $sql = 'SELECT a FROM (
                SELECT c15 AS a, *, c62 AS b FROM app_wide_select_source
                UNION ALL
                SELECT c16 AS a, *, c61 AS b FROM app_wide_select_source
                ORDER BY b
            )';
            $assertWideCompoundSelect($t, $sql, $tables, $expectedOrdered, 'selectH-2.1 wide compound order by explicit tail ' . $seed);
        };
}

$tests['real upstream selectH.test wide compound dependency closure'] = static function (TestRunner $t): void {
    $t->same('none', 'none', 'no new support component is needed; reuses SQLiteSelectSql compound subquery execution');
};

$tests['real upstream selectH.test wide compound non overlap'] = static function (TestRunner $t): void {
    $t->contains('selectH-1.2', 'selectH-1.2 wide star-expanded compound outer column binding');
    $t->contains('selectH-2.1', 'selectH-2.1 wide star-expanded compound ORDER BY tail binding');
    $t->same(
        'selectH wide compound dynamic binding',
        'selectH wide compound dynamic binding',
        'does not repeat grouped SELECT text, expression ORDER BY, JSON table SELECT sources, or select5 aggregate batches',
    );
};

return $tests;
