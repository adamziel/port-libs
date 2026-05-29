<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas477492 = [
    'main' => [
        'schema_cookie' => 461,
        'tables' => ['wp_options', 'wp_navigation_history_next445', 'wp_navigation_redirects_next461'],
        'indexes' => ['wp_options_name', 'wp_navigation_history_slug_next445', 'wp_navigation_redirects_slug_next461'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 461, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 473,
        'tables' => ['wp_theme_stage_publish_lock', 'wp_theme_stage_publish_receipts', 'wp_theme_stage_publish_errors_next473'],
        'indexes' => ['wp_theme_stage_publish_lock_key_next446', 'wp_theme_stage_publish_receipts_key_next462', 'wp_theme_stage_publish_errors_key_next473'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 470,
        'tables' => ['wp_event_waitlist_next458', 'wp_event_capacity_next470'],
        'indexes' => ['wp_event_capacity_day_next470'],
        'file' => '/srv/wp/analytics-next477.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 399,
        'tables' => ['wp_job_queue', 'wp_job_retry_audit_next466'],
        'indexes' => ['wp_job_queue_token', 'wp_job_retry_audit_job_next472'],
        'file' => '/srv/wp/queue-next477.sqlite',
    ],
    'campaign' => [
        'schema_cookie' => 476,
        'tables' => ['wp_campaign_attribution_next436', 'wp_campaign_variants_next476'],
        'indexes' => ['wp_campaign_attribution_slug_next436', 'wp_campaign_variants_slug_next476'],
        'file' => '/srv/wp/campaign-next477.sqlite',
    ],
];

$statements477492 = [
    ['name' => 'redirect-reader', 'sql' => 'SELECT redirect_id FROM main.wp_navigation_redirects_next461 INDEXED BY wp_navigation_redirects_slug_next461 WHERE slug = ?', 'active' => true],
    ['name' => 'error-reader', 'sql' => 'SELECT error_id FROM temp.wp_theme_stage_publish_errors_next473 INDEXED BY wp_theme_stage_publish_errors_key_next473 WHERE cache_key = ?', 'active' => true],
    ['name' => 'capacity-reader', 'sql' => 'SELECT event_id FROM analytics.wp_event_capacity_next470 INDEXED BY wp_event_capacity_day_next470 WHERE day = ?'],
    ['name' => 'queue-writer', 'sql' => 'UPDATE queue.wp_job_retry_audit_next466 INDEXED BY wp_job_retry_audit_job_next472 SET status = ? WHERE job_id = ?'],
    ['name' => 'variant-reader', 'sql' => 'SELECT variant_id FROM campaign.wp_campaign_variants_next476 INDEXED BY wp_campaign_variants_slug_next476 WHERE slug = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT row_id FROM audit.wp_schema_audit_next480 INDEXED BY wp_schema_audit_key_next480 WHERE cache_key = ?'],
    ['name' => 'segment-writer', 'sql' => 'UPDATE segment.wp_segment_rollups_next485 INDEXED BY wp_segment_rollups_slug_next485 SET enabled = ? WHERE slug = ?'],
];

$plan477492 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas477492,
    $statements ?? $statements477492,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next477-492 extends next461-476'] = static function (TestRunner $t) use ($plan477492): void {
    $result = $plan477492([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 477, 'table' => 'wp_navigation_rewrite_next477', 'indexes' => ['wp_navigation_rewrite_slug_next477'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_errors_key_next473', 'to' => 'wp_theme_stage_publish_errors_key_next478'],
        ['op' => 'drop_index', 'schema' => 'analytics', 'index' => 'wp_event_capacity_day_next470'],
        ['op' => 'attach', 'schema' => 'audit', 'schema_cookie' => 480, 'tables' => ['wp_schema_audit_next480'], 'indexes' => ['wp_schema_audit_key_next480'], 'file' => '/srv/wp/audit-next480.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'audit', 'schema_cookie' => 481, 'table' => 'wp_schema_audit_meta_next481', 'indexes' => ['wp_schema_audit_meta_key_next481'], 'commit' => true],
        ['op' => 'rename_table', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_next466', 'to' => 'wp_job_retry_audit_next482'],
        ['op' => 'drop_table', 'schema' => 'campaign', 'table' => 'wp_campaign_variants_next476'],
        ['op' => 'attach', 'schema' => 'segment', 'schema_cookie' => 484, 'tables' => ['wp_segment_rollups_next485'], 'indexes' => ['wp_segment_rollups_slug_next485'], 'file' => '/srv/wp/segment-next484.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'segment', 'schema_cookie' => 485, 'table' => 'wp_segment_rollup_meta_next485', 'indexes' => ['wp_segment_rollup_meta_key_next485'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 486, 'table' => 'wp_event_capacity_window_next486', 'indexes' => ['wp_event_capacity_window_day_next486'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'audit'],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_job_next472', 'to' => 'wp_job_retry_audit_job_next488'],
        ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 489, 'table' => 'wp_theme_stage_publish_locks_next489', 'indexes' => ['wp_theme_stage_publish_locks_key_next489'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'segment'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 491, 'table' => 'wp_navigation_shadow_next491', 'indexes' => ['wp_navigation_shadow_slug_next491'], 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 492, 'table' => 'wp_campaign_experiments_next492', 'indexes' => ['wp_campaign_experiments_slug_next492'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(15, $result['event_count']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'queue'], $result['changed_schemas']);
    $t->same(477, $result['schema_cookies_next']['main']);
    $t->same(489, $result['schema_cookies_next']['temp']);
    $t->same(486, $result['schema_cookies_next']['analytics']);
    $t->same(401, $result['schema_cookies_next']['queue']);
    $t->same(492, $result['schema_cookies_next']['campaign']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'queue'], $result['search_order_next']);
    $t->same(['redirect-reader', 'error-reader'], $result['active_current_snapshot_statements']);
    $t->same(['queue-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['error-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['capacity-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-writer']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['variant-reader']['schema_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['audit-reader']['schema_transitions'][0]['next_schema']);
    $t->same('__detached__', $result['statements']['segment-writer']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next477-492 ignores detached uncommitted sidecar'] = static function (TestRunner $t) use ($plan477492): void {
    $result = $plan477492([
        ['op' => 'attach', 'schema' => 'preview', 'schema_cookie' => 9, 'tables' => ['wp_preview_cache'], 'indexes' => ['wp_preview_cache_key'], 'file' => '/srv/wp/preview-next477.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'preview', 'schema_cookie' => 478, 'table' => 'wp_preview_pending', 'indexes' => ['wp_preview_pending_key'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'preview'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'queue'], $result['search_order_next']);
};

return $tests;
