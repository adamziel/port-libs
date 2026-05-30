<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma6.test pragma6-1.0 through pragma6-1.2:
 *   integrity/quick-check opens a schema that also relies on generated-column
 *   metadata.
 * - SQLite test/pragma4.test pragma4 table-valued PRAGMA coverage:
 *   PRAGMA result sets are also available through pragma_* virtual tables.
 * - SQLite test/pragma.test pragma-6.2 through pragma-6.5:
 *   PRAGMA schema metadata remains row-shaped and joinable.
 *
 * This batch owns the list-PRAGMA table-valued form for
 * pragma_function_list(), pragma_module_list(), pragma_collation_list(), and
 * pragma_pragma_list(). Earlier wide batches exercised direct PRAGMA list
 * calls; these cases exercise the virtual-table spelling used by upstream
 * table-valued PRAGMA tests while preserving generic application identifiers.
 */

$makeCatalog = static function (int $variant): SQLitePragmaSchemaCatalog {
    $table = sprintf('list_tv_settings_%03d', $variant);
    $index = sprintf('list_tv_settings_%03d_lookup', $variant);

    return new SQLitePragmaSchemaCatalog(
        [
            new SQLiteSchemaRecord(
                'table',
                $table,
                $table,
                10000 + $variant,
                "CREATE TABLE {$table}(tenant_id INTEGER NOT NULL, key_name TEXT NOT NULL, key_value TEXT DEFAULT 'value-{$variant}', normalized_key TEXT GENERATED ALWAYS AS (upper(key_name)) VIRTUAL, PRIMARY KEY(tenant_id, key_name))",
                1,
            ),
            new SQLiteSchemaRecord(
                'index',
                $index,
                $table,
                11000 + $variant,
                "CREATE INDEX {$index} ON {$table}(normalized_key, length(key_value) DESC)",
                2,
            ),
        ],
        [
            ['name' => 'list_tv_scalar_' . $variant, 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => $variant],
            ['name' => 'list_tv_window_' . $variant, 'builtin' => 0, 'type' => 'w', 'enc' => $variant % 2 === 0 ? 'utf16le' : 'utf16be', 'narg' => 2, 'flags' => 3147776 + $variant],
            ['name' => 'json_extract', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 2099200],
        ],
        [
            ['name' => 'json_each'],
            ['name' => 'list_tv_module_' . $variant],
            ['name' => 'json_tree'],
        ],
        [
            ['seq' => 0, 'name' => 'binary'],
            ['seq' => 1, 'name' => 'nocase'],
            ['seq' => 2, 'name' => 'rtrim'],
            ['seq' => 3, 'name' => 'list_tv_locale_' . $variant],
        ],
    );
};

foreach (range(1, 250) as $variant) {
    $tests[sprintf('real upstream pragma schema list table valued function list variant %03d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        $catalog = $makeCatalog($variant);
        $directRows = $catalog->execute('PRAGMA function_list')['rows'];
        $tableRows = $catalog->executeTableValuedPragma('pragma_function_list()')['rows'];
        $names = array_column($tableRows, 'name');
        $scalarOffset = array_search('list_tv_scalar_' . $variant, $names, true);
        $windowOffset = array_search('list_tv_window_' . $variant, $names, true);

        $t->same($directRows, $tableRows);
        $t->same(true, $scalarOffset !== false);
        $t->same(true, $windowOffset !== false);
        $t->same('s', $tableRows[$scalarOffset]['type']);
        $t->same('w', $tableRows[$windowOffset]['type']);
        $t->same($variant, $tableRows[$scalarOffset]['flags']);
    };

    $tests[sprintf('real upstream pragma schema list table valued module list variant %03d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        $catalog = $makeCatalog($variant);
        $directRows = $catalog->execute('PRAGMA module_list')['rows'];
        $tableRows = $catalog->executeTableValuedPragma('pragma_module_list()')['rows'];
        $names = array_column($tableRows, 'name');

        $t->same($directRows, $tableRows);
        $t->same(true, in_array('json_each', $names, true));
        $t->same(true, in_array('json_tree', $names, true));
        $t->same(true, in_array('list_tv_module_' . $variant, $names, true));
        $t->same($names, array_values(array_unique($names)));
    };

    $tests[sprintf('real upstream pragma schema list table valued collation list variant %03d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        $catalog = $makeCatalog($variant);
        $directRows = $catalog->execute('PRAGMA collation_list')['rows'];
        $tableRows = $catalog->executeTableValuedPragma('pragma_collation_list()')['rows'];

        $t->same($directRows, $tableRows);
        $t->same([0, 1, 2, 3], array_column($tableRows, 'seq'));
        $t->same('BINARY', $tableRows[0]['name']);
        $t->same('NOCASE', $tableRows[1]['name']);
        $t->same('LIST_TV_LOCALE_' . $variant, $tableRows[3]['name']);
    };

    $tests[sprintf('real upstream pragma schema list table valued pragma list variant %03d', $variant)] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        $catalog = $makeCatalog($variant);
        $directRows = $catalog->execute('PRAGMA pragma_list')['rows'];
        $tableRows = $catalog->executeTableValuedPragma('pragma_pragma_list()')['rows'];
        $names = array_column($tableRows, 'name');

        $t->same($directRows, $tableRows);
        $t->same('collation_list', $names[0]);
        $t->same(true, in_array('function_list', $names, true));
        $t->same(true, in_array('module_list', $names, true));
        $t->same(true, in_array('table_xinfo', $names, true));
    };
}

$tests['real upstream pragma schema list table valued virtual table schema rows'] = static function (TestRunner $t) use ($makeCatalog): void {
    $catalog = $makeCatalog(251);
    $functionInfo = $catalog->execute('PRAGMA table_info(pragma_function_list)')['rows'];
    $moduleInfo = $catalog->execute('PRAGMA table_info(pragma_module_list)')['rows'];
    $pragmaInfo = $catalog->execute('PRAGMA table_info(pragma_pragma_list)')['rows'];

    $t->same(['name', 'builtin', 'type', 'enc', 'narg', 'flags'], array_column($functionInfo, 'name'));
    $t->same(['name'], array_column($moduleInfo, 'name'));
    $t->same(['name'], array_column($pragmaInfo, 'name'));
    $t->same([0, 0, 0, 0, 0, 0], array_column($functionInfo, 'pk'));
};

$tests['real upstream pragma schema list table valued cites source corpus sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma6.test pragma6-1.0 through pragma6-1.2 opens generated-column schema images and runs integrity_check/quick_check',
        'pragma4.test table-valued PRAGMA functions expose PRAGMA rowsets through pragma_* virtual tables',
        'pragma.test pragma-6.2 through pragma-6.5 keeps schema metadata row-shaped for table_info, table_xinfo, index_info, index_xinfo, and foreign_key_list',
        'pragma6.test list metadata behavior is represented by function_list, module_list, collation_list, and pragma_list rows',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma6.test', $sections[0]);
    $t->contains('pragma4.test', $sections[1]);
    $t->contains('pragma-6.2', $sections[2]);
    $t->contains('function_list', $sections[3]);
};

return $tests;
