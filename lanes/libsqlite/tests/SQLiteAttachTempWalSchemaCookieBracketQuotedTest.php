<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCookieSourcePlan;

$tests = [];

$schemas = static fn (): array => [
    'main' => [
        'schema_cookie' => 40,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 41, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 44, 'commit' => false],
        ],
        'wal_schema_cookie' => 42,
        'tables' => ['sqlite_schema', 'wp_options', 'wp_posts'],
        'next_tables' => ['sqlite_schema', 'wp_options', 'wp_posts', 'wp_plugin_state'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 6,
        'temp_schema_cookie' => 7,
        'tables' => ['sqlite_schema', 'wp_options_stage'],
        'next_tables' => ['sqlite_schema', 'wp_options_stage', 'wp_options'],
        'file' => '',
        'temp' => true,
    ],
    'site' => [
        'schema_cookie' => 10,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 11, 'commit' => true],
            ['page' => 2, 'schema_cookie' => 99, 'commit' => true],
        ],
        'tables' => ['sqlite_schema', 'wp_2_options'],
        'next_tables' => ['sqlite_schema', 'wp_2_options', 'wp_options'],
        'file' => '/srv/wp/site.sqlite',
    ],
    'analytics' => [
        'schema_cookie' => 3,
        'wal_schema_cookie' => 4,
        'tables' => ['sqlite_schema', 'wp_events'],
        'next_tables' => ['sqlite_schema', 'wp_events'],
        'file' => '/srv/wp/analytics.sqlite',
    ],
];

$statements = static fn (): array => [
    ['name' => 'bracket-main-schema-reader', 'sql' => 'SELECT sql FROM [main].[sqlite_schema] WHERE name = ?'],
    ['name' => 'bracket-temp-master-reader', 'sql' => 'SELECT name FROM [temp].[sqlite_master] WHERE type = ?'],
    ['name' => 'bracket-site-options-reader', 'sql' => 'SELECT option_value FROM [site].[wp_2_options] WHERE option_name = ?'],
    ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_value FROM [wp_options] WHERE option_name = ?', 'active' => true],
    ['name' => 'stage-insert', 'sql' => 'INSERT INTO [temp].[wp_options_stage](option_name, option_value) VALUES (?, ?)'],
    ['name' => 'analytics-reader', 'sql' => 'SELECT event_id FROM [analytics].[wp_events]'],
    ['name' => 'site-shadow-reader', 'sql' => 'SELECT option_name FROM [site].[wp_options]'],
    ['name' => 'main-plugin-state', 'sql' => 'SELECT option_name FROM [main].[wp_plugin_state]'],
];

$plan = static fn (?array $schemasArg = null, ?array $statementsArg = null, string $source = 'main'): array => SQLiteAttachWalTempSchemaCookieSourcePlan::bracketQuotedSchemaCookieSourcePlan(
    $schemasArg ?? $schemas(),
    $statementsArg ?? $statements(),
    $source,
);

$value = static function (array $data, string $path): mixed {
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

$pathCases = [
    'operation marker' => ['operation', 'attach-temp-wal-schema-cookie-bracket-quoted-source'],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-temp-wal-schema-cookie-bracket-quoted-source'],
    'bracket dependency marker' => ['dependencies.3', 'sqlite-bracket-quoted-attach-schema-cookie-source'],
    'schema alias dependency marker' => ['dependencies.4', 'sqlite-schema-table-alias-cookie-source'],
    'search order preserves temp main attached' => ['search_order', ['temp', 'main', 'site', 'analytics']],
    'main current cookie from committed wal page one' => ['schema_cookies_current.main', 41],
    'main next cookie from wal header' => ['schema_cookies_next.main', 42],
    'main uncommitted page one ignored' => ['schema_cookie_sources.main.wal_tail_ignored', true],
    'temp current cookie from temp schema' => ['schema_cookies_current.temp', 6],
    'temp next cookie from rollback journal' => ['schema_cookies_next.temp', 7],
    'site current cookie from committed wal page one' => ['schema_cookies_current.site', 11],
    'site next cookie stable' => ['schema_cookies_next.site', 11],
    'analytics next cookie from wal header' => ['schema_cookies_next.analytics', 4],
    'changed schemas include temp main analytics' => ['changed_schemas', ['temp', 'main', 'analytics']],
    'statement count' => ['statement_count', 8],
    'expired statements' => ['expired_statements', ['bracket-main-schema-reader', 'bracket-temp-master-reader', 'unqualified-options-reader', 'stage-insert', 'analytics-reader', 'site-shadow-reader', 'main-plugin-state']],
    'stable site options reader' => ['stable_statements', ['bracket-site-options-reader']],
    'retryable reads' => ['retryable_read_statements', ['bracket-main-schema-reader', 'bracket-temp-master-reader', 'unqualified-options-reader', 'analytics-reader', 'site-shadow-reader', 'main-plugin-state']],
    'write blocks' => ['write_statements_blocked_before_retry', ['stage-insert']],
    'active current snapshot' => ['active_current_snapshot_statements', ['unqualified-options-reader']],
    'main schema bracket normalized table' => ['statements.0.tables.0', 'main.sqlite_schema'],
    'main schema reader prepare schema' => ['statements.0.schema_transitions.0.prepare_schema', 'main'],
    'main schema reader next source' => ['statements.0.schema_transitions.0.next_cookie_source', 'wal_commit_header'],
    'main schema reader reprepare' => ['statements.0.schema_transitions.0.requires_reprepare', true],
    'temp sqlite master alias normalized' => ['statements.1.tables.0', 'temp.sqlite_schema'],
    'temp master prepare found' => ['statements.1.schema_transitions.0.prepare_found', true],
    'temp master next source' => ['statements.1.schema_transitions.0.next_cookie_source', 'temp_rollback_journal'],
    'site bracket qualified table stable' => ['statements.2.requires_reprepare', false],
    'site bracket qualified next action' => ['statements.2.next_step_action', 'reuse_prepared_statement'],
    'unqualified bracket options current main' => ['statements.3.schema_transitions.0.prepare_schema', 'main'],
    'unqualified bracket options next temp' => ['statements.3.schema_transitions.0.next_schema', 'temp'],
    'active bracket reader current ok' => ['statements.3.sqlite_result_on_current_step', 'SQLITE_OK'],
    'active bracket reader reset schema' => ['statements.3.next_step_action', 'finish_current_snapshot_then_sqlite_schema_on_reset'],
    'stage insert bracket temp stable resolution' => ['statements.4.schema_transitions.0.resolution_changed', false],
    'stage insert temp cookie changed' => ['statements.4.schema_transitions.0.requires_reprepare', true],
    'analytics read cookie changes' => ['statements.5.schema_transitions.0.requires_reprepare', true],
    'site shadow current missing' => ['statements.6.schema_transitions.0.prepare_found', false],
    'site shadow next found' => ['statements.6.schema_transitions.0.next_found', true],
    'main plugin state current missing' => ['statements.7.schema_transitions.0.prepare_found', false],
    'main plugin state next found' => ['statements.7.schema_transitions.0.next_found', true],
    'database list analytics file' => ['database_list.3.file', '/srv/wp/analytics.sqlite'],
    'database list analytics next cookie' => ['database_list.3.next_schema_cookie', 4],
];

foreach ($pathCases as $name => [$path, $expected]) {
    $tests['attach temp wal schema cookie bracket quoted ' . $name] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
        $t->same($expected, $value($plan(), $path));
    };
}

$tests['attach temp wal schema cookie bracket quoted source accepted'] = static function (TestRunner $t) use ($schemas, $statements): void {
    $result = SQLiteAttachWalTempSchemaCookieSourcePlan::bracketQuotedSchemaCookieSourcePlan($schemas(), $statements(), '[site]');
    $t->same('site', $result['source']);
};

$tests['attach temp wal schema cookie bracket quoted sqlite master bare alias resolves'] = static function (TestRunner $t) use ($schemas): void {
    $result = SQLiteAttachWalTempSchemaCookieSourcePlan::bracketQuotedSchemaCookieSourcePlan($schemas(), [
        ['name' => 'bare-master', 'sql' => 'SELECT name FROM sqlite_master WHERE type = ?'],
    ]);
    $t->same('temp', $result['statements'][0]['schema_transitions'][0]['prepare_schema']);
    $t->same('sqlite_schema', $result['statements'][0]['tables'][0]);
};

$tests['attach temp wal schema cookie bracket quoted stable without pending bracket cookies'] = static function (TestRunner $t) use ($schemas): void {
    $schemas = $schemas();
    unset($schemas['main']['wal_schema_cookie'], $schemas['temp']['temp_schema_cookie'], $schemas['analytics']['wal_schema_cookie']);
    $schemas['main']['next_tables'] = $schemas['main']['tables'];
    $schemas['temp']['next_tables'] = $schemas['temp']['tables'];
    $schemas['site']['next_tables'] = $schemas['site']['tables'];
    $result = SQLiteAttachWalTempSchemaCookieSourcePlan::bracketQuotedSchemaCookieSourcePlan($schemas, [
        ['name' => 'main-schema', 'sql' => 'SELECT name FROM [main].[sqlite_schema]'],
        ['name' => 'site-options', 'sql' => 'SELECT option_value FROM [site].[wp_2_options]'],
    ]);
    $t->same('schema_cache_stable', $result['status']);
};

$tests['attach temp wal schema cookie bracket quoted rejects empty bracket identifier'] = static function (TestRunner $t) use ($schemas): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachWalTempSchemaCookieSourcePlan::bracketQuotedSchemaCookieSourcePlan($schemas(), [
        ['name' => 'bad', 'sql' => 'SELECT sql FROM []'],
    ]));
};

$tests['attach temp wal schema cookie bracket quoted update bracket target blocks write retry'] = static function (TestRunner $t) use ($schemas): void {
    $result = SQLiteAttachWalTempSchemaCookieSourcePlan::bracketQuotedSchemaCookieSourcePlan($schemas(), [
        ['name' => 'update-main', 'sql' => 'UPDATE [main].[wp_options] SET option_value = ? WHERE option_name = ?'],
    ]);
    $t->same('main.wp_options', $result['statements'][0]['tables'][0]);
    $t->same(['update-main'], $result['write_statements_blocked_before_retry']);
    $t->same('sqlite_schema_before_write_retry', $result['statements'][0]['next_step_action']);
};

$tests['attach temp wal schema cookie bracket quoted delete bracket target extracts schema'] = static function (TestRunner $t) use ($schemas): void {
    $result = SQLiteAttachWalTempSchemaCookieSourcePlan::bracketQuotedSchemaCookieSourcePlan($schemas(), [
        ['name' => 'delete-stage', 'sql' => 'DELETE FROM [temp].[wp_options_stage] WHERE option_name = ?'],
    ]);
    $t->same('temp.wp_options_stage', $result['statements'][0]['tables'][0]);
    $t->same('temp_rollback_journal', $result['statements'][0]['schema_transitions'][0]['next_cookie_source']);
};

$tests['attach temp wal schema cookie bracket quoted sqlite master alias remains schema table'] = static function (TestRunner $t) use ($schemas): void {
    $result = SQLiteAttachWalTempSchemaCookieSourcePlan::bracketQuotedSchemaCookieSourcePlan($schemas(), [
        ['name' => 'quoted-master', 'sql' => 'SELECT name FROM "main"."sqlite_master" WHERE type = ?'],
    ]);
    $t->same('main.sqlite_schema', $result['statements'][0]['tables'][0]);
    $t->same(true, $result['statements'][0]['schema_transitions'][0]['prepare_found']);
};

return $tests;
