<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas161164 = [
    'main' => [
        'schema_cookie' => 161,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_name'],
    ],
    'temp' => [
        'schema_cookie' => 61,
        'tables' => ['wp_import_queue'],
        'indexes' => ['wp_import_queue_slug'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 91,
        'tables' => ['wp_posts_archive', 'wp_terms_archive'],
        'indexes' => ['wp_archive_posts_date', 'wp_archive_terms_slug'],
        'file' => '/srv/wp/archive-next161.sqlite',
    ],
];

$statements161164 = [
    ['name' => 'options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'archive-terms-reader', 'sql' => 'SELECT name FROM archive.wp_terms_archive WHERE slug = ?'],
    ['name' => 'main-comments-reader', 'sql' => 'SELECT comment_ID FROM main.wp_comments WHERE comment_post_ID = ?'],
    ['name' => 'active-archive-index-reader', 'sql' => 'SELECT ID FROM archive.wp_posts_archive INDEXED BY wp_archive_posts_date WHERE post_date > ?', 'active' => true],
];

$plan161164 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas161164,
    $statements ?? $statements161164,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next161 temp options shadow wins unqualified reader'] = static function (TestRunner $t) use ($plan161164): void {
    $result = $plan161164([
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options'],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same('main', $result['statements']['options-reader']['schema_transitions'][0]['current_schema']);
    $t->same('temp', $result['statements']['options-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['options-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next162 detach expires qualified archive reader'] = static function (TestRunner $t) use ($plan161164): void {
    $result = $plan161164([
        ['op' => 'detach', 'schema' => 'archive'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same('archive', $result['statements']['archive-terms-reader']['schema_transitions'][0]['current_schema']);
    $t->same('__detached__', $result['statements']['archive-terms-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['archive-terms-reader']['schema_transitions'][0]['next_found']);
    $t->same(['archive-terms-reader', 'active-archive-index-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next163 committed wal table resolves main qualified reader'] = static function (TestRunner $t) use ($plan161164): void {
    $result = $plan161164([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 163, 'table' => 'wp_comments'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(163, $result['schema_cookies_next']['main']);
    $t->same('main', $result['statements']['main-comments-reader']['schema_transitions'][0]['current_schema']);
    $t->same(false, $result['statements']['main-comments-reader']['schema_transitions'][0]['current_found']);
    $t->same('main', $result['statements']['main-comments-reader']['schema_transitions'][0]['next_schema']);
    $t->same(true, $result['statements']['main-comments-reader']['schema_transitions'][0]['next_found']);
    $t->same(['options-reader', 'main-comments-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next164 active attached index rename finishes current snapshot'] = static function (TestRunner $t) use ($plan161164): void {
    $result = $plan161164([
        ['op' => 'rename_index', 'schema' => 'archive', 'from' => 'wp_archive_posts_date', 'to' => 'wp_archive_posts_date_2026'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(92, $result['schema_cookies_next']['archive']);
    $t->same(true, $result['statements']['active-archive-index-reader']['index_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['active-archive-index-reader']['index_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['active-archive-index-reader']['next_step_action']);
    $t->same(['active-archive-index-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next161 164 ignores rolled back wal table'] = static function (TestRunner $t) use ($plan161164): void {
    $result = $plan161164([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 164, 'table' => 'wp_comments', 'commit' => false],
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options'],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(161, $result['schema_cookies_next']['main']);
    $t->same(62, $result['schema_cookies_next']['temp']);
    $t->same('main', $result['statements']['main-comments-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['main-comments-reader']['schema_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next161 164 rejects detach main'] = static function (TestRunner $t) use ($plan161164): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan161164([
        ['op' => 'detach', 'schema' => 'main'],
    ]));
};

return $tests;
