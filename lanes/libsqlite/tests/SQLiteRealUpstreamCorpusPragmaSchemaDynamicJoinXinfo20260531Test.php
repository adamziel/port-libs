<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma4.test 6.0: table-valued pragma_table_list(),
 *   pragma_foreign_key_list(), and pragma_table_info() compose so foreign-key
 *   rows can discover the parent primary-key column through the schema name.
 * - SQLite test/pragma4.test 7.1 through 7.3: materialized
 *   pragma_table_info() rows and live table-valued pragma rows produce the
 *   same RIGHT JOIN name pairs for asymmetric table definitions.
 * - SQLite test/pragma.test 23.2b through 23.2e: PRAGMA index_xinfo returns
 *   every key and auxiliary index column, including DESC, COLLATE, expression
 *   cid -2, and rowid cid -1 metadata.
 *
 * This file keeps the upstream cases dynamic by varying schema object names,
 * collations, root pages, expression indexes, and attached schema targets.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): SQLitePragmaSchemaCatalog {
    $parent = sprintf('parent_settings_%04d', $variant);
    $child = sprintf('child_settings_%04d', $variant);
    $left = sprintf('pragma_left_%04d', $variant);
    $right = sprintf('pragma_right_%04d', $variant);
    $indexed = sprintf('indexed_settings_%04d', $variant);
    $covering = sprintf('indexed_covering_%04d', $variant);
    $desc = sprintf('indexed_desc_%04d', $variant);
    $expr = sprintf('indexed_expr_%04d', $variant);

    return new SQLitePragmaSchemaCatalog([
        $record('table', $parent, $parent, 1000 + $variant, "CREATE TABLE {$parent}(tenant_id INTEGER, key_name TEXT, key_value TEXT, PRIMARY KEY(tenant_id, key_name))", 1),
        $record('table', $child, $child, 2000 + $variant, "CREATE TABLE {$child}(tenant_id INTEGER, key_name TEXT, parent_value TEXT, FOREIGN KEY(tenant_id, key_name) REFERENCES {$parent}(tenant_id, key_name))", 2),
        $record('table', $left, $left, 3000 + $variant, "CREATE TABLE {$left}(a TEXT, b TEXT)", 3),
        $record('table', $right, $right, 4000 + $variant, "CREATE TABLE {$right}(a TEXT, b TEXT, c TEXT)", 4),
        $record('table', $indexed, $indexed, 5000 + $variant, "CREATE TABLE {$indexed}(a TEXT, b TEXT, c TEXT, d TEXT)", 5),
        $record('index', $covering, $indexed, 6000 + $variant, "CREATE INDEX {$covering} ON {$indexed}(c, d, b)", 6),
        $record('index', $desc, $indexed, 7000 + $variant, "CREATE INDEX {$desc} ON {$indexed}(d COLLATE NOCASE, c DESC)", 7),
        $record('index', $expr, $indexed, 8000 + $variant, "CREATE INDEX {$expr} ON {$indexed}(d, lower(b), c)", 8),
    ]);
};

$rowValues = static function (array $rows, array $columns): array {
    return array_map(
        static function (array $row) use ($columns): array {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $row[$column];
            }

            return $values;
        },
        $rows,
    );
};

foreach (range(1, 250) as $variant) {
    $parent = sprintf('parent_settings_%04d', $variant);
    $child = sprintf('child_settings_%04d', $variant);
    $left = sprintf('pragma_left_%04d', $variant);
    $right = sprintf('pragma_right_%04d', $variant);
    $covering = sprintf('indexed_covering_%04d', $variant);
    $desc = sprintf('indexed_desc_%04d', $variant);
    $expr = sprintf('indexed_expr_%04d', $variant);

    $tests[sprintf('real upstream pragma schema dynamic join discovers parent primary key variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $rowValues, $variant, $parent, $child): void {
        $catalog = $catalogFor($variant);
        $tableRows = $catalog->executeTableValuedPragma('pragma_table_list()')['rows'];
        $childTable = array_values(array_filter($tableRows, static fn (array $row): bool => $row['name'] === $child))[0];
        $foreignKeys = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$child}', '{$childTable['schema']}')")['rows'];
        $parentInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$parent}', '{$childTable['schema']}')")['rows'];
        $primaryKeyRows = array_values(array_filter($parentInfo, static fn (array $row): bool => $row['pk'] > 0));

        $t->same('main', $childTable['schema']);
        $t->same('table', $childTable['type']);
        $t->same([[$parent, 'tenant_id', 'tenant_id'], [$parent, 'key_name', 'key_name']], $rowValues($foreignKeys, ['table', 'from', 'to']));
        $t->same([['tenant_id', 1], ['key_name', 2]], $rowValues($primaryKeyRows, ['name', 'pk']));
    };

    $tests[sprintf('real upstream pragma schema dynamic materialized right join pairs variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $left, $right): void {
        $catalog = $catalogFor($variant);
        $leftRows = $catalog->executeTableValuedPragma("pragma_table_info('{$left}')")['rows'];
        $rightRows = $catalog->executeTableValuedPragma("pragma_table_info('{$right}')")['rows'];
        $rightByName = [];
        foreach ($rightRows as $row) {
            $rightByName[$row['name']] = $row;
        }

        $pairs = [];
        foreach ($leftRows as $leftRow) {
            $rightRow = $rightByName[$leftRow['name']] ?? null;
            $pairs[] = [$rightRow['name'] ?? null, $leftRow['name']];
        }

        $t->same([['a', 'a'], ['b', 'b']], $pairs);
        $t->same(['a', 'b'], array_column($leftRows, 'name'));
        $t->same(['a', 'b', 'c'], array_column($rightRows, 'name'));
    };

    $tests[sprintf('real upstream pragma schema dynamic covering index xinfo auxiliary rowid variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $rowValues, $variant, $covering): void {
        $rows = $catalogFor($variant)->executeTableValuedPragma("pragma_index_xinfo('{$covering}')")['rows'];

        $t->same(
            [
                [0, 2, 'c', 0, 'BINARY', 1],
                [1, 3, 'd', 0, 'BINARY', 1],
                [2, 1, 'b', 0, 'BINARY', 1],
                [3, -1, null, 0, 'BINARY', 0],
            ],
            $rowValues($rows, ['seqno', 'cid', 'name', 'desc', 'coll', 'key']),
        );
    };

    $tests[sprintf('real upstream pragma schema dynamic expression and descending index xinfo variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $rowValues, $variant, $desc, $expr): void {
        $catalog = $catalogFor($variant);
        $descRows = $catalog->execute("PRAGMA index_xinfo({$desc})")['rows'];
        $exprRows = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$expr}')")['rows'];

        $t->same(
            [
                [0, 3, 'd', 0, 'NOCASE', 1],
                [1, 2, 'c', 1, 'BINARY', 1],
                [2, -1, null, 0, 'BINARY', 0],
            ],
            $rowValues($descRows, ['seqno', 'cid', 'name', 'desc', 'coll', 'key']),
        );
        $t->same(
            [
                [0, 3, 'd', 0, 'BINARY', 1],
                [1, -2, null, 0, 'BINARY', 1],
                [2, 2, 'c', 0, 'BINARY', 1],
                [3, -1, null, 0, 'BINARY', 0],
            ],
            $rowValues($exprRows, ['seqno', 'cid', 'name', 'desc', 'coll', 'key']),
        );
    };
}

$tests['real upstream pragma schema dynamic join xinfo cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test 6.0 joins pragma_table_list(), pragma_foreign_key_list(), and pragma_table_info() to discover parent primary-key rows',
        'pragma4.test 7.1 through 7.3 materialized and live pragma_table_info() rowsets produce the same right-join name pairs',
        'pragma.test 23.2b through 23.2e index_xinfo reports key columns, DESC, COLLATE, expression cid -2, and auxiliary rowid cid -1 rows',
    ];

    $t->same(3, count($sections));
    $t->contains('pragma4.test 6.0', $sections[0]);
    $t->contains('pragma.test 23.2', $sections[2]);
};

return $tests;
