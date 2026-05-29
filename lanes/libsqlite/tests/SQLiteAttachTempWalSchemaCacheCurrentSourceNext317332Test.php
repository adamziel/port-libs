<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas317332 = [
    'main' => [
        'schema_cookie' => 302,
        'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_comments', 'wp_users', 'wp_terms', 'wp_term_taxonomy', 'wp_site_health'],
        'indexes' => ['wp_options_name', 'wp_posts_type_status_next293', 'wp_postmeta_key', 'wp_comments_post', 'wp_users_login_next308', 'wp_terms_slug', 'wp_term_taxonomy_taxonomy', 'wp_site_health_status'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 302, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 192,
        'tables' => ['wp_import_batch', 'wp_theme_stage', 'wp_term_stage'],
        'indexes' => ['wp_import_batch_token', 'wp_theme_stage_stylesheet_next302', 'wp_term_stage_slug_next287'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 80,
        'tables' => ['wp_events', 'wp_eventmeta'],
        'indexes' => ['wp_events_name', 'wp_eventmeta_key_next313'],
        'file' => '/srv/wp/analytics-next317.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 282,
        'tables' => ['wp_audit_log', 'wp_auditmeta'],
        'indexes' => ['wp_audit_log_action', 'wp_auditmeta_key'],
        'file' => '/srv/wp/audit-next317.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 307,
        'tables' => ['wp_job_queue', 'wp_job_claims'],
        'indexes' => ['wp_job_queue_token', 'wp_job_claims_token'],
        'file' => '/srv/wp/queue-next317.sqlite',
    ],
    'campaign' => [
        'schema_cookie' => 316,
        'tables' => ['wp_campaigns', 'wp_campaignmeta'],
        'indexes' => ['wp_campaigns_slug', 'wp_campaignmeta_key'],
        'file' => '/srv/wp/campaign-next317.sqlite',
    ],
];

$statements317332 = [
    ['name' => 'site-health-reader', 'sql' => 'SELECT status FROM main.wp_site_health INDEXED BY wp_site_health_status WHERE check_name = ?', 'active' => true],
    ['name' => 'temp-import-reader', 'sql' => 'SELECT batch_id FROM temp.wp_import_batch INDEXED BY wp_import_batch_token WHERE token = ?', 'active' => true],
    ['name' => 'analytics-event-reader', 'sql' => 'SELECT event_id FROM analytics.wp_events INDEXED BY wp_events_name WHERE event_name = ?'],
    ['name' => 'analytics-meta-writer', 'sql' => 'UPDATE analytics.wp_eventmeta INDEXED BY wp_eventmeta_key_next313 SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'audit-meta-reader', 'sql' => 'SELECT meta_value FROM audit.wp_auditmeta INDEXED BY wp_auditmeta_key WHERE meta_key = ?', 'active' => true],
    ['name' => 'queue-claim-reader', 'sql' => 'SELECT claim_id FROM queue.wp_job_claims INDEXED BY wp_job_claims_token WHERE token = ?'],
    ['name' => 'campaign-writer', 'sql' => 'UPDATE campaign.wp_campaignmeta INDEXED BY wp_campaignmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'media-reader', 'sql' => 'SELECT item_id FROM media.wp_media INDEXED BY wp_media_mime WHERE mime_type = ?'],
    ['name' => 'options-writer', 'sql' => 'UPDATE wp_options INDEXED BY wp_options_name SET option_value = ? WHERE option_name = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT post_id FROM archive.wp_archived_posts INDEXED BY wp_archived_posts_date WHERE post_date >= ?'],
];

$plan317332 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext317332(
    $schemas ?? $schemas317332,
    $statements ?? $statements317332,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next317-332 combined batch follows next301-316'] = static function (TestRunner $t) use ($plan317332): void {
    $result = $plan317332([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 317, 'table' => 'wp_block_patterns', 'indexes' => ['wp_block_patterns_slug'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_import_batch_token', 'to' => 'wp_import_batch_token_next318'],
        ['op' => 'drop_table', 'schema' => 'campaign', 'table' => 'wp_campaigns'],
        ['op' => 'attach', 'schema' => 'media', 'schema_cookie' => 12, 'tables' => ['wp_media'], 'indexes' => ['wp_media_mime'], 'file' => '/srv/wp/media-next320.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 321, 'table' => 'wp_event_rollup_next321', 'indexes' => ['wp_event_rollup_day_next321'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'audit'],
        ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_claims_token', 'to' => 'wp_job_claims_token_next323'],
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_term_stage'],
        ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 44, 'tables' => ['wp_archived_posts'], 'indexes' => ['wp_archived_posts_date'], 'file' => '/srv/wp/archive-next325.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'media', 'schema_cookie' => 326, 'table' => 'wp_media_meta', 'indexes' => ['wp_media_meta_key'], 'commit' => false],
        ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_comments'],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'rename_index', 'schema' => 'campaign', 'from' => 'wp_campaignmeta_key', 'to' => 'wp_campaignmeta_key_next329'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 330, 'table' => 'wp_job_history', 'indexes' => ['wp_job_history_finished'], 'commit' => true],
        ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_events'],
        ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 332, 'table' => 'wp_campaign_audit', 'indexes' => ['wp_campaign_audit_campaign'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next317-332', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next317', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next332', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next316', $result['dependencies'][31]);
    $t->same(15, $result['event_count']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'campaign', 'media', 'queue'], $result['changed_schemas']);
    $t->same(318, $result['schema_cookies_next']['main']);
    $t->same(194, $result['schema_cookies_next']['temp']);
    $t->same(322, $result['schema_cookies_next']['analytics']);
    $t->same(332, $result['schema_cookies_next']['campaign']);
    $t->same(330, $result['schema_cookies_next']['queue']);
    $t->same(12, $result['schema_cookies_next']['media']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'media', 'queue'], $result['search_order_next']);
    $t->same(['site-health-reader', 'temp-import-reader', 'audit-meta-reader'], $result['active_current_snapshot_statements']);
    $t->same(['analytics-meta-writer', 'campaign-writer', 'options-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['temp-import-reader']['index_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['audit-meta-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['analytics-event-reader']['schema_transitions'][0]['next_found']);
    $t->same(true, $result['statements']['media-reader']['schema_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['archive-reader']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next317-332 transient scratch leaves readers reusable'] = static function (TestRunner $t) use ($plan317332): void {
    $result = $plan317332([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 9, 'tables' => ['wp_scratch'], 'indexes' => ['wp_scratch_key'], 'file' => '/srv/wp/scratch-next317.sqlite'],
        ['op' => 'detach', 'schema' => 'scratch'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 319, 'table' => 'wp_rewrite_rules_stage', 'indexes' => ['wp_rewrite_rules_stage_key'], 'commit' => false],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'campaign', 'queue'], $result['search_order_next']);
};

return $tests;
