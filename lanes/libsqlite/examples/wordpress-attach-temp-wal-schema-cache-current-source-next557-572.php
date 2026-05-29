<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => ['schema_cookie' => 556, 'tables' => ['wp_options', 'wp_navigation_rule_next525', 'wp_navigation_rule_locale_next541', 'wp_navigation_rule_locale_meta_next556'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_slug_next525', 'wp_navigation_rule_locale_slug_next541', 'wp_navigation_rule_locale_meta_key_next556'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 556, 'commit' => true]]],
    'temp' => ['schema_cookie' => 523, 'tables' => ['wp_theme_stage_publish_retries_next529'], 'indexes' => ['wp_theme_stage_publish_retries_key_next542'], 'temp' => true],
    'queue' => ['schema_cookie' => 544, 'tables' => ['wp_job_retry_audit_next514', 'wp_job_retry_window_next528', 'wp_job_retry_checkpoint_next544'], 'indexes' => ['wp_job_retry_audit_job_next520', 'wp_job_retry_window_job_next528', 'wp_job_retry_checkpoint_job_next544'], 'file' => '/srv/wp/queue-next557.sqlite'],
    'campaign' => ['schema_cookie' => 541, 'tables' => ['wp_campaign_restore_next524', 'wp_campaign_restore_meta_next545'], 'indexes' => ['wp_campaign_restore_slug_next526', 'wp_campaign_restore_meta_key_next540'], 'file' => '/srv/wp/campaign-next557.sqlite'],
    'search' => ['schema_cookie' => 539, 'tables' => ['wp_search_queue_next537', 'wp_search_queue_meta_next538'], 'indexes' => ['wp_search_queue_meta_key_next538'], 'file' => '/srv/wp/search-next557.sqlite'],
    'audit' => ['schema_cookie' => 554, 'tables' => ['wp_schema_audit_next553', 'wp_schema_audit_meta_next554'], 'indexes' => ['wp_schema_audit_key_next553', 'wp_schema_audit_meta_key_next554'], 'file' => '/srv/wp/audit-next557.sqlite'],
];

$statements = [
    ['name' => 'nav-locale-reader', 'sql' => 'SELECT locale_id FROM main.wp_navigation_rule_locale_next541 INDEXED BY wp_navigation_rule_locale_slug_next541 WHERE slug = ?', 'active' => true],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next529 INDEXED BY wp_theme_stage_publish_retries_key_next542 WHERE cache_key = ?', 'active' => true],
    ['name' => 'queue-checkpoint-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_next544 INDEXED BY wp_job_retry_checkpoint_job_next544 SET attempts = attempts + 1 WHERE job_id = ?'],
    ['name' => 'campaign-meta-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaign_restore_meta_next545 INDEXED BY wp_campaign_restore_meta_key_next540 WHERE meta_key = ?'],
    ['name' => 'search-meta-reader', 'sql' => 'SELECT queue_id FROM search.wp_search_queue_meta_next538 INDEXED BY wp_search_queue_meta_key_next538 WHERE meta_key = ?'],
    ['name' => 'audit-meta-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_meta_next554 INDEXED BY wp_schema_audit_meta_key_next554 WHERE meta_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_next568 INDEXED BY wp_schema_archive_key_next568 WHERE archive_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext557572($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 557, 'table' => 'wp_navigation_rule_locale_term_next557', 'indexes' => ['wp_navigation_rule_locale_term_key_next557'], 'commit' => true],
    ['op' => 'rename_table', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_retries_next529', 'to' => 'wp_theme_stage_publish_retries_next558'],
    ['op' => 'drop_index', 'schema' => 'campaign', 'index' => 'wp_campaign_restore_meta_key_next540'],
    ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 560, 'table' => 'wp_job_retry_checkpoint_lock_next560', 'indexes' => ['wp_job_retry_checkpoint_lock_job_next560'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'search'],
    ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 568, 'tables' => ['wp_schema_archive_next568'], 'indexes' => ['wp_schema_archive_key_next568'], 'file' => '/srv/wp/archive-next568.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 569, 'table' => 'wp_schema_archive_meta_next569', 'indexes' => ['wp_schema_archive_meta_key_next569'], 'commit' => true],
    ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_meta_next554'],
    ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 571, 'table' => 'wp_job_retry_checkpoint_receipt_next571', 'indexes' => ['wp_job_retry_checkpoint_receipt_job_next571'], 'commit' => false],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 572, 'table' => 'wp_navigation_rule_locale_receipt_next572', 'indexes' => ['wp_navigation_rule_locale_receipt_key_next572'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next557-572');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next557');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next572');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next556', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['changed_schemas'] === ['temp', 'main', 'archive', 'audit', 'campaign', 'queue', 'search']);
    assert($plan['schema_cookies_next']['main'] === 572);
    assert($plan['schema_cookies_next']['temp'] === 524);
    assert($plan['schema_cookies_next']['queue'] === 560);
    assert($plan['schema_cookies_next']['campaign'] === 542);
    assert($plan['schema_cookies_next']['audit'] === 555);
    assert($plan['schema_cookies_next']['archive'] === 569);
    assert($plan['search_order_next'] === ['temp', 'main', 'archive', 'audit', 'campaign', 'queue']);
    assert(in_array('temp-retry-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('queue-checkpoint-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['search-meta-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['archive-reader']['schema_transitions'][0]['next_schema'] === 'archive');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next557-572 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
