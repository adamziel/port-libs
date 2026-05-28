<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas92 = [
    'main' => [
        'schema_cookie' => 11,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 12, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_posts', 'wp_optionmeta'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 3,
        'tables' => ['wp_options_stage'],
        'temp' => true,
        'file' => '',
    ],
    'site' => [
        'schema_cookie' => 6,
        'tables' => ['wp_options', 'wp_site_meta'],
        'file' => '/srv/wp/site.sqlite',
    ],
    'archive' => [
        'schema_cookie' => 4,
        'tables' => ['wp_options_archive', 'wp_legacy_meta'],
        'file' => '/srv/wp/archive.sqlite',
    ],
];

$statements92 = [
    ['name' => 'active-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?', 'active' => true],
    ['name' => 'stage-writer', 'sql' => 'INSERT INTO temp.wp_options_stage(option_name, option_value) VALUES (?, ?)'],
    ['name' => 'main-post-reader', 'sql' => 'SELECT post_title FROM main.wp_posts WHERE ID = ?'],
    ['name' => 'site-meta-reader', 'sql' => 'SELECT meta_value FROM site.wp_site_meta WHERE meta_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT option_value FROM archive.wp_options_archive WHERE option_name = ?'],
    ['name' => 'future-unqualified-reader', 'sql' => 'SELECT option_value FROM wp_plugin_state WHERE option_name = ?'],
    ['name' => 'delete-main-option', 'sql' => 'DELETE FROM main.wp_options WHERE option_name = ?'],
    ['name' => 'quoted-temp-reader', 'sql' => 'SELECT option_name FROM "temp"."wp_options_stage"'],
];

$events92 = [
    ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 13, 'table' => 'wp_plugin_state'],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'attach', 'schema' => 'analytics', 'schema_cookie' => 1, 'tables' => ['wp_events'], 'file' => '/srv/wp/analytics.sqlite'],
];

$plan92 = static fn (?array $events = null, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::plan(
    $schemas ?? $schemas92,
    $statements ?? $statements92,
    $events ?? $events92,
);

$value92 = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        $cursor = is_numeric($part) ? $cursor[(int) $part] : $cursor[$part];
    }

    return $cursor;
};

$pathCases92 = [
    'status expired' => ['status', 'schema_cache_expired'],
    'operation marker' => ['operation', 'attach-wal-temp-schema-cache-current-source-next92'],
    'source main' => ['source', 'main'],
    'event count' => ['event_count', 4],
    'statement count' => ['statement_count', 8],
    'search order current' => ['search_order_current', ['temp', 'main', 'archive', 'site']],
    'search order next includes attached analytics' => ['search_order_next', ['temp', 'main', 'analytics', 'site']],
    'current main cookie uses WAL page one' => ['schema_cookies_current.main', 12],
    'next main cookie explicit WAL commit' => ['schema_cookies_next.main', 13],
    'temp cookie advanced' => ['schema_cookies_next.temp', 4],
    'site cookie stable' => ['schema_cookies_next.site', 6],
    'archive detached changed' => ['changed_schemas', ['temp', 'main', 'analytics', 'archive']],
    'expired statements list' => ['expired_statements', ['active-options-reader', 'stage-writer', 'main-post-reader', 'archive-reader', 'future-unqualified-reader', 'delete-main-option', 'quoted-temp-reader']],
    'stable statements list' => ['stable_statements', ['site-meta-reader']],
    'active current snapshot list' => ['active_current_snapshot_statements', ['active-options-reader']],
    'retryable read list' => ['retryable_read_statements', ['active-options-reader', 'main-post-reader', 'archive-reader', 'future-unqualified-reader', 'quoted-temp-reader']],
    'write blocked list' => ['write_statements_blocked_before_retry', ['stage-writer', 'delete-main-option']],
    'requires reprepare' => ['requires_reprepare', true],
    'active reader current schema main' => ['statements.active-options-reader.schema_transitions.0.current_schema', 'main'],
    'active reader next schema temp' => ['statements.active-options-reader.schema_transitions.0.next_schema', 'temp'],
    'active reader current found' => ['statements.active-options-reader.schema_transitions.0.current_found', true],
    'active reader next found' => ['statements.active-options-reader.schema_transitions.0.next_found', true],
    'active reader resolution changed' => ['statements.active-options-reader.schema_transitions.0.resolution_changed', true],
    'active reader current step ok' => ['statements.active-options-reader.sqlite_result_on_current_step', 'SQLITE_OK'],
    'active reader reset action' => ['statements.active-options-reader.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'stage writer current temp' => ['statements.stage-writer.current_schemas', ['temp']],
    'stage writer next temp' => ['statements.stage-writer.next_schemas', ['temp']],
    'stage writer cookie changed' => ['statements.stage-writer.schema_transitions.0.schema_cookie_changed', true],
    'stage writer write action' => ['statements.stage-writer.next_step_action', 'sqlite_schema_before_write_retry'],
    'main post reader cookie changed' => ['statements.main-post-reader.schema_transitions.0.schema_cookie_changed', true],
    'main post reader read action' => ['statements.main-post-reader.next_step_action', 'sqlite_schema_then_reprepare_read_statement'],
    'site reader stable action' => ['statements.site-meta-reader.next_step_action', 'reuse_prepared_statement_current_source'],
    'site reader not expired' => ['statements.site-meta-reader.requires_reprepare', false],
    'archive reader detached next schema' => ['statements.archive-reader.schema_transitions.0.next_schema', '__detached__'],
    'archive reader next missing' => ['statements.archive-reader.schema_transitions.0.next_found', false],
    'archive reader expires on detach' => ['statements.archive-reader.requires_reprepare', true],
    'future reader current missing' => ['statements.future-unqualified-reader.schema_transitions.0.current_found', false],
    'future reader next main found' => ['statements.future-unqualified-reader.schema_transitions.0.next_found', true],
    'future reader next schema main' => ['statements.future-unqualified-reader.schema_transitions.0.next_schema', 'main'],
    'delete main read only false' => ['statements.delete-main-option.read_only', false],
    'delete main write action' => ['statements.delete-main-option.next_step_action', 'sqlite_schema_before_write_retry'],
    'quoted temp table parsed' => ['statements.quoted-temp-reader.tables', ['temp.wp_options_stage']],
    'quoted temp current schema' => ['statements.quoted-temp-reader.schema_transitions.0.current_schema', 'temp'],
    'quoted temp expires on temp schema write' => ['statements.quoted-temp-reader.requires_reprepare', true],
    'attach event logged' => ['events.3.op', 'attach'],
    'attach event schema' => ['events.3.schema', 'analytics'],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-wal-temp-schema-cache-current-source-next92'],
];

foreach ($pathCases92 as $name => [$path, $expected]) {
    $tests['attach wal temp schema cache current source next92 ' . $name] = static function (TestRunner $t) use ($plan92, $value92, $path, $expected): void {
        $t->same($expected, $value92($plan92(), $path));
    };
}

$tests['attach wal temp schema cache current source next92 temp drop reveals main source'] = static function (TestRunner $t) use ($plan92): void {
    $schemas = [
        'main' => ['schema_cookie' => 5, 'tables' => ['wp_options']],
        'temp' => ['schema_cookie' => 2, 'tables' => ['wp_options'], 'temp' => true],
    ];
    $result = $plan92([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ], [
        ['name' => 'reader', 'sql' => 'SELECT option_value FROM wp_options', 'active' => true],
    ], $schemas);
    $t->same('temp', $result['statements']['reader']['schema_transitions'][0]['current_schema']);
    $t->same('main', $result['statements']['reader']['schema_transitions'][0]['next_schema']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['reader']['next_step_action']);
};

$tests['attach wal temp schema cache current source next92 rollback-like uncommitted wal frame ignored'] = static function (TestRunner $t) use ($plan92): void {
    $result = $plan92([], [
        ['name' => 'reader', 'sql' => 'SELECT post_title FROM main.wp_posts'],
    ], [
        'main' => [
            'schema_cookie' => 7,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 99, 'commit' => false],
            ],
            'tables' => ['wp_posts'],
        ],
    ]);
    $t->same('schema_cache_stable', $result['status']);
    $t->same(7, $result['schema_cookies_current']['main']);
};

$tests['attach wal temp schema cache current source next92 committed later wal frame wins current source'] = static function (TestRunner $t) use ($plan92): void {
    $result = $plan92([], [
        ['name' => 'reader', 'sql' => 'SELECT post_title FROM main.wp_posts'],
    ], [
        'main' => [
            'schema_cookie' => 7,
            'wal_frames' => [
                ['page' => 2, 'schema_cookie' => 88, 'commit' => true],
                ['page' => 1, 'schema_cookie' => 8, 'commit' => true],
                ['page' => 1, 'schema_cookie' => 9, 'commit' => true],
            ],
            'tables' => ['wp_posts'],
        ],
    ]);
    $t->same('schema_cache_stable', $result['status']);
    $t->same(9, $result['schema_cookies_current']['main']);
};

$tests['attach wal temp schema cache current source next92 quoted attach schema normalizes'] = static function (TestRunner $t) use ($plan92): void {
    $result = $plan92([
        ['op' => 'attach', 'schema' => '"ArchiveTwo"', 'schema_cookie' => 1, 'tables' => ['Wp_Options']],
    ], [
        ['name' => 'future', 'sql' => 'SELECT option_value FROM archivetwo.wp_options'],
    ], [
        'main' => ['schema_cookie' => 1, 'tables' => []],
    ]);
    $t->same('__detached__', $result['statements']['future']['schema_transitions'][0]['current_schema']);
    $t->same('archivetwo', $result['statements']['future']['schema_transitions'][0]['next_schema']);
    $t->same(true, $result['statements']['future']['requires_reprepare']);
};

$tests['attach wal temp schema cache current source next92 rejects bad detach'] = static function (TestRunner $t) use ($plan92): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan92([
        ['op' => 'detach', 'schema' => 'main'],
    ]));
};

$tests['attach wal temp schema cache current source next92 rejects noninteger wal cookie'] = static function (TestRunner $t) use ($plan92): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan92([], null, [
        'main' => ['schema_cookie' => 1, 'wal_schema_cookie' => 'bad', 'tables' => ['wp_options']],
    ]));
};

return $tests;
