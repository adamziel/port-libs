<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => ['schema_cookie' => 398, 'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_users', 'wp_global_styles', 'wp_navigation_menus', 'wp_navigation_aliases', 'wp_navigation_redirects'], 'indexes' => ['wp_options_name', 'wp_posts_type_status_next293', 'wp_postmeta_key', 'wp_users_login_next308', 'wp_global_styles_slug', 'wp_navigation_menus_slug', 'wp_navigation_aliases_slug', 'wp_navigation_redirects_slug'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 398, 'commit' => true]]],
    'temp' => ['schema_cookie' => 412, 'tables' => ['wp_theme_stage', 'wp_theme_stage_preview', 'wp_theme_stage_publish', 'wp_theme_stage_archive', 'wp_theme_stage_diff'], 'indexes' => ['wp_theme_stage_stylesheet_next350', 'wp_theme_stage_preview_key_next366', 'wp_theme_stage_publish_key_next382', 'wp_theme_stage_archive_key_next398', 'wp_theme_stage_diff_key'], 'temp' => true],
    'analytics' => ['schema_cookie' => 410, 'tables' => ['wp_eventmeta', 'wp_event_archive', 'wp_event_rollup_next362', 'wp_event_staff_next410'], 'indexes' => ['wp_eventmeta_key_next313', 'wp_event_staff_day_next410'], 'file' => '/srv/wp/analytics-next413.sqlite'],
    'media' => ['schema_cookie' => 341, 'tables' => ['wp_media', 'wp_media_metadata_next387'], 'indexes' => ['wp_media_mime'], 'file' => '/srv/wp/media-next413.sqlite'],
    'queue' => ['schema_cookie' => 391, 'tables' => ['wp_job_queue', 'wp_job_claims', 'wp_job_history', 'wp_job_deadletter', 'wp_job_retries', 'wp_job_retry_notes', 'wp_job_retry_audit_next403'], 'indexes' => ['wp_job_queue_token', 'wp_job_claims_token_next323', 'wp_job_history_finished_next339', 'wp_job_deadletter_reason_next360', 'wp_job_retries_job_next376', 'wp_job_retry_notes_job_next392', 'wp_job_retry_audit_job_next408'], 'file' => '/srv/wp/queue-next413.sqlite'],
    'campaign' => ['schema_cookie' => 404, 'tables' => ['wp_campaign_archive_next404'], 'indexes' => ['wp_campaigns_slug', 'wp_campaign_archive_slug_next404'], 'file' => '/srv/wp/campaign-next413.sqlite'],
];

$statements = [
    ['name' => 'redirect-reader', 'sql' => 'SELECT redirect_id FROM main.wp_navigation_redirects INDEXED BY wp_navigation_redirects_slug WHERE slug = ?', 'active' => true],
    ['name' => 'diff-reader', 'sql' => 'SELECT stage_id FROM temp.wp_theme_stage_diff INDEXED BY wp_theme_stage_diff_key WHERE cache_key = ?', 'active' => true],
    ['name' => 'staff-reader', 'sql' => 'SELECT staff_id FROM analytics.wp_event_staff_next410 INDEXED BY wp_event_staff_day_next410 WHERE day = ?'],
    ['name' => 'media-mime-writer', 'sql' => 'UPDATE media.wp_media INDEXED BY wp_media_mime SET mime_type = ? WHERE mime_type = ?'],
    ['name' => 'queue-audit-reader', 'sql' => 'SELECT audit_id FROM queue.wp_job_retry_audit_next403 INDEXED BY wp_job_retry_audit_job_next408 WHERE job_id = ?', 'active' => true],
    ['name' => 'campaign-archive-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaign_archive_next404 INDEXED BY wp_campaign_archive_slug_next404 WHERE slug = ?'],
    ['name' => 'experiment-reader', 'sql' => 'SELECT experiment_id FROM experiments.wp_experiments INDEXED BY wp_experiments_slug WHERE slug = ?'],
    ['name' => 'audit-writer', 'sql' => 'UPDATE audit.wp_audit_trail INDEXED BY wp_audit_trail_type SET event_type = ? WHERE event_type = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext413428($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 413, 'table' => 'wp_navigation_rules_next413', 'indexes' => ['wp_navigation_rules_slug_next413'], 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_diff_key', 'to' => 'wp_theme_stage_diff_key_next414'],
    ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_staff_next410'],
    ['op' => 'attach', 'schema' => 'experiments', 'schema_cookie' => 97, 'tables' => ['wp_experiments'], 'indexes' => ['wp_experiments_slug'], 'file' => '/srv/wp/experiments-next416.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'experiments', 'schema_cookie' => 417, 'table' => 'wp_experimentmeta', 'indexes' => ['wp_experimentmeta_key'], 'commit' => true],
    ['op' => 'drop_index', 'schema' => 'media', 'index' => 'wp_media_mime'],
    ['op' => 'rename_table', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_next403', 'to' => 'wp_job_retry_audit_next419'],
    ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 420, 'table' => 'wp_campaign_rollup_next420', 'indexes' => ['wp_campaign_rollup_slug_next420'], 'commit' => true],
    ['op' => 'attach', 'schema' => 'audit', 'schema_cookie' => 99, 'tables' => ['wp_audit_trail'], 'indexes' => ['wp_audit_trail_type'], 'file' => '/srv/wp/audit-next421.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'audit', 'schema_cookie' => 422, 'table' => 'wp_audit_detail', 'indexes' => ['wp_audit_detail_key'], 'commit' => false],
    ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_navigation_aliases'],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_job_next408', 'to' => 'wp_job_retry_audit_job_next424'],
    ['op' => 'detach', 'schema' => 'experiments'],
    ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 426, 'table' => 'wp_event_capacity_next426', 'indexes' => ['wp_event_capacity_day_next426'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'audit'],
    ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 428, 'table' => 'wp_theme_stage_publish_queue', 'indexes' => ['wp_theme_stage_publish_queue_key'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next413-428');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next413');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next428');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next412', $plan['dependencies'], true));
    assert($plan['event_count'] === 15);
    assert($plan['changed_schemas'] === ['temp', 'main', 'analytics', 'campaign', 'media', 'queue']);
    assert($plan['schema_cookies_next']['main'] === 414);
    assert($plan['schema_cookies_next']['temp'] === 428);
    assert($plan['schema_cookies_next']['analytics'] === 426);
    assert($plan['schema_cookies_next']['media'] === 342);
    assert($plan['schema_cookies_next']['queue'] === 393);
    assert($plan['schema_cookies_next']['campaign'] === 420);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'campaign', 'media', 'queue']);
    assert(in_array('diff-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('queue-audit-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('media-mime-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['staff-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['experiment-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['audit-writer']['schema_transitions'][0]['next_schema'] === '__detached__');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next413-428 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
