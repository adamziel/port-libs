<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$makeCatalog = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([
    new SQLiteSchemaRecord(
        'table',
        'app_settings',
        'app_settings',
        2,
        "CREATE TABLE app_settings(
            setting_id INTEGER PRIMARY KEY,
            setting_key TEXT NOT NULL DEFAULT '',
            setting_value TEXT,
            eager_load TEXT DEFAULT 'yes',
            setting_key_fold TEXT GENERATED ALWAYS AS (lower(setting_key)) VIRTUAL,
            setting_value_len INTEGER GENERATED ALWAYS AS (length(setting_value)) STORED,
            UNIQUE(setting_key)
        )",
        1,
    ),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_app_settings_1', 'app_settings', 3, null, 2),
    new SQLiteSchemaRecord('index', 'app_settings_eager_load_key', 'app_settings', 4, 'CREATE INDEX app_settings_eager_load_key ON app_settings(eager_load, setting_key)', 3),
    new SQLiteSchemaRecord('index', 'app_settings_large_eager_load', 'app_settings', 5, "CREATE UNIQUE INDEX app_settings_large_eager_load ON app_settings(setting_value_len) WHERE eager_load = 'yes'", 4),
]);

$tests = [
    'executes schema PRAGMA table and index catalog rows for application metadata' => static function (TestRunner $t): void {
        $catalog = new SQLitePragmaSchemaCatalog([
            new SQLiteSchemaRecord(
                'table',
                'app_settings',
                'app_settings',
                2,
                "CREATE TABLE app_settings(
                    setting_id INTEGER PRIMARY KEY,
                    setting_key TEXT NOT NULL DEFAULT '',
                    setting_value TEXT,
                    eager_load TEXT DEFAULT 'yes',
                    setting_key_fold TEXT GENERATED ALWAYS AS (lower(setting_key)) VIRTUAL,
                    setting_value_len INTEGER GENERATED ALWAYS AS (length(setting_value)) STORED,
                    UNIQUE(setting_key)
                )",
                1,
            ),
            new SQLiteSchemaRecord('index', 'sqlite_autoindex_app_settings_1', 'app_settings', 3, null, 2),
            new SQLiteSchemaRecord('index', 'app_settings_eager_load_key', 'app_settings', 4, 'CREATE INDEX app_settings_eager_load_key ON app_settings(eager_load, setting_key)', 3),
            new SQLiteSchemaRecord('index', 'app_settings_large_eager_load', 'app_settings', 5, "CREATE UNIQUE INDEX app_settings_large_eager_load ON app_settings(setting_value_len) WHERE eager_load = 'yes'", 4),
        ]);

        $tableInfo = $catalog->execute('PRAGMA table_info(app_settings)');
        $t->same('ok', $tableInfo['status']);
        $t->same('table_info', $tableInfo['pragma']);
        $t->same('main', $tableInfo['schema']);
        $t->same('app_settings', $tableInfo['target']);
        $t->same(4, count($tableInfo['rows']));
        $t->same(['cid' => 0, 'name' => 'setting_id', 'type' => 'INTEGER', 'notnull' => 0, 'dflt_value' => null, 'pk' => 1], $tableInfo['rows'][0]);
        $t->same(['cid' => 1, 'name' => 'setting_key', 'type' => 'TEXT', 'notnull' => 1, 'dflt_value' => "''", 'pk' => 0], $tableInfo['rows'][1]);
        $t->same(['cid' => 2, 'name' => 'setting_value', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0], $tableInfo['rows'][2]);
        $t->same(['cid' => 3, 'name' => 'eager_load', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => "'yes'", 'pk' => 0], $tableInfo['rows'][3]);

        $xinfo = $catalog->execute('PRAGMA main.table_xinfo("app_settings")');
        $t->same('table_xinfo', $xinfo['pragma']);
        $t->same('main', $xinfo['schema']);
        $t->same(6, count($xinfo['rows']));
        $t->same(0, $xinfo['rows'][0]['hidden']);
        $t->same(0, $xinfo['rows'][3]['hidden']);
        $t->same(['cid' => 4, 'name' => 'setting_key_fold', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0, 'hidden' => 2], $xinfo['rows'][4]);
        $t->same(['cid' => 5, 'name' => 'setting_value_len', 'type' => 'INTEGER', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0, 'hidden' => 3], $xinfo['rows'][5]);

        $indexList = $catalog->execute('PRAGMA index_list(app_settings)');
        $t->same('index_list', $indexList['pragma']);
        $t->same(3, count($indexList['rows']));
        $t->same(['seq' => 0, 'name' => 'sqlite_autoindex_app_settings_1', 'unique' => 1, 'origin' => 'u', 'partial' => 0], $indexList['rows'][0]);
        $t->same(['seq' => 1, 'name' => 'app_settings_eager_load_key', 'unique' => 0, 'origin' => 'c', 'partial' => 0], $indexList['rows'][1]);
        $t->same(['seq' => 2, 'name' => 'app_settings_large_eager_load', 'unique' => 1, 'origin' => 'c', 'partial' => 1], $indexList['rows'][2]);

        $autoIndexInfo = $catalog->execute('PRAGMA index_info(sqlite_autoindex_app_settings_1)');
        $t->same('index_info', $autoIndexInfo['pragma']);
        $t->same(1, count($autoIndexInfo['rows']));
        $t->same(['seqno' => 0, 'cid' => 1, 'name' => 'setting_key'], $autoIndexInfo['rows'][0]);

        $compoundIndexInfo = $catalog->execute('PRAGMA index_info(app_settings_eager_load_key)');
        $t->same(2, count($compoundIndexInfo['rows']));
        $t->same(['seqno' => 0, 'cid' => 3, 'name' => 'eager_load'], $compoundIndexInfo['rows'][0]);
        $t->same(['seqno' => 1, 'cid' => 1, 'name' => 'setting_key'], $compoundIndexInfo['rows'][1]);

        $partialExpressionInfo = $catalog->execute('PRAGMA index_info(app_settings_large_eager_load)');
        $t->same(1, count($partialExpressionInfo['rows']));
        $t->same(['seqno' => 0, 'cid' => 5, 'name' => 'setting_value_len'], $partialExpressionInfo['rows'][0]);
        $t->same([], $catalog->execute('PRAGMA table_info(missing_settings)')['rows']);
        $t->same([], $catalog->execute('PRAGMA index_info(missing_index)')['rows']);
    },
    'preserves quoted names and table-level primary-key ordinals in schema PRAGMAs' => static function (TestRunner $t): void {
        $catalog = new SQLitePragmaSchemaCatalog([
            new SQLiteSchemaRecord(
                'table',
                'app tenant settings',
                'app tenant settings',
                2,
                "CREATE TABLE \"app tenant settings\"(
                    tenant_id INTEGER NOT NULL,
                    setting_key TEXT NOT NULL,
                    setting_value TEXT DEFAULT (json_object('eager_load','yes')),
                    CONSTRAINT tenant_settings_pk PRIMARY KEY(tenant_id, setting_key),
                    UNIQUE(setting_key, tenant_id)
                ) WITHOUT ROWID",
                1,
            ),
            new SQLiteSchemaRecord('index', 'sqlite_autoindex_app tenant settings_1', 'app tenant settings', 3, null, 2),
        ]);

        $tableInfo = $catalog->execute('PRAGMA table_info("app tenant settings")');
        $t->same(3, count($tableInfo['rows']));
        $t->same(['cid' => 0, 'name' => 'tenant_id', 'type' => 'INTEGER', 'notnull' => 1, 'dflt_value' => null, 'pk' => 1], $tableInfo['rows'][0]);
        $t->same(['cid' => 1, 'name' => 'setting_key', 'type' => 'TEXT', 'notnull' => 1, 'dflt_value' => null, 'pk' => 2], $tableInfo['rows'][1]);
        $t->same(['cid' => 2, 'name' => 'setting_value', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => "json_object('eager_load','yes')", 'pk' => 0], $tableInfo['rows'][2]);

        $indexList = $catalog->execute('PRAGMA index_list("app tenant settings")');
        $t->same(1, count($indexList['rows']));
        $t->same('sqlite_autoindex_app tenant settings_1', $indexList['rows'][0]['name']);
        $t->same(1, $indexList['rows'][0]['unique']);
        $t->same('pk', $indexList['rows'][0]['origin']);

        $indexInfo = $catalog->execute('PRAGMA index_info("sqlite_autoindex_app tenant settings_1")');
        $t->same(2, count($indexInfo['rows']));
        $t->same(['seqno' => 0, 'cid' => 0, 'name' => 'tenant_id'], $indexInfo['rows'][0]);
        $t->same(['seqno' => 1, 'cid' => 1, 'name' => 'setting_key'], $indexInfo['rows'][1]);

        $schemaQualified = $catalog->execute('PRAGMA temp.table_xinfo("app tenant settings")');
        $t->same('temp', $schemaQualified['schema']);
        $t->same('app tenant settings', $schemaQualified['target']);
        $t->same(3, count($schemaQualified['rows']));
    },
    'rejects unsupported schema PRAGMA SQL shapes' => static function (TestRunner $t): void {
        $catalog = new SQLitePragmaSchemaCatalog([]);

        $t->same([], $catalog->execute('PRAGMA foreign_key_list(app_settings)')['rows']);
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->execute('SELECT PRAGMA table_info(app_settings)'));
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->execute('PRAGMA table_info(app_settings'));
    },
];

foreach ([
    'table-info equals syntax preserves table target' => ['PRAGMA table_info = app_settings', 'target', 'app_settings'],
    'table-info quoted single table target' => ["PRAGMA table_info('app_settings')", 'pragma', 'table_info'],
    'table-info bracket quoted table target' => ['PRAGMA table_info([app_settings])', 'target', 'app_settings'],
    'table-info backtick quoted table target' => ['PRAGMA table_info(`app_settings`)', 'target', 'app_settings'],
    'table-xinfo returns generated virtual column' => ['PRAGMA table_xinfo(app_settings)', 'rows.4.hidden', 2],
    'table-xinfo returns generated stored column' => ['PRAGMA table_xinfo(app_settings)', 'rows.5.hidden', 3],
    'table-info omits generated columns' => ['PRAGMA table_info(app_settings)', 'rows.count', 4],
    'table-info reports integer primary key' => ['PRAGMA table_info(app_settings)', 'rows.0.pk', 1],
    'table-info reports text default literal' => ['PRAGMA table_info(app_settings)', 'rows.3.dflt_value', "'yes'"],
    'table-info reports not-null option name' => ['PRAGMA table_info(app_settings)', 'rows.1.notnull', 1],
    'table-info reports nullable option value' => ['PRAGMA table_info(app_settings)', 'rows.2.notnull', 0],
    'schema-qualified table-xinfo keeps schema name' => ['PRAGMA aux.table_xinfo(app_settings)', 'schema', 'aux'],
    'index-list returns autoindex first' => ['PRAGMA index_list(app_settings)', 'rows.0.name', 'sqlite_autoindex_app_settings_1'],
    'index-list reports created index origin' => ['PRAGMA index_list(app_settings)', 'rows.1.origin', 'c'],
    'index-list reports explicit nonunique index' => ['PRAGMA index_list(app_settings)', 'rows.1.unique', 0],
    'index-list reports explicit unique index' => ['PRAGMA index_list(app_settings)', 'rows.2.unique', 1],
    'index-list reports partial index' => ['PRAGMA index_list(app_settings)', 'rows.2.partial', 1],
    'index-info autoindex resolves unique column cid' => ['PRAGMA index_info(sqlite_autoindex_app_settings_1)', 'rows.0.cid', 1],
    'index-info autoindex resolves unique column name' => ['PRAGMA index_info(sqlite_autoindex_app_settings_1)', 'rows.0.name', 'setting_key'],
    'index-info compound first column' => ['PRAGMA index_info(app_settings_eager_load_key)', 'rows.0.name', 'eager_load'],
    'index-info compound second column' => ['PRAGMA index_info(app_settings_eager_load_key)', 'rows.1.name', 'setting_key'],
    'index-info expression-backed generated column cid' => ['PRAGMA index_info(app_settings_large_eager_load)', 'rows.0.cid', 5],
    'missing table-info returns empty rows' => ['PRAGMA table_info(missing)', 'rows.count', 0],
    'missing index-info returns empty rows' => ['PRAGMA index_info(missing)', 'rows.count', 0],
] as $name => [$sql, $path, $expected]) {
    $tests[$name] = static function (TestRunner $t) use ($makeCatalog, $sql, $path, $expected): void {
        $value = $makeCatalog()->execute($sql);
        foreach (explode('.', $path) as $part) {
            if ($part === 'count') {
                $value = count($value);
                continue;
            }
            $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
        }
        $t->same($expected, $value);
    };
}

return $tests;
