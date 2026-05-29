<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas181184 = [
    'main' => [
        'schema_cookie' => 181,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_name', 'wp_posts_date'],
    ],
    'temp' => [
        'schema_cookie' => 84,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_temp_options_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 180,
        'tables' => ['wp_comments', 'wp_terms_archive'],
        'indexes' => ['wp_comments_post_id', 'wp_terms_archive_slug'],
        'file' => '/srv/wp/archive-next181.sqlite',
    ],
];

$statements181184 = [
    ['name' => 'media-reader', 'sql' => 'SELECT post_id FROM media.wp_postmeta WHERE meta_key = ?'],
    ['name' => 'archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments INDEXED BY wp_comments_post_id WHERE comment_post_ID = ?'],
    ['name' => 'active-temp-posts-reader', 'sql' => 'SELECT ID FROM temp.wp_posts WHERE post_status = ?', 'active' => true],
    ['name' => 'unqualified-posts-reader', 'sql' => 'SELECT ID FROM wp_posts WHERE post_status = ?'],
    ['name' => 'main-options-writer', 'sql' => 'UPDATE main.wp_options SET option_value = ? WHERE option_name = ?'],
];

$plan181184 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas181184,
    $statements ?? $statements181184,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next181 attach media resolves qualified reader'] = static function (TestRunner $t) use ($plan181184): void {
    $result = $plan181184([
        ['op' => 'attach', 'schema' => 'media', 'schema_cookie' => 181, 'tables' => ['wp_postmeta'], 'indexes' => ['wp_postmeta_key'], 'file' => '/srv/wp/media-next181.sqlite'],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same(['media'], $result['changed_schemas']);
    $t->same('__detached__', $result['statements']['media-reader']['schema_transitions'][0]['current_schema']);
    $t->same('media', $result['statements']['media-reader']['schema_transitions'][0]['next_schema']);
    $t->same(true, $result['statements']['media-reader']['schema_transitions'][0]['next_found']);
    $t->same(['media-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next182 drop attached table expires indexed reader'] = static function (TestRunner $t) use ($plan181184): void {
    $result = $plan181184([
        ['op' => 'drop_table', 'schema' => 'archive', 'table' => 'wp_comments'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(true, $result['statements']['archive-comments-reader']['schema_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['archive-comments-reader']['schema_transitions'][0]['next_found']);
    $t->same(true, $result['statements']['archive-comments-reader']['index_transitions'][0]['next_found']);
    $t->same('sqlite_schema_then_reprepare_read_statement', $result['statements']['archive-comments-reader']['next_step_action']);
    $t->same(['archive-comments-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next183 rename temp table keeps active cursor until reset'] = static function (TestRunner $t) use ($plan181184): void {
    $result = $plan181184([
        ['op' => 'rename_table', 'schema' => 'temp', 'from' => 'wp_posts', 'to' => 'wp_posts_next183'],
    ]);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same(true, $result['statements']['active-temp-posts-reader']['schema_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['active-temp-posts-reader']['schema_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['active-temp-posts-reader']['next_step_action']);
    $t->same(['active-temp-posts-reader'], $result['active_current_snapshot_statements']);
    $t->same(['active-temp-posts-reader', 'unqualified-posts-reader'], $result['expired_statements']);
};

$tests['attach temp wal schema cache current source next184 uncommitted temp wal event is filtered'] = static function (TestRunner $t) use ($plan181184): void {
    $result = $plan181184([
        ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 185, 'table' => 'wp_new_temp_shadow', 'commit' => false],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same(0, $result['event_count']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['media-reader', 'archive-comments-reader', 'active-temp-posts-reader', 'unqualified-posts-reader', 'main-options-writer'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next181 184 rejects attach of temp schema'] = static function (TestRunner $t) use ($plan181184): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan181184([
        ['op' => 'attach', 'schema' => 'temp', 'tables' => ['wp_shadow']],
    ]));
};

return $tests;
