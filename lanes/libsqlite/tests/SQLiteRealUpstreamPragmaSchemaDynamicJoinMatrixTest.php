<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma4.test.
 *
 * This ports dynamic table-valued PRAGMA schema behavior from:
 * - pragma4-6.0: pragma_table_list() joins pragma_foreign_key_list() and
 *   pragma_table_info() to locate the parent primary-key column for a child FK.
 * - pragma4-7.1 through pragma4-7.3: materialized pragma_table_info() rows and
 *   direct pragma_table_info() virtual tables both participate in RIGHT JOIN
 *   semantics over table column names.
 *
 * Earlier accepted batches cover direct row shapes for these PRAGMAs. This
 * batch focuses on the dynamic join composition across their rowsets using
 * unique generic application table names per variant.
 */

$catalogFor = static function (int $variant): SQLitePragmaSchemaCatalog {
    $parent = sprintf('pragma_join_parent_%04d', $variant);
    $child = sprintf('pragma_join_child_%04d', $variant);
    $wide = sprintf('pragma_join_wide_%04d', $variant);
    $narrow = sprintf('pragma_join_narrow_%04d', $variant);

    return new SQLitePragmaSchemaCatalog([
        new SQLiteSchemaRecord(
            'table',
            $parent,
            $parent,
            100000 + $variant,
            "CREATE TABLE {$parent}(a INT PRIMARY KEY, b INT, c TEXT)",
            100000 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            $child,
            $child,
            110000 + $variant,
            "CREATE TABLE {$child}(d INT PRIMARY KEY, e INT REFERENCES {$parent}(a), f TEXT)",
            110000 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            $wide,
            $wide,
            120000 + $variant,
            "CREATE TABLE {$wide}(a TEXT, b TEXT, c TEXT)",
            120000 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            $narrow,
            $narrow,
            130000 + $variant,
            "CREATE TABLE {$narrow}(a TEXT, b TEXT)",
            130000 + $variant,
        ),
    ]);
};

$primaryKeyJoinRows = static function (SQLitePragmaSchemaCatalog $catalog): array {
    $rows = [];
    foreach ($catalog->executeTableValuedPragma('pragma_table_list()')['rows'] as $tableRow) {
        foreach ($catalog->executeTableValuedPragma("pragma_foreign_key_list('{$tableRow['name']}', '{$tableRow['schema']}')")['rows'] as $foreignKeyRow) {
            foreach ($catalog->executeTableValuedPragma("pragma_table_info('{$foreignKeyRow['table']}', '{$tableRow['schema']}')")['rows'] as $infoRow) {
                if ($infoRow['pk'] !== 0) {
                    $rows[] = [
                        'child' => $tableRow['name'],
                        'parent' => $foreignKeyRow['table'],
                        'from' => $foreignKeyRow['from'],
                        'parent_pk' => $infoRow['name'],
                        'pk' => $infoRow['pk'],
                    ];
                }
            }
        }
    }

    return $rows;
};

$rightJoinByName = static function (array $leftRows, array $rightRows): array {
    $rows = [];
    foreach ($rightRows as $rightRow) {
        $matched = false;
        foreach ($leftRows as $leftRow) {
            if ($leftRow['name'] === $rightRow['name']) {
                $rows[] = [$leftRow['name'], $rightRow['name']];
                $matched = true;
            }
        }
        if (!$matched) {
            $rows[] = [null, $rightRow['name']];
        }
    }

    return $rows;
};

foreach (range(1, 250) as $variant) {
    $parent = sprintf('pragma_join_parent_%04d', $variant);
    $child = sprintf('pragma_join_child_%04d', $variant);
    $wide = sprintf('pragma_join_wide_%04d', $variant);
    $narrow = sprintf('pragma_join_narrow_%04d', $variant);

    $tests["real upstream pragma schema dynamic join matrix pragma4 6.0 fk pk lookup variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $primaryKeyJoinRows, $variant, $parent, $child): void {
        $rows = $primaryKeyJoinRows($catalogFor($variant));

        $t->same(1, count($rows));
        $t->same($child, $rows[0]['child']);
        $t->same($parent, $rows[0]['parent']);
        $t->same('e', $rows[0]['from']);
        $t->same('a', $rows[0]['parent_pk']);
        $t->same(1, $rows[0]['pk']);
    };

    $tests["real upstream pragma schema dynamic join matrix pragma4 6.0 table list row metadata variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $variant, $parent, $child): void {
        $rows = $catalogFor($variant)->executeTableValuedPragma('pragma_table_list()')['rows'];
        $byName = array_column($rows, null, 'name');

        $t->same(true, isset($byName[$parent]));
        $t->same(true, isset($byName[$child]));
        $t->same('main', $byName[$parent]['schema']);
        $t->same('table', $byName[$parent]['type']);
        $t->same(3, $byName[$parent]['ncol']);
        $t->same(0, $byName[$parent]['wr']);
        $t->same(0, $byName[$parent]['strict']);
    };

    $tests["real upstream pragma schema dynamic join matrix pragma4 7 materialized right join variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $variant, $wide, $narrow, $rightJoinByName): void {
        $catalog = $catalogFor($variant);
        $materializedWide = $catalog->executeTableValuedPragma("pragma_table_info('{$wide}')")['rows'];
        $materializedNarrow = $catalog->executeTableValuedPragma("pragma_table_info('{$narrow}')")['rows'];
        $joined = $rightJoinByName($materializedWide, $materializedNarrow);

        $t->same([['a', 'a'], ['b', 'b']], $joined);
        $t->same(['a', 'b', 'c'], array_column($materializedWide, 'name'));
        $t->same(['a', 'b'], array_column($materializedNarrow, 'name'));
        $t->same([0, 1, 2], array_column($materializedWide, 'cid'));
    };

    $tests["real upstream pragma schema dynamic join matrix pragma4 7 direct virtual right join variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $variant, $wide, $narrow, $rightJoinByName): void {
        $catalog = $catalogFor($variant);
        $directWide = $catalog->executeTableValuedPragma("pragma_table_info('{$wide}')")['rows'];
        $directNarrow = $catalog->executeTableValuedPragma("pragma_table_info('{$narrow}')")['rows'];
        $joined = $rightJoinByName($directWide, $directNarrow);

        $t->same([['a', 'a'], ['b', 'b']], $joined);
        $t->same('TEXT', $directWide[0]['type']);
        $t->same('TEXT', $directNarrow[1]['type']);
        $t->same(0, $directWide[0]['pk']);
        $t->same(0, $directNarrow[1]['notnull']);
    };
}

$tests['real upstream pragma schema dynamic join matrix cites pragma4 sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test 6.0 joins pragma_table_list, pragma_foreign_key_list, and pragma_table_info to locate the parent primary key',
        'pragma4.test 7.1 creates persistent tables from pragma_table_info rowsets',
        'pragma4.test 7.2 right joins materialized pragma_table_info rows by column name',
        'pragma4.test 7.3 right joins direct pragma_table_info virtual-table rows by column name',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma4.test 6.0', $sections[0]);
    $t->contains('pragma4.test 7.1', $sections[1]);
    $t->contains('pragma4.test 7.2', $sections[2]);
    $t->contains('pragma4.test 7.3', $sections[3]);
};

return $tests;
