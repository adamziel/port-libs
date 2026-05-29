<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => ['schema_cookie' => 382, 'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_users', 'wp_global_styles', 'wp_navigation_menus', 'wp_navigation_locations', 'wp_navigation_aliases'], 'indexes' => ['wp_options_name', 'wp_posts_type_status_next293', 'wp_postmeta_key', 'wp_users_login_next308', 'wp_global_styles_slug', 'wp_navigation_menus_slug', 'wp_navigation_locations_menu', 'wp_navigation_aliases_slug'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 382, 'commit' => true]]],
    'temp' => ['schema_cookie' => 396, 'tables' => ['wp_theme_stage', 'wp_theme_stage_preview', 'wp_theme_stage_publish', 'wp_theme_stage_archive'], 'indexes' => ['wp_theme_stage_stylesheet_next350', 'wp_theme_stage_preview_key_next366', 'wp_theme_stage_publish_key_next382', 'wp_theme_stage_archive_key'], 'temp' => true],
    'analytics' => ['schema_cookie' => 394, 'tables' => ['wp_eventmeta', 'wp_event_archive', 'wp_event_rollup_next362', 'wp_event_capacity_next394'], 'indexes' => ['wp_eventmeta_key_next313', 'wp_event_capacity_day_next394'], 'file' => '/srv/wp/analytics-next397.sqlite'],
    'media' => ['schema_cookie' => 340, 'tables' => ['wp_media', 'wp_media_metadata_next387'], 'indexes' => ['wp_media_mime', 'wp_media_meta_key'], 'file' => '/srv/wp/media-next397.sqlite'],
    'queue' => ['schema_cookie' => 389, 'tables' => ['wp_job_queue', 'wp_job_claims', 'wp_job_history', 'wp_job_deadletter', 'wp_job_retries', 'wp_job_retry_notes', 'wp_job_retry_audit'], 'indexes' => ['wp_job_queue_token', 'wp_job_claims_token_next323', 'wp_job_history_finished_next339', 'wp_job_deadletter_reason_next360', 'wp_job_retries_job_next376', 'wp_job_retry_notes_job_next392', 'wp_job_retry_audit_job'], 'file' => '/srv/wp/queue-next397.sqlite'],
    'campaign' => ['schema_cookie' => 347, 'tables' => [], 'indexes' => ['wp_campaigns_slug'], 'file' => '/srv/wp/campaign-next397.sqlite'],
];

$statements = [
    ['name' => 'alias-reader', 'sql' => 'SELECT alias_id FROM main.wp_navigation_aliases INDEXED BY wp_navigation_aliases_slug WHERE slug = ?', 'active' => true],
    ['name' => 'archive-reader', 'sql' => 'SELECT stage_id FROM temp.wp_theme_stage_archive INDEXED BY wp_theme_stage_archive_key WHERE cache_key = ?', 'active' => true],
    ['name' => 'capacity-reader', 'sql' => 'SELECT capacity FROM analytics.wp_event_capacity_next394 INDEXED BY wp_event_capacity_day_next394 WHERE day = ?'],
    ['name' => 'media-meta-writer', 'sql' => 'UPDATE media.wp_media_metadata_next387 INDEXED BY wp_media_meta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'queue-audit-reader', 'sql' => 'SELECT audit_id FROM queue.wp_job_retry_audit INDEXED BY wp_job_retry_audit_job WHERE job_id = ?', 'active' => true],
    ['name' => 'campaign-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaigns INDEXED BY wp_campaigns_slug WHERE slug = ?'],
    ['name' => 'segments-reader', 'sql' => 'SELECT segment_id FROM segments.wp_segments INDEXED BY wp_segments_slug WHERE slug = ?'],
    ['name' => 'ledger-writer', 'sql' => 'UPDATE ledger.wp_sync_ledger INDEXED BY wp_sync_ledger_status SET status = ? WHERE status = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext397412($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 397, 'table' => 'wp_navigation_redirects', 'indexes' => ['wp_navigation_redirects_slug'], 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_archive_key', 'to' => 'wp_theme_stage_archive_key_next398'],
    ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_capacity_next394'],
    ['op' => 'attach', 'schema' => 'segments', 'schema_cookie' => 91, 'tables' => ['wp_segments'], 'indexes' => ['wp_segments_slug'], 'file' => '/srv/wp/segments-next400.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'segments', 'schema_cookie' => 401, 'table' => 'wp_segmentmeta', 'indexes' => ['wp_segmentmeta_key'], 'commit' => true],
    ['op' => 'drop_index', 'schema' => 'media', 'index' => 'wp_media_meta_key'],
    ['op' => 'rename_table', 'schema' => 'queue', 'from' => 'wp_job_retry_audit', 'to' => 'wp_job_retry_audit_next403'],
    ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 404, 'table' => 'wp_campaign_archive_next404', 'indexes' => ['wp_campaign_archive_slug_next404'], 'commit' => true],
    ['op' => 'attach', 'schema' => 'ledger', 'schema_cookie' => 95, 'tables' => ['wp_sync_ledger'], 'indexes' => ['wp_sync_ledger_status'], 'file' => '/srv/wp/ledger-next405.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'ledger', 'schema_cookie' => 406, 'table' => 'wp_sync_ledger_meta', 'indexes' => ['wp_sync_ledger_meta_key'], 'commit' => false],
    ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_navigation_locations'],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_job', 'to' => 'wp_job_retry_audit_job_next408'],
    ['op' => 'detach', 'schema' => 'segments'],
    ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 410, 'table' => 'wp_event_staff_next410', 'indexes' => ['wp_event_staff_day_next410'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'ledger'],
    ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 412, 'table' => 'wp_theme_stage_diff', 'indexes' => ['wp_theme_stage_diff_key'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next397-412');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next397');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next412');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next396', $plan['dependencies'], true));
    assert($plan['event_count'] === 15);
    assert($plan['changed_schemas'] === ['temp', 'main', 'analytics', 'campaign', 'media', 'queue']);
    assert($plan['schema_cookies_next']['main'] === 398);
    assert($plan['schema_cookies_next']['temp'] === 412);
    assert($plan['schema_cookies_next']['analytics'] === 410);
    assert($plan['schema_cookies_next']['media'] === 341);
    assert($plan['schema_cookies_next']['queue'] === 391);
    assert($plan['schema_cookies_next']['campaign'] === 404);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'campaign', 'media', 'queue']);
    assert(in_array('archive-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('queue-audit-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('media-meta-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['capacity-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['segments-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['ledger-writer']['schema_transitions'][0]['next_schema'] === '__detached__');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next397-412 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
