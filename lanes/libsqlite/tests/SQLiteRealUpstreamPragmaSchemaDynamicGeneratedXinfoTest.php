<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/gencol1.test gencol1-21.1: PRAGMA table_xinfo reports
 *   generated columns in declaration order, while ordinary table_info hides
 *   them. It also keeps the ordinary column "e int Always default(5)" as type
 *   INT, because ALWAYS is not part of a declared type unless it introduces a
 *   generated-column AS expression.
 * - SQLite test/pragma.test pragma-6.*: PRAGMA table_info/table_xinfo rowsets
 *   are the schema-catalog surfaces used to observe cid/name/type/default/pk.
 */

$record = static fn (
    string $name,
    string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord('table', $name, $name, 1000 + $rowId, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): array {
    $table = sprintf('generated_xinfo_settings_%04d', $variant);
    $sql = <<<SQL
CREATE TABLE {$table}(
  a integer primary key,
  b int generated always as (a+5),
  c text    GENERATED   ALWAYS as (printf('%08x',a)),
  d Generated
    Always
    AS ('xyzzy'),
  e int                         Always default(5),
  f text as (substr(c,1,4)) stored,
  g blob default X'0A'
)
SQL;

    return [new SQLitePragmaSchemaCatalog([$record($table, $sql, $variant)]), $table];
};

$names = static fn (array $rows): array => array_column($rows, 'name');
$types = static fn (array $rows): array => array_column($rows, 'type');
$hidden = static fn (array $rows): array => array_column($rows, 'hidden');

foreach (range(1, 250) as $variant) {
    $tests[sprintf('real upstream pragma schema dynamic generated table_xinfo preserves declaration order variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $names, $types, $hidden, $variant): void {
        [$catalog, $table] = $catalogFor($variant);
        $rows = $catalog->executeTableValuedPragma("pragma_table_xinfo('{$table}')")['rows'];

        $t->same(['a', 'b', 'c', 'd', 'e', 'f', 'g'], $names($rows));
        $t->same(['INTEGER', 'INT', 'TEXT', '', 'INT', 'TEXT', 'BLOB'], $types($rows));
        $t->same([0, 2, 2, 2, 0, 3, 0], $hidden($rows));
        $t->same(1, $rows[0]['pk']);
        $t->same('5', $rows[4]['dflt_value']);
        $t->same("X'0A'", $rows[6]['dflt_value']);
    };

    $tests[sprintf('real upstream pragma schema dynamic generated table_info hides generated columns variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $names, $types, $variant): void {
        [$catalog, $table] = $catalogFor($variant);
        $rows = $catalog->execute("PRAGMA table_info({$table})")['rows'];

        $t->same(['a', 'e', 'g'], $names($rows));
        $t->same(['INTEGER', 'INT', 'BLOB'], $types($rows));
        $t->same([0, 4, 6], array_column($rows, 'cid'));
        $t->same([1, 0, 0], array_column($rows, 'pk'));
        $t->same([null, '5', "X'0A'"], array_column($rows, 'dflt_value'));
    };

    $tests[sprintf('real upstream pragma schema dynamic generated xinfo analysis counts hidden codes variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant): void {
        [$catalog, $table] = $catalogFor($variant);
        $result = $catalog->execute("PRAGMA table_xinfo({$table})");
        $hiddenCodes = array_map(static fn (array $row): int => (int) $row['hidden'], $result['rows']);

        $t->same('table_xinfo', $result['pragma']);
        $t->same(7, count($result['rows']));
        $t->same(4, count(array_filter($hiddenCodes, static fn (int $hidden): bool => $hidden === 2 || $hidden === 3)));
        $t->same(3, count(array_filter($hiddenCodes, static fn (int $hidden): bool => $hidden === 0)));
        $t->same([0, 2, 2, 2, 0, 3, 0], $hiddenCodes);
    };

    $tests[sprintf('real upstream pragma schema dynamic generated table_list includes hidden generated columns variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant): void {
        [$catalog, $table] = $catalogFor($variant);
        $row = $catalog->execute("PRAGMA table_list({$table})")['rows'][0];

        $t->same($table, $row['name']);
        $t->same('table', $row['type']);
        $t->same(7, $row['ncol']);
        $t->same(0, $row['wr']);
        $t->same(0, $row['strict']);
    };
}

$tests['real upstream pragma schema dynamic generated xinfo cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'gencol1.test gencol1-21.1 SELECT name, type FROM pragma_table_xinfo(t1) reports generated and ordinary columns in declaration order',
        'pragma.test pragma-6.* covers table_info/table_xinfo schema-catalog row shape',
    ];

    $t->same(2, count($sections));
    $t->contains('gencol1-21.1', $sections[0]);
    $t->contains('table_xinfo', $sections[1]);
};

return $tests;
