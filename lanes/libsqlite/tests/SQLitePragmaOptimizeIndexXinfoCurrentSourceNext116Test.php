<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaOptimizeIndexXinfoCurrentSourceYield;
use PortLibs\LibSqlite\SQLitePragmaOptimizePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowId = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$makeCatalog = static function (bool $tempExpression = true) use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT DEFAULT 'yes')", 1),
            $record('index', 'wp_options_name_main', 'wp_options', 3, 'CREATE INDEX wp_options_name_main ON wp_options(option_name COLLATE NOCASE DESC)', 2),
            $record('table', 'wp_postmeta', 'wp_postmeta', 4, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER, meta_key TEXT, meta_value TEXT)', 3),
            $record('index', 'wp_postmeta_key', 'wp_postmeta', 5, 'CREATE INDEX wp_postmeta_key ON wp_postmeta(meta_key)', 4),
        ],
        [
            $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_name TEXT, option_value TEXT, autoload TEXT)', 1),
            $record('index', 'wp_options_name_temp', 'wp_options', 7, $tempExpression
                ? 'CREATE INDEX wp_options_name_temp ON wp_options(option_name, length(option_value) COLLATE BINARY DESC)'
                : 'CREATE INDEX wp_options_name_temp ON wp_options(option_name, autoload)', 2),
        ],
    );
    $catalog->attach('archive', '/srv/archive.sqlite', [
        $record('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(blog_id INTEGER, option_name TEXT, option_value TEXT)', 1),
        $record('index', 'wp_options_archive_name', 'wp_options', 9, 'CREATE INDEX wp_options_archive_name ON wp_options(blog_id, option_name COLLATE RTRIM)', 2),
    ]);

    return $catalog;
};

$sqls = [
    'PRAGMA index_xinfo(wp_options_name_temp)',
    'PRAGMA main.index_xinfo(wp_options_name_main)',
    'PRAGMA index_xinfo(wp_postmeta_key)',
    'pragma_index_xinfo("wp_options_archive_name","archive")',
];

$tables = [
    [
        'schema' => 'temp',
        'name' => 'wp_options',
        'rowCount' => 9000,
        'statRowCount' => 100,
        'touched' => true,
        'schemaCookie' => 14,
        'expectedSchemaCookie' => 14,
        'statCookie' => 2,
        'expectedStatCookie' => 2,
        'sourceId' => 'temp-wp-options-v14',
        'expectedSourceId' => 'temp-wp-options-v14',
    ],
    [
        'schema' => 'main',
        'name' => 'wp_options',
        'rowCount' => 12000,
        'statRowCount' => 8000,
        'touched' => true,
        'schemaCookie' => 41,
        'expectedSchemaCookie' => 41,
        'statCookie' => 7,
        'expectedStatCookie' => 7,
        'sourceId' => 'main-wp-options-v41',
        'expectedSourceId' => 'main-wp-options-v41',
    ],
    [
        'schema' => 'main',
        'name' => 'wp_postmeta',
        'rowCount' => 240000,
        'statRowCount' => 240000,
        'touched' => false,
        'schemaCookie' => 41,
        'expectedSchemaCookie' => 41,
        'statCookie' => 7,
        'expectedStatCookie' => 7,
        'sourceId' => 'main-wp-postmeta-v41',
        'expectedSourceId' => 'main-wp-postmeta-v41',
    ],
    [
        'schema' => 'archive',
        'name' => 'wp_options',
        'rowCount' => 4000,
        'statRowCount' => 400,
        'touched' => true,
        'schemaCookie' => 3,
        'expectedSchemaCookie' => 4,
        'statCookie' => 1,
        'expectedStatCookie' => 1,
        'sourceId' => 'archive-wp-options-v3',
        'expectedSourceId' => 'archive-wp-options-v3',
    ],
];

$page = static fn (?array $resume = null, int $offset = 0, int $limit = 10, bool $tempExpression = true, string $optimizeSql = 'PRAGMA optimize'): array => SQLitePragmaOptimizeIndexXinfoCurrentSourceYield::page(
    $makeCatalog($tempExpression),
    $sqls,
    new SQLitePragmaOptimizePlan(),
    $optimizeSql,
    $tables,
    $offset,
    $limit,
    $resume,
);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : $value[(int) $part];
    }

    return $value;
};

$tests = [];

foreach ([
    'status' => ['status', 'ok'],
    'row count' => ['row_count', 4],
    'next offset capped' => ['next_offset', 4],
    'optimize schema defaults main' => ['optimize.schema', 'main'],
    'optimize analyze count main only' => ['optimize.analyze_count', 1],
    'optimize skipped count main only' => ['optimize.skipped_count', 1],
    'optimize source stable main' => ['optimize.stable', true],
    'temp row resolves temp schema' => ['rows.0.schema', 'temp'],
    'temp row resolves index' => ['rows.0.index', 'wp_options_name_temp'],
    'temp row resolves owner table' => ['rows.0.table', 'wp_options'],
    'temp row keeps rootpage' => ['rows.0.rootpage', 7],
    'temp row has three xinfo rows' => ['rows.0.row_count', 3],
    'temp row has two key columns' => ['rows.0.key_columns', 2],
    'temp row has one auxiliary row' => ['rows.0.auxiliary_columns', 1],
    'temp row has expression column' => ['rows.0.expression_columns', 1],
    'temp row has rowid auxiliary' => ['rows.0.rowid_auxiliary', 1],
    'temp row has descending key' => ['rows.0.descending_columns', 1],
    'temp row keeps binary collation' => ['rows.0.collations', ['BINARY']],
    'temp key names include expression null' => ['rows.0.key_names', ['option_name', null]],
    'temp is unseen by main optimize' => ['rows.0.optimize_action', 'unseen'],
    'main row resolves schema' => ['rows.1.schema', 'main'],
    'main row resolves index' => ['rows.1.index', 'wp_options_name_main'],
    'main row owner table' => ['rows.1.table', 'wp_options'],
    'main row key names' => ['rows.1.key_names', ['option_name']],
    'main row no expression columns' => ['rows.1.expression_columns', 0],
    'main row rowid auxiliary' => ['rows.1.rowid_auxiliary', 1],
    'main row no case collation' => ['rows.1.collations', ['NOCASE', 'BINARY']],
    'main row descending count' => ['rows.1.descending_columns', 1],
    'main row analyze action' => ['rows.1.optimize_action', 'analyze'],
    'main row touched reason' => ['rows.1.optimize_reason', 'touched-table'],
    'main row analyze sql' => ['rows.1.optimize_sql', 'ANALYZE "main"."wp_options"'],
    'main row current source token' => ['rows.1.current_source', 'main:wp_options:schema=41:stat=7:source=main-wp-options-v41'],
    'postmeta row resolves index' => ['rows.2.index', 'wp_postmeta_key'],
    'postmeta row owner table' => ['rows.2.table', 'wp_postmeta'],
    'postmeta row skip action' => ['rows.2.optimize_action', 'skip'],
    'postmeta row skip reason' => ['rows.2.optimize_reason', 'up-to-date'],
    'postmeta row source token' => ['rows.2.current_source', 'main:wp_postmeta:schema=41:stat=7:source=main-wp-postmeta-v41'],
    'archive row resolves schema' => ['rows.3.schema', 'archive'],
    'archive row resolves owner' => ['rows.3.table', 'wp_options'],
    'archive row key count' => ['rows.3.key_columns', 2],
    'archive row rtrim collation' => ['rows.3.collations', ['BINARY', 'RTRIM']],
    'archive row unseen by main optimize' => ['rows.3.optimize_action', 'unseen'],
] as $name => [$path, $expected]) {
    $tests['pragma optimize index_xinfo current source next116 ' . $name] = static function (TestRunner $t) use ($page, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($page(), $path));
    };
}

$tests['pragma optimize index_xinfo current source next116 attached optimize sees archive stale'] = static function (TestRunner $t) use ($page): void {
    $result = $page(null, 0, 10, true, 'PRAGMA archive.optimize');

    $t->same('archive', $result['optimize']['schema']);
    $t->same(false, $result['optimize']['stable']);
    $t->same('skip', $result['rows'][3]['optimize_action']);
    $t->same('stale-current-source', $result['rows'][3]['optimize_reason']);
    $t->same('archive:wp_options:schema=3:stat=1:source=archive-wp-options-v3', $result['rows'][3]['current_source']);
};

$tests['pragma optimize index_xinfo current source next116 force mask analyzes stable skipped table'] = static function (TestRunner $t) use ($page): void {
    $result = $page(null, 0, 10, true, 'PRAGMA optimize=0x10000');

    $t->same('analyze', $result['rows'][1]['optimize_action']);
    $t->same('analyze', $result['rows'][2]['optimize_action']);
    $t->same('force-all', $result['rows'][2]['optimize_reason']);
    $t->same('ANALYZE "main"."wp_postmeta"', $result['rows'][2]['optimize_sql']);
};

$tests['pragma optimize index_xinfo current source next116 paginates stable source'] = static function (TestRunner $t) use ($page): void {
    $first = $page(null, 0, 2);
    $second = $page(['source_id' => $first['source_id'], 'next_offset' => 2], 2, 2);

    $t->same(2, count($first['rows']));
    $t->same(2, count($second['rows']));
    $t->same('wp_options_name_temp', $first['rows'][0]['index']);
    $t->same('wp_postmeta_key', $second['rows'][0]['index']);
    $t->same($first['source_id'], $second['source_id']);
};

$tests['pragma optimize index_xinfo current source next116 source changes with index xinfo rows'] = static function (TestRunner $t) use ($page): void {
    $expression = $page(null, 0, 10, true);
    $plain = $page(null, 0, 10, false);

    $t->same(true, $expression['source_id'] !== $plain['source_id']);
    $t->same(1, $expression['rows'][0]['expression_columns']);
    $t->same(0, $plain['rows'][0]['expression_columns']);
    $t->same(['option_name', 'autoload'], $plain['rows'][0]['key_names']);
};

$tests['pragma optimize index_xinfo current source next116 rejects stale current source cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(null, 0, 2, true);

    $t->throws(InvalidArgumentException::class, static fn () => $page(['source_id' => $first['source_id'], 'next_offset' => 2], 2, 2, false));
};

$tests['pragma optimize index_xinfo current source next116 rejects stale offset cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(null, 0, 2);

    $t->throws(InvalidArgumentException::class, static fn () => $page(['source_id' => $first['source_id'], 'next_offset' => 2], 3, 1));
};

$tests['pragma optimize index_xinfo current source next116 accepts source only cursor'] = static function (TestRunner $t) use ($page): void {
    $first = $page(null, 0, 1);
    $third = $page(['source_id' => $first['source_id']], 2, 1);

    $t->same('wp_postmeta_key', $third['rows'][0]['index']);
};

$tests['pragma optimize index_xinfo current source next116 rejects unsupported pragma'] = static function (TestRunner $t) use ($makeCatalog, $tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaOptimizeIndexXinfoCurrentSourceYield::page($makeCatalog(), ['PRAGMA table_info(wp_options)'], new SQLitePragmaOptimizePlan(), 'PRAGMA optimize', $tables, 0, 1));
};

$tests['pragma optimize index_xinfo current source next116 rejects bad offset limit and empty sql'] = static function (TestRunner $t) use ($makeCatalog, $sqls, $tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaOptimizeIndexXinfoCurrentSourceYield::page($makeCatalog(), $sqls, new SQLitePragmaOptimizePlan(), 'PRAGMA optimize', $tables, -1, 1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaOptimizeIndexXinfoCurrentSourceYield::page($makeCatalog(), $sqls, new SQLitePragmaOptimizePlan(), 'PRAGMA optimize', $tables, 0, 0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaOptimizeIndexXinfoCurrentSourceYield::page($makeCatalog(), [''], new SQLitePragmaOptimizePlan(), 'PRAGMA optimize', $tables, 0, 1));
};

return $tests;
