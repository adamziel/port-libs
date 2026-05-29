<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas173176 = [
    'main' => [
        'schema_cookie' => 173,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_name', 'wp_posts_date'],
    ],
    'temp' => [
        'schema_cookie' => 73,
        'tables' => ['wp_options'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 101,
        'tables' => ['wp_posts_archive', 'wp_terms_archive'],
        'indexes' => ['wp_archive_posts_date', 'wp_terms_archive_slug'],
        'file' => '/srv/wp/archive-next173.sqlite',
    ],
];

$statements173176 = [
    ['name' => 'pending-archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments WHERE comment_post_ID = ?'],
    ['name' => 'temp-options-index-reader', 'sql' => 'SELECT option_value FROM temp.wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'active-archive-terms-reader', 'sql' => 'SELECT term_id FROM archive.wp_terms_archive WHERE slug = ?', 'active' => true],
    ['name' => 'options-writer', 'sql' => 'UPDATE wp_options SET option_value = ? WHERE option_name = ?'],
    ['name' => 'main-posts-reader', 'sql' => 'SELECT ID FROM wp_posts WHERE post_date > ?'],
];

$plan173176 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext173176(
    $schemas ?? $schemas173176,
    $statements ?? $statements173176,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next173 attached wal table create expires missing reader'] = static function (TestRunner $t) use ($plan173176): void {
    $result = $plan173176([
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 173, 'table' => 'wp_comments'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next173-176', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next173', $result['dependencies'][0]);
    $t->same(['archive'], $result['changed_schemas']);
    $t->same(false, $result['statements']['pending-archive-comments-reader']['schema_transitions'][0]['current_found']);
    $t->same(true, $result['statements']['pending-archive-comments-reader']['schema_transitions'][0]['next_found']);
    $t->same(['pending-archive-comments-reader', 'active-archive-terms-reader'], $result['retryable_read_statements']);
    $t->same(['temp-options-index-reader', 'options-writer', 'main-posts-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next174 temp index create resolves indexed by reader'] = static function (TestRunner $t) use ($plan173176): void {
    $result = $plan173176([
        ['op' => 'create_index', 'schema' => 'temp', 'index' => 'wp_temp_options_name'],
    ]);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same(false, $result['statements']['temp-options-index-reader']['index_transitions'][0]['current_found']);
    $t->same(true, $result['statements']['temp-options-index-reader']['index_transitions'][0]['next_found']);
    $t->same('sqlite_schema_then_reprepare_read_statement', $result['statements']['temp-options-index-reader']['next_step_action']);
    $t->same(['temp-options-index-reader', 'options-writer'], $result['expired_statements']);
};

$tests['attach temp wal schema cache current source next175 attached table rename expires active current snapshot'] = static function (TestRunner $t) use ($plan173176): void {
    $result = $plan173176([
        ['op' => 'rename_table', 'schema' => 'archive', 'from' => 'wp_terms_archive', 'to' => 'wp_terms_archive_2026'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(true, $result['statements']['active-archive-terms-reader']['active']);
    $t->same(true, $result['statements']['active-archive-terms-reader']['schema_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['active-archive-terms-reader']['schema_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['active-archive-terms-reader']['next_step_action']);
    $t->same(['active-archive-terms-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next176 rolled back wal event is filtered before cache expiry'] = static function (TestRunner $t) use ($plan173176): void {
    $result = $plan173176([
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 174, 'table' => 'wp_comments', 'commit' => false],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same(0, $result['event_count']);
    $t->same([], $result['changed_schemas']);
    $t->same(false, $result['statements']['pending-archive-comments-reader']['schema_transitions'][0]['next_found']);
    $t->same([], $result['expired_statements']);
    $t->same(['pending-archive-comments-reader', 'temp-options-index-reader', 'active-archive-terms-reader', 'options-writer', 'main-posts-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next173 176 rejects duplicate attach schema'] = static function (TestRunner $t) use ($plan173176): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan173176([
        ['op' => 'attach', 'schema' => 'archive', 'tables' => ['wp_new_archive']],
    ]));
};

return $tests;
