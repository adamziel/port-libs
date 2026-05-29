<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => ['schema_cookie' => 446, 'tables' => ['wp_options', 'wp_navigation_history_next445'], 'indexes' => ['wp_options_name', 'wp_navigation_history_slug_next445'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 446, 'commit' => true]]],
    'temp' => ['schema_cookie' => 460, 'tables' => ['wp_theme_stage_publish_receipts'], 'indexes' => ['wp_theme_stage_publish_receipts_key'], 'temp' => true],
    'analytics' => ['schema_cookie' => 458, 'tables' => ['wp_event_waitlist_next458'], 'indexes' => ['wp_event_waitlist_day_next458'], 'file' => '/srv/wp/analytics-next461.sqlite'],
    'queue' => ['schema_cookie' => 397, 'tables' => ['wp_job_retry_audit_next451'], 'indexes' => ['wp_job_retry_audit_job_next456'], 'file' => '/srv/wp/queue-next461.sqlite'],
    'campaign' => ['schema_cookie' => 452, 'tables' => ['wp_campaign_budget_next452'], 'indexes' => ['wp_campaign_budget_slug_next452'], 'file' => '/srv/wp/campaign-next461.sqlite'],
];

$statements = [
    ['name' => 'history-reader', 'sql' => 'SELECT navigation_id FROM main.wp_navigation_history_next445 INDEXED BY wp_navigation_history_slug_next445 WHERE slug = ?', 'active' => true],
    ['name' => 'receipt-reader', 'sql' => 'SELECT receipt_id FROM temp.wp_theme_stage_publish_receipts INDEXED BY wp_theme_stage_publish_receipts_key WHERE cache_key = ?', 'active' => true],
    ['name' => 'waitlist-reader', 'sql' => 'SELECT event_id FROM analytics.wp_event_waitlist_next458 INDEXED BY wp_event_waitlist_day_next458 WHERE day = ?'],
    ['name' => 'queue-writer', 'sql' => 'UPDATE queue.wp_job_retry_audit_next451 INDEXED BY wp_job_retry_audit_job_next456 SET status = ? WHERE job_id = ?'],
    ['name' => 'budget-reader', 'sql' => 'SELECT budget_id FROM campaign.wp_campaign_budget_next452 INDEXED BY wp_campaign_budget_slug_next452 WHERE slug = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT row_id FROM archive.wp_content_archive INDEXED BY wp_content_archive_slug WHERE slug = ?'],
    ['name' => 'rules-writer', 'sql' => 'UPDATE rules.wp_segment_rules INDEXED BY wp_segment_rules_slug SET enabled = ? WHERE slug = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext461476($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 461, 'table' => 'wp_navigation_redirects_next461', 'indexes' => ['wp_navigation_redirects_slug_next461'], 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_receipts_key', 'to' => 'wp_theme_stage_publish_receipts_key_next462'],
    ['op' => 'drop_index', 'schema' => 'analytics', 'index' => 'wp_event_waitlist_day_next458'],
    ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 121, 'tables' => ['wp_content_archive'], 'indexes' => ['wp_content_archive_slug'], 'file' => '/srv/wp/archive-next464.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 465, 'table' => 'wp_content_archive_meta_next465', 'indexes' => ['wp_content_archive_meta_key_next465'], 'commit' => true],
    ['op' => 'rename_table', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_next451', 'to' => 'wp_job_retry_audit_next466'],
    ['op' => 'drop_table', 'schema' => 'campaign', 'table' => 'wp_campaign_budget_next452'],
    ['op' => 'attach', 'schema' => 'rules', 'schema_cookie' => 123, 'tables' => ['wp_segment_rules'], 'indexes' => ['wp_segment_rules_slug'], 'file' => '/srv/wp/rules-next468.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'rules', 'schema_cookie' => 469, 'table' => 'wp_segment_rule_meta_next469', 'indexes' => ['wp_segment_rule_meta_key_next469'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 470, 'table' => 'wp_event_capacity_next470', 'indexes' => ['wp_event_capacity_day_next470'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_audit_job_next456', 'to' => 'wp_job_retry_audit_job_next472'],
    ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 473, 'table' => 'wp_theme_stage_publish_errors_next473', 'indexes' => ['wp_theme_stage_publish_errors_key_next473'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'rules'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 475, 'table' => 'wp_navigation_audit_next475', 'indexes' => ['wp_navigation_audit_slug_next475'], 'commit' => false],
    ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 476, 'table' => 'wp_campaign_variants_next476', 'indexes' => ['wp_campaign_variants_slug_next476'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next461-476');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next461');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next476');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next460', $plan['dependencies'], true));
    assert($plan['event_count'] === 15);
    assert($plan['changed_schemas'] === ['temp', 'main', 'analytics', 'campaign', 'queue']);
    assert($plan['schema_cookies_next']['main'] === 461);
    assert($plan['schema_cookies_next']['temp'] === 473);
    assert($plan['schema_cookies_next']['analytics'] === 470);
    assert($plan['schema_cookies_next']['queue'] === 399);
    assert($plan['schema_cookies_next']['campaign'] === 476);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'campaign', 'queue']);
    assert(in_array('receipt-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('queue-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['waitlist-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['archive-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['rules-writer']['schema_transitions'][0]['next_schema'] === '__detached__');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next461-476 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
