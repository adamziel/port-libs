<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas541556 = [
    'main' => [
        'schema_cookie' => 525,
        'tables' => ['wp_options', 'wp_navigation_preview_next523', 'wp_navigation_rule_next525'],
        'indexes' => ['wp_options_name', 'wp_navigation_preview_slug_next523', 'wp_navigation_rule_slug_next525'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 525, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 522,
        'tables' => ['wp_theme_stage_publish_retries_next529'],
        'indexes' => ['wp_theme_stage_publish_retries_key_next521'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 519,
        'tables' => ['wp_event_capacity_bucket_next518'],
        'indexes' => ['wp_event_capacity_bucket_day_next518'],
        'file' => '/srv/wp/analytics-next541.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 528,
        'tables' => ['wp_job_retry_audit_next514', 'wp_job_retry_window_next528'],
        'indexes' => ['wp_job_retry_audit_job_next520', 'wp_job_retry_window_job_next528'],
        'file' => '/srv/wp/queue-next541.sqlite',
    ],
    'campaign' => [
        'schema_cookie' => 540,
        'tables' => ['wp_campaign_restore_next524', 'wp_campaign_restore_meta_next540'],
        'indexes' => ['wp_campaign_restore_slug_next526', 'wp_campaign_restore_meta_key_next540'],
        'file' => '/srv/wp/campaign-next541.sqlite',
    ],
    'search' => [
        'schema_cookie' => 538,
        'tables' => ['wp_search_queue_next537', 'wp_search_queue_meta_next538'],
        'indexes' => ['wp_search_queue_slug_next537', 'wp_search_queue_meta_key_next538'],
        'file' => '/srv/wp/search-next541.sqlite',
    ],
];

$statements541556 = [
    ['name' => 'nav-rule-reader', 'sql' => 'SELECT rule_id FROM main.wp_navigation_rule_next525 INDEXED BY wp_navigation_rule_slug_next525 WHERE slug = ?', 'active' => true],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next529 INDEXED BY wp_theme_stage_publish_retries_key_next521 WHERE cache_key = ?', 'active' => true],
    ['name' => 'queue-window-writer', 'sql' => 'UPDATE queue.wp_job_retry_window_next528 INDEXED BY wp_job_retry_window_job_next528 SET attempts = attempts + 1 WHERE job_id = ?'],
    ['name' => 'campaign-meta-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaign_restore_meta_next540 INDEXED BY wp_campaign_restore_meta_key_next540 WHERE meta_key = ?'],
    ['name' => 'search-queue-writer', 'sql' => 'UPDATE search.wp_search_queue_next537 INDEXED BY wp_search_queue_slug_next537 SET touched = 1 WHERE slug = ?'],
    ['name' => 'media-cache-reader', 'sql' => 'SELECT media_id FROM media.wp_media_derivative_next548 INDEXED BY wp_media_derivative_slug_next548 WHERE slug = ?'],
    ['name' => 'audit-log-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_next553 INDEXED BY wp_schema_audit_key_next553 WHERE audit_key = ?'],
];

$plan541556 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas541556,
    $statements ?? $statements541556,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next541-556 extends next525-540 handoff'] = static function (TestRunner $t) use ($plan541556): void {
    $result = $plan541556([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 541, 'table' => 'wp_navigation_rule_locale_next541', 'indexes' => ['wp_navigation_rule_locale_slug_next541'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_retries_key_next521', 'to' => 'wp_theme_stage_publish_retries_key_next542'],
        ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_capacity_bucket_next518'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 544, 'table' => 'wp_job_retry_checkpoint_next544', 'indexes' => ['wp_job_retry_checkpoint_job_next544'], 'commit' => true],
        ['op' => 'rename_table', 'schema' => 'campaign', 'from' => 'wp_campaign_restore_meta_next540', 'to' => 'wp_campaign_restore_meta_next545'],
        ['op' => 'attach', 'schema' => 'media', 'schema_cookie' => 548, 'tables' => ['wp_media_derivative_next548'], 'indexes' => ['wp_media_derivative_slug_next548'], 'file' => '/srv/wp/media-next548.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'media', 'schema_cookie' => 549, 'table' => 'wp_media_derivative_meta_next549', 'indexes' => ['wp_media_derivative_meta_key_next549'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'media'],
        ['op' => 'drop_index', 'schema' => 'search', 'index' => 'wp_search_queue_slug_next537'],
        ['op' => 'attach', 'schema' => 'audit', 'schema_cookie' => 553, 'tables' => ['wp_schema_audit_next553'], 'indexes' => ['wp_schema_audit_key_next553'], 'file' => '/srv/wp/audit-next553.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'audit', 'schema_cookie' => 554, 'table' => 'wp_schema_audit_meta_next554', 'indexes' => ['wp_schema_audit_meta_key_next554'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 555, 'table' => 'wp_job_retry_checkpoint_shadow_next555', 'indexes' => ['wp_job_retry_checkpoint_shadow_job_next555'], 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 556, 'table' => 'wp_navigation_rule_locale_meta_next556', 'indexes' => ['wp_navigation_rule_locale_meta_key_next556'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(12, $result['event_count']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'campaign', 'queue', 'search'], $result['changed_schemas']);
    $t->same(556, $result['schema_cookies_next']['main']);
    $t->same(523, $result['schema_cookies_next']['temp']);
    $t->same(520, $result['schema_cookies_next']['analytics']);
    $t->same(544, $result['schema_cookies_next']['queue']);
    $t->same(541, $result['schema_cookies_next']['campaign']);
    $t->same(539, $result['schema_cookies_next']['search']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'campaign', 'queue', 'search'], $result['search_order_next']);
    $t->same(['nav-rule-reader', 'temp-retry-reader'], $result['active_current_snapshot_statements']);
    $t->same(['queue-window-writer', 'search-queue-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['temp-retry-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['campaign-meta-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['search-queue-writer']['index_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['media-cache-reader']['schema_transitions'][0]['next_schema']);
    $t->same('audit', $result['statements']['audit-log-reader']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next541-556 keeps clean handoff stable'] = static function (TestRunner $t) use ($plan541556): void {
    $result = $plan541556([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 541, 'tables' => ['wp_scratch_next541'], 'indexes' => ['wp_scratch_key_next541'], 'file' => '/srv/wp/scratch-next541.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'scratch', 'schema_cookie' => 542, 'table' => 'wp_scratch_pending_next542', 'indexes' => ['wp_scratch_pending_key_next542'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'queue', 'search'], $result['search_order_next']);
};

return $tests;
