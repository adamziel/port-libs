<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas10211036 = [
    'main' => [
        'schema_cookie' => 1020,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next1020'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next1020'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 1020, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 1008,
        'tables' => ['wp_theme_stage_publish_token_next1008', 'wp_import_stage_shadow_next1021'],
        'indexes' => ['wp_import_stage_shadow_key_next1021'],
        'temp' => true,
    ],
    'review' => [
        'schema_cookie' => 1016,
        'tables' => ['wp_schema_review_receipt_next1016'],
        'indexes' => ['wp_schema_review_receipt_key_next1016'],
        'file' => '/srv/wp/review-next1021.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 1018,
        'tables' => ['wp_schema_publish_final_next1018'],
        'indexes' => ['wp_schema_publish_final_key_next1018'],
        'file' => '/srv/wp/publish-next1021.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 923,
        'tables' => ['wp_job_retry_dispatch_next1021'],
        'indexes' => ['wp_job_retry_dispatch_key_next1021'],
        'file' => '/srv/wp/queue-next1021.sqlite',
    ],
    'metrics' => [
        'schema_cookie' => 1024,
        'tables' => ['wp_schema_metrics_receipt_next1024'],
        'indexes' => ['wp_schema_metrics_receipt_key_next1024'],
        'file' => '/srv/wp/metrics-next1021.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 1028,
        'tables' => ['wp_schema_audit_log_next1028'],
        'indexes' => ['wp_schema_audit_log_key_next1028'],
        'file' => '/srv/wp/audit-next1021.sqlite',
    ],
];

$statements10211036 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next1020 INDEXED BY wp_navigation_rule_locale_publish_final_key_next1020 WHERE nav_key = ?', 'active' => true],
    ['name' => 'temp-shadow-reader', 'sql' => 'SELECT shadow_id FROM temp.wp_import_stage_shadow_next1021 INDEXED BY wp_import_stage_shadow_key_next1021 WHERE shadow_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next1016 INDEXED BY wp_schema_review_receipt_key_next1016 WHERE review_key = ?'],
    ['name' => 'publish-writer', 'sql' => 'UPDATE publish.wp_schema_publish_final_next1018 INDEXED BY wp_schema_publish_final_key_next1018 SET accepted = 1 WHERE publish_key = ?', 'active' => true],
    ['name' => 'queue-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_dispatch_next1021 INDEXED BY wp_job_retry_dispatch_key_next1021 WHERE job_key = ?'],
    ['name' => 'metrics-reader', 'sql' => 'SELECT metric_id FROM metrics.wp_schema_metrics_receipt_next1024 INDEXED BY wp_schema_metrics_receipt_key_next1024 WHERE metric_key = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_log_next1028 INDEXED BY wp_schema_audit_log_key_next1028 WHERE audit_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_final_next1036 INDEXED BY wp_schema_archive_final_key_next1036 WHERE archive_key = ?'],
];

$plan10211036 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext10211036(
    $schemas ?? $schemas10211036,
    $statements ?? $statements10211036,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next1021-1036 extends next1005-1020 handoff'] = static function (TestRunner $t) use ($plan10211036): void {
    $result = $plan10211036([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_stage_shadow_next1021'],
        ['op' => 'rename_index', 'schema' => 'review', 'from' => 'wp_schema_review_receipt_key_next1016', 'to' => 'wp_schema_review_receipt_key_next1026'],
        ['op' => 'rename_table', 'schema' => 'publish', 'from' => 'wp_schema_publish_final_next1018', 'to' => 'wp_schema_publish_final_next1030'],
        ['op' => 'drop_index', 'schema' => 'queue', 'index' => 'wp_job_retry_dispatch_key_next1021'],
        ['op' => 'wal_commit', 'schema' => 'metrics', 'schema_cookie' => 1032, 'table' => 'wp_schema_metrics_receipt_next1032', 'indexes' => ['wp_schema_metrics_receipt_key_next1032'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'audit'],
        ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 1036, 'tables' => ['wp_schema_archive_final_next1036'], 'indexes' => ['wp_schema_archive_final_key_next1036'], 'file' => '/srv/wp/archive-next1036.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 1036, 'table' => 'wp_navigation_rule_locale_publish_final_next1036', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next1036'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 1021, 'table' => 'wp_schema_archive_uncommitted_next1021', 'indexes' => ['wp_schema_archive_uncommitted_key_next1021'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next1021-1036', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next1021', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next1036', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next1005', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next1020', $result['dependencies'][31]);
    $t->same(8, $result['event_count']);
    $t->same(1036, $result['schema_cookies_next']['main']);
    $t->same(1009, $result['schema_cookies_next']['temp']);
    $t->same(1017, $result['schema_cookies_next']['review']);
    $t->same(1019, $result['schema_cookies_next']['publish']);
    $t->same(924, $result['schema_cookies_next']['queue']);
    $t->same(1032, $result['schema_cookies_next']['metrics']);
    $t->same(1036, $result['schema_cookies_next']['archive']);
    $t->same(false, isset($result['schema_cookies_next']['audit']));
    $t->same(['main-final-reader', 'publish-writer'], $result['active_current_snapshot_statements']);
    $t->same(['publish-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['temp-shadow-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['review-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['publish-writer']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['audit-reader']['schema_transitions'][0]['next_found']);
    $t->same('archive', $result['statements']['archive-reader']['schema_transitions'][0]['next_schema']);
    $t->same([], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next1021-1036 ignores uncommitted scratch detach'] = static function (TestRunner $t) use ($plan10211036): void {
    $result = $plan10211036([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 1021, 'tables' => ['wp_schema_scratch_next1021'], 'indexes' => ['wp_schema_scratch_key_next1021'], 'file' => '/srv/wp/scratch-next1021.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 1022, 'table' => 'wp_schema_scratch_meta_next1022', 'indexes' => ['wp_schema_scratch_meta_key_next1022'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'audit', 'metrics', 'publish', 'queue', 'review'], $result['search_order_next']);
};

return $tests;
