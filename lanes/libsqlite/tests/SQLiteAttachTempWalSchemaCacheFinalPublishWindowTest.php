<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemasFinalPublish = [
    'main' => [
        'schema_cookie' => 1004,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 1004, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 990,
        'tables' => ['wp_theme_stage_publish_token'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 955,
        'tables' => ['wp_schema_archive_receipt'],
        'indexes' => ['wp_schema_archive_receipt_key'],
        'file' => '/srv/wp/archive-final.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 955,
        'tables' => ['wp_schema_handoff_receipt'],
        'indexes' => ['wp_schema_handoff_receipt_key'],
        'file' => '/srv/wp/handoff-final.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 1002,
        'tables' => ['wp_schema_publish_done', 'wp_schema_publish_final'],
        'indexes' => ['wp_schema_publish_done_key', 'wp_schema_publish_final_key'],
        'file' => '/srv/wp/publish-final.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 922,
        'tables' => ['wp_job_retry_dispatch'],
        'indexes' => ['wp_job_retry_dispatch_key'],
        'file' => '/srv/wp/queue-final.sqlite',
    ],
    'seal' => [
        'schema_cookie' => 996,
        'tables' => ['wp_schema_seal_receipt'],
        'indexes' => ['wp_schema_seal_receipt_key'],
        'file' => '/srv/wp/seal-final.sqlite',
    ],
];

$statementsFinalPublish = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final INDEXED BY wp_navigation_rule_locale_publish_final_key WHERE nav_key = ?', 'active' => true],
    ['name' => 'temp-token-writer', 'sql' => 'UPDATE temp.wp_theme_stage_publish_token SET touched = 1 WHERE token = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt INDEXED BY wp_schema_archive_receipt_key WHERE archive_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt INDEXED BY wp_schema_handoff_receipt_key WHERE handoff_key = ?'],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done INDEXED BY wp_schema_publish_done_key WHERE publish_key = ?'],
    ['name' => 'queue-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_dispatch INDEXED BY wp_job_retry_dispatch_key WHERE job_key = ?'],
    ['name' => 'seal-reader', 'sql' => 'SELECT seal_id FROM seal.wp_schema_seal_receipt INDEXED BY wp_schema_seal_receipt_key WHERE seal_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt INDEXED BY wp_schema_review_receipt_key WHERE review_key = ?'],
];

$planFinalPublish = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::finalSchemaCachePublishWindow(
    $schemas ?? $schemasFinalPublish,
    $statements ?? $statementsFinalPublish,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache final publish window extends preparation handoff'] = static function (TestRunner $t) use ($planFinalPublish): void {
    $result = $planFinalPublish([
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 1008, 'table' => 'wp_theme_stage_publish_token_ready', 'commit' => true],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt', 'to' => 'wp_schema_handoff_receipt_published'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 1016, 'tables' => ['wp_schema_review_receipt'], 'indexes' => ['wp_schema_review_receipt_key'], 'file' => '/srv/wp/review-final.sqlite'],
        ['op' => 'drop_index', 'schema' => 'queue', 'index' => 'wp_job_retry_dispatch_key'],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'wal_commit', 'schema' => 'seal', 'schema_cookie' => 1018, 'table' => 'wp_schema_seal_receipt_ready', 'indexes' => ['wp_schema_seal_receipt_ready_key'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 1020, 'table' => 'wp_navigation_rule_locale_publish_ready', 'indexes' => ['wp_navigation_rule_locale_publish_ready_key'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'review', 'schema_cookie' => 1005, 'table' => 'wp_schema_review_uncommitted', 'indexes' => ['wp_schema_review_uncommitted_key'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-final-publish-window', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(7, $result['event_count']);
    $t->same(1020, $result['schema_cookies_next']['main']);
    $t->same(1008, $result['schema_cookies_next']['temp']);
    $t->same(956, $result['schema_cookies_next']['handoff']);
    $t->same(1002, $result['schema_cookies_next']['publish']);
    $t->same(923, $result['schema_cookies_next']['queue']);
    $t->same(1018, $result['schema_cookies_next']['seal']);
    $t->same(1016, $result['schema_cookies_next']['review']);
    $t->same(false, isset($result['schema_cookies_next']['archive']));
    $t->same(['main-final-reader'], $result['active_current_snapshot_statements']);
    $t->same(['temp-token-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['archive-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-reader']['index_transitions'][0]['next_found']);
    $t->same('review', $result['statements']['review-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['publish-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache final publish window ignores detached scratch review'] = static function (TestRunner $t) use ($planFinalPublish): void {
    $result = $planFinalPublish([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 1005, 'tables' => ['wp_schema_scratch_review'], 'indexes' => ['wp_schema_scratch_review_key'], 'file' => '/srv/wp/scratch-final.sqlite'],
        ['op' => 'schema_write', 'schema' => 'scratch', 'schema_cookie' => 1006, 'table' => 'wp_schema_scratch_review_meta', 'indexes' => ['wp_schema_scratch_review_meta_key'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'handoff', 'publish', 'queue', 'seal'], $result['search_order_next']);
};

return $tests;
