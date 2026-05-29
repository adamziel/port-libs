<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas185188 = [
    'main' => [
        'schema_cookie' => 185,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_name', 'wp_posts_date'],
    ],
    'temp' => [
        'schema_cookie' => 88,
        'tables' => ['wp_options', 'wp_uploads'],
        'indexes' => ['wp_temp_options_name', 'wp_uploads_token'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 184,
        'tables' => ['wp_comments', 'wp_terms_archive'],
        'indexes' => ['wp_comments_post_id', 'wp_terms_archive_slug'],
        'file' => '/srv/wp/archive-next185.sqlite',
    ],
];

$statements185188 = [
    ['name' => 'temp-upload-reader', 'sql' => 'SELECT file FROM temp.wp_uploads INDEXED BY wp_uploads_token WHERE token = ?', 'active' => true],
    ['name' => 'archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments INDEXED BY wp_comments_post_id WHERE comment_post_ID = ?'],
    ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options WHERE option_name = ?'],
    ['name' => 'main-posts-writer', 'sql' => 'UPDATE wp_posts SET post_modified = ? WHERE ID = ?'],
];

$plan185188 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext185188(
    $schemas ?? $schemas185188,
    $statements ?? $statements185188,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next185 temp wal drop index holds active snapshot'] = static function (TestRunner $t) use ($plan185188): void {
    $result = $plan185188([
        ['op' => 'drop_index', 'schema' => 'temp', 'index' => 'wp_uploads_token'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next185-188', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next185', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next181', $result['dependencies'][4]);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same(false, $result['statements']['temp-upload-reader']['index_transitions'][0]['next_found']);
    $t->same(['temp-upload-reader', 'unqualified-options-reader'], $result['expired_statements']);
    $t->same(['temp-upload-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next186 detach attached reader remains retryable'] = static function (TestRunner $t) use ($plan185188): void {
    $result = $plan185188([
        ['op' => 'detach', 'schema' => 'archive'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same('__detached__', $result['statements']['archive-comments-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['archive-comments-reader'], $result['retryable_read_statements']);
    $t->same([], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next187 main ddl blocks writer before retry'] = static function (TestRunner $t) use ($plan185188): void {
    $result = $plan185188([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 187, 'table' => 'wp_posts'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(['main-options-reader', 'main-posts-writer'], $result['expired_statements']);
    $t->same(['main-options-reader'], $result['retryable_read_statements']);
    $t->same(['temp-upload-reader', 'archive-comments-reader', 'unqualified-options-reader'], $result['stable_statements']);
    $t->same(['main-posts-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next188 duplicate wal events still consolidate'] = static function (TestRunner $t) use ($plan185188): void {
    $result = $plan185188([
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 188, 'table' => 'wp_commentmeta'],
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 188, 'table' => 'wp_commentmeta'],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(['archive'], $result['changed_schemas']);
    $t->same(188, $result['schema_cookies_next']['archive']);
    $t->same(['archive-comments-reader'], $result['expired_statements']);
};

return $tests;
