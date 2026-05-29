<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCookieSourcePlan;

$tests = [];

$schemas87 = static fn (): array => [
    'main' => [
        'schema_cookie' => 20,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 21, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 22, 'commit' => false],
        ],
        'wal_schema_cookie' => 23,
        'tables' => ['wp_options', 'wp_posts'],
        'next_tables' => ['wp_options', 'wp_posts', 'wp_plugin_state'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 4,
        'temp_schema_cookie' => 5,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 99, 'commit' => true],
        ],
        'tables' => ['wp_options_stage'],
        'next_tables' => ['wp_options_stage', 'wp_options'],
        'file' => '',
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 11,
        'wal_frames' => [
            ['page' => 2, 'schema_cookie' => 40, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 12, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 13, 'commit' => false],
        ],
        'tables' => ['wp_archive_options'],
        'next_tables' => ['wp_archive_options', 'wp_options'],
        'file' => '/srv/wp/archive.sqlite',
    ],
    'network' => [
        'schema_cookie' => 7,
        'wal_schema_cookie' => 8,
        'tables' => ['wp_blogs'],
        'next_tables' => ['wp_blogs'],
        'file' => '/srv/wp/network.sqlite',
    ],
];

$statements87 = static fn (): array => [
    ['name' => 'active-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?', 'active' => true],
    ['name' => 'stage-insert', 'sql' => 'INSERT INTO wp_options_stage(option_name, option_value) VALUES (?, ?)'],
    ['name' => 'archive-reader', 'sql' => 'SELECT option_name FROM archive.wp_archive_options'],
    ['name' => 'network-reader', 'sql' => 'SELECT blog_id FROM network.wp_blogs WHERE domain = ?'],
    ['name' => 'plugin-state-reader', 'sql' => 'SELECT option_name FROM main.wp_plugin_state'],
    ['name' => 'posts-update', 'sql' => 'UPDATE main.wp_posts SET post_title = ? WHERE ID = ?'],
    ['name' => 'archive-shadow-reader', 'sql' => 'SELECT option_name FROM archive.wp_options'],
];

$plan87 = static fn (?array $schemas = null, ?array $statements = null, string $source = 'main'): array => SQLiteAttachWalTempSchemaCookieSourcePlan::plan(
    $schemas ?? $schemas87(),
    $statements ?? $statements87(),
    $source,
);

$value87 = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }

        return null;
    }

    return $cursor;
};

$pathCases87 = [
    'status expired' => ['status', 'schema_cache_expired'],
    'operation marker' => ['operation', 'attach-wal-temp-schema-cookie-source'],
    'source main' => ['source', 'main'],
    'search order' => ['search_order', ['temp', 'main', 'archive', 'network']],
    'current main cookie from last committed wal frame' => ['schema_cookies_current.main', 21],
    'next main cookie from wal commit header' => ['schema_cookies_next.main', 23],
    'main current source' => ['schema_cookie_sources.main.current_source', 'wal_page1_frame'],
    'main current frame index' => ['schema_cookie_sources.main.current_frame_index', 0],
    'main next source' => ['schema_cookie_sources.main.next_source', 'wal_commit_header'],
    'main uncommitted wal tail ignored' => ['schema_cookie_sources.main.wal_tail_ignored', true],
    'temp current cookie ignores wal frame' => ['schema_cookies_current.temp', 4],
    'temp next cookie from rollback journal' => ['schema_cookies_next.temp', 5],
    'temp current source' => ['schema_cookie_sources.temp.current_source', 'temp_schema_cookie'],
    'temp next source' => ['schema_cookie_sources.temp.next_source', 'temp_rollback_journal'],
    'temp frame index remains null' => ['schema_cookie_sources.temp.next_frame_index', null],
    'temp uses rollback journal' => ['schema_cookie_sources.temp.temp_uses_rollback_journal', true],
    'archive current cookie from page one wal frame' => ['schema_cookies_current.archive', 12],
    'archive next cookie remains committed page one' => ['schema_cookies_next.archive', 12],
    'archive ignores non page one frame' => ['schema_cookie_sources.archive.current_frame_index', 1],
    'archive uncommitted tail ignored' => ['schema_cookie_sources.archive.wal_tail_ignored', true],
    'network current cookie from database header' => ['schema_cookies_current.network', 7],
    'network next cookie from wal commit header' => ['schema_cookies_next.network', 8],
    'changed schemas' => ['changed_schemas', ['temp', 'main', 'network']],
    'statement count' => ['statement_count', 7],
    'expired statements' => ['expired_statements', ['active-options-reader', 'stage-insert', 'network-reader', 'plugin-state-reader', 'posts-update', 'archive-shadow-reader']],
    'stable archive reader' => ['stable_statements', ['archive-reader']],
    'active current snapshot' => ['active_current_snapshot_statements', ['active-options-reader']],
    'retryable reads' => ['retryable_read_statements', ['active-options-reader', 'network-reader', 'plugin-state-reader', 'archive-shadow-reader']],
    'write blocks' => ['write_statements_blocked_before_retry', ['stage-insert', 'posts-update']],
    'requires reprepare' => ['requires_reprepare', true],
    'active reader current schema main before temp shadow' => ['statements.0.schema_transitions.0.prepare_schema', 'main'],
    'active reader next schema temp after temp schema cookie' => ['statements.0.schema_transitions.0.next_schema', 'temp'],
    'active reader current source wal' => ['statements.0.schema_transitions.0.prepare_cookie_source', 'wal_page1_frame'],
    'active reader next source temp journal' => ['statements.0.schema_transitions.0.next_cookie_source', 'temp_rollback_journal'],
    'active reader resolution changed' => ['statements.0.schema_transitions.0.resolution_changed', true],
    'active reader step ok' => ['statements.0.sqlite_result_on_current_step', 'SQLITE_OK'],
    'active reader reset action' => ['statements.0.next_step_action', 'finish_current_snapshot_then_sqlite_schema_on_reset'],
    'stage insert prepare schema temp' => ['statements.1.schema_transitions.0.prepare_schema', 'temp'],
    'stage insert next schema temp' => ['statements.1.schema_transitions.0.next_schema', 'temp'],
    'stage insert cookie changed' => ['statements.1.schema_transitions.0.requires_reprepare', true],
    'stage insert write action' => ['statements.1.next_step_action', 'sqlite_schema_before_write_retry'],
    'archive reader remains stable' => ['statements.2.requires_reprepare', false],
    'archive reader cookie source wal page one' => ['statements.2.schema_transitions.0.prepare_cookie_source', 'wal_page1_frame'],
    'archive reader action reuse' => ['statements.2.next_step_action', 'reuse_prepared_statement'],
    'network reader cookie changes' => ['statements.3.schema_transitions.0.requires_reprepare', true],
    'network reader next source wal header' => ['statements.3.schema_transitions.0.next_cookie_source', 'wal_commit_header'],
    'plugin state current missing' => ['statements.4.schema_transitions.0.prepare_found', false],
    'plugin state next found' => ['statements.4.schema_transitions.0.next_found', true],
    'posts update cookie changes only' => ['statements.5.schema_transitions.0.resolution_changed', false],
    'posts update write retry' => ['statements.5.next_step_action', 'sqlite_schema_before_write_retry'],
    'archive shadow current missing' => ['statements.6.schema_transitions.0.prepare_found', false],
    'archive shadow next found' => ['statements.6.schema_transitions.0.next_found', true],
    'database list temp next cookie' => ['database_list.0.next_schema_cookie', 5],
    'database list main file' => ['database_list.1.file', '/srv/wp/current.sqlite'],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-wal-temp-schema-cookie-source'],
];

foreach ($pathCases87 as $name => [$path, $expected]) {
    $tests['attach wal temp schema cookie current source next87 ' . $name] = static function (TestRunner $t) use ($plan87, $value87, $path, $expected): void {
        $t->same($expected, $value87($plan87(), $path));
    };
}

$tests['attach wal temp schema cookie current source next87 archive source accepted'] = static function (TestRunner $t) use ($plan87): void {
    $t->same('archive', $plan87(null, null, 'archive')['source']);
};

$tests['attach wal temp schema cookie current source next87 no pending cookies remains stable'] = static function (TestRunner $t) use ($schemas87, $statements87): void {
    $schemas = $schemas87();
    unset($schemas['main']['wal_schema_cookie'], $schemas['temp']['temp_schema_cookie'], $schemas['network']['wal_schema_cookie']);
    $schemas['main']['next_tables'] = $schemas['main']['tables'];
    $schemas['temp']['next_tables'] = $schemas['temp']['tables'];
    $schemas['archive']['next_tables'] = $schemas['archive']['tables'];
    $result = SQLiteAttachWalTempSchemaCookieSourcePlan::plan($schemas, array_slice($statements87(), 2, 2));
    $t->same('schema_cache_stable', $result['status']);
};

$tests['attach wal temp schema cookie current source next87 rejects empty statements'] = static function (TestRunner $t) use ($schemas87): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachWalTempSchemaCookieSourcePlan::plan($schemas87(), []));
};

$tests['attach wal temp schema cookie current source next87 rejects missing source'] = static function (TestRunner $t) use ($schemas87, $statements87): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachWalTempSchemaCookieSourcePlan::plan($schemas87(), $statements87(), 'missing'));
};

$tests['attach wal temp schema cookie current source next87 rejects non integer cookie'] = static function (TestRunner $t) use ($schemas87, $statements87): void {
    $schemas = $schemas87();
    $schemas['main']['schema_cookie'] = '20';
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachWalTempSchemaCookieSourcePlan::plan($schemas, $statements87()));
};

$tests['attach wal temp schema cookie current source next87 rejects malformed wal frame'] = static function (TestRunner $t) use ($schemas87, $statements87): void {
    $schemas = $schemas87();
    $schemas['archive']['wal_frames'][] = ['schema_cookie' => 14];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachWalTempSchemaCookieSourcePlan::plan($schemas, $statements87()));
};

$tests['attach wal temp schema cookie current source next87 rejects statement without table'] = static function (TestRunner $t) use ($schemas87): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachWalTempSchemaCookieSourcePlan::plan($schemas87(), [
        ['name' => 'constant', 'sql' => 'SELECT 1'],
    ]));
};

return $tests;
