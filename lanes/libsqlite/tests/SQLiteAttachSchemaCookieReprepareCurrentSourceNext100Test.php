<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachSchemaCookieRepreparePlan;

$tests = [];

$schemas100 = static fn (): array => [
    'main' => [
        'schema_cookie' => 100,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 101, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 199, 'commit' => false],
        ],
        'tables' => ['sqlite_schema', 'wp_options', 'wp_posts'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 7,
        'tables' => ['sqlite_schema', 'wp_option_stage'],
        'file' => '',
        'temp' => true,
    ],
    'site' => [
        'schema_cookie' => 20,
        'wal_schema_cookie' => 21,
        'tables' => ['sqlite_schema', 'wp_2_options', 'wp_terms'],
        'file' => '/srv/wp/site.sqlite',
    ],
    'archive' => [
        'schema_cookie' => 30,
        'tables' => ['sqlite_schema', 'wp_archive_options'],
        'file' => '/srv/wp/archive.sqlite',
    ],
];

$statements100 = static fn (): array => [
    [
        'name' => 'cte-site-reader',
        'sql' => 'WITH recent_options AS (SELECT option_name FROM [site].[wp_2_options]) SELECT option_name FROM recent_options ORDER BY option_name',
    ],
    [
        'name' => 'recursive-main-reader',
        'sql' => 'WITH RECURSIVE option_walk(name) AS (SELECT option_name FROM [main].[wp_options] UNION ALL SELECT option_name FROM [main].[wp_options]) SELECT name FROM option_walk',
        'active' => true,
    ],
    [
        'name' => 'materialized-archive-reader',
        'sql' => 'WITH archive_opts AS MATERIALIZED (SELECT option_name FROM [archive].[wp_archive_options]) SELECT option_name FROM archive_opts',
    ],
    [
        'name' => 'not-materialized-temp-insert',
        'sql' => 'WITH stage_names AS NOT MATERIALIZED (SELECT option_name FROM [temp].[wp_option_stage]) INSERT INTO [main].[wp_options](option_name) SELECT option_name FROM stage_names',
    ],
    [
        'name' => 'main-schema-alias-reader',
        'sql' => 'SELECT name FROM [main].[sqlite_master] WHERE type = ?',
    ],
    [
        'name' => 'site-update-write',
        'sql' => 'UPDATE [site].[wp_terms] SET name = ? WHERE term_id = ?',
    ],
    [
        'name' => 'future-blog-reader',
        'sql' => 'WITH future AS (SELECT option_value FROM [blog100].[wp_options]) SELECT option_value FROM future',
    ],
    [
        'name' => 'temp-shadow-reader',
        'sql' => 'WITH stage AS (SELECT option_name FROM [temp].[wp_option_stage]) SELECT option_name FROM stage',
    ],
];

$events100 = static fn (): array => [
    ['op' => 'schema_write', 'schema' => 'site', 'object' => 'wp_2_options'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 102],
    ['op' => 'attach', 'schema' => 'blog100', 'schema_cookie' => 1, 'tables' => ['wp_options'], 'file' => '/srv/wp/blog100.sqlite'],
    ['op' => 'detach', 'schema' => 'archive'],
];

$plan100 = static fn (?array $schemas = null, ?array $statements = null, ?array $events = null, string $source = 'main'): array => SQLiteAttachSchemaCookieRepreparePlan::schemaCookieRepreparePlan(
    $schemas ?? $schemas100(),
    $statements ?? $statements100(),
    $events ?? $events100(),
    $source,
);

$value100 = static function (array $data, string $path): mixed {
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

$pathCases100 = [
    'operation marker' => ['operation', 'attach-schema-cookie-reprepare'],
    'next100 dependency first' => ['dependencies.0', 'sqlite-attach-schema-cookie-reprepare'],
    'retains base dependency' => ['dependencies.1', 'sqlite-attach-schema-cookie-reprepare-current-source-next84'],
    'status expired' => ['status', 'schema_cache_expired'],
    'statement count' => ['statement_count', 8],
    'event count' => ['event_count', 4],
    'search order before' => ['search_order_before', ['temp', 'main', 'archive', 'site']],
    'search order after' => ['search_order_after', ['temp', 'main', 'site', 'blog100']],
    'current main cookie ignores uncommitted wal tail' => ['schema_cookies_current.main', 101],
    'next main cookie from wal event' => ['schema_cookies_next.main', 102],
    'site cookie from wal schema header' => ['schema_cookies_current.site', 21],
    'site cookie increments after schema write' => ['schema_cookies_next.site', 22],
    'archive detached cookie removed' => ['schema_cookies_next.archive', null],
    'attached blog cookie' => ['schema_cookies_next.blog100', 1],
    'changed schemas sorted' => ['changed_schemas', ['archive', 'blog100', 'main', 'site']],
    'attached schemas' => ['attached_schemas', ['blog100']],
    'detached schemas' => ['detached_schemas', ['archive']],
    'written schemas' => ['written_schemas', ['site', 'main']],
    'expired statements' => ['expired_statements', ['cte-site-reader', 'recursive-main-reader', 'materialized-archive-reader', 'not-materialized-temp-insert', 'main-schema-alias-reader', 'site-update-write', 'future-blog-reader']],
    'stable statements' => ['stable_statements', ['temp-shadow-reader']],
    'active current snapshot statements' => ['active_current_snapshot_statements', ['recursive-main-reader']],
    'retryable reads' => ['retryable_read_statements', ['cte-site-reader', 'recursive-main-reader', 'materialized-archive-reader', 'main-schema-alias-reader', 'future-blog-reader']],
    'write blocked statements' => ['write_statements_blocked_before_retry', ['not-materialized-temp-insert', 'site-update-write']],
    'cte site tables exclude cte alias' => ['statements.0.tables', ['site.wp_2_options']],
    'cte site prepare schema' => ['statements.0.prepare_schemas', ['site']],
    'cte site next schema' => ['statements.0.next_schemas', ['site']],
    'cte site cookie before' => ['statements.0.transitions.0.prepare_schema_cookie', 21],
    'cte site cookie after' => ['statements.0.transitions.0.next_schema_cookie', 22],
    'recursive tables dedupe base table' => ['statements.1.tables', ['main.wp_options']],
    'recursive active current ok' => ['statements.1.sqlite_result_on_current_step', 'SQLITE_OK'],
    'recursive active reset action' => ['statements.1.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'materialized archive excludes cte alias' => ['statements.2.tables', ['archive.wp_archive_options']],
    'materialized archive next missing' => ['statements.2.transitions.0.next_found', false],
    'materialized archive resolution changed' => ['statements.2.transitions.0.resolution_changed', true],
    'not materialized insert tables' => ['statements.3.tables', ['temp.wp_option_stage', 'main.wp_options']],
    'not materialized insert write action' => ['statements.3.next_step_action', 'sqlite_schema_before_write_retry'],
    'not materialized temp transition stable' => ['statements.3.transitions.0.requires_reprepare', false],
    'not materialized main transition expires' => ['statements.3.transitions.1.requires_reprepare', true],
    'sqlite master alias normalized' => ['statements.4.tables', ['main.sqlite_schema']],
    'sqlite master alias reprepare' => ['statements.4.transitions.0.requires_reprepare', true],
    'site update bracket target normalized' => ['statements.5.tables', ['site.wp_terms']],
    'site update write blocked' => ['statements.5.next_step_action', 'sqlite_schema_before_write_retry'],
    'future blog current missing' => ['statements.6.transitions.0.prepare_found', false],
    'future blog next found' => ['statements.6.transitions.0.next_found', true],
    'future blog prepare cookie null' => ['statements.6.transitions.0.prepare_schema_cookie', null],
    'future blog next cookie' => ['statements.6.transitions.0.next_schema_cookie', 1],
    'temp shadow stable tables' => ['statements.7.tables', ['temp.wp_option_stage']],
    'temp shadow action reuse' => ['statements.7.next_step_action', 'reuse_prepared_statement'],
    'database before archive' => ['database_list_before.2.name', 'archive'],
    'database after blog file' => ['database_list_after.3.file', '/srv/wp/blog100.sqlite'],
];

foreach ($pathCases100 as $name => [$path, $expected]) {
    $tests['attach schema cookie reprepare current source next100 ' . $name] = static function (TestRunner $t) use ($plan100, $value100, $path, $expected): void {
        $t->same($expected, $value100($plan100(), $path));
    };
}

$tests['attach schema cookie reprepare current source next100 cte alias alone is not a schema dependency'] = static function (TestRunner $t) use ($schemas100): void {
    $result = SQLiteAttachSchemaCookieRepreparePlan::schemaCookieRepreparePlan($schemas100(), [
        ['name' => 'site-only', 'sql' => 'WITH c AS (SELECT option_name FROM [site].[wp_2_options]) SELECT option_name FROM c'],
    ], [
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 103],
    ]);

    $t->same(['site.wp_2_options'], $result['statements'][0]['tables']);
    $t->same(false, $result['statements'][0]['requires_reprepare']);
    $t->same('schema_cache_stable', $result['status']);
};

$tests['attach schema cookie reprepare current source next100 cte body main table expires on main cookie'] = static function (TestRunner $t) use ($schemas100): void {
    $result = SQLiteAttachSchemaCookieRepreparePlan::schemaCookieRepreparePlan($schemas100(), [
        ['name' => 'main-body', 'sql' => 'WITH c AS (SELECT option_name FROM [main].[wp_options]) SELECT option_name FROM c'],
    ], [
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 103],
    ]);

    $t->same(['main.wp_options'], $result['statements'][0]['tables']);
    $t->same(true, $result['statements'][0]['requires_reprepare']);
};

$tests['attach schema cookie reprepare current source next100 multiple ctes drop both aliases'] = static function (TestRunner $t) use ($schemas100): void {
    $result = SQLiteAttachSchemaCookieRepreparePlan::schemaCookieRepreparePlan($schemas100(), [
        ['name' => 'multi', 'sql' => 'WITH a AS (SELECT option_name FROM [site].[wp_2_options]), b AS (SELECT option_name FROM a) SELECT option_name FROM b'],
    ], [
        ['op' => 'schema_write', 'schema' => 'site'],
    ]);

    $t->same(['site.wp_2_options'], $result['statements'][0]['tables']);
    $t->same(true, $result['statements'][0]['requires_reprepare']);
};

$tests['attach schema cookie reprepare current source next100 quoted cte alias is filtered'] = static function (TestRunner $t) use ($schemas100): void {
    $result = SQLiteAttachSchemaCookieRepreparePlan::schemaCookieRepreparePlan($schemas100(), [
        ['name' => 'quoted-cte', 'sql' => 'WITH [Recent Options] AS (SELECT option_name FROM [site].[wp_2_options]) SELECT option_name FROM [Recent Options]'],
    ], [
        ['op' => 'schema_write', 'schema' => 'main'],
    ]);

    $t->same(['site.wp_2_options'], $result['statements'][0]['tables']);
    $t->same(false, $result['statements'][0]['requires_reprepare']);
};

$tests['attach schema cookie reprepare current source next100 bracket source accepted'] = static function (TestRunner $t) use ($schemas100, $statements100): void {
    $result = SQLiteAttachSchemaCookieRepreparePlan::schemaCookieRepreparePlan($schemas100(), [$statements100()[0]], [], '[site]');
    $t->same('site', $result['source']);
};

$tests['attach schema cookie reprepare current source next100 cte values without real table rejected'] = static function (TestRunner $t) use ($schemas100): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachSchemaCookieRepreparePlan::schemaCookieRepreparePlan($schemas100(), [
        ['name' => 'values-only', 'sql' => 'WITH c(x) AS (VALUES (1)) SELECT x FROM c'],
    ], []));
};

$tests['attach schema cookie reprepare current source next100 rejects unterminated quoted cte'] = static function (TestRunner $t) use ($schemas100): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachSchemaCookieRepreparePlan::schemaCookieRepreparePlan($schemas100(), [
        ['name' => 'bad-cte', 'sql' => 'WITH [bad'],
    ], []));
};

$tests['attach schema cookie reprepare current source next100 rejects unterminated cte body'] = static function (TestRunner $t) use ($schemas100): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachSchemaCookieRepreparePlan::schemaCookieRepreparePlan($schemas100(), [
        ['name' => 'bad-body', 'sql' => 'WITH c AS (SELECT option_name FROM [main].[wp_options] SELECT option_name FROM c'],
    ], []));
};

$tests['attach schema cookie reprepare current source next100 uncommitted wal frame remains ignored'] = static function (TestRunner $t) use ($schemas100): void {
    $schemas = $schemas100();
    unset($schemas['main']['wal_frames'][0]);
    $result = SQLiteAttachSchemaCookieRepreparePlan::schemaCookieRepreparePlan($schemas, [
        ['name' => 'main-reader', 'sql' => 'SELECT option_name FROM [main].[wp_options]'],
    ], []);

    $t->same(100, $result['schema_cookies_current']['main']);
    $t->same('schema_cache_stable', $result['status']);
};

return $tests;
