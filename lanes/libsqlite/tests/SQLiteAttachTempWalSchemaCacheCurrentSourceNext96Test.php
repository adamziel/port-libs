<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCookieSourcePlan;

$tests = [];

$schemas96 = static fn (): array => [
    'main' => [
        'schema_cookie' => 30,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 31, 'commit' => true],
        ],
        'wal_schema_cookie' => 32,
        'tables' => ['wp_options', 'wp_posts', 'wp_plugin_state'],
        'next_tables' => ['wp_options', 'wp_posts', 'wp_plugin_state', 'wp_new_main'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 5,
        'temp_schema_cookie' => 6,
        'tables' => ['wp_options_stage'],
        'next_tables' => ['wp_options_stage', 'wp_options'],
        'file' => '',
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 12,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 13, 'commit' => true],
        ],
        'wal_schema_cookie' => 14,
        'tables' => ['wp_options', 'wp_optionmeta', 'wp_archive_state'],
        'next_tables' => ['wp_options', 'wp_optionmeta', 'wp_archive_state', 'wp_archive_new'],
        'file' => '/srv/wp/archive.sqlite',
    ],
    'network' => [
        'schema_cookie' => 8,
        'tables' => ['wp_blogs', 'wp_options'],
        'next_tables' => ['wp_blogs', 'wp_options'],
        'file' => '/srv/wp/network.sqlite',
    ],
];

$statements96 = static fn (): array => [
    ['name' => 'archive-view-options', 'source' => 'archive', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?', 'active' => true],
    ['name' => 'archive-view-meta-join', 'source' => 'archive', 'sql' => 'SELECT m.meta_value FROM wp_options AS o JOIN wp_optionmeta AS m ON m.option_id = o.option_id'],
    ['name' => 'archive-view-main-qualified', 'source' => 'archive', 'sql' => 'SELECT option_value FROM main.wp_options WHERE option_name = ?'],
    ['name' => 'main-trigger-stage-insert', 'source' => 'main', 'sql' => 'INSERT INTO wp_options_stage(option_name, option_value) VALUES (?, ?)'],
    ['name' => 'network-view-options', 'source' => 'network', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'archive-new-object', 'source' => 'archive', 'sql' => 'SELECT option_name FROM wp_archive_new'],
    ['name' => 'main-new-object', 'source' => 'main', 'sql' => 'SELECT option_name FROM wp_new_main'],
    ['name' => 'quoted-source-archive', 'source' => '"archive"', 'sql' => 'SELECT option_name FROM wp_archive_state'],
];

$plan96 = static fn (?array $schemas = null, ?array $statements = null, string $source = 'main'): array => SQLiteAttachWalTempSchemaCookieSourcePlan::plan(
    $schemas ?? $schemas96(),
    $statements ?? $statements96(),
    $source,
);

$value96 = static function (array $data, string $path): mixed {
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

$pathCases96 = [
    'status expired' => ['status', 'schema_cache_expired'],
    'operation remains source cookie plan' => ['operation', 'attach-wal-temp-schema-cookie-current-source-next87'],
    'global source main' => ['source', 'main'],
    'search order unchanged' => ['search_order', ['temp', 'main', 'archive', 'network']],
    'current temp cookie' => ['schema_cookies_current.temp', 5],
    'next temp cookie' => ['schema_cookies_next.temp', 6],
    'current main cookie from wal frame' => ['schema_cookies_current.main', 31],
    'next main cookie from wal header' => ['schema_cookies_next.main', 32],
    'current archive cookie from wal frame' => ['schema_cookies_current.archive', 13],
    'next archive cookie from wal header' => ['schema_cookies_next.archive', 14],
    'network cookie stable' => ['schema_cookies_next.network', 8],
    'changed schemas source order' => ['changed_schemas', ['temp', 'main', 'archive']],
    'statement count' => ['statement_count', 8],
    'expired statements' => ['expired_statements', ['archive-view-options', 'archive-view-meta-join', 'archive-view-main-qualified', 'main-trigger-stage-insert', 'network-view-options', 'archive-new-object', 'main-new-object', 'quoted-source-archive']],
    'no stable statements after temp shadow and wal cookie changes' => ['stable_statements', []],
    'active archive snapshot' => ['active_current_snapshot_statements', ['archive-view-options']],
    'retryable reads' => ['retryable_read_statements', ['archive-view-options', 'archive-view-meta-join', 'archive-view-main-qualified', 'network-view-options', 'archive-new-object', 'main-new-object', 'quoted-source-archive']],
    'write blocked statements' => ['write_statements_blocked_before_retry', ['main-trigger-stage-insert']],
    'archive view source captured' => ['statements.0.prepare_source_schema', 'archive'],
    'archive unqualified current schema archive' => ['statements.0.schema_transitions.0.prepare_schema', 'archive'],
    'archive unqualified next schema temp after temp DDL shadows' => ['statements.0.schema_transitions.0.next_schema', 'temp'],
    'archive unqualified prepare source in transition' => ['statements.0.schema_transitions.0.prepare_source_schema', 'archive'],
    'archive unqualified current found' => ['statements.0.schema_transitions.0.prepare_found', true],
    'archive unqualified next found' => ['statements.0.schema_transitions.0.next_found', true],
    'archive unqualified resolution changed' => ['statements.0.schema_transitions.0.resolution_changed', true],
    'archive active current step ok' => ['statements.0.sqlite_result_on_current_step', 'SQLITE_OK'],
    'archive active reset action' => ['statements.0.next_step_action', 'finish_current_snapshot_then_sqlite_schema_on_reset'],
    'archive join first table source archive' => ['statements.1.schema_transitions.0.prepare_schema', 'archive'],
    'archive join second table source archive' => ['statements.1.schema_transitions.1.prepare_schema', 'archive'],
    'archive join schemas deduped' => ['statements.1.prepare_schemas', ['archive']],
    'archive join next schemas include temp archive' => ['statements.1.next_schemas', ['temp', 'archive']],
    'archive join second table stable resolution' => ['statements.1.schema_transitions.1.resolution_changed', false],
    'main qualified remains main' => ['statements.2.schema_transitions.0.prepare_schema', 'main'],
    'main qualified cookie source wal frame' => ['statements.2.schema_transitions.0.prepare_cookie_source', 'wal_page1_frame'],
    'main qualified next source wal header' => ['statements.2.schema_transitions.0.next_cookie_source', 'wal_commit_header'],
    'main trigger source captured' => ['statements.3.prepare_source_schema', 'main'],
    'main trigger temp stage schema' => ['statements.3.schema_transitions.0.prepare_schema', 'temp'],
    'main trigger write retry' => ['statements.3.next_step_action', 'sqlite_schema_before_write_retry'],
    'network view source captured' => ['statements.4.prepare_source_schema', 'network'],
    'network unqualified current schema network' => ['statements.4.schema_transitions.0.prepare_schema', 'network'],
    'network unqualified next schema temp after temp DDL shadows' => ['statements.4.schema_transitions.0.next_schema', 'temp'],
    'network view expires on temp shadow' => ['statements.4.requires_reprepare', true],
    'archive new object current missing archive' => ['statements.5.schema_transitions.0.prepare_schema', 'main'],
    'archive new object next found archive' => ['statements.5.schema_transitions.0.next_schema', 'archive'],
    'main new object next found main' => ['statements.6.schema_transitions.0.next_schema', 'main'],
    'quoted source normalized' => ['statements.7.prepare_source_schema', 'archive'],
    'quoted source archive stable object schema' => ['statements.7.schema_transitions.0.prepare_schema', 'archive'],
    'database list archive next cookie' => ['database_list.2.next_schema_cookie', 14],
    'dependency source marker' => ['dependencies.0', 'sqlite-attach-wal-temp-schema-cookie-current-source-next87'],
];

foreach ($pathCases96 as $name => [$path, $expected]) {
    $tests['attach temp wal schema cache current source next96 ' . $name] = static function (TestRunner $t) use ($plan96, $value96, $path, $expected): void {
        $t->same($expected, $value96($plan96(), $path));
    };
}

$tests['attach temp wal schema cache current source next96 temp still wins over attached source'] = static function (TestRunner $t) use ($schemas96): void {
    $schemas = $schemas96();
    $schemas['temp']['tables'][] = 'wp_options';
    $schemas['temp']['next_tables'][] = 'wp_options';
    $result = SQLiteAttachWalTempSchemaCookieSourcePlan::plan($schemas, [
        ['name' => 'archive-options', 'source' => 'archive', 'sql' => 'SELECT option_value FROM wp_options'],
    ]);
    $t->same('temp', $result['statements'][0]['schema_transitions'][0]['prepare_schema']);
};

$tests['attach temp wal schema cache current source next96 missing attached source is rejected'] = static function (TestRunner $t) use ($schemas96): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachWalTempSchemaCookieSourcePlan::plan($schemas96(), [
        ['name' => 'bad-source', 'source' => 'missing', 'sql' => 'SELECT option_value FROM wp_options'],
    ]));
};

$tests['attach temp wal schema cache current source next96 global archive source remains default'] = static function (TestRunner $t) use ($schemas96): void {
    $result = SQLiteAttachWalTempSchemaCookieSourcePlan::plan($schemas96(), [
        ['name' => 'archive-default', 'sql' => 'SELECT option_value FROM wp_options'],
    ], 'archive');
    $t->same('archive', $result['statements'][0]['prepare_source_schema']);
    $t->same('archive', $result['statements'][0]['schema_transitions'][0]['prepare_schema']);
};

$tests['attach temp wal schema cache current source next96 explicit main source overrides global archive'] = static function (TestRunner $t) use ($schemas96): void {
    $result = SQLiteAttachWalTempSchemaCookieSourcePlan::plan($schemas96(), [
        ['name' => 'main-default', 'source' => 'main', 'sql' => 'SELECT option_value FROM wp_options'],
    ], 'archive');
    $t->same('main', $result['statements'][0]['prepare_source_schema']);
    $t->same('main', $result['statements'][0]['schema_transitions'][0]['prepare_schema']);
};

return $tests;
