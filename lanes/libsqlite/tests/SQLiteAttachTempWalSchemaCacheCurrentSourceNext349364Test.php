<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas349364 = [
    'main' => [
        'schema_cookie' => 334,
        'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_users', 'wp_term_taxonomy', 'wp_site_health', 'wp_block_patterns', 'wp_global_styles'],
        'indexes' => ['wp_options_name', 'wp_posts_type_status_next293', 'wp_postmeta_key', 'wp_users_login_next308', 'wp_term_taxonomy_taxonomy', 'wp_site_health_status', 'wp_block_patterns_slug', 'wp_global_styles_slug'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 334, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 196,
        'tables' => ['wp_theme_stage'],
        'indexes' => ['wp_theme_stage_stylesheet_next334'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 348,
        'tables' => ['wp_eventmeta', 'wp_event_archive'],
        'indexes' => ['wp_eventmeta_key_next313', 'wp_event_archive_day'],
        'file' => '/srv/wp/analytics-next349.sqlite',
    ],
    'campaign' => [
        'schema_cookie' => 344,
        'tables' => ['wp_campaignmeta', 'wp_campaign_audit', 'wp_campaign_segments'],
        'indexes' => ['wp_campaigns_slug', 'wp_campaignmeta_key_next329', 'wp_campaign_segments_slug'],
        'file' => '/srv/wp/campaign-next349.sqlite',
    ],
    'media' => [
        'schema_cookie' => 337,
        'tables' => ['wp_media', 'wp_media_meta'],
        'indexes' => ['wp_media_mime', 'wp_media_meta_key'],
        'file' => '/srv/wp/media-next349.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 347,
        'tables' => ['wp_job_queue', 'wp_job_claims', 'wp_job_history', 'wp_job_deadletter'],
        'indexes' => ['wp_job_queue_token', 'wp_job_claims_token_next323', 'wp_job_history_finished_next339', 'wp_job_deadletter_reason'],
        'file' => '/srv/wp/queue-next349.sqlite',
    ],
];

$statements349364 = [
    ['name' => 'global-style-reader', 'sql' => 'SELECT style_id FROM main.wp_global_styles INDEXED BY wp_global_styles_slug WHERE slug = ?', 'active' => true],
    ['name' => 'theme-stage-reader', 'sql' => 'SELECT stylesheet FROM temp.wp_theme_stage INDEXED BY wp_theme_stage_stylesheet_next334 WHERE stylesheet = ?', 'active' => true],
    ['name' => 'analytics-archive-reader', 'sql' => 'SELECT day FROM analytics.wp_event_archive INDEXED BY wp_event_archive_day WHERE day >= ?'],
    ['name' => 'campaign-segment-reader', 'sql' => 'SELECT segment_id FROM campaign.wp_campaign_segments INDEXED BY wp_campaign_segments_slug WHERE slug = ?', 'active' => true],
    ['name' => 'media-meta-writer', 'sql' => 'UPDATE media.wp_media_meta INDEXED BY wp_media_meta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'queue-deadletter-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_deadletter INDEXED BY wp_job_deadletter_reason WHERE reason = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_audit_log INDEXED BY wp_audit_log_action WHERE action = ?'],
    ['name' => 'staging-writer', 'sql' => 'UPDATE staging.wp_stage_items INDEXED BY wp_stage_items_status SET status = ? WHERE status = ?'],
];

$plan349364 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext349364(
    $schemas ?? $schemas349364,
    $statements ?? $statements349364,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next349-364 extends next333-348'] = static function (TestRunner $t) use ($plan349364): void {
    $result = $plan349364([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 349, 'table' => 'wp_navigation_menus', 'indexes' => ['wp_navigation_menus_slug'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_stylesheet_next334', 'to' => 'wp_theme_stage_stylesheet_next350'],
        ['op' => 'drop_index', 'schema' => 'analytics', 'index' => 'wp_event_archive_day'],
        ['op' => 'attach', 'schema' => 'audit', 'schema_cookie' => 52, 'tables' => ['wp_audit_log'], 'indexes' => ['wp_audit_log_action'], 'file' => '/srv/wp/audit-next352.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'audit', 'schema_cookie' => 353, 'table' => 'wp_audit_meta', 'indexes' => ['wp_audit_meta_key'], 'commit' => true],
        ['op' => 'drop_table', 'schema' => 'campaign', 'table' => 'wp_campaign_segments'],
        ['op' => 'rename_table', 'schema' => 'media', 'from' => 'wp_media_meta', 'to' => 'wp_media_metadata_next355'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 356, 'table' => 'wp_job_retries', 'indexes' => ['wp_job_retries_job'], 'commit' => true],
        ['op' => 'attach', 'schema' => 'staging', 'schema_cookie' => 57, 'tables' => ['wp_stage_items'], 'indexes' => ['wp_stage_items_status'], 'file' => '/srv/wp/staging-next357.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'staging', 'schema_cookie' => 358, 'table' => 'wp_stage_itemmeta', 'indexes' => ['wp_stage_itemmeta_key'], 'commit' => false],
        ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_site_health'],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_deadletter_reason', 'to' => 'wp_job_deadletter_reason_next360'],
        ['op' => 'detach', 'schema' => 'audit'],
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 362, 'table' => 'wp_event_rollup_next362', 'indexes' => ['wp_event_rollup_day_next362'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'staging'],
        ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 364, 'table' => 'wp_theme_stage_preview', 'indexes' => ['wp_theme_stage_preview_key'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next349-364', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next349', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next364', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next348', $result['dependencies'][31]);
    $t->same(15, $result['event_count']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['changed_schemas']);
    $t->same(350, $result['schema_cookies_next']['main']);
    $t->same(364, $result['schema_cookies_next']['temp']);
    $t->same(362, $result['schema_cookies_next']['analytics']);
    $t->same(345, $result['schema_cookies_next']['campaign']);
    $t->same(338, $result['schema_cookies_next']['media']);
    $t->same(357, $result['schema_cookies_next']['queue']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['search_order_next']);
    $t->same(['global-style-reader', 'theme-stage-reader', 'campaign-segment-reader'], $result['active_current_snapshot_statements']);
    $t->same(['media-meta-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['theme-stage-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['analytics-archive-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['campaign-segment-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['media-meta-writer']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-deadletter-reader']['index_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['audit-reader']['schema_transitions'][0]['next_schema']);
    $t->same('__detached__', $result['statements']['staging-writer']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next349-364 duplicate uncommitted churn stays stable'] = static function (TestRunner $t) use ($plan349364): void {
    $result = $plan349364([
        ['op' => 'attach', 'schema' => 'preview', 'schema_cookie' => 4, 'tables' => ['wp_preview'], 'indexes' => ['wp_preview_key'], 'file' => '/srv/wp/preview-next349.sqlite'],
        ['op' => 'detach', 'schema' => 'preview'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 351, 'table' => 'wp_unpublished_next349', 'indexes' => ['wp_unpublished_next349_key'], 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 351, 'table' => 'wp_unpublished_next349', 'indexes' => ['wp_unpublished_next349_key'], 'commit' => false],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['search_order_next']);
};

return $tests;
