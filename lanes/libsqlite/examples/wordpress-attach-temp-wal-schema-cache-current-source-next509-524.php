<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => ['schema_cookie' => 493, 'tables' => ['wp_options', 'wp_navigation_rewrite_meta_next493'], 'indexes' => ['wp_options_name', 'wp_navigation_rewrite_meta_key_next493'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 493, 'commit' => true]]],
    'temp' => ['schema_cookie' => 505, 'tables' => ['wp_theme_stage_publish_errors_next505'], 'indexes' => ['wp_theme_stage_publish_errors_key_next505'], 'temp' => true],
    'analytics' => ['schema_cookie' => 502, 'tables' => ['wp_event_capacity_slice_next502'], 'indexes' => ['wp_event_capacity_slice_day_next502'], 'file' => '/srv/wp/analytics-next509.sqlite'],
    'queue' => ['schema_cookie' => 403, 'tables' => ['wp_job_retry_audit_next498'], 'indexes' => ['wp_job_retry_audit_job_next504'], 'file' => '/srv/wp/queue-next509.sqlite'],
    'campaign' => ['schema_cookie' => 508, 'tables' => ['wp_campaign_archive_next508'], 'indexes' => ['wp_campaign_archive_slug_next508'], 'file' => '/srv/wp/campaign-next509.sqlite'],
];

$statements = [
    ['name' => 'rewrite-meta-reader', 'sql' => 'SELECT rule_id FROM main.wp_navigation_rewrite_meta_next493 INDEXED BY wp_navigation_rewrite_meta_key_next493 WHERE meta_key = ?', 'active' => true],
    ['name' => 'temp-error-reader', 'sql' => 'SELECT error_id FROM temp.wp_theme_stage_publish_errors_next505 INDEXED BY wp_theme_stage_publish_errors_key_next505 WHERE cache_key = ?', 'active' => true],
    ['name' => 'analytics-slice-reader', 'sql' => 'SELECT slice_id FROM analytics.wp_event_capacity_slice_next502 INDEXED BY wp_event_capacity_slice_day_next502 WHERE day = ?'],
    ['name' => 'queue-audit-writer', 'sql' => 'UPDATE queue.wp_job_retry_audit_next498 INDEXED BY wp_job_retry_audit_job_next504 SET status = ? WHERE job_id = ?'],
    ['name' => 'campaign-archive-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaign_archive_next508 INDEXED BY wp_campaign_archive_slug_next508 WHERE slug = ?'],
    ['name' => 'segments-reader', 'sql' => 'SELECT segment_id FROM segments.wp_segment_cache_next512 INDEXED BY wp_segment_cache_slug_next512 WHERE slug = ?'],
    ['name' => 'experiments-writer', 'sql' => 'UPDATE experiments.wp_experiment_rollups_next517 INDEXED BY wp_experiment_rollups_slug_next517 SET enabled = ? WHERE slug = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext509524($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 509, 'table' => 'wp_navigation_redirect_next509', 'indexes' => ['wp_navigation_redirect_slug_next509'], 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_errors_key_next505', 'to' => 'wp_theme_stage_publish_errors_key_next510'],
    ['op' => 'drop_index', 'schema' => 'analytics', 'index' => 'wp_event_capacity_slice_day_next502'],
    ['op' => 'attach', 'schema' => 'segments', 'schema_cookie' => 512, 'tables' => ['wp_segment_cache_next512'], 'indexes' => ['wp_segment_cache_slug_next512'], 'file' => '/srv/wp/segments-next512.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'segments', 'schema_cookie' => 513, 'table' => 'wp_segment_cache_meta_next513', 'indexes' => ['wp_segment_cache_meta_key_next513'], 'commit' => true],
    ['op' => 'rename_table', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_next498', 'to' => 'wp_job_retry_audit_next514'],
    ['op' => 'drop_table', 'schema' => 'campaign', 'table' => 'wp_campaign_archive_next508'],
    ['op' => 'attach', 'schema' => 'experiments', 'schema_cookie' => 516, 'tables' => ['wp_experiment_rollups_next517'], 'indexes' => ['wp_experiment_rollups_slug_next517'], 'file' => '/srv/wp/experiments-next516.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'experiments', 'schema_cookie' => 517, 'table' => 'wp_experiment_rollup_meta_next517', 'indexes' => ['wp_experiment_rollup_meta_key_next517'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 518, 'table' => 'wp_event_capacity_bucket_next518', 'indexes' => ['wp_event_capacity_bucket_day_next518'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'segments'],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_job_next504', 'to' => 'wp_job_retry_audit_job_next520'],
    ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 521, 'table' => 'wp_theme_stage_publish_retries_next521', 'indexes' => ['wp_theme_stage_publish_retries_key_next521'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'experiments'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 523, 'table' => 'wp_navigation_preview_next523', 'indexes' => ['wp_navigation_preview_slug_next523'], 'commit' => false],
    ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 524, 'table' => 'wp_campaign_restore_next524', 'indexes' => ['wp_campaign_restore_slug_next524'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next509-524');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next509');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next524');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next508', $plan['dependencies'], true));
    assert($plan['event_count'] === 15);
    assert($plan['changed_schemas'] === ['temp', 'main', 'analytics', 'campaign', 'queue']);
    assert($plan['schema_cookies_next']['main'] === 509);
    assert($plan['schema_cookies_next']['temp'] === 521);
    assert($plan['schema_cookies_next']['analytics'] === 518);
    assert($plan['schema_cookies_next']['queue'] === 405);
    assert($plan['schema_cookies_next']['campaign'] === 524);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'campaign', 'queue']);
    assert(in_array('temp-error-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('queue-audit-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['analytics-slice-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['segments-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['experiments-writer']['schema_transitions'][0]['next_schema'] === '__detached__');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next509-524 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
