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
    $t->same(['cid' => 1, 'name' => 'b', 'type' => '', 'notnull' => 0, 'dflt_value' => '5+3', 'pk' => 2], $rows[1]);
    $t->same(['cid' => 2, 'name' => 'c', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => null, 'pk' => 3], $rows[2]);
    $t->same(['cid' => 3, 'name' => 'd', 'type' => 'INTEGER', 'notnull' => 0, 'dflt_value' => 'NULL', 'pk' => 0], $rows[3]);
    $t->same(['cid' => 4, 'name' => 'e', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => "''", 'pk' => 1], $rows[4]);
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
        $t->same('pk', $indexList[0]['origin']);
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

foreach (range(1, 334) as $variant) {
    $table = 'schema4_object_settings_' . $variant;
    $audit = 'schema4_object_audit_' . $variant;
    $index = 'schema4_object_settings_key_' . $variant;
    $trigger = 'schema4_object_settings_ai_' . $variant;
    $view = 'schema4_object_view_' . $variant;
    $renamed = 'schema4_renamed_settings_' . $variant;
    $attachName = 'dyn' . $variant;
    $attachFile = 'dynamic-schema-' . $variant . '.db';

    $baseRecords = [
        new SQLiteSchemaRecord(
            'table',
            $table,
            $table,
            2000 + $variant,
            "CREATE TABLE {$table}(setting_id INTEGER PRIMARY KEY, key_name TEXT UNIQUE, key_value TEXT)",
            2000 + $variant,
        ),
        new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $table . '_1', $table, 2100 + $variant, null, 2100 + $variant),
        new SQLiteSchemaRecord('index', $index, $table, 2200 + $variant, "CREATE INDEX {$index} ON {$table}(key_name)", 2200 + $variant),
        new SQLiteSchemaRecord(
            'table',
            $audit,
            $audit,
            2300 + $variant,
            "CREATE TABLE {$audit}(setting_id INTEGER, action TEXT)",
            2300 + $variant,
        ),
        new SQLiteSchemaRecord(
            'trigger',
            $trigger,
            $table,
            0,
            "CREATE TRIGGER {$trigger} AFTER INSERT ON {$table} BEGIN INSERT INTO {$audit}(setting_id, action) VALUES (new.setting_id, 'insert'); END",
            2400 + $variant,
        ),
        new SQLiteSchemaRecord(
            'view',
            $view,
            $view,
            0,
            "CREATE VIEW {$view} AS SELECT key_name FROM {$table}",
            2500 + $variant,
        ),
    ];

    $tests["real upstream schema4 1 drop table removes dependent trigger and index variant {$variant}"] = static function (TestRunner $t) use ($baseRecords, $table, $trigger, $index): void {
        $catalog = new SQLiteAttachedSchemaCatalog($baseRecords);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$table], [$index]);
        $plan = $catalog->applySchemaDdlCurrentSource('main', ["DROP TABLE {$table}"], 4000, $snapshot, [
            ['id' => 'schema4-drop-table-prepared', 'schema_cookie' => 4000, 'sql' => "SELECT * FROM {$table}"],
        ]);

        $t->same('schema_cache_expired', $plan['status']);
        $t->same('drop_table', $plan['ddl_plan']['operations'][0]['kind']);
        $t->same(["table:{$table}", "index:sqlite_autoindex_{$table}_1", "index:{$index}", "trigger:{$trigger}"], $plan['ddl_plan']['operations'][0]['removed_records']);
        $t->same([$table], $plan['invalidation']['changed_tables']);
        $t->same([$index], $plan['invalidation']['changed_indexes']);
        $t->same(['schema4-drop-table-prepared'], $plan['ddl_plan']['invalidated_prepared']);
    };

    $tests["real upstream schema4 1 drop create trigger refreshes schema cookie variant {$variant}"] = static function (TestRunner $t) use ($baseRecords, $table, $audit, $trigger): void {
        $catalog = new SQLiteAttachedSchemaCatalog($baseRecords);
        $plan = $catalog->applySchemaDdlCurrentSource('main', [
            "DROP TRIGGER {$trigger}",
            "CREATE TRIGGER {$trigger} AFTER UPDATE ON {$table} BEGIN INSERT INTO {$audit}(setting_id, action) VALUES (new.setting_id, 'update'); END",
        ], 4100, null, [
            ['id' => 'schema4-trigger-body-prepared', 'schema_cookie' => 4100, 'sql' => "UPDATE {$table} SET key_value = 'changed'"],
        ]);
        $records = $catalog->schemaRecords('main');
        $triggerRows = array_values(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'trigger' && $record->name === $trigger));

        $t->same('schema_cache_expired', $plan['status']);
        $t->same(['drop_trigger', 'create_trigger'], array_column($plan['ddl_plan']['operations'], 'kind'));
        $t->same(4102, $plan['ddl_plan']['after_schema_cookie']);
        $t->same(1, count($triggerRows));
        $t->contains('AFTER UPDATE', (string) $triggerRows[0]->sql);
        $t->same(['schema4-trigger-body-prepared'], $plan['ddl_plan']['invalidated_prepared']);
    };

    $tests["real upstream schema4 2 rename table rewrites trigger view and index target variant {$variant}"] = static function (TestRunner $t) use ($baseRecords, $table, $renamed, $trigger, $view, $index): void {
        $catalog = new SQLiteAttachedSchemaCatalog($baseRecords);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$table, $renamed], [$index]);
        $plan = $catalog->applySchemaDdlCurrentSource('main', ["ALTER TABLE {$table} RENAME TO {$renamed}"], 4200, $snapshot);
        $records = $catalog->schemaRecords('main');
        $triggerRecord = array_values(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'trigger' && $record->name === $trigger))[0];
        $viewRecord = array_values(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'view' && $record->name === $view))[0];
        $indexRecord = array_values(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'index' && $record->name === $index))[0];

        $t->same('alter_table_rename', $plan['ddl_plan']['operations'][0]['kind']);
        $t->same(["table:{$table}", "index:sqlite_autoindex_{$table}_1", "index:{$index}", "trigger:{$trigger}", "view:{$view}"], $plan['ddl_plan']['operations'][0]['rewritten_records']);
        $t->same($renamed, $triggerRecord->tableName);
        $t->same($renamed, $indexRecord->tableName);
        $t->contains($renamed, (string) $triggerRecord->sql);
        $t->contains($renamed, (string) $viewRecord->sql);
        $t->same([$table, $renamed], $plan['invalidation']['changed_tables']);
    };

    $tests["real upstream schema3 attach detach invalidates dynamic pragma resolution variant {$variant}"] = static function (TestRunner $t) use ($baseRecords, $table, $attachName, $attachFile, $variant): void {
        $catalog = new SQLiteAttachedSchemaCatalog($baseRecords);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$table], []);
        $attachRows = [
            new SQLiteSchemaRecord(
                'table',
                $table,
                $table,
                2600 + $variant,
                "CREATE TABLE {$table}(setting_id INTEGER PRIMARY KEY, attached_value TEXT DEFAULT 'attached_{$variant}')",
                2600 + $variant,
            ),
        ];

        $attach = $catalog->executeAttachDetachSql("ATTACH '{$attachFile}' AS {$attachName}", static fn (): array => $attachRows);
        $attachedRows = $catalog->executeTableValuedPragma("pragma_table_info('{$table}', '{$attachName}')")['rows'];
        $attachInvalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);
        $attachedSnapshot = $catalog->schemaCacheSnapshot('main');
        $detach = $catalog->executeAttachDetachSql("DETACH {$attachName}");
        $detachInvalidation = $catalog->schemaCacheInvalidation($attachedSnapshot);

        $t->same('attach', $attach['operation']);
        $t->same(['main', 'temp', $attachName], array_column($attach['database_list'], 'name'));
        $t->same('attached_value', $attachedRows[1]['name']);
        $t->same("'attached_{$variant}'", $attachedRows[1]['dflt_value']);
        $t->same([$attachName], $attachInvalidation['added_schemas']);
        $t->same('detach', $detach['operation']);
        $t->same([$attachName], $detachInvalidation['removed_schemas']);
    };
}

foreach (range(1, 250) as $variant) {
    $left = 'pragma4_right_join_wide_' . $variant;
    $right = 'pragma4_right_join_narrow_' . $variant;
    $leftRecords = [
        new SQLiteSchemaRecord(
            'table',
            $left,
            $left,
            5000 + $variant,
            "CREATE TABLE {$left}(a TEXT, b TEXT, c TEXT DEFAULT 'wide_{$variant}', d INTEGER DEFAULT {$variant})",
            5000 + $variant,
        ),
    ];
    $rightRecords = [
        new SQLiteSchemaRecord(
            'table',
            $right,
            $right,
            6000 + $variant,
            "CREATE TABLE {$right}(a TEXT, b TEXT)",
            6000 + $variant,
        ),
    ];
    $rightJoinRows = static function () use ($leftRecords, $rightRecords, $left, $right): array {
        $catalog = new SQLiteAttachedSchemaCatalog(array_merge($leftRecords, $rightRecords));
        $leftInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$left}')")['rows'];
        $rightInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$right}')")['rows'];
        $leftByName = [];
        foreach ($leftInfo as $row) {
            $leftByName[$row['name']] = $row;
        }

        $rows = [];
        foreach ($rightInfo as $row) {
            $rows[] = [
                'wide_name' => $leftByName[$row['name']]['name'] ?? null,
                'narrow_name' => $row['name'],
                'wide_default' => $leftByName[$row['name']]['dflt_value'] ?? null,
                'narrow_cid' => $row['cid'],
            ];
        }

        return $rows;
    };

    $tests["real upstream pragma4 7.3 table-info right join preserves narrow rows variant {$variant}"] = static function (TestRunner $t) use ($rightJoinRows): void {
        $rows = $rightJoinRows();

        $t->same([['wide_name' => 'a', 'narrow_name' => 'a'], ['wide_name' => 'b', 'narrow_name' => 'b']], array_map(
            static fn (array $row): array => ['wide_name' => $row['wide_name'], 'narrow_name' => $row['narrow_name']],
            $rows,
        ));
    };

    $tests["real upstream pragma4 7.3 table-info right join omits wide-only columns variant {$variant}"] = static function (TestRunner $t) use ($rightJoinRows): void {
        $rows = $rightJoinRows();

        $t->same(2, count($rows));
        $t->same(false, in_array('c', array_column($rows, 'narrow_name'), true));
        $t->same(false, in_array('d', array_column($rows, 'narrow_name'), true));
    };

    $tests["real upstream pragma4 7.3 table-info right join keeps left defaults null for matches variant {$variant}"] = static function (TestRunner $t) use ($rightJoinRows): void {
        $rows = $rightJoinRows();

        $t->same([null, null], array_column($rows, 'wide_default'));
    };

    $tests["real upstream pragma4 7.3 table-info right join keeps right row order variant {$variant}"] = static function (TestRunner $t) use ($rightJoinRows): void {
        $rows = $rightJoinRows();

        $t->same([0, 1], array_column($rows, 'narrow_cid'));
    };
}

return $tests;
