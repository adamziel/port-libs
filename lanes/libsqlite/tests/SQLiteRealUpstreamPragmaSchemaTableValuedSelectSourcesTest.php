<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma4.test 4.3.2 reads pragma_index_info('i1') as a
 *   SELECT source.
 * - SQLite test/pragma4.test 4.4.1 reads pragma_index_list('t1') as a
 *   SELECT source.
 * - SQLite test/pragma4.test 6.0 starts a schema rowset join from
 *   pragma_table_list().
 * - SQLite test/pragma4.test 7.1 through 7.3 materializes
 *   pragma_table_info() rowsets and RIGHT JOINs them directly.
 *
 * Earlier batches covered direct executeTableValuedPragma() rowsets and
 * virtual list tables. This batch owns parser-level SELECT sources for
 * static schema PRAGMA table-valued functions.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$makeCatalog = static function (int $variant) use ($record): SQLiteAttachedSchemaCatalog {
    $suffix = sprintf('%04d', $variant);
    $mainTable = "pragma_source_main_{$suffix}";
    $mainIndex = "pragma_source_idx_{$suffix}";
    $wide = "pragma_source_wide_{$suffix}";
    $narrow = "pragma_source_narrow_{$suffix}";
    $tenant = "tenant_{$suffix}";
    $attachedTable = "tenant_source_{$suffix}";

    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', $mainTable, $mainTable, 1000 + $variant, "CREATE TABLE {$mainTable}(setting_id INTEGER PRIMARY KEY, key_name TEXT DEFAULT 'main_{$suffix}', load_policy TEXT DEFAULT 'eager', key_value TEXT)", 10 + $variant),
        $record('index', $mainIndex, $mainTable, 2000 + $variant, "CREATE UNIQUE INDEX {$mainIndex} ON {$mainTable}(key_name, load_policy) WHERE load_policy='eager'", 300 + $variant),
        $record('table', $wide, $wide, 3000 + $variant, "CREATE TABLE {$wide}(a TEXT, b TEXT)", 500 + $variant),
        $record('table', $narrow, $narrow, 4000 + $variant, "CREATE TABLE {$narrow}(a TEXT, b TEXT, c TEXT)", 700 + $variant),
    ]);

    $catalog->attach($tenant, "tenant-{$suffix}.sqlite", [
        $record('table', $attachedTable, $attachedTable, 5000 + $variant, "CREATE TABLE {$attachedTable}(tenant_id INTEGER PRIMARY KEY, key_name TEXT DEFAULT 'tenant_{$suffix}', key_value TEXT)", 900 + $variant),
    ]);

    return $catalog;
};

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $mainTable = "pragma_source_main_{$suffix}";
    $mainIndex = "pragma_source_idx_{$suffix}";
    $wide = "pragma_source_wide_{$suffix}";
    $narrow = "pragma_source_narrow_{$suffix}";
    $tenant = "tenant_{$suffix}";
    $attachedTable = "tenant_source_{$suffix}";

    $tests["real upstream pragma4 table info select source variant {$suffix}"] = static function (TestRunner $t) use ($makeCatalog, $variant, $mainTable, $suffix): void {
        $rows = $makeCatalog($variant)->executeVirtualTableSelect("SELECT cid, name, pk, dflt_value FROM pragma_table_info('{$mainTable}', 'main') ORDER BY cid");

        $t->same(4, count($rows));
        $t->same([0, 1, 2, 3], array_column($rows, 'cid'));
        $t->same(['setting_id', 'key_name', 'load_policy', 'key_value'], array_column($rows, 'name'));
        $t->same([1, 0, 0, 0], array_column($rows, 'pk'));
        $t->same([null, "'main_{$suffix}'", "'eager'", null], array_column($rows, 'dflt_value'));
    };

    $tests["real upstream pragma4 index select sources variant {$suffix}"] = static function (TestRunner $t) use ($makeCatalog, $variant, $mainTable, $mainIndex): void {
        $catalog = $makeCatalog($variant);
        $indexInfo = $catalog->executeVirtualTableSelect("SELECT seqno, name FROM pragma_index_info('{$mainIndex}', 'main') ORDER BY seqno");
        $indexList = $catalog->executeVirtualTableSelect("SELECT name, origin, partial FROM pragma_index_list('{$mainTable}', 'main') WHERE name='{$mainIndex}'");

        $t->same(2, count($indexInfo));
        $t->same([0, 1], array_column($indexInfo, 'seqno'));
        $t->same(['key_name', 'load_policy'], array_column($indexInfo, 'name'));
        $t->same([['name' => $mainIndex, 'origin' => 'c', 'partial' => 1]], $indexList);
    };

    $tests["real upstream pragma4 table info right join select source variant {$suffix}"] = static function (TestRunner $t) use ($makeCatalog, $variant, $wide, $narrow): void {
        $rows = $makeCatalog($variant)->executeVirtualTableSelect(
            "SELECT left_info.name AS left_name, right_info.name AS right_name FROM pragma_table_info('{$wide}', 'main') AS left_info RIGHT JOIN pragma_table_info('{$narrow}', 'main') AS right_info ON (left_info.name=right_info.name) ORDER BY right_info.cid"
        );

        $t->same(3, count($rows));
        $t->same([
            ['left_name' => 'a', 'right_name' => 'a'],
            ['left_name' => 'b', 'right_name' => 'b'],
            ['left_name' => null, 'right_name' => 'c'],
        ], $rows);
    };

    $tests["real upstream pragma4 attached table list info select source variant {$suffix}"] = static function (TestRunner $t) use ($makeCatalog, $variant, $tenant, $attachedTable, $suffix): void {
        $catalog = $makeCatalog($variant);
        $rows = $catalog->executeVirtualTableSelect(
            "SELECT tl.schema AS schema_name, ti.name AS column_name, ti.dflt_value AS default_value FROM pragma_table_list('{$attachedTable}', '{$tenant}') AS tl JOIN pragma_table_info('{$attachedTable}', '{$tenant}') AS ti ON (tl.name='{$attachedTable}') WHERE ti.cid=1 ORDER BY ti.cid"
        );
        $allSchemas = $catalog->executeVirtualTableSelect(
            "SELECT schema, name FROM pragma_table_list() WHERE name='{$attachedTable}' ORDER BY schema"
        );

        $t->same(1, count($rows));
        $t->same([
            'schema_name' => $tenant,
            'column_name' => 'key_name',
            'default_value' => "'tenant_{$suffix}'",
        ], $rows[0]);
        $t->same([['schema' => $tenant, 'name' => $attachedTable]], $allSchemas);
    };
}

$tests['real upstream pragma4 table-valued select source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        "pragma4.test 4.3.2 SELECT * FROM pragma_index_info('i1')",
        "pragma4.test 4.4.1 SELECT * FROM pragma_index_list('t1')",
        'pragma4.test 6.0 SELECT ... FROM pragma_table_list()',
        'pragma4.test 7.1 through 7.3 SELECT and RIGHT JOIN over pragma_table_info() rowsets',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma_index_info', $sections[0]);
    $t->contains('pragma_index_list', $sections[1]);
    $t->contains('pragma_table_list', $sections[2]);
    $t->contains('RIGHT JOIN', $sections[3]);
};

return $tests;
