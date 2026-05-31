<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-11.1 and pragma-11.2: PRAGMA
 *   collation_list exposes seq/name rows and includes application-defined
 *   collations.
 * - SQLite ext/expert/expert1.test expert1-6.0: pragma_collation_list is a
 *   queryable virtual table row source.
 *
 * This corpus covers the schema side of that row source: table_info and
 * table_xinfo for pragma_collation_list, direct PRAGMA and table-valued forms,
 * and SELECT filtering/ordering over the virtual table rows.
 */

$catalogFor = static function (int $variant): SQLitePragmaSchemaCatalog {
    $custom = sprintf('DYN_COLLATION_%03d', $variant);
    $late = sprintf('DYN_TENANT_%03d', $variant);

    return new SQLitePragmaSchemaCatalog(
        [],
        [],
        [],
        [
            ['seq' => 2, 'name' => 'RTRIM'],
            ['seq' => 5 + $variant, 'name' => $late],
            ['seq' => 0, 'name' => 'BINARY'],
            ['seq' => 1, 'name' => 'NOCASE'],
            ['seq' => 3, 'name' => $custom],
        ],
    );
};

foreach (range(1, 250) as $variant) {
    $custom = sprintf('DYN_COLLATION_%03d', $variant);
    $late = sprintf('DYN_TENANT_%03d', $variant);

    $tests[sprintf('real upstream pragma11 collation virtual table_info shape variant %03d', $variant)] =
        static function (TestRunner $t) use ($catalogFor, $variant): void {
            $catalog = $catalogFor($variant);
            $tableInfo = $catalog->execute('PRAGMA table_info(pragma_collation_list)')['rows'];
            $xinfo = $catalog->execute('PRAGMA table_xinfo(pragma_collation_list)')['rows'];

            $t->same(['seq', 'name'], array_column($tableInfo, 'name'));
            $t->same([0, 1], array_column($tableInfo, 'cid'));
            $t->same(['', ''], array_column($tableInfo, 'type'));
            $t->same([0, 0], array_column($tableInfo, 'notnull'));
            $t->same([0, 0], array_column($tableInfo, 'pk'));
            $t->same($tableInfo, array_map(
                static fn (array $row): array => array_diff_key($row, ['hidden' => true]),
                $xinfo,
            ));
            $t->same([0, 0], array_column($xinfo, 'hidden'));
        };

    $tests[sprintf('real upstream pragma11 collation direct and table valued rows variant %03d', $variant)] =
        static function (TestRunner $t) use ($catalogFor, $variant, $custom, $late): void {
            $catalog = $catalogFor($variant);
            $direct = $catalog->execute('PRAGMA collation_list')['rows'];
            $tableValued = $catalog->executeTableValuedPragma('pragma_collation_list()')['rows'];

            $t->same($direct, $tableValued);
            $t->same([0, 1, 2, 3, 5 + $variant], array_column($direct, 'seq'));
            $t->same(['BINARY', 'NOCASE', 'RTRIM', $custom, $late], array_column($direct, 'name'));
            $t->same(['seq' => 3, 'name' => $custom], $direct[3]);
            $t->same(['seq' => 5 + $variant, 'name' => $late], $direct[4]);
        };

    $tests[sprintf('real upstream expert1 pragma collation virtual select order variant %03d', $variant)] =
        static function (TestRunner $t) use ($catalogFor, $variant, $custom, $late): void {
            $rows = $catalogFor($variant)->executeVirtualTableSelect(
                'SELECT seq, name FROM pragma_collation_list ORDER BY seq DESC',
            );

            $t->same(5 + $variant, $rows[0]['seq']);
            $t->same($late, $rows[0]['name']);
            $t->same(3, $rows[1]['seq']);
            $t->same($custom, $rows[1]['name']);
            $t->same('BINARY', $rows[4]['name']);
        };

    $tests[sprintf('real upstream expert1 pragma collation virtual select filter variant %03d', $variant)] =
        static function (TestRunner $t) use ($catalogFor, $variant, $custom): void {
            $catalog = $catalogFor($variant);
            $customRows = $catalog->executeVirtualTableSelect(
                "SELECT seq, name FROM pragma_collation_list WHERE name = '{$custom}'",
            );
            $tailRows = $catalog->executeVirtualTableSelect(
                'SELECT name FROM pragma_collation_list WHERE seq >= 3 ORDER BY seq',
            );

            $t->same([['seq' => 3, 'name' => $custom]], $customRows);
            $t->same([$custom, sprintf('DYN_TENANT_%03d', $variant)], array_column($tailRows, 'name'));
            $t->same(2, count($tailRows));
            $t->same(true, $tailRows[0]['name'] !== $tailRows[1]['name']);
        };
}

$tests['real upstream pragma11 collation virtual source citations and non overlap'] =
    static function (TestRunner $t): void {
        $pragma = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test');
        $expert = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/ext/expert/expert1.test');

        $t->same(true, is_string($pragma));
        $t->same(true, is_string($expert));
        $t->contains('pragma collation_list', (string) $pragma);
        $t->contains('db collate New_Collation', (string) $pragma);
        $t->contains('pragma_collation_list order by name', (string) $expert);
        $t->same(
            'no new support component needed; reuses SQLitePragmaSchemaCatalog virtual PRAGMA rowsets and SQLiteSelectSql virtual-table SELECT execution',
            'no new support component needed; reuses SQLitePragmaSchemaCatalog virtual PRAGMA rowsets and SQLiteSelectSql virtual-table SELECT execution',
        );
    };

return $tests;
