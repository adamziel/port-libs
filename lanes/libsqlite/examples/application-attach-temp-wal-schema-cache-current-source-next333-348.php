<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 318, 'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_users', 'wp_terms', 'wp_term_taxonomy', 'wp_site_health', 'wp_block_patterns'], 'indexes' => ['wp_options_name', 'wp_posts_type_status_next293', 'wp_postmeta_key', 'wp_users_login_next308', 'wp_terms_slug', 'wp_term_taxonomy_taxonomy', 'wp_site_health_status', 'wp_block_patterns_slug'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 318, 'commit' => true]]],
    'temp' => ['schema_cookie' => 194, 'tables' => ['wp_import_batch', 'wp_theme_stage'], 'indexes' => ['wp_import_batch_token_next318', 'wp_theme_stage_stylesheet_next302'], 'temp' => true],
    'analytics' => ['schema_cookie' => 322, 'tables' => ['wp_eventmeta', 'wp_event_rollup_next321'], 'indexes' => ['wp_eventmeta_key_next313', 'wp_event_rollup_day_next321'], 'file' => '/srv/wp/analytics-next333.sqlite'],
    'campaign' => ['schema_cookie' => 332, 'tables' => ['wp_campaignmeta', 'wp_campaign_audit'], 'indexes' => ['wp_campaigns_slug', 'wp_campaignmeta_key_next329', 'wp_campaign_audit_campaign'], 'file' => '/srv/wp/campaign-next333.sqlite'],
    'media' => ['schema_cookie' => 12, 'tables' => ['wp_media'], 'indexes' => ['wp_media_mime'], 'file' => '/srv/wp/media-next333.sqlite'],
    'queue' => ['schema_cookie' => 330, 'tables' => ['wp_job_queue', 'wp_job_claims', 'wp_job_history'], 'indexes' => ['wp_job_queue_token', 'wp_job_claims_token_next323', 'wp_job_history_finished'], 'file' => '/srv/wp/queue-next333.sqlite'],
];

$statements = [
    ['name' => 'block-pattern-reader', 'sql' => 'SELECT pattern_id FROM main.wp_block_patterns INDEXED BY wp_block_patterns_slug WHERE slug = ?', 'active' => true],
    ['name' => 'theme-stage-reader', 'sql' => 'SELECT stylesheet FROM temp.wp_theme_stage INDEXED BY wp_theme_stage_stylesheet_next302 WHERE stylesheet = ?', 'active' => true],
    ['name' => 'analytics-rollup-reader', 'sql' => 'SELECT day FROM analytics.wp_event_rollup_next321 INDEXED BY wp_event_rollup_day_next321 WHERE day = ?'],
    ['name' => 'campaign-audit-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaign_audit INDEXED BY wp_campaign_audit_campaign WHERE campaign_id = ?', 'active' => true],
    ['name' => 'media-writer', 'sql' => 'UPDATE media.wp_media INDEXED BY wp_media_mime SET title = ? WHERE mime_type = ?'],
    ['name' => 'queue-history-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_history INDEXED BY wp_job_history_finished WHERE finished_at >= ?'],
    ['name' => 'reports-reader', 'sql' => 'SELECT report_id FROM reports.wp_reports INDEXED BY wp_reports_slug WHERE slug = ?'],
    ['name' => 'archive-writer', 'sql' => 'UPDATE archive.wp_archived_posts INDEXED BY wp_archived_posts_date SET post_status = ? WHERE post_date < ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 333, 'table' => 'wp_global_styles', 'indexes' => ['wp_global_styles_slug'], 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_stylesheet_next302', 'to' => 'wp_theme_stage_stylesheet_next334'],
    ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_rollup_next321'],
    ['op' => 'attach', 'schema' => 'reports', 'schema_cookie' => 35, 'tables' => ['wp_reports'], 'indexes' => ['wp_reports_slug'], 'file' => '/srv/wp/reports-next336.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'media', 'schema_cookie' => 337, 'table' => 'wp_media_meta', 'indexes' => ['wp_media_meta_key'], 'commit' => true],
    ['op' => 'drop_index', 'schema' => 'campaign', 'index' => 'wp_campaign_audit_campaign'],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_history_finished', 'to' => 'wp_job_history_finished_next339'],
    ['op' => 'wal_commit', 'schema' => 'reports', 'schema_cookie' => 340, 'table' => 'wp_reportmeta', 'indexes' => ['wp_reportmeta_key'], 'commit' => false],
    ['op' => 'detach', 'schema' => 'reports'],
    ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 47, 'tables' => ['wp_archived_posts'], 'indexes' => ['wp_archived_posts_date'], 'file' => '/srv/wp/archive-next342.sqlite'],
    ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_terms'],
    ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 344, 'table' => 'wp_campaign_segments', 'indexes' => ['wp_campaign_segments_slug'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_batch'],
    ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 347, 'table' => 'wp_job_deadletter', 'indexes' => ['wp_job_deadletter_reason'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 348, 'table' => 'wp_event_archive', 'indexes' => ['wp_event_archive_day'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 15);
    assert($plan['changed_schemas'] === ['temp', 'main', 'analytics', 'campaign', 'media', 'queue']);
    assert($plan['schema_cookies_next']['main'] === 334);
    assert($plan['schema_cookies_next']['temp'] === 196);
    assert($plan['schema_cookies_next']['analytics'] === 348);
    assert($plan['schema_cookies_next']['campaign'] === 344);
    assert($plan['schema_cookies_next']['media'] === 337);
    assert($plan['schema_cookies_next']['queue'] === 347);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'campaign', 'media', 'queue']);
    assert(in_array('theme-stage-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('campaign-audit-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('media-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['analytics-rollup-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['reports-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['archive-writer']['schema_transitions'][0]['next_schema'] === '__detached__');

    echo "application-attach-temp-wal-schema-cache-current-source-next333-348 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
