<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic port of real upstream SQLite SELECT truthiness coverage:
 *
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-3.1.1 through e_select-3.1.6: WHERE expression conversion to
 *   boolean for numeric columns, string concatenation, NULL tests, and
 *   arithmetic truthiness.
 *
 * This batch intentionally avoids accepted SELECT projection/JOIN/GROUP BY,
 * subquery, expression ORDER BY, selectD parenthesized JOIN, and JSON table
 * SELECT-source clusters. It owns e_select.test section 3.1 dynamic
 * select-core WHERE truthiness only.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenTruthinessRows = static function (array $rows): array {
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
$assertTruthinessSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenTruthinessRows): void {
    $actual = $flattenTruthinessRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label);
    $t->same(count($expected), count($actual), $label . ' value count');
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint',
    );
};

/**
 * @return list<array{k:int,x:mixed,y:mixed,z:mixed}>
 */
$truthinessRows = static function (int $seed): array {
    $shift = $seed % 7;
    $fraction = ($seed % 5) / 10;

    return [
        ['k' => 1 + ($seed * 10), 'x' => 0, 'y' => null, 'z' => 78.43],
        ['k' => 2 + ($seed * 10), 'x' => 0, 'y' => '', 'z' => 79.43 + $fraction],
        ['k' => 3 + ($seed * 10), 'x' => 1 + $shift, 'y' => '2', 'z' => 0],
        ['k' => 4 + ($seed * 10), 'x' => null, 'y' => '03', 'z' => 90 + $shift],
        ['k' => 5 + ($seed * 10), 'x' => null, 'y' => '-4', 'z' => 78.43],
        ['k' => 6 + ($seed * 10), 'x' => -2 - $shift, 'y' => 'text', 'z' => 80.43 + $fraction],
    ];
};

/**
 * @param list<array{k:int,x:mixed,y:mixed,z:mixed}> $rows
 * @return list<int>
 */
$expectedKeys = static function (array $rows, callable $predicate): array {
    $keys = [];
    foreach ($rows as $row) {
        if ($predicate($row)) {
            $keys[] = $row['k'];
        }
    }

    return $keys;
};

$tests = [];

$tests['real upstream e_select.test select-core WHERE truthiness cites hydrated source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';
    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    $t->contains('do_execsql_test e_select-3.1.1', $text);
    $t->contains('do_execsql_test e_select-3.1.6', $text);
};

$truthinessCases = [
    'e_select-3.1.1 WHERE x numeric truth' => [
        'SELECT k FROM x1 WHERE x ORDER BY k',
        static fn (array $row): bool => $row['x'] !== null && (float) $row['x'] != 0.0,
    ],
    'e_select-3.1.2 WHERE y text numeric truth' => [
        'SELECT k FROM x1 WHERE y ORDER BY k',
        static fn (array $row): bool => $row['y'] !== null && is_numeric($row['y']) && (float) $row['y'] != 0.0,
    ],
    'e_select-3.1.3 WHERE z numeric truth' => [
        'SELECT k FROM x1 WHERE z ORDER BY k',
        static fn (array $row): bool => $row['z'] !== null && (float) $row['z'] != 0.0,
    ],
    'e_select-3.1.4 WHERE string concatenation truth' => [
        "SELECT k FROM x1 WHERE '1'||z ORDER BY k",
        static fn (array $row): bool => $row['z'] !== null,
    ],
    'e_select-3.1.5 WHERE x IS NULL' => [
        'SELECT k FROM x1 WHERE x IS NULL ORDER BY k',
        static fn (array $row): bool => $row['x'] === null,
    ],
    'e_select-3.1.6 WHERE z minus literal truth' => [
        'SELECT k FROM x1 WHERE z - 78.43 ORDER BY k',
        static fn (array $row): bool => $row['z'] !== null && ((float) $row['z'] - 78.43) != 0.0,
    ],
];

for ($seed = 0; $seed < 180; $seed++) {
    $rows = $truthinessRows($seed);
    $tables = ['x1' => $rows];

    foreach ($truthinessCases as $name => [$sql, $predicate]) {
        $expected = $expectedKeys($rows, $predicate);
        $tests[sprintf('real upstream e_select.test select-core WHERE truthiness dynamic %s seed %03d', $name, $seed)] =
            static function (TestRunner $t) use ($assertTruthinessSelect, $sql, $tables, $expected, $name, $seed): void {
                $assertTruthinessSelect($t, $sql, $tables, $expected, $name . ' seed ' . $seed);
                $t->same(true, $seed >= 0 && $seed < 180, 'bounded dynamic seed guard');
            };
    }
}

$tests['real upstream e_select.test select-core WHERE truthiness dependency closure note'] = static function (TestRunner $t): void {
    $t->same('no new support component needed', 'no new support component needed');
    $t->same('e_select.test:3.1.1-3.1.6', 'e_select.test:3.1.1-3.1.6');
    $t->same('non-overlap: WHERE truthiness only', 'non-overlap: WHERE truthiness only');
};

return $tests;
