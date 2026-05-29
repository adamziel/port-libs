<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachSchemaCookieRepreparePlan;

$schemas103 = static fn (): array => [
    'main' => [
        'schema_cookie' => 40,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 41, 'commit' => true],
        ],
        'tables' => ['sqlite_schema', 'wp_options', 'wp_posts'],
        'file' => '/srv/wp/current.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 5,
        'tables' => ['sqlite_schema', 'wp_option_stage'],
        'file' => '',
        'temp' => true,
    ],
    'site' => [
        'schema_cookie' => 12,
        'tables' => ['sqlite_schema', 'wp_2_options', 'wp_2_posts'],
        'file' => '/srv/wp/site.sqlite',
        'cache' => 'shared',
    ],
    'archive' => [
        'schema_cookie' => 18,
        'tables' => ['sqlite_schema', 'wp_archive_options'],
        'file' => '/srv/wp/archive.sqlite',
        'cache' => 'shared',
    ],
];

$statements103 = static fn (): array => [
    ['name' => 'main-active-reader', 'sql' => 'SELECT option_value FROM [main].[wp_options] WHERE option_name = ?', 'active' => true],
    ['name' => 'site-reader', 'sql' => 'SELECT option_value FROM [site].[wp_2_options] WHERE option_name = ?'],
    ['name' => 'site-update', 'sql' => 'UPDATE [site].[wp_2_posts] SET post_title = ? WHERE ID = ?'],
    ['name' => 'temp-stage-reader', 'sql' => 'SELECT option_name FROM [temp].[wp_option_stage] ORDER BY option_name'],
    ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_name FROM wp_options WHERE option_name LIKE ?'],
    ['name' => 'future-blog-reader', 'sql' => 'SELECT option_value FROM [blog103].[wp_options]'],
    ['name' => 'archive-reader', 'sql' => 'SELECT option_value FROM [archive].[wp_archive_options]'],
    ['name' => 'main-insert-from-temp', 'sql' => 'INSERT INTO [main].[wp_options](option_name) SELECT option_name FROM [temp].[wp_option_stage]'],
];

$events103 = static fn (): array => [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 42],
    ['op' => 'schema_write', 'schema' => 'site', 'object' => 'wp_2_options'],
    ['op' => 'schema_write', 'schema' => 'temp', 'object' => 'wp_options'],
    ['op' => 'attach', 'schema' => 'blog103', 'schema_cookie' => 3, 'tables' => ['wp_options'], 'file' => '/srv/wp/blog103.sqlite'],
    ['op' => 'detach', 'schema' => 'archive'],
];

$cache103 = static fn (): array => [
    'main' => ['file' => '/srv/wp/current.sqlite', 'cache' => 'shared', 'schema_cookie' => 41, 'generation' => 8],
    'site' => ['file' => '/srv/wp/site.sqlite', 'cache' => 'shared', 'schema_cookie' => 12, 'generation' => 9],
    'archive' => ['file' => '/srv/wp/archive.sqlite', 'cache' => 'shared', 'schema_cookie' => 18, 'generation' => 10],
    'blog103' => ['file' => '/srv/wp/blog103.sqlite', 'cache' => 'shared', 'schema_cookie' => 2, 'generation' => 11],
];

$plan103 = static fn (?array $schemas = null, ?array $statements = null, ?array $events = null, ?array $cache = null): array => SQLiteAttachSchemaCookieRepreparePlan::currentSourceNext103(
    $schemas ?? $schemas103(),
    $statements ?? $statements103(),
    $events ?? $events103(),
    $cache ?? $cache103(),
);

$value103 = static function (array $data, string $path): mixed {
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

$pathCases103 = [
    'operation marker' => ['operation', 'attach-temp-schema-cache-reprepare-current-source-next103'],
    'dependency first' => ['dependencies.0', 'sqlite-attach-temp-schema-cache-reprepare-current-source-next103'],
    'retains next84 dependency' => ['dependencies.1', 'sqlite-attach-schema-cookie-reprepare-current-source-next84'],
    'status expired' => ['status', 'schema_cache_expired'],
    'source main' => ['source', 'main'],
    'event count' => ['event_count', 5],
    'statement count' => ['statement_count', 8],
    'search order before' => ['search_order_before', ['temp', 'main', 'archive', 'site']],
    'search order after' => ['search_order_after', ['temp', 'main', 'site', 'blog103']],
    'current main cookie from wal' => ['schema_cookies_current.main', 41],
    'next main cookie from wal event' => ['schema_cookies_next.main', 42],
    'next temp cookie after temp ddl' => ['schema_cookies_next.temp', 6],
    'next site cookie after schema write' => ['schema_cookies_next.site', 13],
    'detached archive removed' => ['schema_cookies_next.archive', null],
    'attached blog cookie' => ['schema_cookies_next.blog103', 3],
    'changed schemas' => ['changed_schemas', ['archive', 'blog103', 'main', 'site', 'temp']],
    'attached schema list' => ['attached_schemas', ['blog103']],
    'detached schema list' => ['detached_schemas', ['archive']],
    'written schemas' => ['written_schemas', ['main', 'site', 'temp']],
    'expired statements' => ['expired_statements', ['main-active-reader', 'site-reader', 'site-update', 'temp-stage-reader', 'unqualified-options-reader', 'future-blog-reader', 'archive-reader', 'main-insert-from-temp']],
    'stable statements' => ['stable_statements', []],
    'active current statements' => ['active_current_snapshot_statements', ['main-active-reader']],
    'retryable reads' => ['retryable_read_statements', ['main-active-reader', 'site-reader', 'temp-stage-reader', 'unqualified-options-reader', 'future-blog-reader', 'archive-reader']],
    'write blocked statements' => ['write_statements_blocked_before_retry', ['site-update', 'main-insert-from-temp']],
    'requires reprepare' => ['requires_reprepare', true],
    'requires shared cache reload' => ['requires_shared_cache_reload', true],
    'shared cache reload schemas' => ['shared_cache_reload_schemas', ['blog103', 'main', 'site']],
    'shared cache reuse schemas empty' => ['shared_cache_reuse_schemas', []],
    'uncached schemas include temp' => ['uncached_schemas', ['temp']],
    'main cache generation' => ['schema_cache_entries.main.cache_generation', 8],
    'main cached cookie' => ['schema_cache_entries.main.cached_schema_cookie', 41],
    'main current cookie' => ['schema_cache_entries.main.current_schema_cookie', 41],
    'main next cookie' => ['schema_cache_entries.main.next_schema_cookie', 42],
    'main requires reload' => ['schema_cache_entries.main.requires_reload', true],
    'main cache action' => ['schema_cache_entries.main.next_source_action', 'reload_shared_schema_cache_before_reprepare'],
    'site cached cookie' => ['schema_cache_entries.site.cached_schema_cookie', 12],
    'site next cookie' => ['schema_cache_entries.site.next_schema_cookie', 13],
    'site requires reload' => ['schema_cache_entries.site.requires_reload', true],
    'blog cached stale cookie' => ['schema_cache_entries.blog103.cached_schema_cookie', 2],
    'blog attached next cookie' => ['schema_cache_entries.blog103.next_schema_cookie', 3],
    'blog requires reload' => ['schema_cache_entries.blog103.requires_reload', true],
    'temp uncacheable' => ['schema_cache_entries.temp.cacheable', false],
    'temp no reload action' => ['schema_cache_entries.temp.next_source_action', 'load_schema_records_without_shared_cache'],
    'main active tables' => ['statements.0.tables', ['main.wp_options']],
    'main active reload schemas' => ['statements.0.shared_cache_reload_schemas', ['main']],
    'main active cache action' => ['statements.0.next_source_cache_action', 'finish_current_source_then_reload_cache_on_reset'],
    'main active sqlite ok' => ['statements.0.sqlite_result_on_current_step', 'SQLITE_OK'],
    'main active reset action' => ['statements.0.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'site reader reload schemas' => ['statements.1.shared_cache_reload_schemas', ['site']],
    'site reader cache action' => ['statements.1.next_source_cache_action', 'reload_cache_then_reprepare_next_step'],
    'site update read only false' => ['statements.2.read_only', false],
    'site update write retry' => ['statements.2.next_step_action', 'sqlite_schema_before_write_retry'],
    'temp stage no shared reload' => ['statements.3.next_source_cache_action', 'no_shared_cache_reload'],
    'temp stage expires on temp schema cookie' => ['statements.3.next_step_action', 'sqlite_schema_then_reprepare_and_retry'],
    'unqualified current schema main' => ['statements.4.transitions.0.prepare_schema', 'main'],
    'unqualified next schema temp' => ['statements.4.transitions.0.next_schema', 'temp'],
    'unqualified reloads prepared main cache before temp shadow reprepare' => ['statements.4.shared_cache_reload_schemas', ['main']],
    'future blog current missing' => ['statements.5.transitions.0.prepare_found', false],
    'future blog next found' => ['statements.5.transitions.0.next_found', true],
    'future blog reload schema' => ['statements.5.shared_cache_reload_schemas', ['blog103']],
    'archive next missing' => ['statements.6.transitions.0.next_found', false],
    'archive no reload after detach' => ['statements.6.shared_cache_reload_schemas', []],
    'insert from temp tables' => ['statements.7.tables', ['main.wp_options', 'temp.wp_option_stage']],
    'insert from temp reload schemas' => ['statements.7.shared_cache_reload_schemas', ['main']],
    'active reset reload statements' => ['active_reset_shared_cache_reload_statements', ['main-active-reader']],
    'next step reload statements' => ['next_step_shared_cache_reload_statements', ['site-reader', 'site-update', 'unqualified-options-reader', 'future-blog-reader', 'main-insert-from-temp']],
];

foreach ($pathCases103 as $name => [$path, $expected]) {
    $tests['attach temp schema cache reprepare current source next103 ' . $name] = static function (TestRunner $t) use ($plan103, $value103, $path, $expected): void {
        $t->same($expected, $value103($plan103(), $path));
    };
}

$tests['attach temp schema cache reprepare current source next103 cache reuse stays stable'] = static function (TestRunner $t) use ($schemas103, $cache103): void {
    $result = SQLiteAttachSchemaCookieRepreparePlan::currentSourceNext103($schemas103(), [
        ['name' => 'site-reader', 'sql' => 'SELECT option_value FROM [site].[wp_2_options]'],
    ], [], $cache103());

    $t->same('schema_cache_stable', $result['status']);
    $t->same(['archive', 'main', 'site'], $result['shared_cache_reuse_schemas']);
    $t->same(false, $result['requires_shared_cache_reload']);
};

$tests['attach temp schema cache reprepare current source next103 private cache does not reload'] = static function (TestRunner $t) use ($schemas103, $events103, $cache103): void {
    $cache = $cache103();
    $cache['site']['cache'] = 'private';
    $result = SQLiteAttachSchemaCookieRepreparePlan::currentSourceNext103($schemas103(), [
        ['name' => 'site-reader', 'sql' => 'SELECT option_value FROM [site].[wp_2_options]'],
    ], $events103(), $cache);

    $t->same(false, $result['schema_cache_entries']['site']['cacheable']);
    $t->same([], $result['statements'][0]['shared_cache_reload_schemas']);
};

$tests['attach temp schema cache reprepare current source next103 missing cache entry loads without shared reload'] = static function (TestRunner $t) use ($schemas103, $events103): void {
    $result = SQLiteAttachSchemaCookieRepreparePlan::currentSourceNext103($schemas103(), [
        ['name' => 'main-reader', 'sql' => 'SELECT option_value FROM [main].[wp_options]'],
    ], $events103(), []);

    $t->same(null, $result['schema_cache_entries']['main']['cached_schema_cookie']);
    $t->same(false, $result['schema_cache_entries']['main']['requires_reload']);
};

$tests['attach temp schema cache reprepare current source next103 invalid cache cookie rejected'] = static function (TestRunner $t) use ($schemas103, $events103, $cache103): void {
    $cache = $cache103();
    $cache['main']['schema_cookie'] = '41';
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachSchemaCookieRepreparePlan::currentSourceNext103($schemas103(), [
        ['name' => 'main-reader', 'sql' => 'SELECT option_value FROM [main].[wp_options]'],
    ], $events103(), $cache));
};

return $tests;
