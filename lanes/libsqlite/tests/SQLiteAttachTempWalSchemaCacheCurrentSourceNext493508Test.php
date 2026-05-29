<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas493508 = [
    'main' => [
        'schema_cookie' => 477,
        'tables' => ['wp_options', 'wp_navigation_rewrite_next477'],
        'indexes' => ['wp_options_name', 'wp_navigation_rewrite_slug_next477'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 477, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 489,
        'tables' => ['wp_theme_stage_publish_locks_next489', 'wp_theme_stage_publish_receipts'],
        'indexes' => ['wp_theme_stage_publish_locks_key_next489', 'wp_theme_stage_publish_receipts_key_next462'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 486,
        'tables' => ['wp_event_capacity_window_next486'],
        'indexes' => ['wp_event_capacity_window_day_next486'],
        'file' => '/srv/wp/analytics-next493.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 401,
        'tables' => ['wp_job_retry_audit_next482'],
        'indexes' => ['wp_job_retry_audit_job_next488'],
        'file' => '/srv/wp/queue-next493.sqlite',
    ],
    'campaign' => [
        'schema_cookie' => 492,
        'tables' => ['wp_campaign_experiments_next492'],
        'indexes' => ['wp_campaign_experiments_slug_next492'],
        'file' => '/srv/wp/campaign-next493.sqlite',
    ],
];

$statements493508 = [
    ['name' => 'rewrite-reader', 'sql' => 'SELECT rule_id FROM main.wp_navigation_rewrite_next477 INDEXED BY wp_navigation_rewrite_slug_next477 WHERE slug = ?', 'active' => true],
    ['name' => 'lock-reader', 'sql' => 'SELECT lock_id FROM temp.wp_theme_stage_publish_locks_next489 INDEXED BY wp_theme_stage_publish_locks_key_next489 WHERE cache_key = ?', 'active' => true],
    ['name' => 'capacity-window-reader', 'sql' => 'SELECT window_id FROM analytics.wp_event_capacity_window_next486 INDEXED BY wp_event_capacity_window_day_next486 WHERE day = ?'],
    ['name' => 'queue-writer', 'sql' => 'UPDATE queue.wp_job_retry_audit_next482 INDEXED BY wp_job_retry_audit_job_next488 SET status = ? WHERE job_id = ?'],
    ['name' => 'experiment-reader', 'sql' => 'SELECT experiment_id FROM campaign.wp_campaign_experiments_next492 INDEXED BY wp_campaign_experiments_slug_next492 WHERE slug = ?'],
    ['name' => 'inventory-reader', 'sql' => 'SELECT sku_id FROM inventory.wp_inventory_cache_next496 INDEXED BY wp_inventory_cache_sku_next496 WHERE sku = ?'],
    ['name' => 'metrics-writer', 'sql' => 'UPDATE metrics.wp_metric_rollups_next501 INDEXED BY wp_metric_rollups_slug_next501 SET enabled = ? WHERE slug = ?'],
];

$plan493508 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext493508(
    $schemas ?? $schemas493508,
    $statements ?? $statements493508,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next493-508 extends next477-492'] = static function (TestRunner $t) use ($plan493508): void {
    $result = $plan493508([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 493, 'table' => 'wp_navigation_rewrite_meta_next493', 'indexes' => ['wp_navigation_rewrite_meta_key_next493'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_locks_key_next489', 'to' => 'wp_theme_stage_publish_locks_key_next494'],
        ['op' => 'drop_index', 'schema' => 'analytics', 'index' => 'wp_event_capacity_window_day_next486'],
        ['op' => 'attach', 'schema' => 'inventory', 'schema_cookie' => 496, 'tables' => ['wp_inventory_cache_next496'], 'indexes' => ['wp_inventory_cache_sku_next496'], 'file' => '/srv/wp/inventory-next496.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'inventory', 'schema_cookie' => 497, 'table' => 'wp_inventory_cache_meta_next497', 'indexes' => ['wp_inventory_cache_meta_key_next497'], 'commit' => true],
        ['op' => 'rename_table', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_next482', 'to' => 'wp_job_retry_audit_next498'],
        ['op' => 'drop_table', 'schema' => 'campaign', 'table' => 'wp_campaign_experiments_next492'],
        ['op' => 'attach', 'schema' => 'metrics', 'schema_cookie' => 500, 'tables' => ['wp_metric_rollups_next501'], 'indexes' => ['wp_metric_rollups_slug_next501'], 'file' => '/srv/wp/metrics-next500.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'metrics', 'schema_cookie' => 501, 'table' => 'wp_metric_rollup_meta_next501', 'indexes' => ['wp_metric_rollup_meta_key_next501'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 502, 'table' => 'wp_event_capacity_slice_next502', 'indexes' => ['wp_event_capacity_slice_day_next502'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'inventory'],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_job_next488', 'to' => 'wp_job_retry_audit_job_next504'],
        ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 505, 'table' => 'wp_theme_stage_publish_errors_next505', 'indexes' => ['wp_theme_stage_publish_errors_key_next505'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'metrics'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 507, 'table' => 'wp_navigation_shadow_next507', 'indexes' => ['wp_navigation_shadow_slug_next507'], 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 508, 'table' => 'wp_campaign_archive_next508', 'indexes' => ['wp_campaign_archive_slug_next508'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next493-508', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next493', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next508', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next492', $result['dependencies'][31]);
    $t->same(15, $result['event_count']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'queue'], $result['changed_schemas']);
    $t->same(493, $result['schema_cookies_next']['main']);
    $t->same(505, $result['schema_cookies_next']['temp']);
    $t->same(502, $result['schema_cookies_next']['analytics']);
    $t->same(403, $result['schema_cookies_next']['queue']);
    $t->same(508, $result['schema_cookies_next']['campaign']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'queue'], $result['search_order_next']);
    $t->same(['rewrite-reader', 'lock-reader'], $result['active_current_snapshot_statements']);
    $t->same(['queue-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['lock-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['capacity-window-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-writer']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['experiment-reader']['schema_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['inventory-reader']['schema_transitions'][0]['next_schema']);
    $t->same('__detached__', $result['statements']['metrics-writer']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next493-508 ignores detached uncommitted sidecar'] = static function (TestRunner $t) use ($plan493508): void {
    $result = $plan493508([
        ['op' => 'attach', 'schema' => 'preview', 'schema_cookie' => 9, 'tables' => ['wp_preview_cache'], 'indexes' => ['wp_preview_cache_key'], 'file' => '/srv/wp/preview-next493.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'preview', 'schema_cookie' => 494, 'table' => 'wp_preview_pending', 'indexes' => ['wp_preview_pending_key'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'preview'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'queue'], $result['search_order_next']);
};

return $tests;
