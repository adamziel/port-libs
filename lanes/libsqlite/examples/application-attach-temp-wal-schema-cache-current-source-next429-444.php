<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 414, 'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_users', 'wp_global_styles', 'wp_navigation_menus', 'wp_navigation_redirects', 'wp_navigation_rules_next413'], 'indexes' => ['wp_options_name', 'wp_posts_type_status_next293', 'wp_postmeta_key', 'wp_users_login_next308', 'wp_global_styles_slug', 'wp_navigation_menus_slug', 'wp_navigation_redirects_slug', 'wp_navigation_rules_slug_next413'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 414, 'commit' => true]]],
    'temp' => ['schema_cookie' => 428, 'tables' => ['wp_theme_stage', 'wp_theme_stage_preview', 'wp_theme_stage_publish', 'wp_theme_stage_archive', 'wp_theme_stage_diff', 'wp_theme_stage_publish_queue'], 'indexes' => ['wp_theme_stage_stylesheet_next350', 'wp_theme_stage_preview_key_next366', 'wp_theme_stage_publish_key_next382', 'wp_theme_stage_archive_key_next398', 'wp_theme_stage_diff_key_next414', 'wp_theme_stage_publish_queue_key'], 'temp' => true],
    'analytics' => ['schema_cookie' => 426, 'tables' => ['wp_eventmeta', 'wp_event_archive', 'wp_event_rollup_next362', 'wp_event_capacity_next426'], 'indexes' => ['wp_eventmeta_key_next313', 'wp_event_capacity_day_next426'], 'file' => '/srv/wp/analytics-next429.sqlite'],
    'media' => ['schema_cookie' => 342, 'tables' => ['wp_media', 'wp_media_metadata_next387'], 'indexes' => [], 'file' => '/srv/wp/media-next429.sqlite'],
    'queue' => ['schema_cookie' => 393, 'tables' => ['wp_job_queue', 'wp_job_claims', 'wp_job_history', 'wp_job_deadletter', 'wp_job_retries', 'wp_job_retry_notes', 'wp_job_retry_audit_next419'], 'indexes' => ['wp_job_queue_token', 'wp_job_claims_token_next323', 'wp_job_history_finished_next339', 'wp_job_deadletter_reason_next360', 'wp_job_retries_job_next376', 'wp_job_retry_notes_job_next392', 'wp_job_retry_audit_job_next424'], 'file' => '/srv/wp/queue-next429.sqlite'],
    'campaign' => ['schema_cookie' => 420, 'tables' => ['wp_campaign_archive_next404', 'wp_campaign_rollup_next420'], 'indexes' => ['wp_campaigns_slug', 'wp_campaign_archive_slug_next404', 'wp_campaign_rollup_slug_next420'], 'file' => '/srv/wp/campaign-next429.sqlite'],
];

$statements = [
    ['name' => 'rules-reader', 'sql' => 'SELECT rule_id FROM main.wp_navigation_rules_next413 INDEXED BY wp_navigation_rules_slug_next413 WHERE slug = ?', 'active' => true],
    ['name' => 'publish-queue-reader', 'sql' => 'SELECT queue_id FROM temp.wp_theme_stage_publish_queue INDEXED BY wp_theme_stage_publish_queue_key WHERE cache_key = ?', 'active' => true],
    ['name' => 'capacity-reader', 'sql' => 'SELECT capacity FROM analytics.wp_event_capacity_next426 INDEXED BY wp_event_capacity_day_next426 WHERE day = ?'],
    ['name' => 'media-reader', 'sql' => 'SELECT media_id FROM media.wp_media WHERE mime_type = ?'],
    ['name' => 'queue-audit-reader', 'sql' => 'SELECT audit_id FROM queue.wp_job_retry_audit_next419 INDEXED BY wp_job_retry_audit_job_next424 WHERE job_id = ?', 'active' => true],
    ['name' => 'campaign-rollup-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaign_rollup_next420 INDEXED BY wp_campaign_rollup_slug_next420 WHERE slug = ?'],
    ['name' => 'segments-reader', 'sql' => 'SELECT segment_id FROM segments.wp_segments INDEXED BY wp_segments_slug WHERE slug = ?'],
    ['name' => 'ledger-writer', 'sql' => 'UPDATE ledger.wp_sync_ledger INDEXED BY wp_sync_ledger_status SET status = ? WHERE status = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 429, 'table' => 'wp_navigation_sitemaps_next429', 'indexes' => ['wp_navigation_sitemaps_slug_next429'], 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_queue_key', 'to' => 'wp_theme_stage_publish_queue_key_next430'],
    ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_capacity_next426'],
    ['op' => 'attach', 'schema' => 'segments', 'schema_cookie' => 101, 'tables' => ['wp_segments'], 'indexes' => ['wp_segments_slug'], 'file' => '/srv/wp/segments-next432.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'segments', 'schema_cookie' => 433, 'table' => 'wp_segmentmeta', 'indexes' => ['wp_segmentmeta_key'], 'commit' => true],
    ['op' => 'create_index', 'schema' => 'media', 'index' => 'wp_media_mime_next434'],
    ['op' => 'rename_table', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_next419', 'to' => 'wp_job_retry_audit_next435'],
    ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 436, 'table' => 'wp_campaign_attribution_next436', 'indexes' => ['wp_campaign_attribution_slug_next436'], 'commit' => true],
    ['op' => 'attach', 'schema' => 'ledger', 'schema_cookie' => 103, 'tables' => ['wp_sync_ledger'], 'indexes' => ['wp_sync_ledger_status'], 'file' => '/srv/wp/ledger-next437.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'ledger', 'schema_cookie' => 438, 'table' => 'wp_sync_ledger_meta', 'indexes' => ['wp_sync_ledger_meta_key'], 'commit' => false],
    ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_navigation_redirects'],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_job_next424', 'to' => 'wp_job_retry_audit_job_next440'],
    ['op' => 'detach', 'schema' => 'segments'],
    ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 442, 'table' => 'wp_event_allocation_next442', 'indexes' => ['wp_event_allocation_day_next442'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'ledger'],
    ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 444, 'table' => 'wp_theme_stage_publish_lock', 'indexes' => ['wp_theme_stage_publish_lock_key'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 15);
    assert($plan['changed_schemas'] === ['temp', 'main', 'analytics', 'campaign', 'media', 'queue']);
    assert($plan['schema_cookies_next']['main'] === 430);
    assert($plan['schema_cookies_next']['temp'] === 444);
    assert($plan['schema_cookies_next']['analytics'] === 442);
    assert($plan['schema_cookies_next']['media'] === 343);
    assert($plan['schema_cookies_next']['queue'] === 395);
    assert($plan['schema_cookies_next']['campaign'] === 436);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'campaign', 'media', 'queue']);
    assert(in_array('publish-queue-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('queue-audit-reader', $plan['active_current_snapshot_statements'], true));
    assert($plan['statements']['capacity-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['segments-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['ledger-writer']['schema_transitions'][0]['next_schema'] === '__detached__');

    echo "application-attach-temp-wal-schema-cache-current-source-next429-444 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
