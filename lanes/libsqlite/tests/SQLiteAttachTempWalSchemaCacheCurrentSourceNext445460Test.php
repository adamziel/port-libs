<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas445460 = [
    'main' => [
        'schema_cookie' => 430,
        'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_users', 'wp_global_styles', 'wp_navigation_menus', 'wp_navigation_rules_next413', 'wp_navigation_sitemaps_next429'],
        'indexes' => ['wp_options_name', 'wp_posts_type_status_next293', 'wp_postmeta_key', 'wp_users_login_next308', 'wp_global_styles_slug', 'wp_navigation_menus_slug', 'wp_navigation_rules_slug_next413', 'wp_navigation_sitemaps_slug_next429'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 430, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 444,
        'tables' => ['wp_theme_stage', 'wp_theme_stage_preview', 'wp_theme_stage_publish', 'wp_theme_stage_archive', 'wp_theme_stage_diff', 'wp_theme_stage_publish_queue', 'wp_theme_stage_publish_lock'],
        'indexes' => ['wp_theme_stage_stylesheet_next350', 'wp_theme_stage_preview_key_next366', 'wp_theme_stage_publish_key_next382', 'wp_theme_stage_archive_key_next398', 'wp_theme_stage_diff_key_next414', 'wp_theme_stage_publish_queue_key_next430', 'wp_theme_stage_publish_lock_key'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 442,
        'tables' => ['wp_eventmeta', 'wp_event_archive', 'wp_event_rollup_next362', 'wp_event_allocation_next442'],
        'indexes' => ['wp_eventmeta_key_next313', 'wp_event_allocation_day_next442'],
        'file' => '/srv/wp/analytics-next445.sqlite',
    ],
    'media' => [
        'schema_cookie' => 343,
        'tables' => ['wp_media', 'wp_media_metadata_next387'],
        'indexes' => ['wp_media_mime_next434'],
        'file' => '/srv/wp/media-next445.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 395,
        'tables' => ['wp_job_queue', 'wp_job_claims', 'wp_job_history', 'wp_job_deadletter', 'wp_job_retries', 'wp_job_retry_notes', 'wp_job_retry_audit_next435'],
        'indexes' => ['wp_job_queue_token', 'wp_job_claims_token_next323', 'wp_job_history_finished_next339', 'wp_job_deadletter_reason_next360', 'wp_job_retries_job_next376', 'wp_job_retry_notes_job_next392', 'wp_job_retry_audit_job_next440'],
        'file' => '/srv/wp/queue-next445.sqlite',
    ],
    'campaign' => [
        'schema_cookie' => 436,
        'tables' => ['wp_campaign_archive_next404', 'wp_campaign_rollup_next420', 'wp_campaign_attribution_next436'],
        'indexes' => ['wp_campaigns_slug', 'wp_campaign_archive_slug_next404', 'wp_campaign_rollup_slug_next420', 'wp_campaign_attribution_slug_next436'],
        'file' => '/srv/wp/campaign-next445.sqlite',
    ],
];

$statements445460 = [
    ['name' => 'sitemap-reader', 'sql' => 'SELECT sitemap_id FROM main.wp_navigation_sitemaps_next429 INDEXED BY wp_navigation_sitemaps_slug_next429 WHERE slug = ?', 'active' => true],
    ['name' => 'publish-lock-reader', 'sql' => 'SELECT lock_id FROM temp.wp_theme_stage_publish_lock INDEXED BY wp_theme_stage_publish_lock_key WHERE cache_key = ?', 'active' => true],
    ['name' => 'allocation-reader', 'sql' => 'SELECT allocation_id FROM analytics.wp_event_allocation_next442 INDEXED BY wp_event_allocation_day_next442 WHERE day = ?'],
    ['name' => 'media-mime-writer', 'sql' => 'UPDATE media.wp_media INDEXED BY wp_media_mime_next434 SET mime_type = ? WHERE media_id = ?'],
    ['name' => 'queue-audit-reader', 'sql' => 'SELECT audit_id FROM queue.wp_job_retry_audit_next435 INDEXED BY wp_job_retry_audit_job_next440 WHERE job_id = ?', 'active' => true],
    ['name' => 'campaign-attribution-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaign_attribution_next436 INDEXED BY wp_campaign_attribution_slug_next436 WHERE slug = ?'],
    ['name' => 'segments-reader', 'sql' => 'SELECT segment_id FROM segments.wp_segments INDEXED BY wp_segments_slug WHERE slug = ?'],
    ['name' => 'ledger-writer', 'sql' => 'UPDATE ledger.wp_sync_ledger INDEXED BY wp_sync_ledger_status SET status = ? WHERE status = ?'],
];

$plan445460 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext445460(
    $schemas ?? $schemas445460,
    $statements ?? $statements445460,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next445-460 extends next429-444'] = static function (TestRunner $t) use ($plan445460): void {
    $result = $plan445460([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 445, 'table' => 'wp_navigation_history_next445', 'indexes' => ['wp_navigation_history_slug_next445'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_lock_key', 'to' => 'wp_theme_stage_publish_lock_key_next446'],
        ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_allocation_next442'],
        ['op' => 'attach', 'schema' => 'segments', 'schema_cookie' => 111, 'tables' => ['wp_segments'], 'indexes' => ['wp_segments_slug'], 'file' => '/srv/wp/segments-next448.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'segments', 'schema_cookie' => 449, 'table' => 'wp_segment_rules_next449', 'indexes' => ['wp_segment_rules_slug_next449'], 'commit' => true],
        ['op' => 'drop_index', 'schema' => 'media', 'index' => 'wp_media_mime_next434'],
        ['op' => 'rename_table', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_next435', 'to' => 'wp_job_retry_audit_next451'],
        ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 452, 'table' => 'wp_campaign_budget_next452', 'indexes' => ['wp_campaign_budget_slug_next452'], 'commit' => true],
        ['op' => 'attach', 'schema' => 'ledger', 'schema_cookie' => 113, 'tables' => ['wp_sync_ledger'], 'indexes' => ['wp_sync_ledger_status'], 'file' => '/srv/wp/ledger-next453.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'ledger', 'schema_cookie' => 454, 'table' => 'wp_sync_ledger_meta', 'indexes' => ['wp_sync_ledger_meta_key'], 'commit' => false],
        ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_navigation_rules_next413'],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_job_next440', 'to' => 'wp_job_retry_audit_job_next456'],
        ['op' => 'detach', 'schema' => 'segments'],
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 458, 'table' => 'wp_event_waitlist_next458', 'indexes' => ['wp_event_waitlist_day_next458'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'ledger'],
        ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 460, 'table' => 'wp_theme_stage_publish_receipts', 'indexes' => ['wp_theme_stage_publish_receipts_key'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next445-460', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next445', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next460', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next444', $result['dependencies'][31]);
    $t->same(15, $result['event_count']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['changed_schemas']);
    $t->same(446, $result['schema_cookies_next']['main']);
    $t->same(460, $result['schema_cookies_next']['temp']);
    $t->same(458, $result['schema_cookies_next']['analytics']);
    $t->same(344, $result['schema_cookies_next']['media']);
    $t->same(397, $result['schema_cookies_next']['queue']);
    $t->same(452, $result['schema_cookies_next']['campaign']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['search_order_next']);
    $t->same(['sitemap-reader', 'publish-lock-reader', 'queue-audit-reader'], $result['active_current_snapshot_statements']);
    $t->same(['media-mime-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['publish-lock-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['allocation-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['media-mime-writer']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-audit-reader']['schema_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['segments-reader']['schema_transitions'][0]['next_schema']);
    $t->same('__detached__', $result['statements']['ledger-writer']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next445-460 ignores uncommitted staging churn'] = static function (TestRunner $t) use ($plan445460): void {
    $result = $plan445460([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 19, 'tables' => ['wp_scratch'], 'indexes' => ['wp_scratch_key'], 'file' => '/srv/wp/scratch-next445.sqlite'],
        ['op' => 'detach', 'schema' => 'scratch'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 447, 'table' => 'wp_pending_next445', 'indexes' => ['wp_pending_next445_key'], 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 447, 'table' => 'wp_pending_next445', 'indexes' => ['wp_pending_next445_key'], 'commit' => false],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['search_order_next']);
};

return $tests;
