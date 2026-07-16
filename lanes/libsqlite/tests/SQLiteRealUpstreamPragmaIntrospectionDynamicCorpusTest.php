<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$makeCatalog = static function (int $variant = 0): SQLitePragmaSchemaCatalog {
    $functions = [
        ['name' => 'upper', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
        ['name' => 'external_rank_' . $variant, 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 2, 'flags' => 0],
        ['name' => 'external_window_' . $variant, 'builtin' => 0, 'type' => 'w', 'enc' => 'utf8', 'narg' => 1, 'flags' => 0],
    ];

    return new SQLitePragmaSchemaCatalog(
        [
            new SQLiteSchemaRecord(
                'table',
                'app_settings_' . $variant,
                'app_settings_' . $variant,
                2,
                "CREATE TABLE app_settings_{$variant}(setting_id INTEGER PRIMARY KEY, key_name TEXT)",
                1,
            ),
        ],
        $functions,
        [
            ['name' => 'json_each'],
            ['name' => 'json_tree'],
            ['name' => 'fts5_' . $variant],
        ],
    );
};

$valueAt = static function (array $value, string $path): mixed {
    $cursor = $value;
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $cursor = count($cursor);
            continue;
        }
        $cursor = is_numeric($part) ? $cursor[(int) $part] : $cursor[$part];
    }

    return $cursor;
};

$tests = [];

$tests['real upstream pragma5 1.0 table_info pragma_function_list shape'] = static function (TestRunner $t) use ($makeCatalog): void {
    $rows = $makeCatalog(1)->execute('PRAGMA table_info(pragma_function_list)')['rows'];

    $t->same([
        ['cid' => 0, 'name' => 'name', 'type' => '', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0],
        ['cid' => 1, 'name' => 'builtin', 'type' => '', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0],
        ['cid' => 2, 'name' => 'type', 'type' => '', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0],
        ['cid' => 3, 'name' => 'enc', 'type' => '', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0],
        ['cid' => 4, 'name' => 'narg', 'type' => '', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0],
        ['cid' => 5, 'name' => 'flags', 'type' => '', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0],
    ], $rows);
};

$tests['real upstream pragma5 2.0 table_info pragma_module_list shape'] = static function (TestRunner $t) use ($makeCatalog): void {
    $rows = $makeCatalog(2)->execute('PRAGMA table_info(pragma_module_list)')['rows'];

    $t->same([['cid' => 0, 'name' => 'name', 'type' => '', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0]], $rows);
};

$tests['real upstream pragma5 3.0 table_info pragma_pragma_list shape'] = static function (TestRunner $t) use ($makeCatalog): void {
    $rows = $makeCatalog(3)->execute('PRAGMA table_info(pragma_pragma_list)')['rows'];

    $t->same([['cid' => 0, 'name' => 'name', 'type' => '', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0]], $rows);
};

$tests['real upstream pragma5 3.1 pragma_list contains pragma_list'] = static function (TestRunner $t) use ($makeCatalog): void {
    $names = array_column($makeCatalog(4)->execute('PRAGMA pragma_list')['rows'], 'name');

    $t->same(true, in_array('pragma_list', $names, true));
    $t->same(true, in_array('function_list', $names, true));
    $t->same(true, in_array('module_list', $names, true));
    $t->same($names, array_values(array_unique($names)));
};

foreach ([
    'pragma_function_list' => ['count' => 6, 'last' => 'flags'],
    'pragma_module_list' => ['count' => 1, 'last' => 'name'],
    'pragma_pragma_list' => ['count' => 1, 'last' => 'name'],
] as $virtualTable => $expected) {
    $tests["real upstream pragma5 table_xinfo {$virtualTable} has no hidden columns"] = static function (TestRunner $t) use ($makeCatalog, $virtualTable, $expected): void {
        $rows = $makeCatalog(5)->execute("PRAGMA table_xinfo({$virtualTable})")['rows'];

        $t->same($expected['count'], count($rows));
        $t->same($expected['last'], $rows[count($rows) - 1]['name']);
        $t->same(array_fill(0, $expected['count'], 0), array_column($rows, 'hidden'));
    };
}

foreach (range(1, 140) as $variant) {
    $tests["real upstream pragma5 dynamic function-list query variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        $rows = $makeCatalog($variant)->executeTableValuedPragma('pragma_function_list()')['rows'];
        $external = array_values(array_filter($rows, static fn (array $row): bool => str_starts_with((string) $row['name'], 'external_')));

        $t->same(2, count($external));
        $t->same('external_rank_' . $variant, $external[0]['name']);
        $t->same(0, $external[0]['builtin']);
        $t->same('s', $external[0]['type']);
        $t->same(2, $external[0]['narg']);
        $t->same('external_window_' . $variant, $external[1]['name']);
        $t->same('w', $external[1]['type']);
        $t->same(1, $external[1]['narg']);
    };

    $tests["real upstream pragma5 dynamic module-list query variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        $rows = $makeCatalog($variant)->executeTableValuedPragma('pragma_module_list()')['rows'];

        $t->same('fts5_' . $variant, $rows[0]['name']);
        $t->same('json_each', $rows[1]['name']);
        $t->same('json_tree', $rows[2]['name']);
    };

    $tests["real upstream pragma5 dynamic pragma-list virtual schema variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $variant): void {
        $catalog = $makeCatalog($variant);
        $pragmaRows = $catalog->executeTableValuedPragma('pragma_pragma_list()')['rows'];
        $functionInfo = $catalog->execute("PRAGMA table_info(pragma_function_list)")['rows'];
        $moduleInfo = $catalog->execute("PRAGMA table_info(pragma_module_list)")['rows'];

        $names = array_column($pragmaRows, 'name');
        sort($names);

        $t->same('function_list', $valueAt(['rows' => $pragmaRows], 'rows.2.name'));
        $t->same(true, in_array('table_info', $names, true));
        $t->same(true, in_array('table_xinfo', $names, true));
        $t->same(6, count($functionInfo));
        $t->same('flags', $functionInfo[5]['name']);
        $t->same(1, count($moduleInfo));
        $t->same('name', $moduleInfo[0]['name']);
    };
}

return $tests;
