<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheTransactionPlan;

$tests = [];

$schemas = [
    'main' => [
        'schema_cookie' => 41,
        'wal_schema_cookie' => 42,
        'tables' => ['wp_options', 'wp_posts'],
        'next_tables' => ['wp_options', 'wp_posts', 'wp_plugin_state'],
        'indexes' => ['wp_options_name'],
        'next_indexes' => ['wp_options_name', 'wp_plugin_state_name'],
        'file' => '/srv/wp/current.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 7,
        'tables' => ['wp_options_stage'],
        'next_tables' => ['wp_options'],
        'indexes' => ['wp_options_stage_name'],
        'next_indexes' => ['wp_options_name_temp'],
        'temp' => true,
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 11,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 12, 'commit' => true],
        ],
        'tables' => ['wp_archive_options'],
        'next_tables' => ['wp_archive_options', 'wp_options'],
        'indexes' => [],
        'next_indexes' => ['wp_options_name'],
        'file' => '/srv/wp/archive.sqlite',
        'cache' => 'shared',
    ],
    'network' => [
        'schema_cookie' => 3,
        'tables' => ['wp_blogs'],
        'next_tables' => ['wp_blogs'],
        'indexes' => [],
        'next_indexes' => [],
        'file' => '/srv/wp/network.sqlite',
    ],
];

$operations = [
    ['op' => 'schema_write', 'schema' => 'main', 'object' => 'wp_plugin_state'],
    ['op' => 'savepoint', 'savepoint' => 'plugin_import'],
    ['op' => 'schema_write', 'schema' => 'temp', 'object' => 'wp_options_stage'],
    ['op' => 'schema_write', 'schema' => 'archive', 'object' => 'wp_options_name'],
    ['op' => 'rollback_to', 'savepoint' => 'plugin_import'],
    ['op' => 'schema_write', 'schema' => 'network', 'object' => 'wp_blogs_domain'],
    ['op' => 'release', 'savepoint' => 'plugin_import'],
];

$statements = [
    ['name' => 'active-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?', 'active' => true],
    ['name' => 'stage-insert', 'sql' => 'INSERT INTO wp_options_stage(option_name, option_value) VALUES (?, ?)'],
    ['name' => 'archive-options-reader', 'sql' => 'SELECT option_name FROM archive.wp_options'],
    ['name' => 'network-reader', 'sql' => 'SELECT blog_id FROM network.wp_blogs WHERE domain = ?'],
    ['name' => 'main-post-update', 'sql' => 'UPDATE main.wp_posts SET post_title = ? WHERE ID = ?'],
    ['name' => 'plugin-state-reader', 'sql' => 'SELECT option_name FROM main.wp_plugin_state'],
];

$plan = static fn (?string $outcome = null, ?array $overrideSchemas = null, ?array $overrideOperations = null, ?array $overrideStatements = null): array => SQLiteAttachWalTempSchemaCacheTransactionPlan::plan(
    $overrideSchemas ?? $schemas,
    $overrideOperations ?? $operations,
    $overrideStatements ?? $statements,
    $outcome ?? 'commit',
);

$value = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        $cursor = is_numeric($part) ? $cursor[(int) $part] : $cursor[$part];
    }

    return $cursor;
};

$cases = [
    'status expired after commit' => ['status', 'schema_cache_expired'],
    'operation marker' => ['operation', 'attach-wal-temp-schema-cache-transaction'],
    'outcome commit' => ['outcome', 'commit'],
    'source main' => ['source', 'main'],
    'transaction committed' => ['transaction_status', 'committed'],
    'transaction attempted reprepare schemas include rolled back writes' => ['transaction_reprepare_schemas', ['archive', 'main', 'network', 'temp']],
    'current main cookie from transaction baseline' => ['schema_cookies_current.main', 42],
    'next main cookie after committed schema write' => ['schema_cookies_next.main', 43],
    'current temp cookie from rollback baseline' => ['schema_cookies_current.temp', 7],
    'next temp cookie unchanged by rolled back savepoint' => ['schema_cookies_next.temp', 7],
    'current archive cookie from committed wal frame' => ['schema_cookies_current.archive', 12],
    'next archive cookie unchanged by savepoint rollback' => ['schema_cookies_next.archive', 12],
    'network next cookie committed' => ['schema_cookies_next.network', 4],
    'changed schemas only committed writes' => ['changed_schemas', ['main', 'network']],
    'object changed schemas preserve committed schema ddl' => ['object_changed_schemas', ['main']],
    'expired statements' => ['expired_statements', ['active-options-reader', 'network-reader', 'main-post-update', 'plugin-state-reader']],
    'stable statements from rolled back temp archive writes' => ['stable_statements', ['stage-insert', 'archive-options-reader']],
    'statement count' => ['statement_count', 6],
    'active current snapshot list' => ['active_current_snapshot_statements', ['active-options-reader']],
    'retryable read statements' => ['retryable_read_statements', ['active-options-reader', 'network-reader', 'plugin-state-reader']],
    'write blocked statements' => ['write_statements_blocked_before_retry', ['main-post-update']],
    'requires reprepare' => ['requires_reprepare', true],
    'active reader action' => ['statements.0.next_step_action', 'finish_current_snapshot_then_sqlite_schema_on_reset'],
    'active reader sqlite ok while running' => ['statements.0.sqlite_result', 'SQLITE_OK'],
    'active reader current schema temp shadow' => ['statements.0.current_schemas', ['main']],
    'active reader next schema remains main after temp rollback' => ['statements.0.next_schemas', ['main']],
    'stage insert reuse action after temp rollback' => ['statements.1.next_step_action', 'reuse_prepared_statement'],
    'stage insert not read retryable' => ['statements.1.retryable_after_reprepare', false],
    'archive reader reuse action after archive rollback' => ['statements.2.next_step_action', 'reuse_prepared_statement'],
    'archive reader current missing qualified table' => ['statements.2.schema_transitions.0.current_found', false],
    'archive reader next qualified table still missing after rollback' => ['statements.2.schema_transitions.0.next_found', false],
    'network reader current schema' => ['statements.3.current_schemas', ['network']],
    'network reader next schema' => ['statements.3.next_schemas', ['network']],
    'main update write action' => ['statements.4.next_step_action', 'sqlite_schema_before_write_retry'],
    'plugin state current missing' => ['statements.5.schema_transitions.0.current_found', false],
    'plugin state next found' => ['statements.5.schema_transitions.0.next_found', true],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-wal-temp-schema-cache-transaction'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['attach wal temp schema cache current next77 ' . $name] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
        $t->same($expected, $value($plan(), $path));
    };
}

$rollback = static fn (): array => $plan('rollback');
$rollbackCases = [
    'rollback status stable' => ['status', 'schema_cache_stable'],
    'rollback outcome' => ['outcome', 'rollback'],
    'rollback transaction status' => ['transaction_status', 'rolled_back'],
    'rollback changed schemas empty' => ['changed_schemas', []],
    'rollback expired empty' => ['expired_statements', []],
    'rollback stable statements' => ['stable_statements', ['active-options-reader', 'stage-insert', 'archive-options-reader', 'network-reader', 'main-post-update', 'plugin-state-reader']],
    'rollback no active expiration' => ['active_current_snapshot_statements', ['active-options-reader']],
    'rollback no retry reads' => ['retryable_read_statements', []],
    'rollback no write blocks' => ['write_statements_blocked_before_retry', []],
    'rollback reprepare false' => ['requires_reprepare', false],
    'rollback main current cookie' => ['schema_cookies_current.main', 42],
    'rollback main next cookie' => ['schema_cookies_next.main', 42],
    'rollback network current cookie' => ['schema_cookies_current.network', 3],
    'rollback network next cookie' => ['schema_cookies_next.network', 3],
    'rollback statement action reuse' => ['statements.0.next_step_action', 'reuse_prepared_statement'],
];

foreach ($rollbackCases as $name => [$path, $expected]) {
    $tests['attach wal temp schema cache current next77 ' . $name] = static function (TestRunner $t) use ($rollback, $value, $path, $expected): void {
        $t->same($expected, $value($rollback(), $path));
    };
}

$tests['attach wal temp schema cache current next77 source can be attached schema'] = static function (TestRunner $t) use ($plan): void {
    $result = SQLiteAttachWalTempSchemaCacheTransactionPlan::plan($GLOBALS['schemas'] ?? [], [], []);
    $t->same('unused', $result['source']);
};

unset($tests['attach wal temp schema cache current next77 source can be attached schema']);

$tests['attach wal temp schema cache current next77 archive source accepted'] = static function (TestRunner $t) use ($schemas, $operations, $statements): void {
    $result = SQLiteAttachWalTempSchemaCacheTransactionPlan::plan($schemas, $operations, $statements, 'commit', 'archive');
    $t->same('archive', $result['source']);
};

$tests['attach wal temp schema cache current next77 rolled back temp write does not expire stage insert'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan();
    $t->same(false, $result['statements']['1']['schema_transitions']['0']['requires_reprepare']);
};

$tests['attach wal temp schema cache current next77 committed temp write expires unqualified reader'] = static function (TestRunner $t) use ($schemas, $operations, $statements): void {
    $ops = $operations;
    unset($ops[4]);
    $ops = array_values($ops);
    $result = SQLiteAttachWalTempSchemaCacheTransactionPlan::plan($schemas, $ops, $statements);
    $t->same(['temp', 'main', 'archive', 'network'], $result['changed_schemas']);
};

$tests['attach wal temp schema cache current next77 committed temp write makes wp_options next schema temp'] = static function (TestRunner $t) use ($schemas, $operations, $statements): void {
    $ops = $operations;
    unset($ops[4]);
    $ops = array_values($ops);
    $result = SQLiteAttachWalTempSchemaCacheTransactionPlan::plan($schemas, $ops, $statements);
    $t->same('temp', $result['statements']['0']['schema_transitions']['0']['next_schema']);
};

$tests['attach wal temp schema cache current next77 committed archive write expires qualified reader'] = static function (TestRunner $t) use ($schemas, $operations, $statements): void {
    $ops = $operations;
    unset($ops[4]);
    $ops = array_values($ops);
    $result = SQLiteAttachWalTempSchemaCacheTransactionPlan::plan($schemas, $ops, $statements);
    $t->same(true, $result['statements']['2']['requires_reprepare']);
};

$tests['attach wal temp schema cache current next77 rejects empty statements'] = static function (TestRunner $t) use ($schemas, $operations): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachWalTempSchemaCacheTransactionPlan::plan($schemas, $operations, []));
};

$tests['attach wal temp schema cache current next77 rejects missing transaction schema'] = static function (TestRunner $t) use ($schemas, $operations, $statements): void {
    $bad = $schemas;
    $bad['ghost'] = ['schema_cookie' => 1, 'tables' => ['wp_ghost']];
    $ops = $operations;
    $ops[] = ['op' => 'schema_write', 'schema' => 'ghost', 'object' => 'wp_ghost'];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachWalTempSchemaCacheTransactionPlan::plan($bad, $ops, $statements, 'commit', 'missing'));
};

return $tests;
