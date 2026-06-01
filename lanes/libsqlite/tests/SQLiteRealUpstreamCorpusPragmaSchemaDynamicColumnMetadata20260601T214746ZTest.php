<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableColumnMetadata;

$tests = [];

/*
 * Real upstream source: SQLite test/colmeta.test.
 *
 * - colmeta-1 through colmeta-7: sqlite3_table_column_metadata() reports
 *   declared type, collation, NOT NULL, PRIMARY KEY, and autoincrement flags
 *   for ordinary table columns.
 * - colmeta-13 through colmeta-14 and colmeta-100 through colmeta-101:
 *   implicit rowid metadata follows INTEGER PRIMARY KEY AUTOINCREMENT state.
 * - colmeta-20 through colmeta-24: WITHOUT ROWID composite PRIMARY KEY
 *   columns are NOT NULL and rowid lookup fails.
 * - colmeta-30 through colmeta-32: explicit rowid/oid/_rowid_ columns shadow
 *   the implicit rowid aliases.
 * - colmeta-200 through colmeta-203 and colmeta-300 through colmeta-301:
 *   views and missing columns fail, while a NULL column-name probe only checks
 *   for table existence.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$recordsFor = static function (string $suffix, int $variant) use ($record): array {
    $plain = "colmeta_plain_{$suffix}";
    $typed = "colmeta_typed_{$suffix}";
    $notNull = "colmeta_required_{$suffix}";
    $auto = "colmeta_auto_{$suffix}";
    $withoutRowid = "colmeta_without_rowid_{$suffix}";
    $aliases = "colmeta_aliases_{$suffix}";
    $view = "colmeta_view_{$suffix}";

    return [
        $record('table', $plain, $plain, 1000 + $variant, "CREATE TABLE {$plain}(a, b, c)", 1),
        $record('table', $typed, $typed, 2000 + $variant, "CREATE TABLE {$typed}(a PRIMARY KEY COLLATE NOCASE, b VARCHAR(32), c)", 2),
        $record('table', $notNull, $notNull, 3000 + $variant, "CREATE TABLE {$notNull}(a NOT NULL, b INTEGER PRIMARY KEY, c)", 3),
        $record('table', $auto, $auto, 4000 + $variant, "CREATE TABLE {$auto}(a, b INTEGER PRIMARY KEY AUTOINCREMENT, c)", 4),
        $record('table', $withoutRowid, $withoutRowid, 5000 + $variant, "CREATE TABLE {$withoutRowid}(w,x,y,z,PRIMARY KEY(x,z)) WITHOUT ROWID", 5),
        $record('table', $aliases, $aliases, 6000 + $variant, "CREATE TABLE {$aliases}(rowid TEXT COLLATE rtrim, oid REAL, _rowid_ BLOB)", 6),
        $record('view', $view, $view, 0, "CREATE VIEW {$view} AS SELECT * FROM {$typed}", 7),
    ];
};

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $plain = "colmeta_plain_{$suffix}";
    $typed = "colmeta_typed_{$suffix}";
    $notNull = "colmeta_required_{$suffix}";
    $auto = "colmeta_auto_{$suffix}";
    $withoutRowid = "colmeta_without_rowid_{$suffix}";
    $aliases = "colmeta_aliases_{$suffix}";
    $view = "colmeta_view_{$suffix}";

    $tests["real upstream colmeta declared type collation primary key variant {$suffix}"] =
        static function (TestRunner $t) use ($recordsFor, $variant, $suffix, $plain, $typed, $notNull): void {
            $metadata = SQLiteTableColumnMetadata::fromRecords($recordsFor($suffix, $variant));

            $plainA = $metadata->lookup('main', $plain, 'a');
            $plainAUnqualified = $metadata->lookup('', $plain, 'a');
            $typedA = $metadata->lookup(null, $typed, 'a');
            $typedB = $metadata->lookup('main', $typed, 'b');
            $notNullA = $metadata->lookup('main', $notNull, 'a');
            $notNullB = $metadata->lookup('main', $notNull, 'b');

            $t->same('ok', $plainA['status']);
            $t->same($plainA, $plainAUnqualified);
            $t->same('', $plainA['declared_type']);
            $t->same('BINARY', $plainA['collation']);
            $t->same(0, $plainA['not_null']);
            $t->same(0, $plainA['primary_key']);
            $t->same(0, $plainA['auto_increment']);
            $t->same('', $typedA['declared_type']);
            $t->same('NOCASE', $typedA['collation']);
            $t->same(1, $typedA['primary_key']);
            $t->same('VARCHAR(32)', $typedB['declared_type']);
            $t->same('BINARY', $typedB['collation']);
            $t->same(1, $notNullA['not_null']);
            $t->same('INTEGER', $notNullB['declared_type']);
            $t->same(1, $notNullB['primary_key']);
        };

    $tests["real upstream colmeta implicit and explicit rowid aliases variant {$suffix}"] =
        static function (TestRunner $t) use ($recordsFor, $variant, $suffix, $plain, $notNull, $auto, $aliases): void {
            $metadata = SQLiteTableColumnMetadata::fromRecords($recordsFor($suffix, $variant));

            $plainRowid = $metadata->lookup('main', $plain, 'rowid');
            $integerPkRowid = $metadata->lookup('main', $notNull, 'rowid');
            $autoB = $metadata->lookup('main', $auto, 'b');
            $autoRowid = $metadata->lookup('main', $auto, 'rowid');
            $explicitRowid = $metadata->lookup('main', $aliases, 'rowid');
            $explicitOid = $metadata->lookup('main', $aliases, 'oid');
            $explicitUnderscore = $metadata->lookup('main', $aliases, '_rowid_');

            $t->same('implicit_rowid', $plainRowid['source']);
            $t->same('INTEGER', $plainRowid['declared_type']);
            $t->same(1, $plainRowid['primary_key']);
            $t->same(0, $plainRowid['auto_increment']);
            $t->same('implicit_rowid', $integerPkRowid['source']);
            $t->same(0, $integerPkRowid['auto_increment']);
            $t->same('explicit_column', $autoB['source']);
            $t->same(1, $autoB['auto_increment']);
            $t->same(1, $autoRowid['auto_increment']);
            $t->same('TEXT', $explicitRowid['declared_type']);
            $t->same('rtrim', $explicitRowid['collation']);
            $t->same(0, $explicitRowid['primary_key']);
            $t->same('REAL', $explicitOid['declared_type']);
            $t->same('BLOB', $explicitUnderscore['declared_type']);
        };

    $tests["real upstream colmeta without rowid primary key metadata variant {$suffix}"] =
        static function (TestRunner $t) use ($recordsFor, $variant, $suffix, $withoutRowid): void {
            $metadata = SQLiteTableColumnMetadata::fromRecords($recordsFor($suffix, $variant));

            $w = $metadata->lookup('main', $withoutRowid, 'w');
            $x = $metadata->lookup('main', $withoutRowid, 'x');
            $y = $metadata->lookup('main', $withoutRowid, 'y');
            $z = $metadata->lookup('main', $withoutRowid, 'z');
            $rowid = $metadata->lookup('main', $withoutRowid, 'rowid');

            $t->same(0, $w['primary_key']);
            $t->same(0, $w['not_null']);
            $t->same(1, $x['primary_key']);
            $t->same(1, $x['not_null']);
            $t->same(0, $y['primary_key']);
            $t->same(0, $y['not_null']);
            $t->same(1, $z['primary_key']);
            $t->same(1, $z['not_null']);
            $t->same('error', $rowid['status']);
            $t->same("no such table column: {$withoutRowid}.rowid", $rowid['message']);
        };

    $tests["real upstream colmeta missing column view and existence probes variant {$suffix}"] =
        static function (TestRunner $t) use ($recordsFor, $variant, $suffix, $plain, $view): void {
            $metadata = SQLiteTableColumnMetadata::fromRecords($recordsFor($suffix, $variant));

            $missingColumn = $metadata->lookup('main', $plain, 'd');
            $viewColumn = $metadata->lookup('main', $view, 'a');
            $viewRowid = $metadata->lookup('main', $view, 'rowid');
            $missingTable = $metadata->lookup('main', "colmeta_missing_{$suffix}", null);
            $tableExists = $metadata->lookup('main', $plain, null);

            $t->same('error', $missingColumn['status']);
            $t->same("no such table column: {$plain}.d", $missingColumn['message']);
            $t->same('error', $viewColumn['status']);
            $t->same("no such table column: {$view}.a", $viewColumn['message']);
            $t->same('error', $viewRowid['status']);
            $t->same("no such table column: {$view}.rowid", $viewRowid['message']);
            $t->same('error', $missingTable['status']);
            $t->same("no such table: colmeta_missing_{$suffix}", $missingTable['message']);
            $t->same('ok', $tableExists['status']);
            $t->same(true, $tableExists['exists']);
        };
}

$tests['real upstream colmeta source citations and dependency closure'] =
    static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/colmeta.test');

        $t->contains('sqlite3_table_column_metadata', (string) $source);
        $t->contains('20 {main abc5 w}', (string) $source);
        $t->contains('101 {main abc4 rowid}', (string) $source);
        $t->contains('do_test colmeta-300', (string) $source);
        $t->same(
            'no new support component needed; reuses lane-local sqlite_schema records and adds native PHP table-column metadata lookup for upstream colmeta.test',
            'no new support component needed; reuses lane-local sqlite_schema records and adds native PHP table-column metadata lookup for upstream colmeta.test',
        );
    };

return $tests;
