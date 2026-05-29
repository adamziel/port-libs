<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas177180 = [
    'main' => [
        'schema_cookie' => 177,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_name', 'wp_posts_date'],
    ],
    'temp' => [
        'schema_cookie' => 80,
        'tables' => ['wp_options'],
        'indexes' => ['wp_temp_options_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 176,
        'tables' => ['wp_comments', 'wp_terms_archive'],
        'indexes' => ['wp_comments_post_id', 'wp_terms_archive_slug'],
        'file' => '/srv/wp/archive-next177.sqlite',
    ],
];

$statements177180 = [
    ['name' => 'archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments INDEXED BY wp_comments_post_id WHERE comment_post_ID = ?'],
    ['name' => 'active-archive-terms-reader', 'sql' => 'SELECT term_id FROM archive.wp_terms_archive INDEXED BY wp_terms_archive_slug WHERE slug = ?', 'active' => true],
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM temp.wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-posts-writer', 'sql' => 'UPDATE wp_posts SET post_modified = ? WHERE ID = ?'],
    ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
];

$plan177180 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext177180(
    $schemas ?? $schemas177180,
    $statements ?? $statements177180,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next177 detach expires qualified archive readers'] = static function (TestRunner $t) use ($plan177180): void {
    $result = $plan177180([
        ['op' => 'detach', 'schema' => 'archive'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next177-180', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next177', $result['dependencies'][0]);
    $t->same(['archive'], $result['changed_schemas']);
    $t->same('__detached__', $result['statements']['archive-comments-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['archive-comments-reader']['schema_transitions'][0]['next_found']);
    $t->same(['archive-comments-reader', 'active-archive-terms-reader'], $result['retryable_read_statements']);
    $t->same(['active-archive-terms-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next178 drop temp index expires indexed by reader'] = static function (TestRunner $t) use ($plan177180): void {
    $result = $plan177180([
        ['op' => 'drop_index', 'schema' => 'temp', 'index' => 'wp_temp_options_name'],
    ]);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same(true, $result['statements']['temp-options-reader']['index_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['temp-options-reader']['index_transitions'][0]['next_found']);
    $t->same('sqlite_schema_then_reprepare_read_statement', $result['statements']['temp-options-reader']['next_step_action']);
    $t->same(['temp-options-reader', 'unqualified-options-reader'], $result['expired_statements']);
};

$tests['attach temp wal schema cache current source next179 rename attached index expires active reader on reset'] = static function (TestRunner $t) use ($plan177180): void {
    $result = $plan177180([
        ['op' => 'rename_index', 'schema' => 'archive', 'from' => 'wp_terms_archive_slug', 'to' => 'wp_terms_archive_slug_2026'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(true, $result['statements']['active-archive-terms-reader']['index_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['active-archive-terms-reader']['index_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['active-archive-terms-reader']['next_step_action']);
    $t->same(['active-archive-terms-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next180 duplicate committed wal schema event is consolidated'] = static function (TestRunner $t) use ($plan177180): void {
    $result = $plan177180([
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 180, 'table' => 'wp_commentmeta'],
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 180, 'table' => 'wp_commentmeta'],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(['archive'], $result['changed_schemas']);
    $t->same(180, $result['schema_cookies_next']['archive']);
    $t->same('schema_cache_expired', $result['status']);
    $t->same(['archive-comments-reader', 'active-archive-terms-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next177 180 rejects detach of temp schema'] = static function (TestRunner $t) use ($plan177180): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan177180([
        ['op' => 'detach', 'schema' => 'temp'],
    ]));
};

return $tests;
