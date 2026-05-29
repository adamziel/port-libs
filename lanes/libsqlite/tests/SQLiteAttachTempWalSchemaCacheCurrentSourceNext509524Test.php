<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas509524 = [
    'main' => [
        'schema_cookie' => 493,
        'tables' => ['wp_options', 'wp_navigation_rewrite_next477', 'wp_navigation_rewrite_meta_next493'],
        'indexes' => ['wp_options_name', 'wp_navigation_rewrite_slug_next477', 'wp_navigation_rewrite_meta_key_next493'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 493, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 505,
        'tables' => ['wp_theme_stage_publish_locks_next489', 'wp_theme_stage_publish_errors_next505'],
        'indexes' => ['wp_theme_stage_publish_locks_key_next494', 'wp_theme_stage_publish_errors_key_next505'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 502,
        'tables' => ['wp_event_capacity_window_next486', 'wp_event_capacity_slice_next502'],
        'indexes' => ['wp_event_capacity_slice_day_next502'],
        'file' => '/srv/wp/analytics-next509.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 403,
        'tables' => ['wp_job_retry_audit_next498'],
        'indexes' => ['wp_job_retry_audit_job_next504'],
        'file' => '/srv/wp/queue-next509.sqlite',
    ],
    'campaign' => [
        'schema_cookie' => 508,
        'tables' => ['wp_campaign_archive_next508'],
        'indexes' => ['wp_campaign_archive_slug_next508'],
        'file' => '/srv/wp/campaign-next509.sqlite',
    ],
];

$statements509524 = [
    ['name' => 'rewrite-meta-reader', 'sql' => 'SELECT rule_id FROM main.wp_navigation_rewrite_meta_next493 INDEXED BY wp_navigation_rewrite_meta_key_next493 WHERE meta_key = ?', 'active' => true],
    ['name' => 'temp-error-reader', 'sql' => 'SELECT error_id FROM temp.wp_theme_stage_publish_errors_next505 INDEXED BY wp_theme_stage_publish_errors_key_next505 WHERE cache_key = ?', 'active' => true],
    ['name' => 'analytics-slice-reader', 'sql' => 'SELECT slice_id FROM analytics.wp_event_capacity_slice_next502 INDEXED BY wp_event_capacity_slice_day_next502 WHERE day = ?'],
    ['name' => 'queue-audit-writer', 'sql' => 'UPDATE queue.wp_job_retry_audit_next498 INDEXED BY wp_job_retry_audit_job_next504 SET status = ? WHERE job_id = ?'],
    ['name' => 'campaign-archive-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaign_archive_next508 INDEXED BY wp_campaign_archive_slug_next508 WHERE slug = ?'],
    ['name' => 'segments-reader', 'sql' => 'SELECT segment_id FROM segments.wp_segment_cache_next512 INDEXED BY wp_segment_cache_slug_next512 WHERE slug = ?'],
    ['name' => 'experiments-writer', 'sql' => 'UPDATE experiments.wp_experiment_rollups_next517 INDEXED BY wp_experiment_rollups_slug_next517 SET enabled = ? WHERE slug = ?'],
];

$plan509524 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext509524(
    $schemas ?? $schemas509524,
    $statements ?? $statements509524,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next509-524 extends next493-508'] = static function (TestRunner $t) use ($plan509524): void {
    $result = $plan509524([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 509, 'table' => 'wp_navigation_redirect_next509', 'indexes' => ['wp_navigation_redirect_slug_next509'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_errors_key_next505', 'to' => 'wp_theme_stage_publish_errors_key_next510'],
        ['op' => 'drop_index', 'schema' => 'analytics', 'index' => 'wp_event_capacity_slice_day_next502'],
        ['op' => 'attach', 'schema' => 'segments', 'schema_cookie' => 512, 'tables' => ['wp_segment_cache_next512'], 'indexes' => ['wp_segment_cache_slug_next512'], 'file' => '/srv/wp/segments-next512.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'segments', 'schema_cookie' => 513, 'table' => 'wp_segment_cache_meta_next513', 'indexes' => ['wp_segment_cache_meta_key_next513'], 'commit' => true],
        ['op' => 'rename_table', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_next498', 'to' => 'wp_job_retry_audit_next514'],
        ['op' => 'drop_table', 'schema' => 'campaign', 'table' => 'wp_campaign_archive_next508'],
        ['op' => 'attach', 'schema' => 'experiments', 'schema_cookie' => 516, 'tables' => ['wp_experiment_rollups_next517'], 'indexes' => ['wp_experiment_rollups_slug_next517'], 'file' => '/srv/wp/experiments-next516.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'experiments', 'schema_cookie' => 517, 'table' => 'wp_experiment_rollup_meta_next517', 'indexes' => ['wp_experiment_rollup_meta_key_next517'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 518, 'table' => 'wp_event_capacity_bucket_next518', 'indexes' => ['wp_event_capacity_bucket_day_next518'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'segments'],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_job_next504', 'to' => 'wp_job_retry_audit_job_next520'],
        ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 521, 'table' => 'wp_theme_stage_publish_retries_next521', 'indexes' => ['wp_theme_stage_publish_retries_key_next521'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'experiments'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 523, 'table' => 'wp_navigation_preview_next523', 'indexes' => ['wp_navigation_preview_slug_next523'], 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 524, 'table' => 'wp_campaign_restore_next524', 'indexes' => ['wp_campaign_restore_slug_next524'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next509-524', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next509', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next524', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next508', $result['dependencies'][31]);
    $t->same(15, $result['event_count']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'queue'], $result['changed_schemas']);
    $t->same(509, $result['schema_cookies_next']['main']);
    $t->same(521, $result['schema_cookies_next']['temp']);
    $t->same(518, $result['schema_cookies_next']['analytics']);
    $t->same(405, $result['schema_cookies_next']['queue']);
    $t->same(524, $result['schema_cookies_next']['campaign']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'queue'], $result['search_order_next']);
    $t->same(['rewrite-meta-reader', 'temp-error-reader'], $result['active_current_snapshot_statements']);
    $t->same(['queue-audit-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['temp-error-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['analytics-slice-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-audit-writer']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['campaign-archive-reader']['schema_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['segments-reader']['schema_transitions'][0]['next_schema']);
    $t->same('__detached__', $result['statements']['experiments-writer']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next509-524 ignores detached uncommitted sidecar'] = static function (TestRunner $t) use ($plan509524): void {
    $result = $plan509524([
        ['op' => 'attach', 'schema' => 'preview', 'schema_cookie' => 10, 'tables' => ['wp_preview_cache_next509'], 'indexes' => ['wp_preview_cache_key_next509'], 'file' => '/srv/wp/preview-next509.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'preview', 'schema_cookie' => 510, 'table' => 'wp_preview_pending_next510', 'indexes' => ['wp_preview_pending_key_next510'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'preview'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'queue'], $result['search_order_next']);
};

return $tests;
