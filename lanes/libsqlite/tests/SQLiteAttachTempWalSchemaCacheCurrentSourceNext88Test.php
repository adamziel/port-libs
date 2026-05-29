<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentNextPlan;

$schemas = [
    'main' => [
        'schema_cookie' => 20,
        'wal_schema_cookie' => 21,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 21, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_posts'],
        'next_tables' => ['wp_options', 'wp_posts', 'wp_plugin_state'],
        'indexes' => ['wp_options_name'],
        'next_indexes' => ['wp_options_name', 'wp_plugin_state_name'],
        'file' => '/srv/wp/current.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 4,
        'tables' => ['wp_options_stage'],
        'next_tables' => ['wp_options_stage', 'wp_options'],
        'indexes' => ['wp_options_stage_name'],
        'next_indexes' => ['wp_options_stage_name', 'wp_options_temp_name'],
        'temp' => true,
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 7,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 8, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_optionmeta'],
        'next_tables' => ['wp_options', 'wp_optionmeta', 'wp_archive_state'],
        'indexes' => ['wp_options_name'],
        'next_indexes' => ['wp_options_name', 'wp_archive_state_name'],
        'file' => '/srv/wp/archive.sqlite',
        'cache' => 'shared',
    ],
    'analytics' => [
        'schema_cookie' => 2,
        'tables' => ['wp_events'],
        'next_tables' => ['wp_events'],
        'indexes' => ['wp_events_name'],
        'next_indexes' => ['wp_events_name'],
        'file' => '/srv/wp/analytics.sqlite',
        'cache' => 'private',
    ],
];

$operations = [
    ['op' => 'schema_write', 'schema' => 'main', 'object' => 'wp_plugin_state'],
    ['op' => 'schema_write', 'schema' => 'temp', 'object' => 'wp_options'],
    ['op' => 'schema_write', 'schema' => 'archive', 'object' => 'wp_archive_state'],
];

$statements = [
    ['name' => 'bracket-main-reader', 'sql' => 'SELECT option_value FROM [main].[wp_options] WHERE option_name = ?'],
    ['name' => 'bracket-temp-insert', 'sql' => 'INSERT INTO [temp].[wp_options_stage](option_name, option_value) VALUES (?, ?)'],
    ['name' => 'bracket-archive-delete', 'sql' => 'DELETE FROM [archive].[wp_options] WHERE option_name = ?'],
    ['name' => 'bracket-archive-join', 'sql' => 'SELECT a.option_name FROM [archive].[wp_options] AS a JOIN [archive].[wp_optionmeta] AS m ON m.option_id = a.option_id'],
    ['name' => 'bracket-main-update', 'sql' => 'UPDATE [main].[wp_posts] SET post_title = ? WHERE ID = ?'],
    ['name' => 'bracket-main-new-reader', 'sql' => 'SELECT option_name FROM [main].[wp_plugin_state]'],
    ['name' => 'bracket-unqualified-reader', 'sql' => 'SELECT option_value FROM [wp_options] WHERE option_name = ?', 'active' => true],
    ['name' => 'bracket-analytics-reader', 'sql' => 'SELECT event_name FROM [analytics].[wp_events] WHERE event_name GLOB ?'],
];

$plan = static fn (?array $ops = null, ?array $stmts = null, string $outcome = 'commit'): array => SQLiteAttachWalTempSchemaCacheCurrentNextPlan::plan(
    $schemas,
    $ops ?? $operations,
    $stmts ?? $statements,
    $outcome,
);

$value = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        $cursor = is_numeric($part) ? $cursor[(int) $part] : $cursor[$part];
    }

    return $cursor;
};

$cases = [
    'status expired' => ['status', 'schema_cache_expired'],
    'operation remains current engine' => ['operation', 'attach-wal-temp-schema-cache-current'],
    'source remains main' => ['source', 'main'],
    'statement count' => ['statement_count', 8],
    'changed schemas follow sqlite search order' => ['changed_schemas', ['temp', 'main', 'archive']],
    'object changed schemas follow sqlite search order' => ['object_changed_schemas', ['temp', 'main', 'archive']],
    'current main cookie reads committed wal' => ['schema_cookies_current.main', 21],
    'next main cookie advances after schema write' => ['schema_cookies_next.main', 22],
    'current temp cookie' => ['schema_cookies_current.temp', 4],
    'next temp cookie advances' => ['schema_cookies_next.temp', 5],
    'current archive cookie reads committed wal frame' => ['schema_cookies_current.archive', 8],
    'next archive cookie advances' => ['schema_cookies_next.archive', 9],
    'analytics cookie stable' => ['schema_cookies_next.analytics', 2],
    'expired statement list' => ['expired_statements', ['bracket-main-reader', 'bracket-temp-insert', 'bracket-archive-delete', 'bracket-archive-join', 'bracket-main-update', 'bracket-main-new-reader', 'bracket-unqualified-reader']],
    'stable analytics statement' => ['stable_statements', ['bracket-analytics-reader']],
    'active current snapshot statement' => ['active_current_snapshot_statements', ['bracket-unqualified-reader']],
    'retryable read statements' => ['retryable_read_statements', ['bracket-main-reader', 'bracket-archive-join', 'bracket-main-new-reader', 'bracket-unqualified-reader']],
    'write blocked statements' => ['write_statements_blocked_before_retry', ['bracket-temp-insert', 'bracket-archive-delete', 'bracket-main-update']],
    'requires reprepare true' => ['requires_reprepare', true],
    'main reader table parsed with bracket schema' => ['statements.0.tables', ['main.wp_options']],
    'main reader current schema' => ['statements.0.current_schemas', ['main']],
    'main reader next schema' => ['statements.0.next_schemas', ['main']],
    'main reader transition table normalized' => ['statements.0.schema_transitions.0.table', 'main.wp_options'],
    'main reader current found' => ['statements.0.schema_transitions.0.current_found', true],
    'main reader next found' => ['statements.0.schema_transitions.0.next_found', true],
    'main reader sqlite schema action' => ['statements.0.next_step_action', 'sqlite_schema_then_reprepare_read_statement'],
    'temp insert table parsed with bracket schema' => ['statements.1.tables', ['temp.wp_options_stage']],
    'temp insert read only false' => ['statements.1.read_only', false],
    'temp insert current schema' => ['statements.1.current_schemas', ['temp']],
    'temp insert write retry action' => ['statements.1.next_step_action', 'sqlite_schema_before_write_retry'],
    'archive delete table parsed with bracket schema' => ['statements.2.tables', ['archive.wp_options']],
    'archive delete current schema' => ['statements.2.current_schemas', ['archive']],
    'archive delete next schema' => ['statements.2.next_schemas', ['archive']],
    'archive delete read only false' => ['statements.2.read_only', false],
    'archive join parses two bracket tables' => ['statements.3.tables', ['archive.wp_options', 'archive.wp_optionmeta']],
    'archive join current schema deduped' => ['statements.3.current_schemas', ['archive']],
    'archive join next schema deduped' => ['statements.3.next_schemas', ['archive']],
    'archive join second table normalized' => ['statements.3.schema_transitions.1.table', 'archive.wp_optionmeta'],
    'main update table parsed' => ['statements.4.tables', ['main.wp_posts']],
    'main update blocked before retry' => ['statements.4.next_step_action', 'sqlite_schema_before_write_retry'],
    'main new reader current missing' => ['statements.5.schema_transitions.0.current_found', false],
    'main new reader next found' => ['statements.5.schema_transitions.0.next_found', true],
    'main new reader resolution unchanged qualified' => ['statements.5.schema_transitions.0.resolution_changed', true],
    'unqualified reader table parsed' => ['statements.6.tables', ['wp_options']],
    'unqualified reader current schema main before temp DDL' => ['statements.6.schema_transitions.0.current_schema', 'main'],
    'unqualified reader next schema temp after temp DDL' => ['statements.6.schema_transitions.0.next_schema', 'temp'],
    'unqualified reader active ok while running' => ['statements.6.sqlite_result', 'SQLITE_OK'],
    'unqualified reader reset action' => ['statements.6.next_step_action', 'finish_current_snapshot_then_sqlite_schema_on_reset'],
    'analytics table parsed with bracket schema' => ['statements.7.tables', ['analytics.wp_events']],
    'analytics stable action' => ['statements.7.next_step_action', 'reuse_prepared_statement'],
    'analytics sqlite ok' => ['statements.7.sqlite_result', 'SQLITE_OK'],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-wal-temp-schema-cache-current'],
];

$tests = [];
foreach ($cases as $name => [$path, $expected]) {
    $tests['attach temp wal schema cache current source next88 ' . $name] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
        $t->same($expected, $value($plan(), $path));
    };
}

$tests['attach temp wal schema cache current source next88 rollback keeps bracket statements reusable'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(null, null, 'rollback');
    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['expired_statements']);
    $t->same(['bracket-main-reader', 'bracket-temp-insert', 'bracket-archive-delete', 'bracket-archive-join', 'bracket-main-update', 'bracket-main-new-reader', 'bracket-unqualified-reader', 'bracket-analytics-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next88 bracket temp commit shadows unqualified reader'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan([
        ['op' => 'schema_write', 'schema' => 'temp', 'object' => 'wp_options'],
    ]);
    $t->same('main', $result['statements']['6']['schema_transitions']['0']['current_schema']);
    $t->same('temp', $result['statements']['6']['schema_transitions']['0']['next_schema']);
};

$tests['attach temp wal schema cache current source next88 bracket main only commit keeps temp insert reusable'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan([
        ['op' => 'schema_write', 'schema' => 'main', 'object' => 'wp_plugin_state'],
    ]);
    $t->same(false, $result['statements']['1']['requires_reprepare']);
    $t->same('reuse_prepared_statement', $result['statements']['1']['next_step_action']);
};

$tests['attach temp wal schema cache current source next88 bracket archive only commit expires archive join'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan([
        ['op' => 'schema_write', 'schema' => 'archive', 'object' => 'wp_archive_state'],
    ]);
    $t->same(true, $result['statements']['3']['requires_reprepare']);
    $t->same(['archive'], $result['statements']['3']['current_schemas']);
};

$tests['attach temp wal schema cache current source next88 rejects empty bracket identifier'] = static function (TestRunner $t) use ($plan, $statements): void {
    $bad = $statements;
    $bad[] = ['name' => 'bad', 'sql' => 'SELECT * FROM []'];
    $t->throws(InvalidArgumentException::class, static fn () => $plan(null, $bad));
};

return $tests;
