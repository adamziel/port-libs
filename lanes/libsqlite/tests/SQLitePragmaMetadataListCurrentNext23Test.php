<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$catalog = static function (): SQLitePragmaSchemaCatalog {
    return new SQLitePragmaSchemaCatalog(
        [
            new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT)', 1),
        ],
        [
            ['name' => 'json_extract', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 2099200],
            ['name' => 'lower', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
            ['name' => 'count', 'builtin' => 1, 'type' => 'w', 'enc' => 'utf8', 'narg' => 0, 'flags' => 2097152],
            ['name' => 'count', 'builtin' => 1, 'type' => 'w', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2097152],
            ['name' => 'wp_slugify', 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2048],
            ['name' => 'wp_json_summary', 'builtin' => 0, 'type' => 'w', 'enc' => 'utf8', 'narg' => 1, 'flags' => 0],
        ],
        [
            ['name' => 'json_tree'],
            ['name' => 'json_each'],
            ['name' => 'wp_options_vtab'],
        ],
        [
            ['seq' => 0, 'name' => 'binary'],
            ['seq' => 1, 'name' => 'nocase'],
            ['seq' => 2, 'name' => 'rtrim'],
            ['seq' => 3, 'name' => 'wp_slug'],
        ],
    );
};

$attached = static function (): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 1),
    ]);
    $catalog->attach('network', '/srv/www/network.sqlite', [
        new SQLiteSchemaRecord('table', 'wp_sitemeta', 'wp_sitemeta', 2, 'CREATE TABLE wp_sitemeta(meta_id INTEGER PRIMARY KEY, meta_key TEXT)', 1),
    ]);

    return $catalog;
};

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$rowBy = static function (array $rows, string $column, mixed $value): array {
    foreach ($rows as $row) {
        if (($row[$column] ?? null) === $value) {
            return $row;
        }
    }

    throw new RuntimeException("Row {$column}=" . var_export($value, true) . ' not found');
};

$tests = [];

$cases = [
    'direct function-list status' => ['PRAGMA function_list', 'status', 'ok'],
    'direct function-list pragma' => ['PRAGMA function_list', 'pragma', 'function_list'],
    'direct function-list schema' => ['PRAGMA function_list', 'schema', 'main'],
    'direct function-list empty target' => ['PRAGMA function_list', 'target', ''],
    'direct function-list row count' => ['PRAGMA function_list', 'rows.count', 6],
    'function-list sorted first count zero arg' => ['PRAGMA function_list', 'rows.0.name', 'count'],
    'function-list sorted first narg' => ['PRAGMA function_list', 'rows.0.narg', 0],
    'function-list aggregate-window type' => ['PRAGMA function_list', 'rows.0.type', 'w'],
    'function-list second count overload' => ['PRAGMA function_list', 'rows.1.narg', 1],
    'function-list json extract position' => ['PRAGMA function_list', 'rows.2.name', 'json_extract'],
    'function-list json extract variadic' => ['PRAGMA function_list', 'rows.2.narg', -1],
    'function-list lower scalar type' => ['PRAGMA function_list', 'rows.3.type', 's'],
    'function-list custom window row' => ['PRAGMA function_list', 'rows.4.name', 'wp_json_summary'],
    'function-list custom window type' => ['PRAGMA function_list', 'rows.4.type', 'w'],
    'function-list custom scalar builtin flag' => ['PRAGMA function_list', 'rows.5.builtin', 0],
    'function-list custom scalar flags' => ['PRAGMA function_list', 'rows.5.flags', 2048],
    'table-valued function-list status' => ['pragma_function_list()', 'status', 'ok'],
    'table-valued function-list row count' => ['pragma_function_list()', 'rows.count', 6],
    'table-valued function-list uppercase accepted' => ['PRAGMA_FUNCTION_LIST()', 'pragma', 'function_list'],
    'table-valued function-list semicolon accepted' => ['pragma_function_list();', 'rows.5.name', 'wp_slugify'],
    'direct module-list status' => ['PRAGMA module_list', 'status', 'ok'],
    'direct module-list pragma' => ['PRAGMA module_list', 'pragma', 'module_list'],
    'direct module-list row count' => ['PRAGMA module_list', 'rows.count', 3],
    'module-list sorted json each' => ['PRAGMA module_list', 'rows.0.name', 'json_each'],
    'module-list sorted json tree' => ['PRAGMA module_list', 'rows.1.name', 'json_tree'],
    'module-list custom module' => ['PRAGMA module_list', 'rows.2.name', 'wp_options_vtab'],
    'table-valued module-list status' => ['pragma_module_list()', 'status', 'ok'],
    'table-valued module-list row count' => ['pragma_module_list()', 'rows.count', 3],
    'table-valued module-list uppercase accepted' => ['PRAGMA_MODULE_LIST()', 'rows.2.name', 'wp_options_vtab'],
    'direct collation-list status' => ['PRAGMA collation_list', 'status', 'ok'],
    'direct collation-list pragma' => ['PRAGMA collation_list', 'pragma', 'collation_list'],
    'direct collation-list row count' => ['PRAGMA collation_list', 'rows.count', 4],
    'collation-list binary sequence' => ['PRAGMA collation_list', 'rows.0.seq', 0],
    'collation-list binary name uppercase' => ['PRAGMA collation_list', 'rows.0.name', 'BINARY'],
    'collation-list nocase sequence' => ['PRAGMA collation_list', 'rows.1.seq', 1],
    'collation-list rtrim sequence' => ['PRAGMA collation_list', 'rows.2.seq', 2],
    'collation-list custom sequence' => ['PRAGMA collation_list', 'rows.3.seq', 3],
    'collation-list custom name uppercase' => ['PRAGMA collation_list', 'rows.3.name', 'WP_SLUG'],
    'table-valued collation-list status' => ['pragma_collation_list()', 'status', 'ok'],
    'table-valued collation-list row count' => ['pragma_collation_list()', 'rows.count', 4],
    'table-valued collation-list semicolon accepted' => ['pragma_collation_list();', 'rows.3.name', 'WP_SLUG'],
];

foreach ($cases as $name => [$sql, $path, $expected]) {
    $tests['pragma metadata list current next23 ' . $name] = static function (TestRunner $t) use ($catalog, $valueAt, $sql, $path, $expected): void {
        $result = str_starts_with(strtolower($sql), 'pragma_')
            ? $catalog()->executeTableValuedPragma($sql)
            : $catalog()->execute($sql);
        $t->same($expected, $valueAt($result, $path));
    };
}

$tests['pragma metadata list current next23 direct and table-valued function rows match'] = static function (TestRunner $t) use ($catalog): void {
    $t->same($catalog()->execute('PRAGMA function_list')['rows'], $catalog()->executeTableValuedPragma('pragma_function_list()')['rows']);
};

$tests['pragma metadata list current next23 direct and table-valued module rows match'] = static function (TestRunner $t) use ($catalog): void {
    $t->same($catalog()->execute('PRAGMA module_list')['rows'], $catalog()->executeTableValuedPragma('pragma_module_list()')['rows']);
};

$tests['pragma metadata list current next23 direct and table-valued collation rows match'] = static function (TestRunner $t) use ($catalog): void {
    $t->same($catalog()->execute('PRAGMA collation_list')['rows'], $catalog()->executeTableValuedPragma('pragma_collation_list()')['rows']);
};

$tests['pragma metadata list current next23 default catalog exposes json table modules'] = static function (TestRunner $t): void {
    $modules = array_column((new SQLitePragmaSchemaCatalog([]))->moduleList(), 'name');
    $t->same(['fts5', 'json_each', 'json_tree', 'rtree'], $modules);
};

$tests['pragma metadata list current next23 default catalog exposes builtin collations'] = static function (TestRunner $t): void {
    $t->same(['BINARY', 'NOCASE', 'RTRIM'], array_column((new SQLitePragmaSchemaCatalog([]))->collationList(), 'name'));
};

$tests['pragma metadata list current next23 default catalog exposes json extract'] = static function (TestRunner $t) use ($rowBy): void {
    $row = $rowBy((new SQLitePragmaSchemaCatalog([]))->functionList(), 'name', 'json_extract');
    $t->same(1, $row['builtin']);
};

$tests['pragma metadata list current next23 default catalog exposes like overload'] = static function (TestRunner $t): void {
    $likes = array_values(array_filter((new SQLitePragmaSchemaCatalog([]))->functionList(), static fn (array $row): bool => $row['name'] === 'like'));
    $t->same([2, 3], array_column($likes, 'narg'));
};

$tests['pragma metadata list current next23 attached direct metadata remains main scoped'] = static function (TestRunner $t) use ($attached): void {
    $result = $attached()->executeSchemaPragma('PRAGMA network.function_list');
    $t->same('network', $result['schema']);
    $t->true(count($result['rows']) > 5);
};

$tests['pragma metadata list current next23 attached unqualified metadata uses main'] = static function (TestRunner $t) use ($attached): void {
    $result = $attached()->executeSchemaPragma('PRAGMA module_list');
    $t->same('main', $result['schema']);
    $t->same(['fts5', 'json_each', 'json_tree', 'rtree'], array_column($result['rows'], 'name'));
};

$tests['pragma metadata list current next23 attached table-valued metadata uses main'] = static function (TestRunner $t) use ($attached): void {
    $result = $attached()->executeTableValuedPragma('pragma_collation_list()');
    $t->same('main', $result['schema']);
    $t->same(['BINARY', 'NOCASE', 'RTRIM'], array_column($result['rows'], 'name'));
};

$tests['pragma metadata list current next23 function cursor walks immutable rows'] = static function (TestRunner $t) use ($catalog): void {
    $cursor = $catalog()->executeTableValuedPragmaCursor('pragma_function_list()');
    $t->same('function_list', $cursor->metadata()['pragma']);
    $t->same('count', $cursor->current()['name']);
    $t->same(1, $cursor->next()['narg']);
    $t->same('json_extract', $cursor->next()['name']);
};

$tests['pragma metadata list current next23 module cursor reaches eof'] = static function (TestRunner $t) use ($catalog): void {
    $cursor = $catalog()->executeTableValuedPragmaCursor('pragma_module_list()');
    $t->same('json_each', $cursor->current()['name']);
    $t->same('json_tree', $cursor->next()['name']);
    $t->same('wp_options_vtab', $cursor->next()['name']);
    $t->same(null, $cursor->next());
    $t->same(true, $cursor->metadata()['eof']);
};

$tests['pragma metadata list current next23 rejects direct metadata arguments'] = static function (TestRunner $t) use ($catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => $catalog()->execute('PRAGMA function_list(1)'));
};

$tests['pragma metadata list current next23 rejects table-valued metadata arguments'] = static function (TestRunner $t) use ($catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => $catalog()->executeTableValuedPragma("pragma_function_list('lower')"));
};

$tests['pragma metadata list current next23 rejects table-valued metadata schema argument'] = static function (TestRunner $t) use ($catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => $catalog()->executeTableValuedPragma("pragma_collation_list('main')"));
};

$tests['pragma metadata list current next23 rejects unknown metadata pragma'] = static function (TestRunner $t) use ($catalog): void {
    $t->throws(InvalidArgumentException::class, static fn () => $catalog()->executeTableValuedPragma('pragma_database_list()'));
};

$tests['pragma metadata list current next23 rejects malformed custom function type'] = static function (TestRunner $t): void {
    $bad = new SQLitePragmaSchemaCatalog([], [['name' => 'wp_bad', 'type' => 'x']]);
    $t->throws(InvalidArgumentException::class, static fn () => $bad->functionList());
};

$tests['pragma metadata list current next23 rejects malformed custom function encoding'] = static function (TestRunner $t): void {
    $bad = new SQLitePragmaSchemaCatalog([], [['name' => 'wp_bad', 'enc' => 'latin1']]);
    $t->throws(InvalidArgumentException::class, static fn () => $bad->functionList());
};

$tests['pragma metadata list current next23 rejects malformed module name'] = static function (TestRunner $t): void {
    $bad = new SQLitePragmaSchemaCatalog([], [], [['name' => '']]);
    $t->throws(InvalidArgumentException::class, static fn () => $bad->moduleList());
};

$tests['pragma metadata list current next23 rejects malformed collation name'] = static function (TestRunner $t): void {
    $bad = new SQLitePragmaSchemaCatalog([], [], [], [['name' => '']]);
    $t->throws(InvalidArgumentException::class, static fn () => $bad->collationList());
};

return $tests;
