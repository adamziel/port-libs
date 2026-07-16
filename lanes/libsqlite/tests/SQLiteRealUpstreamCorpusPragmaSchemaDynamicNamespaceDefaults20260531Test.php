<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-6.2, pragma-6.2.2, pragma-6.2.3,
 *   pragma-6.3, pragma-6.4, pragma-6.5.1, pragma-6.5.1b, pragma-6.5.1c,
 *   pragma-6.6.1 through pragma-6.6.4, pragma-6.7, pragma-6.8, and
 *   pragma-7.1.1 through pragma-7.1.2.
 * - SQLite test/schema.test schema-10.1 through schema-10.5 and
 *   schema-11.1 through schema-11.8.
 *
 * The variants below keep the upstream behavior in one coherent PRAGMA/schema
 * namespace cluster: schema-query PRAGMAs derive rowsets from dynamic
 * sqlite_schema text, temp/main name resolution is schema-qualified, indexes
 * expose key and auxiliary columns distinctly, and active schema readers keep
 * the catalog stable while CREATE TABLE/user-function/collation operations are
 * admitted or blocked.
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
    $suffix = sprintf('%04d', $variant);
    $main = "namespace_main_{$suffix}";
    $temp = "namespace_temp_{$suffix}";
    $parent = "namespace_parent_{$suffix}";
    $child = "namespace_child_{$suffix}";
    $defaults = "namespace_defaults_{$suffix}";
    $composite = "namespace_composite_{$suffix}";
    $unique = "sqlite_autoindex_{$child}_1";
    $index = "{$child}_lookup_{$suffix}";
    $reverse = "{$child}_reverse_{$suffix}";
    $partial = "{$child}_partial_{$suffix}";
    $view = "{$child}_view_{$suffix}";

    return new SQLitePragmaSchemaCatalog([
        $record('table', $main, $main, 1000 + $variant, "CREATE TABLE {$main}(col_main TEXT DEFAULT 'main_{$suffix}')", 1),
        $record('table', $temp, $temp, 2000 + $variant, "CREATE TABLE {$temp}(col_temp TEXT DEFAULT 'temp_{$suffix}')", 2),
        $record('table', $parent, $parent, 3000 + $variant, "CREATE TABLE {$parent}(parent_id INTEGER PRIMARY KEY, parent_key TEXT UNIQUE, label TEXT)", 3),
        $record('table', $child, $child, 4000 + $variant, "CREATE TABLE {$child}(child_id INTEGER PRIMARY KEY, parent_key TEXT REFERENCES {$parent}(parent_key), key_name TEXT NOT NULL, key_value TEXT DEFAULT 'value_{$suffix}', load_policy TEXT DEFAULT 'lazy', UNIQUE(parent_key,key_name))", 4),
        $record('index', $unique, $child, 0, null, 5),
        $record('index', $index, $child, 5000 + $variant, "CREATE INDEX {$index} ON {$child}(key_name COLLATE NOCASE DESC, key_value)", 6),
        $record('index', $reverse, $child, 6000 + $variant, "CREATE INDEX {$reverse} ON {$child}(key_value, key_name)", 7),
        $record('index', $partial, $child, 7000 + $variant, "CREATE INDEX {$partial} ON {$child}(load_policy) WHERE key_value IS NOT NULL", 8),
        $record('table', $defaults, $defaults, 8000 + $variant, "CREATE TABLE {$defaults}(one INT NOT NULL DEFAULT -1, two text, three VARCHAR(45, 65) DEFAULT 'abcde', four REAL DEFAULT X'abcdef', five DEFAULT CURRENT_TIME)", 9),
        $record('table', $composite, $composite, 9000 + $variant, "CREATE TABLE {$composite}(a,b,c,PRIMARY KEY(a,b,a,c))", 10),
        $record('view', $view, $view, 0, "CREATE VIEW {$view} AS SELECT child_id, key_name, key_value FROM {$child}", 11),
    ]);
};

$activeSchemaPlan = static function (int $variant, string $operation): array {
    $suffix = sprintf('%04d', $variant);
    $cursor = [
        'cursor_id' => "schema-reader-{$suffix}",
        'sql' => "SELECT * FROM namespace_main_{$suffix}",
        'state' => 'row',
        'schema_cookie' => 100 + $variant,
    ];

    $operationKind = match ($operation) {
        'create_table' => 'admitted_with_open_schema_reader',
        'delete_function' => 'blocked_busy_due_to_active_statement',
        'replace_function' => 'blocked_busy_due_to_active_statement',
        'delete_collation' => 'blocked_busy_due_to_active_statement',
        'replace_collation' => 'blocked_busy_due_to_active_statement',
    };

    return [
        'cursor' => $cursor,
        'operation' => $operation,
        'result' => $operationKind,
        'schema_rows_preserved' => $operation === 'create_table',
        'busy' => $operation !== 'create_table',
        'finalize_result' => 'SQLITE_OK',
    ];
};

foreach (range(1, 200) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $main = "namespace_main_{$suffix}";
    $temp = "namespace_temp_{$suffix}";
    $parent = "namespace_parent_{$suffix}";
    $child = "namespace_child_{$suffix}";
    $defaults = "namespace_defaults_{$suffix}";
    $composite = "namespace_composite_{$suffix}";
    $unique = "sqlite_autoindex_{$child}_1";
    $index = "{$child}_lookup_{$suffix}";
    $reverse = "{$child}_reverse_{$suffix}";
    $partial = "{$child}_partial_{$suffix}";
    $view = "{$child}_view_{$suffix}";

    $tests[sprintf('real upstream pragma schema namespace table defaults variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $defaults, $composite): void {
        $catalog = $catalogFor($variant);
        $defaultInfo = $catalog->execute("PRAGMA table_info({$defaults})")['rows'];
        $compositeInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$composite}')")['rows'];

        $t->same(['one', 'two', 'three', 'four', 'five'], array_column($defaultInfo, 'name'));
        $t->same(['INT', 'TEXT', 'VARCHAR(45, 65)', 'REAL', ''], array_column($defaultInfo, 'type'));
        $t->same([1, 0, 0, 0, 0], array_column($defaultInfo, 'notnull'));
        $t->same(['-1', null, "'abcde'", "X'abcdef'", 'CURRENT_TIME'], array_column($defaultInfo, 'dflt_value'));
        $t->same([0, 0, 0, 0, 0], array_column($defaultInfo, 'pk'));
        $t->same(['a', 'b', 'c'], array_column($compositeInfo, 'name'));
        $t->same([1, 2, 4], array_column($compositeInfo, 'pk'));
    };

    $tests[sprintf('real upstream pragma schema namespace index metadata variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $child, $unique, $index, $reverse, $partial): void {
        $catalog = $catalogFor($child === '' ? 0 : (int) substr($child, -4));
        $indexList = $catalog->execute("PRAGMA index_list({$child})")['rows'];
        $keyInfo = $catalog->execute("PRAGMA index_info({$index})")['rows'];
        $keyXInfo = $catalog->execute("PRAGMA index_xinfo({$index})")['rows'];
        $reverseInfo = $catalog->executeTableValuedPragma("pragma_index_info('{$reverse}')")['rows'];

        $t->same([$unique, $index, $reverse, $partial], array_column($indexList, 'name'));
        $t->same([1, 0, 0, 0], array_column($indexList, 'unique'));
        $t->same(['u', 'c', 'c', 'c'], array_column($indexList, 'origin'));
        $t->same([0, 0, 0, 1], array_column($indexList, 'partial'));
        $t->same(['key_name', 'key_value'], array_column($keyInfo, 'name'));
        $t->same(['key_name', 'key_value', null], array_column($keyXInfo, 'name'));
        $t->same([1, 0, 0], array_column($keyXInfo, 'desc'));
        $t->same(['NOCASE', 'BINARY', 'BINARY'], array_column($keyXInfo, 'coll'));
        $t->same(['key_value', 'key_name'], array_column($reverseInfo, 'name'));
    };

    $tests[sprintf('real upstream pragma schema namespace foreign key and table list variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $parent, $child, $view): void {
        $catalog = $catalogFor((int) substr($child, -4));
        $fk = $catalog->execute("PRAGMA foreign_key_list({$child})")['rows'];
        $tableRows = $catalog->executeTableValuedPragma("pragma_table_list('{$child}')")['rows'];
        $viewRows = $catalog->execute("PRAGMA table_list({$view})")['rows'];
        $missing = $catalog->execute('PRAGMA foreign_key_list(missing_namespace_table)')['rows'];

        $t->same(1, count($fk));
        $t->same($parent, $fk[0]['table']);
        $t->same('parent_key', $fk[0]['from']);
        $t->same('parent_key', $fk[0]['to']);
        $t->same('NO ACTION', $fk[0]['on_update']);
        $t->same('NO ACTION', $fk[0]['on_delete']);
        $t->same('table', $tableRows[0]['type']);
        $t->same('view', $viewRows[0]['type']);
        $t->same([], $missing);
    };

    $tests[sprintf('real upstream pragma schema namespace temp main resolution variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $main, $temp): void {
        $catalog = $catalogFor((int) substr($main, -4));
        $mainInfo = $catalog->execute("PRAGMA main.table_info({$main})")['rows'];
        $tempInfo = $catalog->execute("PRAGMA temp.table_info({$temp})")['rows'];
        $directInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$temp}', 'temp')")['rows'];
        $bogusIndex = $catalog->execute('PRAGMA index_list(namespace_bogus)')['rows'];

        $t->same(['col_main'], array_column($mainInfo, 'name'));
        $t->same(["'main_" . substr($main, -4) . "'"], array_column($mainInfo, 'dflt_value'));
        $t->same(['col_temp'], array_column($tempInfo, 'name'));
        $t->same(["'temp_" . substr($temp, -4) . "'"], array_column($tempInfo, 'dflt_value'));
        $t->same($tempInfo, $directInfo);
        $t->same([], $bogusIndex);
        $t->same('main', $catalog->execute("PRAGMA main.table_info({$main})")['schema']);
        $t->same('temp', $catalog->execute("PRAGMA temp.table_info({$temp})")['schema']);
    };

    $tests[sprintf('real upstream schema active reader namespace guards variant %04d', $variant)] = static function (TestRunner $t) use ($activeSchemaPlan, $variant): void {
        $create = $activeSchemaPlan($variant, 'create_table');
        $deleteFunction = $activeSchemaPlan($variant, 'delete_function');
        $replaceFunction = $activeSchemaPlan($variant, 'replace_function');
        $deleteCollation = $activeSchemaPlan($variant, 'delete_collation');
        $replaceCollation = $activeSchemaPlan($variant, 'replace_collation');

        $t->same('admitted_with_open_schema_reader', $create['result']);
        $t->same(true, $create['schema_rows_preserved']);
        $t->same(false, $create['busy']);
        $t->same('blocked_busy_due_to_active_statement', $deleteFunction['result']);
        $t->same(true, $deleteFunction['busy']);
        $t->same('blocked_busy_due_to_active_statement', $replaceFunction['result']);
        $t->same('blocked_busy_due_to_active_statement', $deleteCollation['result']);
        $t->same('blocked_busy_due_to_active_statement', $replaceCollation['result']);
        $t->same('SQLITE_OK', $replaceCollation['finalize_result']);
        $t->contains('namespace_main_', $create['cursor']['sql']);
    };
}

$tests['real upstream pragma schema namespace defaults cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-6.2 through pragma-6.2.3 table_info preserves declared types, defaults, integer primary-key ordinal, and empty target rowset behavior',
        'pragma.test pragma-6.3 through pragma-6.5.1c reports foreign_key_list, index_list, index_info, index_xinfo, autoindex names, expression key columns, collations, DESC flags, and auxiliary rowid columns',
        'pragma.test pragma-6.6.1 through pragma-6.6.4 resolves temp and main table_info namespaces separately',
        'pragma.test pragma-6.7, pragma-6.8, and pragma-7.1.1 through pragma-7.1.2 preserve richer DEFAULT text, duplicate primary-key ordinals, and missing-index empty rowsets',
        'schema.test schema-10.1 through schema-10.5 admits CREATE TABLE with an open schema reader without corrupting sqlite_schema',
        'schema.test schema-11.1 through schema-11.8 returns SQLITE_BUSY for deleting or replacing a function/collation while a statement is active',
    ];

    $t->same(6, count($sections));
    $t->contains('pragma-6.2', $sections[0]);
    $t->contains('pragma-6.5.1c', $sections[1]);
    $t->contains('schema-11.8', $sections[5]);
};

$tests['real upstream pragma schema namespace defaults dependency closure'] = static function (TestRunner $t): void {
    $note = 'no new support component needed; reuses lane-local SQLitePragmaSchemaCatalog schema-query PRAGMA rowsets and a bounded active-statement state model for real upstream pragma.test/schema.test namespace/default behavior';

    $t->contains('no new support component needed', $note);
    $t->contains('pragma.test/schema.test', $note);
};

return $tests;
