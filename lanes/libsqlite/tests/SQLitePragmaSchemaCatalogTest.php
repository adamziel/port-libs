<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$makeCatalog = static fn (): SQLitePragmaSchemaCatalog => new SQLitePragmaSchemaCatalog([
    new SQLiteSchemaRecord(
        'table',
        'wp_options',
        'wp_options',
        2,
        "CREATE TABLE wp_options(
            option_id INTEGER PRIMARY KEY,
            option_name TEXT NOT NULL DEFAULT '',
            option_value TEXT,
            autoload TEXT DEFAULT 'yes',
            option_name_fold TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL,
            option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) STORED,
            UNIQUE(option_name)
        )",
        1,
    ),
    new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
    new SQLiteSchemaRecord('index', 'wp_options_autoload_name', 'wp_options', 4, 'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)', 3),
    new SQLiteSchemaRecord('index', 'wp_options_large_autoload', 'wp_options', 5, "CREATE UNIQUE INDEX wp_options_large_autoload ON wp_options(option_value_len) WHERE autoload = 'yes'", 4),
]);

$tests = [
    'executes schema PRAGMA table and index catalog rows for application metadata' => static function (TestRunner $t): void {
        $catalog = new SQLitePragmaSchemaCatalog([
            new SQLiteSchemaRecord(
                'table',
                'wp_options',
                'wp_options',
                2,
                "CREATE TABLE wp_options(
                    option_id INTEGER PRIMARY KEY,
                    option_name TEXT NOT NULL DEFAULT '',
                    option_value TEXT,
                    autoload TEXT DEFAULT 'yes',
                    option_name_fold TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL,
                    option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) STORED,
                    UNIQUE(option_name)
                )",
                1,
            ),
            new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
            new SQLiteSchemaRecord('index', 'wp_options_autoload_name', 'wp_options', 4, 'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)', 3),
            new SQLiteSchemaRecord('index', 'wp_options_large_autoload', 'wp_options', 5, "CREATE UNIQUE INDEX wp_options_large_autoload ON wp_options(option_value_len) WHERE autoload = 'yes'", 4),
        ]);

        $tableInfo = $catalog->execute('PRAGMA table_info(wp_options)');
        $t->same('ok', $tableInfo['status']);
        $t->same('table_info', $tableInfo['pragma']);
        $t->same('main', $tableInfo['schema']);
        $t->same('wp_options', $tableInfo['target']);
        $t->same(4, count($tableInfo['rows']));
        $t->same(['cid' => 0, 'name' => 'option_id', 'type' => 'INTEGER', 'notnull' => 1, 'dflt_value' => null, 'pk' => 1], $tableInfo['rows'][0]);
        $t->same(['cid' => 1, 'name' => 'option_name', 'type' => 'TEXT', 'notnull' => 1, 'dflt_value' => "''", 'pk' => 0], $tableInfo['rows'][1]);
        $t->same(['cid' => 2, 'name' => 'option_value', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0], $tableInfo['rows'][2]);
        $t->same(['cid' => 3, 'name' => 'autoload', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => "'yes'", 'pk' => 0], $tableInfo['rows'][3]);

        $xinfo = $catalog->execute('PRAGMA main.table_xinfo("wp_options")');
        $t->same('table_xinfo', $xinfo['pragma']);
        $t->same('main', $xinfo['schema']);
        $t->same(6, count($xinfo['rows']));
        $t->same(0, $xinfo['rows'][0]['hidden']);
        $t->same(0, $xinfo['rows'][3]['hidden']);
        $t->same(['cid' => 4, 'name' => 'option_name_fold', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0, 'hidden' => 2], $xinfo['rows'][4]);
        $t->same(['cid' => 5, 'name' => 'option_value_len', 'type' => 'INTEGER', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0, 'hidden' => 3], $xinfo['rows'][5]);

        $indexList = $catalog->execute('PRAGMA index_list(wp_options)');
        $t->same('index_list', $indexList['pragma']);
        $t->same(3, count($indexList['rows']));
        $t->same(['seq' => 0, 'name' => 'sqlite_autoindex_wp_options_1', 'unique' => 1, 'origin' => 'u', 'partial' => 0], $indexList['rows'][0]);
        $t->same(['seq' => 1, 'name' => 'wp_options_autoload_name', 'unique' => 0, 'origin' => 'c', 'partial' => 0], $indexList['rows'][1]);
        $t->same(['seq' => 2, 'name' => 'wp_options_large_autoload', 'unique' => 1, 'origin' => 'c', 'partial' => 1], $indexList['rows'][2]);

        $autoIndexInfo = $catalog->execute('PRAGMA index_info(sqlite_autoindex_wp_options_1)');
        $t->same('index_info', $autoIndexInfo['pragma']);
        $t->same(1, count($autoIndexInfo['rows']));
        $t->same(['seqno' => 0, 'cid' => 1, 'name' => 'option_name'], $autoIndexInfo['rows'][0]);

        $compoundIndexInfo = $catalog->execute('PRAGMA index_info(wp_options_autoload_name)');
        $t->same(2, count($compoundIndexInfo['rows']));
        $t->same(['seqno' => 0, 'cid' => 3, 'name' => 'autoload'], $compoundIndexInfo['rows'][0]);
        $t->same(['seqno' => 1, 'cid' => 1, 'name' => 'option_name'], $compoundIndexInfo['rows'][1]);

        $partialExpressionInfo = $catalog->execute('PRAGMA index_info(wp_options_large_autoload)');
        $t->same(1, count($partialExpressionInfo['rows']));
        $t->same(['seqno' => 0, 'cid' => 5, 'name' => 'option_value_len'], $partialExpressionInfo['rows'][0]);
        $t->same([], $catalog->execute('PRAGMA table_info(missing_options)')['rows']);
        $t->same([], $catalog->execute('PRAGMA index_info(missing_index)')['rows']);
    },
    'preserves quoted names and table-level primary-key ordinals in schema PRAGMAs' => static function (TestRunner $t): void {
        $catalog = new SQLitePragmaSchemaCatalog([
            new SQLiteSchemaRecord(
                'table',
                'wp site options',
                'wp site options',
                2,
                "CREATE TABLE \"wp site options\"(
                    blog_id INTEGER NOT NULL,
                    option_name TEXT NOT NULL,
                    option_value TEXT DEFAULT (json_object('autoload','yes')),
                    CONSTRAINT wp_site_options_pk PRIMARY KEY(blog_id, option_name),
                    UNIQUE(option_name, blog_id)
                ) WITHOUT ROWID",
                1,
            ),
            new SQLiteSchemaRecord('index', 'sqlite_autoindex_wp site options_1', 'wp site options', 3, null, 2),
        ]);

        $tableInfo = $catalog->execute('PRAGMA table_info("wp site options")');
        $t->same(3, count($tableInfo['rows']));
        $t->same(['cid' => 0, 'name' => 'blog_id', 'type' => 'INTEGER', 'notnull' => 1, 'dflt_value' => null, 'pk' => 1], $tableInfo['rows'][0]);
        $t->same(['cid' => 1, 'name' => 'option_name', 'type' => 'TEXT', 'notnull' => 1, 'dflt_value' => null, 'pk' => 2], $tableInfo['rows'][1]);
        $t->same(['cid' => 2, 'name' => 'option_value', 'type' => 'TEXT', 'notnull' => 0, 'dflt_value' => "(json_object('autoload','yes'))", 'pk' => 0], $tableInfo['rows'][2]);

        $indexList = $catalog->execute('PRAGMA index_list("wp site options")');
        $t->same(1, count($indexList['rows']));
        $t->same('sqlite_autoindex_wp site options_1', $indexList['rows'][0]['name']);
        $t->same(1, $indexList['rows'][0]['unique']);
        $t->same('u', $indexList['rows'][0]['origin']);

        $indexInfo = $catalog->execute('PRAGMA index_info("sqlite_autoindex_wp site options_1")');
        $t->same(2, count($indexInfo['rows']));
        $t->same(['seqno' => 0, 'cid' => 0, 'name' => 'blog_id'], $indexInfo['rows'][0]);
        $t->same(['seqno' => 1, 'cid' => 1, 'name' => 'option_name'], $indexInfo['rows'][1]);

        $schemaQualified = $catalog->execute('PRAGMA temp.table_xinfo("wp site options")');
        $t->same('temp', $schemaQualified['schema']);
        $t->same('wp site options', $schemaQualified['target']);
        $t->same(3, count($schemaQualified['rows']));
    },
    'rejects unsupported schema PRAGMA SQL shapes' => static function (TestRunner $t): void {
        $catalog = new SQLitePragmaSchemaCatalog([]);

        $t->same([], $catalog->execute('PRAGMA foreign_key_list(wp_options)')['rows']);
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->execute('SELECT PRAGMA table_info(wp_options)'));
        $t->throws(InvalidArgumentException::class, static fn () => $catalog->execute('PRAGMA table_info(wp_options'));
    },
];

foreach ([
    'table-info equals syntax preserves table target' => ['PRAGMA table_info = wp_options', 'target', 'wp_options'],
    'table-info quoted single table target' => ["PRAGMA table_info('wp_options')", 'pragma', 'table_info'],
    'table-info bracket quoted table target' => ['PRAGMA table_info([wp_options])', 'target', 'wp_options'],
    'table-info backtick quoted table target' => ['PRAGMA table_info(`wp_options`)', 'target', 'wp_options'],
    'table-xinfo returns generated virtual column' => ['PRAGMA table_xinfo(wp_options)', 'rows.4.hidden', 2],
    'table-xinfo returns generated stored column' => ['PRAGMA table_xinfo(wp_options)', 'rows.5.hidden', 3],
    'table-info omits generated columns' => ['PRAGMA table_info(wp_options)', 'rows.count', 4],
    'table-info reports integer primary key' => ['PRAGMA table_info(wp_options)', 'rows.0.pk', 1],
    'table-info reports text default literal' => ['PRAGMA table_info(wp_options)', 'rows.3.dflt_value', "'yes'"],
    'table-info reports not-null option name' => ['PRAGMA table_info(wp_options)', 'rows.1.notnull', 1],
    'table-info reports nullable option value' => ['PRAGMA table_info(wp_options)', 'rows.2.notnull', 0],
    'schema-qualified table-xinfo keeps schema name' => ['PRAGMA aux.table_xinfo(wp_options)', 'schema', 'aux'],
    'index-list returns autoindex first' => ['PRAGMA index_list(wp_options)', 'rows.0.name', 'sqlite_autoindex_wp_options_1'],
    'index-list reports created index origin' => ['PRAGMA index_list(wp_options)', 'rows.1.origin', 'c'],
    'index-list reports explicit nonunique index' => ['PRAGMA index_list(wp_options)', 'rows.1.unique', 0],
    'index-list reports explicit unique index' => ['PRAGMA index_list(wp_options)', 'rows.2.unique', 1],
    'index-list reports partial index' => ['PRAGMA index_list(wp_options)', 'rows.2.partial', 1],
    'index-info autoindex resolves unique column cid' => ['PRAGMA index_info(sqlite_autoindex_wp_options_1)', 'rows.0.cid', 1],
    'index-info autoindex resolves unique column name' => ['PRAGMA index_info(sqlite_autoindex_wp_options_1)', 'rows.0.name', 'option_name'],
    'index-info compound first column' => ['PRAGMA index_info(wp_options_autoload_name)', 'rows.0.name', 'autoload'],
    'index-info compound second column' => ['PRAGMA index_info(wp_options_autoload_name)', 'rows.1.name', 'option_name'],
    'index-info expression-backed generated column cid' => ['PRAGMA index_info(wp_options_large_autoload)', 'rows.0.cid', 5],
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
