<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test pragma-6.* schema-query
 * pragmas, pragma-11.* collation_list, and table-valued PRAGMA coverage around
 * pragma_table_info/index_list/index_xinfo. This ports the behavior into the
 * PHP catalog model with many dynamic sqlite_schema shapes.
 */

$record = static fn (
    string $type,
    string $name,
    string $tableName,
    int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $tableName, $rootPage, $sql, $rowId);

$records = [];
$expected = [];
$rowId = 1;
for ($i = 0; $i < 125; $i++) {
    $table = sprintf('app_settings_%03d', $i);
    $index = sprintf('app_settings_%03d_key_idx', $i);
    $partial = sprintf('app_settings_%03d_partial_idx', $i);
    $expr = sprintf('app_settings_%03d_expr_idx', $i);
    $view = sprintf('app_settings_%03d_view', $i);
    $strict = ($i % 5) === 0;
    $withoutRowid = ($i % 7) === 0;
    if ($withoutRowid) {
        $strict = false;
    }

    $suffix = $withoutRowid ? ' WITHOUT ROWID' : ($strict ? ' STRICT' : '');
    $sql = sprintf(
        "CREATE TABLE %s(tenant_id INTEGER NOT NULL, key_name TEXT NOT NULL COLLATE NOCASE, key_value TEXT DEFAULT 'v%d', load_policy TEXT GENERATED ALWAYS AS (upper(key_name)) VIRTUAL, updated_at INTEGER, PRIMARY KEY(tenant_id, key_name))%s",
        $table,
        $i,
        $suffix,
    );
    $records[] = $record('table', $table, $table, 10 + ($i * 4), $sql, $rowId++);
    $records[] = $record('index', $index, $table, 11 + ($i * 4), sprintf('CREATE UNIQUE INDEX %s ON %s(key_name COLLATE NOCASE DESC, tenant_id)', $index, $table), $rowId++);
    $records[] = $record('index', $partial, $table, 12 + ($i * 4), sprintf("CREATE INDEX %s ON %s(updated_at) WHERE load_policy = 'YES'", $partial, $table), $rowId++);
    $records[] = $record('index', $expr, $table, 13 + ($i * 4), sprintf('CREATE INDEX %s ON %s(lower(key_name), length(key_value))', $expr, $table), $rowId++);
    $records[] = $record('view', $view, $view, 0, sprintf('CREATE VIEW %s AS SELECT tenant_id, key_name FROM %s', $view, $table), $rowId++);

    $expected[$table] = [
        'index' => $index,
        'partial' => $partial,
        'expr' => $expr,
        'view' => $view,
        'default' => "'v{$i}'",
        'strict' => $strict ? 1 : 0,
        'without_rowid' => $withoutRowid ? 1 : 0,
    ];
}

$catalog = new SQLitePragmaSchemaCatalog(
    $records,
    [
        ['name' => 'json_extract', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 2099200],
        ['name' => 'app_rank', 'builtin' => 0, 'type' => 'w', 'enc' => 'utf16le', 'narg' => 2, 'flags' => 0],
        ['name' => 'app_norm', 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 0],
    ],
    [
        ['name' => 'json_each'],
        ['name' => 'app_series'],
        ['name' => 'json_tree'],
    ],
    [
        ['seq' => 0, 'name' => 'binary'],
        ['seq' => 1, 'name' => 'nocase'],
        ['seq' => 2, 'name' => 'rtrim'],
        ['seq' => 3, 'name' => 'app_locale'],
    ],
);

$case = static function (string $name, callable $callback) use (&$tests): void {
    $tests['real upstream pragma schema dynamic batch ' . $name] = static function (TestRunner $t) use ($callback): void {
        [$expected, $actual] = $callback();
        $t->same($expected, $actual);
    };
};

foreach ($expected as $table => $meta) {
    $case("pragma-6 table_info {$table} column count", static fn (): array => [4, count($catalog->execute("PRAGMA table_info({$table})")['rows'])]);
    $case("pragma-6 table_info {$table} composite pk ordinals", static fn (): array => [[1, 2, 0, 0], array_column($catalog->execute("PRAGMA table_info({$table})")['rows'], 'pk')]);
    $case("pragma-6 table_info {$table} default value", static fn (): array => [$meta['default'], $catalog->execute("PRAGMA table_info({$table})")['rows'][2]['dflt_value']]);
    $case("pragma-6 table_xinfo {$table} includes generated column", static fn (): array => [5, count($catalog->execute("PRAGMA table_xinfo({$table})")['rows'])]);
    $case("pragma-6 table_xinfo {$table} generated hidden code", static fn (): array => [2, $catalog->execute("PRAGMA table_xinfo({$table})")['rows'][3]['hidden']]);
    $case("pragma-6 index_list {$table} row count", static fn (): array => [3, count($catalog->execute("PRAGMA index_list({$table})")['rows'])]);
    $case("pragma-6 index_list {$table} unique index first", static fn (): array => [$meta['index'], $catalog->execute("PRAGMA index_list({$table})")['rows'][0]['name']]);
    $case("pragma-6 index_list {$table} partial marker", static fn (): array => [1, $catalog->execute("PRAGMA index_list({$table})")['rows'][1]['partial']]);
    $case("pragma-6 index_info {$table} named key sequence", static fn (): array => [['key_name', 'tenant_id'], array_column($catalog->execute("PRAGMA index_info({$meta['index']})")['rows'], 'name')]);
    $case("pragma-6 index_xinfo {$table} descending nocase key", static fn (): array => [[1, 'NOCASE', 1], [$catalog->execute("PRAGMA index_xinfo({$meta['index']})")['rows'][0]['desc'], $catalog->execute("PRAGMA index_xinfo({$meta['index']})")['rows'][0]['coll'], $catalog->execute("PRAGMA index_xinfo({$meta['index']})")['rows'][0]['key']]]);
    $case("pragma table-valued index_xinfo {$table} expression cid", static fn (): array => [-2, $catalog->executeTableValuedPragma("pragma_index_xinfo('{$meta['expr']}')")['rows'][0]['cid']]);
    $case("pragma table_list {$table} flags", static fn (): array => [[$meta['without_rowid'], $meta['strict']], [$catalog->execute("PRAGMA table_list({$table})")['rows'][0]['wr'], $catalog->execute("PRAGMA table_list({$table})")['rows'][0]['strict']]]);
}

$case('pragma-6 missing table_info returns empty rows', static fn (): array => [[], $catalog->execute('PRAGMA table_info(missing_settings)')['rows']]);
$case('pragma-6 missing index_list returns empty rows', static fn (): array => [[], $catalog->execute('PRAGMA index_list(missing_settings)')['rows']]);
$case('pragma-6 quoted target parses as identifier', static fn (): array => ['app_settings_000', SQLitePragmaSchemaCatalog::parsePragma('PRAGMA table_info("app_settings_000")')['target']]);
$case('pragma-6 bracket target parses as identifier', static fn (): array => ['app_settings_001', SQLitePragmaSchemaCatalog::parsePragma('PRAGMA table_xinfo([app_settings_001])')['target']]);
$case('pragma table-valued schema arg parses', static fn (): array => [['pragma' => 'table_info', 'schema' => 'temp', 'target' => 'app_settings_002'], SQLitePragmaSchemaCatalog::parseTableValuedPragma("pragma_table_info('app_settings_002','temp')")]);
$case('pragma-11 collation list is sequence ordered and uppercased', static fn (): array => [['BINARY', 'NOCASE', 'RTRIM', 'APP_LOCALE'], array_column($catalog->execute('PRAGMA collation_list')['rows'], 'name')]);
$case('pragma function_list sorts by name and arity', static fn (): array => [['app_norm', 'app_rank', 'json_extract'], array_column($catalog->executeTableValuedPragma('pragma_function_list()')['rows'], 'name')]);
$case('pragma module_list sorts virtual table modules', static fn (): array => [['app_series', 'json_each', 'json_tree'], array_column($catalog->execute('PRAGMA module_list')['rows'], 'name')]);

return $tests;
