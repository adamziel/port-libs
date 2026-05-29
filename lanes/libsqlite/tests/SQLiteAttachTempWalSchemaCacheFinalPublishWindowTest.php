<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas10051020 = [
    'main' => [
        'schema_cookie' => 1004,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next1004'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next1004'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 1004, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 990,
        'tables' => ['wp_theme_stage_publish_token_next990'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 955,
        'tables' => ['wp_schema_archive_receipt_next986'],
        'indexes' => ['wp_schema_archive_receipt_key_next992'],
        'file' => '/srv/wp/archive-next1005.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 955,
        'tables' => ['wp_schema_handoff_receipt_next970'],
        'indexes' => ['wp_schema_handoff_receipt_key_next920'],
        'file' => '/srv/wp/handoff-next1005.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 1002,
        'tables' => ['wp_schema_publish_done_next1002', 'wp_schema_publish_final_next955'],
        'indexes' => ['wp_schema_publish_done_key_next1002', 'wp_schema_publish_final_key_next955'],
        'file' => '/srv/wp/publish-next1005.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 922,
        'tables' => ['wp_job_retry_dispatch_next1005'],
        'indexes' => ['wp_job_retry_dispatch_key_next1005'],
        'file' => '/srv/wp/queue-next1005.sqlite',
    ],
    'seal' => [
        'schema_cookie' => 996,
        'tables' => ['wp_schema_seal_receipt_next996'],
        'indexes' => ['wp_schema_seal_receipt_key_next996'],
        'file' => '/srv/wp/seal-next1005.sqlite',
    ],
];

$statements10051020 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next1004 INDEXED BY wp_navigation_rule_locale_publish_final_key_next1004 WHERE nav_key = ?', 'active' => true],
    ['name' => 'temp-token-writer', 'sql' => 'UPDATE temp.wp_theme_stage_publish_token_next990 SET touched = 1 WHERE token = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next986 INDEXED BY wp_schema_archive_receipt_key_next992 WHERE archive_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next970 INDEXED BY wp_schema_handoff_receipt_key_next920 WHERE handoff_key = ?'],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next1002 INDEXED BY wp_schema_publish_done_key_next1002 WHERE publish_key = ?'],
    ['name' => 'queue-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_dispatch_next1005 INDEXED BY wp_job_retry_dispatch_key_next1005 WHERE job_key = ?'],
    ['name' => 'seal-reader', 'sql' => 'SELECT seal_id FROM seal.wp_schema_seal_receipt_next996 INDEXED BY wp_schema_seal_receipt_key_next996 WHERE seal_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next1016 INDEXED BY wp_schema_review_receipt_key_next1016 WHERE review_key = ?'],
];

$plan10051020 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::finalSchemaCachePublishWindow(
    $schemas ?? $schemas10051020,
    $statements ?? $statements10051020,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache final publish window extends preparation handoff'] = static function (TestRunner $t) use ($plan10051020): void {
    $result = $plan10051020([
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 1008, 'table' => 'wp_theme_stage_publish_token_next1008', 'commit' => true],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next970', 'to' => 'wp_schema_handoff_receipt_next1010'],
        ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 1016, 'tables' => ['wp_schema_review_receipt_next1016'], 'indexes' => ['wp_schema_review_receipt_key_next1016'], 'file' => '/srv/wp/review-next1016.sqlite'],
        ['op' => 'drop_index', 'schema' => 'queue', 'index' => 'wp_job_retry_dispatch_key_next1005'],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'wal_commit', 'schema' => 'seal', 'schema_cookie' => 1018, 'table' => 'wp_schema_seal_receipt_next1018', 'indexes' => ['wp_schema_seal_receipt_key_next1018'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 1020, 'table' => 'wp_navigation_rule_locale_publish_final_next1020', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next1020'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'review', 'schema_cookie' => 1005, 'table' => 'wp_schema_review_uncommitted_next1005', 'indexes' => ['wp_schema_review_uncommitted_key_next1005'], 'commit' => false],
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

$tests['attach temp wal schema cache final publish window ignores detached scratch review'] = static function (TestRunner $t) use ($plan10051020): void {
    $result = $plan10051020([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 1005, 'tables' => ['wp_schema_scratch_review_next1005'], 'indexes' => ['wp_schema_scratch_review_key_next1005'], 'file' => '/srv/wp/scratch-next1005.sqlite'],
        ['op' => 'schema_write', 'schema' => 'scratch', 'schema_cookie' => 1006, 'table' => 'wp_schema_scratch_review_meta_next1006', 'indexes' => ['wp_schema_scratch_review_meta_key_next1006'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'handoff', 'publish', 'queue', 'seal'], $result['search_order_next']);
};

return $tests;
