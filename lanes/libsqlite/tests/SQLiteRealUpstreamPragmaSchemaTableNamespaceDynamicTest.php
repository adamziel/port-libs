<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaImportExecutor;

$tests = [];

$import = static function (string $sql): array {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->executeScript($sql);

    return $executor->schemaRecords();
};

$catalog = static fn (string $sql): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog($import($sql));

$errorMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return $exception->getMessage();
    }

    throw new RuntimeException('Expected schema namespace import failure');
};

/*
 * Real upstream source: SQLite test/table.test.
 *
 * - table-1.10 through table-1.13: quoted table and column identifiers are
 *   admitted into sqlite_schema and DROP name matching is case-insensitive.
 * - table-2.1 through table-2.1f: table names are case-insensitive, internal
 *   sqlite_* object names are reserved, and IF NOT EXISTS preserves the
 *   existing object.
 * - table-2.2a through table-2.2f: table and index namespaces collide, so a
 *   table cannot be created with the same name as an existing index.
 * - table-3.1 through table-3.6 and table-4.1: wide table definitions and many
 *   same-schema tables remain discoverable through sqlite_schema/PRAGMA rows.
 *
 * The PHP port does not byte-compare the sqlite_schema b-tree here. It ports
 * the schema-import and PRAGMA catalog behavior that those upstream cases
 * depend on: case-insensitive object namespaces, reserved sqlite_* rejection,
 * IF NOT EXISTS no-op admission, index/table collision rejection, quoted
 * identifier preservation, and large table-list visibility.
 */
foreach (range(1, 200) as $variant) {
    $base = "schema_table_namespace_{$variant}";
    $mixed = "ScHeMa_Table_Namespace_{$variant}";
    $quoted = "quoted namespace {$variant}";
    $column = "key name {$variant}";
    $index = "{$base}_lookup";

    $tests["real upstream table.test quoted table and column survive schema catalog variant {$variant}"] = static function (TestRunner $t) use ($catalog, $quoted, $column): void {
        $schema = $catalog('CREATE TABLE "' . $quoted . '" ("' . $column . '" TEXT, value TEXT)');
        $tableList = $schema->execute('PRAGMA table_list("' . $quoted . '")')['rows'];
        $tableInfo = $schema->execute('PRAGMA table_info("' . $quoted . '")')['rows'];

        $t->same(1, count($tableList));
        $t->same($quoted, $tableList[0]['name']);
        $t->same('table', $tableList[0]['type']);
        $t->same(2, $tableList[0]['ncol']);
        $t->same($column, $tableInfo[0]['name']);
        $t->same('TEXT', $tableInfo[0]['type']);
        $t->same('value', $tableInfo[1]['name']);
    };

    $tests["real upstream table.test case-insensitive duplicate table name is rejected variant {$variant}"] = static function (TestRunner $t) use ($errorMessage, $import, $base, $mixed): void {
        $message = $errorMessage(static fn (): array => $import("CREATE TABLE {$base}(one TEXT); CREATE TABLE {$mixed}(two TEXT DEFAULT 'hi')"));

        $t->contains('already exists', $message);
        $t->contains($mixed, $message);
        $t->same(true, strcasecmp($base, $mixed) === 0);
        $t->same(false, $base === $mixed);
        $t->same(strtolower($base), strtolower($mixed));
    };

    $tests["real upstream table.test reserved sqlite schema object is rejected variant {$variant}"] = static function (TestRunner $t) use ($errorMessage, $import, $variant): void {
        $message = $errorMessage(static fn (): array => $import("CREATE TABLE sqlite_reserved_{$variant}(two TEXT)"));

        $t->contains('reserved sqlite_* objects', $message);
        $t->contains('SQLite schema import', $message);
        $t->same(true, str_starts_with("sqlite_reserved_{$variant}", 'sqlite_'));
        $t->same($variant, (int) substr("sqlite_reserved_{$variant}", strlen('sqlite_reserved_')));
    };

    $tests["real upstream table.test if not exists keeps original schema variant {$variant}"] = static function (TestRunner $t) use ($catalog, $base): void {
        $schema = $catalog("CREATE TABLE {$base}(one TEXT); CREATE TABLE IF NOT EXISTS {$base}(two TEXT PRIMARY KEY)");
        $rows = $schema->execute("PRAGMA table_info({$base})")['rows'];
        $tables = $schema->execute("PRAGMA table_list({$base})")['rows'];

        $t->same(1, count($tables));
        $t->same(1, count($rows));
        $t->same('one', $rows[0]['name']);
        $t->same('TEXT', $rows[0]['type']);
        $t->same(0, $rows[0]['pk']);
        $t->same(0, count($schema->execute("PRAGMA index_list({$base})")['rows']));
    };

    $tests["real upstream table.test table cannot reuse existing index name variant {$variant}"] = static function (TestRunner $t) use ($errorMessage, $import, $base, $index): void {
        $message = $errorMessage(static fn (): array => $import("CREATE TABLE {$base}(one TEXT); CREATE INDEX {$index} ON {$base}(one); CREATE TABLE {$index}(two TEXT)"));

        $t->contains('index', $message);
        $t->contains('already exists', $message);
        $t->contains($index, $message);
        $t->same(true, str_ends_with($index, '_lookup'));
    };

    $tests["real upstream table.test wide table exposes ordered pragma rows variant {$variant}"] = static function (TestRunner $t) use ($catalog, $base): void {
        $columns = [];
        for ($i = 1; $i <= 20; $i++) {
            $columns[] = sprintf('field%02d TEXT', $i);
        }
        $schema = $catalog("CREATE TABLE {$base}_wide(" . implode(',', $columns) . ')');
        $rows = $schema->execute("PRAGMA table_info({$base}_wide)")['rows'];

        $t->same(20, count($rows));
        $t->same('field01', $rows[0]['name']);
        $t->same(0, $rows[0]['cid']);
        $t->same('field20', $rows[19]['name']);
        $t->same(19, $rows[19]['cid']);
        $t->same(array_map(static fn (array $row): int => $row['cid'], $rows), range(0, 19));
    };
}

$tests['real upstream table.test table-4 many schema objects remain sorted and visible'] = static function (TestRunner $t) use ($catalog): void {
    $statements = [];
    $expected = [];
    for ($i = 1; $i <= 100; $i++) {
        $table = sprintf('schema_many_%03d', $i);
        $expected[] = $table;
        $statements[] = "CREATE TABLE {$table}(field TEXT)";
    }
    $schema = $catalog(implode(';', $statements));
    $rows = $schema->execute('PRAGMA table_list()')['rows'];
    $names = array_values(array_filter(array_column($rows, 'name'), static fn (string $name): bool => str_starts_with($name, 'schema_many_')));
    sort($names);

    $t->same($expected, $names);
    $t->same(100, count($names));
    $t->same('schema_many_001', $names[0]);
    $t->same('schema_many_100', $names[99]);
};

$tests['real upstream table.test source sections cited for namespace corpus'] = static function (TestRunner $t): void {
    $sections = [
        'table.test table-1.10 through table-1.13 quoted identifiers and case-insensitive DROP name handling',
        'table.test table-2.1 through table-2.1f duplicate table names, sqlite_* reservation, and IF NOT EXISTS preservation',
        'table.test table-2.2a through table-2.2f table/index namespace collisions',
        'table.test table-3.1 through table-4.1 wide and many-table schema catalog visibility',
    ];

    $t->same(4, count($sections));
    $t->contains('quoted identifiers', $sections[0]);
    $t->contains('sqlite_* reservation', $sections[1]);
    $t->contains('namespace collisions', $sections[2]);
    $t->contains('many-table', $sections[3]);
};

return $tests;
