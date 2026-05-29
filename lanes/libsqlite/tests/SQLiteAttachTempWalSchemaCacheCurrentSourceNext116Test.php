<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas116 = [
    'main' => [
        'schema_cookie' => 40,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 41, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name', 'wp_posts_status_date'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 5,
        'tables' => ['wp_options_stage'],
        'indexes' => ['wp_options_stage_name'],
        'temp' => true,
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 9,
        'tables' => ['wp_options'],
        'indexes' => ['wp_archive_option_name'],
        'file' => '/srv/wp/archive.sqlite',
    ],
    'site' => [
        'schema_cookie' => 2,
        'tables' => ['wp_2_options'],
        'indexes' => ['wp_2_options_name'],
        'file' => '/srv/wp/site.sqlite',
    ],
];

$statements116 = [
    ['name' => 'main-indexed-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_autoload_name WHERE autoload = ?'],
    ['name' => 'temp-indexed-writer', 'sql' => 'UPDATE temp.wp_options_stage INDEXED BY wp_options_stage_name SET option_value = ? WHERE option_name = ?'],
    ['name' => 'archive-indexed-reader', 'sql' => 'SELECT option_name FROM archive.wp_options AS ao INDEXED BY wp_archive_option_name WHERE option_name GLOB ?'],
    ['name' => 'site-stable-reader', 'sql' => 'SELECT option_value FROM site.wp_2_options INDEXED BY wp_2_options_name WHERE option_name = ?'],
    ['name' => 'future-main-index-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_future_name WHERE option_name = ?'],
    ['name' => 'active-unqualified-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_options_autoload_name WHERE option_name = ?', 'active' => true],
    ['name' => 'quoted-index-reader', 'sql' => 'SELECT option_value FROM [main].[wp_options] AS o INDEXED BY [wp_options_future_name] WHERE option_name = ?'],
];

$events116 = [
    ['op' => 'drop_index', 'schema' => 'main', 'index' => 'wp_options_autoload_name'],
    ['op' => 'create_index', 'schema' => 'main', 'index' => 'wp_options_future_name'],
    ['op' => 'drop_index', 'schema' => 'temp', 'index' => 'wp_options_stage_name'],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_option_name'],
];

$plan116 = static fn (?array $events = null, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas116,
    $statements ?? $statements116,
    $events ?? $events116,
);

$value116 = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        $cursor = is_numeric($part) ? $cursor[(int) $part] : $cursor[$part];
    }

    return $cursor;
};

$pathCases116 = [
    'status expired' => ['status', 'schema_cache_expired'],
    'operation marker' => ['operation', 'attach-wal-temp-schema-cache-consolidated'],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-temp-wal-schema-cache-consolidated'],
    'index dependency marker' => ['dependencies.1', 'sqlite-indexed-by-schema-cache-expiry'],
    'statement count' => ['statement_count', 7],
    'event count' => ['event_count', 4],
    'current main cookie uses wal source' => ['schema_cookies_current.main', 41],
    'next main cookie after two index ddl events' => ['schema_cookies_next.main', 43],
    'next temp cookie after drop index' => ['schema_cookies_next.temp', 6],
    'next archive cookie after drop index' => ['schema_cookies_next.archive', 10],
    'site cookie stable' => ['schema_cookies_next.site', 2],
    'changed schemas' => ['changed_schemas', ['temp', 'main', 'archive']],
    'expired statements' => ['expired_statements', ['main-indexed-reader', 'temp-indexed-writer', 'archive-indexed-reader', 'future-main-index-reader', 'active-unqualified-reader', 'quoted-index-reader']],
    'stable statement' => ['stable_statements', ['site-stable-reader']],
    'retryable readers' => ['retryable_read_statements', ['main-indexed-reader', 'archive-indexed-reader', 'future-main-index-reader', 'active-unqualified-reader', 'quoted-index-reader']],
    'write blocked' => ['write_statements_blocked_before_retry', ['temp-indexed-writer']],
    'active current snapshot' => ['active_current_snapshot_statements', ['active-unqualified-reader']],
    'main indexed table parsed' => ['statements.main-indexed-reader.tables', ['main.wp_options']],
    'main indexed by parsed' => ['statements.main-indexed-reader.index_transitions.0.index', 'wp_options_autoload_name'],
    'main indexed current found' => ['statements.main-indexed-reader.index_transitions.0.current_found', true],
    'main indexed next missing' => ['statements.main-indexed-reader.index_transitions.0.next_found', false],
    'main indexed transition changes' => ['statements.main-indexed-reader.index_transitions.0.resolution_changed', true],
    'main indexed reprepare' => ['statements.main-indexed-reader.requires_reprepare', true],
    'temp indexed writer read only false' => ['statements.temp-indexed-writer.read_only', false],
    'temp indexed next missing' => ['statements.temp-indexed-writer.index_transitions.0.next_found', false],
    'temp indexed write action' => ['statements.temp-indexed-writer.next_step_action', 'sqlite_schema_before_write_retry'],
    'archive alias index parsed' => ['statements.archive-indexed-reader.index_transitions.0.index', 'wp_archive_option_name'],
    'archive index dropped' => ['statements.archive-indexed-reader.index_transitions.0.next_found', false],
    'site index stable current' => ['statements.site-stable-reader.index_transitions.0.current_found', true],
    'site index stable next' => ['statements.site-stable-reader.index_transitions.0.next_found', true],
    'site statement reused' => ['statements.site-stable-reader.next_step_action', 'reuse_prepared_statement_current_source'],
    'future index current missing' => ['statements.future-main-index-reader.index_transitions.0.current_found', false],
    'future index next found' => ['statements.future-main-index-reader.index_transitions.0.next_found', true],
    'future index reprepare' => ['statements.future-main-index-reader.requires_reprepare', true],
    'active indexed current ok' => ['statements.active-unqualified-reader.sqlite_result_on_current_step', 'SQLITE_OK'],
    'active indexed reset action' => ['statements.active-unqualified-reader.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'quoted index normalized' => ['statements.quoted-index-reader.index_transitions.0.index', 'wp_options_future_name'],
    'quoted index next found' => ['statements.quoted-index-reader.index_transitions.0.next_found', true],
    'drop index event logged' => ['events.0.op', 'drop_index'],
    'create index event logged' => ['events.1.index_name', 'wp_options_future_name'],
];

$tests = [];
foreach ($pathCases116 as $name => [$path, $expected]) {
    $tests['attach temp wal schema cache current source next116 ' . $name] = static function (TestRunner $t) use ($plan116, $value116, $path, $expected): void {
        $t->same($expected, $value116($plan116(), $path));
    };
}

$tests['attach temp wal schema cache current source next116 unrelated table ddl still expires indexed table through cookie'] = static function (TestRunner $t) use ($plan116): void {
    $result = $plan116([
        ['op' => 'schema_write', 'schema' => 'main', 'table' => 'wp_plugin_state'],
    ], [
        ['name' => 'reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_autoload_name'],
    ]);

    $t->same(true, $result['statements']['reader']['schema_transitions'][0]['schema_cookie_changed']);
    $t->same(false, $result['statements']['reader']['index_transitions'][0]['resolution_changed']);
    $t->same(true, $result['statements']['reader']['requires_reprepare']);
};

$tests['attach temp wal schema cache current source next116 stable indexed statement survives unrelated attached index ddl'] = static function (TestRunner $t) use ($plan116): void {
    $result = $plan116([
        ['op' => 'create_index', 'schema' => 'archive', 'index' => 'wp_archive_extra'],
    ], [
        ['name' => 'site-reader', 'sql' => 'SELECT option_value FROM site.wp_2_options INDEXED BY wp_2_options_name'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same(false, $result['statements']['site-reader']['requires_reprepare']);
    $t->same('reuse_prepared_statement_current_source', $result['statements']['site-reader']['next_step_action']);
};

$tests['attach temp wal schema cache current source next116 attach supplies indexed dependency'] = static function (TestRunner $t): void {
    $result = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan([
        'main' => ['schema_cookie' => 1, 'tables' => []],
    ], [
        ['name' => 'future', 'sql' => 'SELECT option_value FROM archive.wp_options INDEXED BY wp_archive_option_name'],
    ], [
        ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 3, 'tables' => ['wp_options'], 'indexes' => ['wp_archive_option_name']],
    ]);

    $t->same('__detached__', $result['statements']['future']['index_transitions'][0]['current_schema']);
    $t->same('archive', $result['statements']['future']['index_transitions'][0]['next_schema']);
    $t->same(true, $result['statements']['future']['index_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next116 rejects empty indexed by name'] = static function (TestRunner $t) use ($plan116): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan116([], [
        ['name' => 'bad', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY []'],
    ]));
};

return $tests;
