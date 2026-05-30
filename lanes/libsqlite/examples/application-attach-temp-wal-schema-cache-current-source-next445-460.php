<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 430, 'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_users', 'wp_global_styles', 'wp_navigation_menus', 'wp_navigation_rules_next413', 'wp_navigation_sitemaps_next429'], 'indexes' => ['wp_options_name', 'wp_posts_type_status_next293', 'wp_postmeta_key', 'wp_users_login_next308', 'wp_global_styles_slug', 'wp_navigation_menus_slug', 'wp_navigation_rules_slug_next413', 'wp_navigation_sitemaps_slug_next429'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 430, 'commit' => true]]],
    'temp' => ['schema_cookie' => 444, 'tables' => ['wp_theme_stage', 'wp_theme_stage_preview', 'wp_theme_stage_publish', 'wp_theme_stage_archive', 'wp_theme_stage_diff', 'wp_theme_stage_publish_queue', 'wp_theme_stage_publish_lock'], 'indexes' => ['wp_theme_stage_stylesheet_next350', 'wp_theme_stage_preview_key_next366', 'wp_theme_stage_publish_key_next382', 'wp_theme_stage_archive_key_next398', 'wp_theme_stage_diff_key_next414', 'wp_theme_stage_publish_queue_key_next430', 'wp_theme_stage_publish_lock_key'], 'temp' => true],
    'analytics' => ['schema_cookie' => 442, 'tables' => ['wp_eventmeta', 'wp_event_archive', 'wp_event_rollup_next362', 'wp_event_allocation_next442'], 'indexes' => ['wp_eventmeta_key_next313', 'wp_event_allocation_day_next442'], 'file' => '/srv/wp/analytics-next445.sqlite'],
    'media' => ['schema_cookie' => 343, 'tables' => ['wp_media', 'wp_media_metadata_next387'], 'indexes' => ['wp_media_mime_next434'], 'file' => '/srv/wp/media-next445.sqlite'],
    'queue' => ['schema_cookie' => 395, 'tables' => ['wp_job_queue', 'wp_job_claims', 'wp_job_history', 'wp_job_deadletter', 'wp_job_retries', 'wp_job_retry_notes', 'wp_job_retry_audit_next435'], 'indexes' => ['wp_job_queue_token', 'wp_job_claims_token_next323', 'wp_job_history_finished_next339', 'wp_job_deadletter_reason_next360', 'wp_job_retries_job_next376', 'wp_job_retry_notes_job_next392', 'wp_job_retry_audit_job_next440'], 'file' => '/srv/wp/queue-next445.sqlite'],
    'campaign' => ['schema_cookie' => 436, 'tables' => ['wp_campaign_archive_next404', 'wp_campaign_rollup_next420', 'wp_campaign_attribution_next436'], 'indexes' => ['wp_campaigns_slug', 'wp_campaign_archive_slug_next404', 'wp_campaign_rollup_slug_next420', 'wp_campaign_attribution_slug_next436'], 'file' => '/srv/wp/campaign-next445.sqlite'],
];

$statements = [
    ['name' => 'sitemap-reader', 'sql' => 'SELECT sitemap_id FROM main.wp_navigation_sitemaps_next429 INDEXED BY wp_navigation_sitemaps_slug_next429 WHERE slug = ?', 'active' => true],
    ['name' => 'publish-lock-reader', 'sql' => 'SELECT lock_id FROM temp.wp_theme_stage_publish_lock INDEXED BY wp_theme_stage_publish_lock_key WHERE cache_key = ?', 'active' => true],
    ['name' => 'allocation-reader', 'sql' => 'SELECT allocation_id FROM analytics.wp_event_allocation_next442 INDEXED BY wp_event_allocation_day_next442 WHERE day = ?'],
    ['name' => 'media-mime-writer', 'sql' => 'UPDATE media.wp_media INDEXED BY wp_media_mime_next434 SET mime_type = ? WHERE media_id = ?'],
    ['name' => 'queue-audit-reader', 'sql' => 'SELECT audit_id FROM queue.wp_job_retry_audit_next435 INDEXED BY wp_job_retry_audit_job_next440 WHERE job_id = ?', 'active' => true],
    ['name' => 'campaign-attribution-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaign_attribution_next436 INDEXED BY wp_campaign_attribution_slug_next436 WHERE slug = ?'],
    ['name' => 'segments-reader', 'sql' => 'SELECT segment_id FROM segments.wp_segments INDEXED BY wp_segments_slug WHERE slug = ?'],
    ['name' => 'ledger-writer', 'sql' => 'UPDATE ledger.wp_sync_ledger INDEXED BY wp_sync_ledger_status SET status = ? WHERE status = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
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

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 15);
    assert($plan['changed_schemas'] === ['temp', 'main', 'analytics', 'campaign', 'media', 'queue']);
    assert($plan['schema_cookies_next']['main'] === 446);
    assert($plan['schema_cookies_next']['temp'] === 460);
    assert($plan['schema_cookies_next']['analytics'] === 458);
    assert($plan['schema_cookies_next']['media'] === 344);
    assert($plan['schema_cookies_next']['queue'] === 397);
    assert($plan['schema_cookies_next']['campaign'] === 452);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'campaign', 'media', 'queue']);
    assert(in_array('publish-lock-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('queue-audit-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('media-mime-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['allocation-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['segments-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['ledger-writer']['schema_transitions'][0]['next_schema'] === '__detached__');

    echo "application-attach-temp-wal-schema-cache-current-source-next445-460 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
