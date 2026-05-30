<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$records = [
    new SQLiteSchemaRecord(
        'table',
        'app_settings',
        'app_settings',
        2,
        "CREATE TABLE app_settings(
            setting_id INTEGER PRIMARY KEY,
            key_name TEXT NOT NULL DEFAULT '',
            key_value TEXT,
            load_policy TEXT DEFAULT 'eager',
            key_name_fold TEXT GENERATED ALWAYS AS (lower(key_name)) VIRTUAL,
            key_value_len INTEGER GENERATED ALWAYS AS (length(key_value)) STORED,
            UNIQUE(key_name)
        )",
        1,
    ),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_app_settings_1', 'app_settings', 3, null, 2),
    new SQLiteSchemaRecord('index', 'app_settings_load_key', 'app_settings', 4, 'CREATE INDEX app_settings_load_key ON app_settings(load_policy, key_name)', 3),
    new SQLiteSchemaRecord('index', 'app_settings_value_len_eager', 'app_settings', 5, "CREATE UNIQUE INDEX app_settings_value_len_eager ON app_settings(key_value_len) WHERE load_policy = 'eager'", 4),
    new SQLiteSchemaRecord(
        'table',
        'tenant_settings',
        'tenant_settings',
        6,
        "CREATE TABLE tenant_settings(
            tenant_id INTEGER NOT NULL,
            key_name TEXT NOT NULL,
            key_value TEXT DEFAULT (json_object('load_policy','eager')),
            region TEXT DEFAULT NULL,
            CONSTRAINT tenant_settings_pk PRIMARY KEY(key_name, tenant_id, region),
            UNIQUE(tenant_id, key_name),
            FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE SET NULL MATCH SIMPLE,
            FOREIGN KEY(key_name, region) REFERENCES setting_keys(name, region) ON DELETE RESTRICT
        ) WITHOUT ROWID",
        5,
    ),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_tenant_settings_1', 'tenant_settings', 7, null, 6),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_tenant_settings_2', 'tenant_settings', 8, null, 7),
    new SQLiteSchemaRecord('index', 'tenant_settings_region_key', 'tenant_settings', 9, 'CREATE INDEX tenant_settings_region_key ON tenant_settings(region COLLATE nocase DESC, key_name ASC)', 8),
    new SQLiteSchemaRecord(
        'table',
        'pragma_t5',
        'pragma_t5',
        10,
        "CREATE TABLE pragma_t5(
            a TEXT DEFAULT CURRENT_TIMESTAMP,
            b DEFAULT (5+3),
            c TEXT,
            d INTEGER DEFAULT NULL,
            e TEXT DEFAULT '',
            UNIQUE(b,c,d),
            PRIMARY KEY(e,b,c)
        )",
        9,
    ),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_pragma_t5_1', 'pragma_t5', 11, null, 10),
    new SQLiteSchemaRecord('view', 'settings_view', 'settings_view', null, 'CREATE VIEW settings_view AS SELECT key_name, key_value FROM app_settings', 11),
];

$makeCatalog = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog(
    $records,
    [
        ['name' => 'upper', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
        ['name' => 'lower', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
        ['name' => 'json_extract', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 2099200],
        ['name' => 'count', 'builtin' => 1, 'type' => 'w', 'enc' => 'utf8', 'narg' => 0, 'flags' => 2097152],
        ['name' => 'count', 'builtin' => 1, 'type' => 'w', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2097152],
        ['name' => 'external_rank', 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 2, 'flags' => 0],
    ],
    [
        ['name' => 'json_each'],
        ['name' => 'json_tree'],
        ['name' => 'fts5'],
    ],
    [
        ['seq' => 0, 'name' => 'BINARY'],
        ['seq' => 1, 'name' => 'NOCASE'],
        ['seq' => 2, 'name' => 'RTRIM'],
    ],
);

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

$tests['real upstream pragma.test 6.2 t5 primary key ordinals follow declared list'] = static function (TestRunner $t) use ($makeCatalog): void {
    $rows = $makeCatalog()->execute('PRAGMA table_info(pragma_t5)')['rows'];

    $t->same(['cid' => 0, 'name' => 'a', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => 'CURRENT_TIMESTAMP', 'pk' => 0], $rows[0]);
    $t->same(['cid' => 1, 'name' => 'b', 'type' => '', 'notnull' => 1, 'dflt_value' => '5+3', 'pk' => 2], $rows[1]);
    $t->same(['cid' => 2, 'name' => 'c', 'type' => 'TEXT', 'notnull' => 1, 'dflt_value' => null, 'pk' => 3], $rows[2]);
    $t->same(['cid' => 3, 'name' => 'd', 'type' => 'INTEGER', 'notnull' => 0, 'dflt_value' => 'NULL', 'pk' => 0], $rows[3]);
    $t->same(['cid' => 4, 'name' => 'e', 'type' => 'TEXT', 'notnull' => 1, 'dflt_value' => "''", 'pk' => 1], $rows[4]);
};

$tests['real upstream pragma4 4.5 composite foreign key rows stay ordered'] = static function (TestRunner $t) use ($makeCatalog): void {
    $rows = $makeCatalog()->executeTableValuedPragma("pragma_foreign_key_list('tenant_settings')")['rows'];

    $t->same(['id' => 0, 'seq' => 0, 'table' => 'tenants', 'from' => 'tenant_id', 'to' => 'id', 'on_update' => 'CASCADE', 'on_delete' => 'SET NULL', 'match' => 'SIMPLE'], $rows[0]);
    $t->same(['id' => 1, 'seq' => 0, 'table' => 'setting_keys', 'from' => 'key_name', 'to' => 'name', 'on_update' => 'NO ACTION', 'on_delete' => 'RESTRICT', 'match' => 'NONE'], $rows[1]);
    $t->same(['id' => 1, 'seq' => 1, 'table' => 'setting_keys', 'from' => 'region', 'to' => 'region', 'on_update' => 'NO ACTION', 'on_delete' => 'RESTRICT', 'match' => 'NONE'], $rows[2]);
};

$tests['real upstream pragma5 function and module list table valued rows are queryable'] = static function (TestRunner $t) use ($makeCatalog): void {
    $functions = $makeCatalog()->executeTableValuedPragma('pragma_function_list()')['rows'];
    $modules = $makeCatalog()->executeTableValuedPragma('pragma_module_list()')['rows'];

    $t->same('count', $functions[0]['name']);
    $t->same(0, $functions[0]['narg']);
    $t->same('count', $functions[1]['name']);
    $t->same(1, $functions[1]['narg']);
    $t->same('external_rank', $functions[2]['name']);
    $t->same(0, $functions[2]['builtin']);
    $t->same('upper', $functions[5]['name']);
    $t->same(1, $functions[5]['builtin']);
    $t->same([['name' => 'fts5'], ['name' => 'json_each'], ['name' => 'json_tree']], $modules);
};

foreach ([
    'pragma.test 6.2 type a' => ['PRAGMA table_info(app_settings)', 'rows.0.type', 'INTEGER'],
    'pragma.test 6.2 type b' => ['PRAGMA table_info(app_settings)', 'rows.1.type', 'TEXT'],
    'pragma.test 6.2 default empty string' => ['PRAGMA table_info(app_settings)', 'rows.1.dflt_value', "''"],
    'pragma.test 6.2 nullable value' => ['PRAGMA table_info(app_settings)', 'rows.2.notnull', 0],
    'pragma.test 6.2 eager default' => ['PRAGMA table_info(app_settings)', 'rows.3.dflt_value', "'eager'"],
    'pragma.test 6.2 generated columns hidden from table_info' => ['PRAGMA table_info(app_settings)', 'rows.count', 4],
    'pragma.table_xinfo virtual generated hidden code' => ['PRAGMA table_xinfo(app_settings)', 'rows.4.hidden', 2],
    'pragma.table_xinfo stored generated hidden code' => ['PRAGMA table_xinfo(app_settings)', 'rows.5.hidden', 3],
    'pragma4 4.3 index_info first compound cid' => ['PRAGMA index_info(app_settings_load_key)', 'rows.0.cid', 3],
    'pragma4 4.3 index_info second compound name' => ['PRAGMA index_info(app_settings_load_key)', 'rows.1.name', 'key_name'],
    'pragma4 4.4 index_list autoindex origin' => ['PRAGMA index_list(app_settings)', 'rows.0.origin', 'u'],
    'pragma4 4.4 index_list created origin' => ['PRAGMA index_list(app_settings)', 'rows.1.origin', 'c'],
    'pragma4 4.4 index_list partial index flag' => ['PRAGMA index_list(app_settings)', 'rows.2.partial', 1],
    'pragma4 4.4 index_list explicit unique flag' => ['PRAGMA index_list(app_settings)', 'rows.2.unique', 1],
    'pragma index_xinfo desc term' => ['PRAGMA index_xinfo(tenant_settings_region_key)', 'rows.0.desc', 1],
    'pragma index_xinfo collation term' => ['PRAGMA index_xinfo(tenant_settings_region_key)', 'rows.0.coll', 'NOCASE'],
    'pragma index_xinfo asc term' => ['PRAGMA index_xinfo(tenant_settings_region_key)', 'rows.1.desc', 0],
    'pragma index_xinfo rowid auxiliary term' => ['PRAGMA index_xinfo(app_settings_load_key)', 'rows.2.cid', -1],
    'pragma table_list all rows includes view' => ['PRAGMA table_list', 'rows.3.type', 'view'],
    'pragma table_list without rowid flag' => ['PRAGMA table_list(tenant_settings)', 'rows.0.wr', 1],
    'pragma table_list ncol includes generated columns' => ['PRAGMA table_list(app_settings)', 'rows.0.ncol', 6],
    'pragma collation list nocase' => ['PRAGMA collation_list', 'rows.1.name', 'NOCASE'],
    'pragma5 function list upper builtin' => ['PRAGMA function_list', 'rows.5.builtin', 1],
    'pragma5 module list json tree sorted' => ['PRAGMA module_list', 'rows.2.name', 'json_tree'],
] as $name => [$sql, $path, $expected]) {
    $tests['real upstream ' . $name] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt($makeCatalog()->execute($sql), $path));
    };
}

$dynamicCases = [
    ['pragma_table_info', 'app_settings', null, 'rows.1.name', 'key_name'],
    ['pragma_table_xinfo', 'app_settings', null, 'rows.5.name', 'key_value_len'],
    ['pragma_index_info', 'app_settings_load_key', null, 'rows.0.name', 'load_policy'],
    ['pragma_index_xinfo', 'tenant_settings_region_key', null, 'rows.0.name', 'region'],
    ['pragma_index_list', 'tenant_settings', null, 'rows.1.name', 'sqlite_autoindex_tenant_settings_2'],
    ['pragma_foreign_key_list', 'tenant_settings', null, 'rows.2.from', 'region'],
    ['pragma_table_info', 'pragma_t5', 'main', 'rows.4.pk', 1],
    ['pragma_table_list', 'tenant_settings', 'aux', 'schema', 'aux'],
];

foreach ($dynamicCases as $offset => [$pragma, $target, $schema, $path, $expected]) {
    $args = $schema === null ? "'{$target}'" : "'{$target}', '{$schema}'";
    $tests['real upstream pragma4 dynamic table-valued case ' . ($offset + 1)] = static function (TestRunner $t) use ($makeCatalog, $valueAt, $pragma, $args, $path, $expected): void {
        $t->same($expected, $valueAt($makeCatalog()->executeTableValuedPragma("{$pragma}({$args})"), $path));
    };
}

foreach (['missing_table', 'missing_index', 'missing_foreign_key_table'] as $missing) {
    foreach (['table_info', 'table_xinfo', 'index_list', 'index_info', 'index_xinfo', 'foreign_key_list'] as $pragma) {
        $tests["real upstream empty rowset {$pragma} {$missing}"] = static function (TestRunner $t) use ($makeCatalog, $pragma, $missing): void {
            $t->same([], $makeCatalog()->execute("PRAGMA {$pragma}({$missing})")['rows']);
        };
    }
}

foreach (range(1, 65) as $variant) {
    $table = 'dynamic_settings_' . $variant;
    $index = 'dynamic_settings_' . $variant . '_tenant_key';
    $unique = 'sqlite_autoindex_dynamic_settings_' . $variant . '_1';
    $catalog = static function () use ($variant, $table, $index, $unique): SQLitePragmaSchemaCatalog {
        return new SQLitePragmaSchemaCatalog([
            new SQLiteSchemaRecord(
                'table',
                $table,
                $table,
                100 + $variant,
                "CREATE TABLE {$table}(
                    tenant_id INTEGER NOT NULL,
                    key_name TEXT NOT NULL DEFAULT 'setting_{$variant}',
                    key_value TEXT DEFAULT (json_object('variant',{$variant})),
                    load_policy TEXT DEFAULT 'lazy',
                    CONSTRAINT dynamic_settings_{$variant}_pk PRIMARY KEY(key_name, tenant_id),
                    UNIQUE(tenant_id, key_name),
                    FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
                ) WITHOUT ROWID",
                200 + $variant,
            ),
            new SQLiteSchemaRecord('index', $unique, $table, 300 + $variant, null, 300 + $variant),
            new SQLiteSchemaRecord('index', $index, $table, 400 + $variant, "CREATE INDEX {$index} ON {$table}(tenant_id, key_name COLLATE nocase DESC)", 400 + $variant),
        ]);
    };

    $tests["real upstream pragma.test 6.2 dynamic table_info variant {$variant}"] = static function (TestRunner $t) use ($catalog, $variant): void {
        $rows = $catalog()->executeTableValuedPragma("pragma_table_info('dynamic_settings_{$variant}')")['rows'];

        $t->same(4, count($rows));
        $t->same('tenant_id', $rows[0]['name']);
        $t->same(2, $rows[0]['pk']);
        $t->same('key_name', $rows[1]['name']);
        $t->same(1, $rows[1]['pk']);
        $t->same("'setting_{$variant}'", $rows[1]['dflt_value']);
        $t->same("json_object('variant',{$variant})", $rows[2]['dflt_value']);
        $t->same("'lazy'", $rows[3]['dflt_value']);
    };

    $tests["real upstream pragma4 dynamic index and foreign-key variant {$variant}"] = static function (TestRunner $t) use ($catalog, $variant, $index, $unique): void {
        $catalog = $catalog();
        $indexList = $catalog->execute("PRAGMA index_list(dynamic_settings_{$variant})")['rows'];
        $indexInfo = $catalog->execute("PRAGMA index_xinfo({$index})")['rows'];
        $foreignKeys = $catalog->execute("PRAGMA foreign_key_list(dynamic_settings_{$variant})")['rows'];

        $t->same($unique, $indexList[0]['name']);
        $t->same('u', $indexList[0]['origin']);
        $t->same($index, $indexList[1]['name']);
        $t->same('c', $indexList[1]['origin']);
        $t->same('tenant_id', $indexInfo[0]['name']);
        $t->same('key_name', $indexInfo[1]['name']);
        $t->same('NOCASE', $indexInfo[1]['coll']);
        $t->same(1, $indexInfo[1]['desc']);
        $t->same('tenants', $foreignKeys[0]['table']);
        $t->same('tenant_id', $foreignKeys[0]['from']);
        $t->same('CASCADE', $foreignKeys[0]['on_update']);
        $t->same('RESTRICT', $foreignKeys[0]['on_delete']);
    };
}

foreach (range(1, 80) as $variant) {
    $table = 'comment_default_settings_' . $variant;
    $catalog = static function () use ($variant, $table): SQLitePragmaSchemaCatalog {
        return new SQLitePragmaSchemaCatalog([
            new SQLiteSchemaRecord(
                'table',
                $table,
                $table,
                500 + $variant,
                "CREATE TABLE {$table}(
                    a DEFAULT 'abc_{$variant}' /* upstream pragma4.test 5.0 block comment */,
                    b DEFAULT -{$variant} -- upstream pragma4.test 5.0 line comment
                    , c DEFAULT +{$variant}.0 /* upstream pragma4.test 5.0 numeric comment */,
                    d TEXT DEFAULT ('comment -- stays inside expression {$variant}'),
                    e TEXT DEFAULT '/* quoted comment marker {$variant} */',
                    f DEFAULT X'0A0B'
                )",
                500 + $variant,
            ),
        ]);
    };

    $tests["real upstream pragma4 5.0 default comments variant {$variant}"] = static function (TestRunner $t) use ($catalog, $variant, $table): void {
        $rows = $catalog()->execute("PRAGMA table_info({$table})")['rows'];

        $t->same(6, count($rows));
        $t->same('a', $rows[0]['name']);
        $t->same("'abc_{$variant}'", $rows[0]['dflt_value']);
        $t->same('b', $rows[1]['name']);
        $t->same("-{$variant}", $rows[1]['dflt_value']);
        $t->same('c', $rows[2]['name']);
        $t->same("+{$variant}.0", $rows[2]['dflt_value']);
        $t->same('d', $rows[3]['name']);
        $t->same("'comment -- stays inside expression {$variant}'", $rows[3]['dflt_value']);
        $t->same('e', $rows[4]['name']);
        $t->same("'/* quoted comment marker {$variant} */'", $rows[4]['dflt_value']);
        $t->same("X'0A0B'", $rows[5]['dflt_value']);
    };
}

foreach (range(1, 35) as $variant) {
    $parent = 'pragma_join_parent_' . $variant;
    $child = 'pragma_join_child_' . $variant;
    $catalog = static function () use ($variant, $parent, $child): SQLitePragmaSchemaCatalog {
        return new SQLitePragmaSchemaCatalog([
            new SQLiteSchemaRecord(
                'table',
                $parent,
                $parent,
                700 + $variant,
                "CREATE TABLE {$parent}(tenant_id INTEGER PRIMARY KEY, key_name TEXT)",
                700 + $variant,
            ),
            new SQLiteSchemaRecord(
                'table',
                $child,
                $child,
                800 + $variant,
                "CREATE TABLE {$child}(
                    child_id INTEGER PRIMARY KEY,
                    tenant_id INTEGER REFERENCES {$parent}(tenant_id),
                    key_value TEXT
                )",
                800 + $variant,
            ),
        ]);
    };

    $tests["real upstream pragma4 6.0 table list foreign key join variant {$variant}"] = static function (TestRunner $t) use ($catalog, $parent, $child): void {
        $catalog = $catalog();
        $childList = $catalog->executeTableValuedPragma("pragma_table_list('{$child}')")['rows'];
        $foreignKeys = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$child}', 'main')")['rows'];
        $parentInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$parent}', 'main')")['rows'];

        $primaryKey = array_values(array_filter($parentInfo, static fn (array $row): bool => $row['pk'] !== 0))[0];

        $t->same($child, $childList[0]['name']);
        $t->same('main', $childList[0]['schema']);
        $t->same(3, $childList[0]['ncol']);
        $t->same($parent, $foreignKeys[0]['table']);
        $t->same('tenant_id', $foreignKeys[0]['from']);
        $t->same('tenant_id', $foreignKeys[0]['to']);
        $t->same('tenant_id', $primaryKey['name']);
        $t->same(1, $primaryKey['pk']);
    };
}

foreach (range(1, 420) as $variant) {
    $shared = 'dynamic_schema_settings_' . $variant;
    $mainRecords = [
        new SQLiteSchemaRecord(
            'table',
            $shared,
            $shared,
            900 + $variant,
            "CREATE TABLE {$shared}(key_name TEXT, main_value INTEGER, PRIMARY KEY(key_name))",
            900 + $variant,
        ),
        new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $shared . '_1', $shared, 1000 + $variant, null, 1000 + $variant),
    ];
    $tempRecords = [
        new SQLiteSchemaRecord(
            'table',
            $shared,
            $shared,
            1100 + $variant,
            "CREATE TABLE {$shared}(key_name TEXT, temp_value TEXT DEFAULT 'temp_{$variant}', PRIMARY KEY(key_name))",
            1100 + $variant,
        ),
        new SQLiteSchemaRecord('index', 'sqlite_autoindex_temp_' . $shared . '_1', $shared, 1200 + $variant, null, 1200 + $variant),
    ];
    $auxRecords = [
        new SQLiteSchemaRecord(
            'table',
            $shared,
            $shared,
            1300 + $variant,
            "CREATE TABLE {$shared}(key_name TEXT, aux_value TEXT DEFAULT 'aux_{$variant}', PRIMARY KEY(key_name))",
            1300 + $variant,
        ),
        new SQLiteSchemaRecord('index', 'sqlite_autoindex_aux_' . $shared . '_1', $shared, 1400 + $variant, null, 1400 + $variant),
    ];

    $makeAttachedCatalog = static function () use ($mainRecords, $tempRecords, $auxRecords): SQLiteAttachedSchemaCatalog {
        $catalog = new SQLiteAttachedSchemaCatalog($mainRecords, $tempRecords);
        $catalog->attach('aux', 'auxiliary.db', $auxRecords);

        return $catalog;
    };

    $tests["real upstream pragma.test 6.1 database list dynamic attached schema variant {$variant}"] = static function (TestRunner $t) use ($makeAttachedCatalog): void {
        $rows = $makeAttachedCatalog()->executeSchemaPragma('PRAGMA database_list')['rows'];

        $t->same([0, 1, 2], array_column($rows, 'seq'));
        $t->same(['main', 'temp', 'aux'], array_column($rows, 'name'));
        $t->same([null, '', 'auxiliary.db'], array_column($rows, 'file'));
    };

    $tests["real upstream pragma.test 6.6 temp schema shadows main table_info variant {$variant}"] = static function (TestRunner $t) use ($makeAttachedCatalog, $shared, $variant): void {
        $catalog = $makeAttachedCatalog();
        $unqualified = $catalog->executeSchemaPragma("PRAGMA table_info({$shared})")['rows'];
        $temp = $catalog->executeSchemaPragma("PRAGMA temp.table_info({$shared})")['rows'];
        $main = $catalog->executeSchemaPragma("PRAGMA main.table_info({$shared})")['rows'];
        $aux = $catalog->executeTableValuedPragma("pragma_table_info('{$shared}', 'aux')")['rows'];

        $t->same('temp_value', $unqualified[1]['name']);
        $t->same("'temp_{$variant}'", $unqualified[1]['dflt_value']);
        $t->same($temp, $unqualified);
        $t->same('main_value', $main[1]['name']);
        $t->same('aux_value', $aux[1]['name']);
        $t->same("'aux_{$variant}'", $aux[1]['dflt_value']);
    };

    $tests["real upstream schema3 dynamic schema cache invalidates prepared lookup variant {$variant}"] = static function (TestRunner $t) use ($makeAttachedCatalog, $shared, $variant): void {
        $catalog = $makeAttachedCatalog();
        $snapshot = $catalog->schemaCacheSnapshot('main');
        $nextRecords = [
            new SQLiteSchemaRecord(
                'table',
                $shared,
                $shared,
                1500 + $variant,
                "CREATE TABLE {$shared}(key_name TEXT, main_value INTEGER, added_column TEXT DEFAULT 'added_{$variant}', PRIMARY KEY(key_name))",
                1500 + $variant,
            ),
            new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $shared . '_1', $shared, 1600 + $variant, null, 1600 + $variant),
        ];

        $catalog->replaceSchemaRecords('main', $nextRecords);
        $invalidation = $catalog->schemaCacheInvalidation($snapshot);
        $mainRows = $catalog->executeSchemaPragma("PRAGMA main.table_info({$shared})")['rows'];
        $unqualifiedRows = $catalog->executeSchemaPragma("PRAGMA table_info({$shared})")['rows'];

        $t->same(false, $invalidation['current']);
        $t->same(1, $invalidation['before_generation']);
        $t->same(2, $invalidation['after_generation']);
        $t->same([], $invalidation['added_schemas']);
        $t->same([], $invalidation['removed_schemas']);
        $t->same(false, $invalidation['sequence_changed']);
        $t->same('added_column', $mainRows[2]['name']);
        $t->same("'added_{$variant}'", $mainRows[2]['dflt_value']);
        $t->same('temp_value', $unqualifiedRows[1]['name']);
    };
}

return $tests;
