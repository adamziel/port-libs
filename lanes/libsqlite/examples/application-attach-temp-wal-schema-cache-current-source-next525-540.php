<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 525, 'tables' => ['wp_options', 'wp_navigation_preview_next523'], 'indexes' => ['wp_options_name', 'wp_navigation_preview_slug_next523'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 525, 'commit' => true]]],
    'temp' => ['schema_cookie' => 521, 'tables' => ['wp_theme_stage_publish_retries_next521'], 'indexes' => ['wp_theme_stage_publish_retries_key_next521'], 'temp' => true],
    'analytics' => ['schema_cookie' => 518, 'tables' => ['wp_event_capacity_bucket_next518'], 'indexes' => ['wp_event_capacity_bucket_day_next518'], 'file' => '/srv/wp/analytics-next525.sqlite'],
    'queue' => ['schema_cookie' => 522, 'tables' => ['wp_job_retry_audit_next514'], 'indexes' => ['wp_job_retry_audit_job_next520'], 'file' => '/srv/wp/queue-next525.sqlite'],
    'campaign' => ['schema_cookie' => 524, 'tables' => ['wp_campaign_restore_next524'], 'indexes' => ['wp_campaign_restore_slug_next524'], 'file' => '/srv/wp/campaign-next525.sqlite'],
];

$statements = [
    ['name' => 'nav-preview-reader', 'sql' => 'SELECT preview_id FROM main.wp_navigation_preview_next523 INDEXED BY wp_navigation_preview_slug_next523 WHERE slug = ?', 'active' => true],
    ['name' => 'temp-retry-writer', 'sql' => 'UPDATE temp.wp_theme_stage_publish_retries_next521 INDEXED BY wp_theme_stage_publish_retries_key_next521 SET tries = tries + 1 WHERE cache_key = ?'],
    ['name' => 'analytics-bucket-reader', 'sql' => 'SELECT bucket_id FROM analytics.wp_event_capacity_bucket_next518 INDEXED BY wp_event_capacity_bucket_day_next518 WHERE day = ?'],
    ['name' => 'queue-audit-reader', 'sql' => 'SELECT status FROM queue.wp_job_retry_audit_next514 INDEXED BY wp_job_retry_audit_job_next520 WHERE job_id = ?'],
    ['name' => 'campaign-restore-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaign_restore_next524 INDEXED BY wp_campaign_restore_slug_next524 WHERE slug = ?'],
    ['name' => 'cdn-cache-reader', 'sql' => 'SELECT cache_id FROM cdn.wp_edge_cache_next532 INDEXED BY wp_edge_cache_slug_next532 WHERE slug = ?'],
    ['name' => 'search-writer', 'sql' => 'UPDATE search.wp_search_queue_next537 INDEXED BY wp_search_queue_slug_next537 SET touched = 1 WHERE slug = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 525, 'table' => 'wp_navigation_rule_next525', 'indexes' => ['wp_navigation_rule_slug_next525'], 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'campaign', 'from' => 'wp_campaign_restore_slug_next524', 'to' => 'wp_campaign_restore_slug_next526'],
    ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_capacity_bucket_next518'],
    ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 528, 'table' => 'wp_job_retry_window_next528', 'indexes' => ['wp_job_retry_window_job_next528'], 'commit' => true],
    ['op' => 'rename_table', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_retries_next521', 'to' => 'wp_theme_stage_publish_retries_next529'],
    ['op' => 'attach', 'schema' => 'cdn', 'schema_cookie' => 532, 'tables' => ['wp_edge_cache_next532'], 'indexes' => ['wp_edge_cache_slug_next532'], 'file' => '/srv/wp/cdn-next532.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'cdn', 'schema_cookie' => 533, 'table' => 'wp_edge_cache_meta_next533', 'indexes' => ['wp_edge_cache_meta_key_next533'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'cdn'],
    ['op' => 'attach', 'schema' => 'search', 'schema_cookie' => 537, 'tables' => ['wp_search_queue_next537'], 'indexes' => ['wp_search_queue_slug_next537'], 'file' => '/srv/wp/search-next537.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'search', 'schema_cookie' => 538, 'table' => 'wp_search_queue_meta_next538', 'indexes' => ['wp_search_queue_meta_key_next538'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 539, 'table' => 'wp_navigation_rule_shadow_next539', 'indexes' => ['wp_navigation_rule_shadow_slug_next539'], 'commit' => false],
    ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 540, 'table' => 'wp_campaign_restore_meta_next540', 'indexes' => ['wp_campaign_restore_meta_key_next540'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 11);
    assert($plan['changed_schemas'] === ['temp', 'analytics', 'campaign', 'queue', 'search']);
    assert($plan['schema_cookies_next']['main'] === 525);
    assert($plan['schema_cookies_next']['temp'] === 522);
    assert($plan['schema_cookies_next']['analytics'] === 519);
    assert($plan['schema_cookies_next']['queue'] === 528);
    assert($plan['schema_cookies_next']['campaign'] === 540);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'campaign', 'queue', 'search']);
    assert(in_array('nav-preview-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('temp-retry-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['analytics-bucket-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['cdn-cache-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['search-writer']['schema_transitions'][0]['next_schema'] === 'search');

    echo "application-attach-temp-wal-schema-cache-current-source-next525-540 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
