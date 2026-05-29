<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => ['schema_cookie' => 572, 'tables' => ['wp_options', 'wp_navigation_rule_next525', 'wp_navigation_rule_locale_next541', 'wp_navigation_rule_locale_meta_next556', 'wp_navigation_rule_locale_receipt_next572'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_slug_next525', 'wp_navigation_rule_locale_slug_next541', 'wp_navigation_rule_locale_meta_key_next556', 'wp_navigation_rule_locale_receipt_key_next572'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 572, 'commit' => true]]],
    'temp' => ['schema_cookie' => 524, 'tables' => ['wp_theme_stage_publish_retries_next558'], 'indexes' => ['wp_theme_stage_publish_retries_key_next542'], 'temp' => true],
    'queue' => ['schema_cookie' => 560, 'tables' => ['wp_job_retry_audit_next514', 'wp_job_retry_window_next528', 'wp_job_retry_checkpoint_next544', 'wp_job_retry_checkpoint_lock_next560'], 'indexes' => ['wp_job_retry_audit_job_next520', 'wp_job_retry_window_job_next528', 'wp_job_retry_checkpoint_job_next544', 'wp_job_retry_checkpoint_lock_job_next560'], 'file' => '/srv/wp/queue-next573.sqlite'],
    'campaign' => ['schema_cookie' => 542, 'tables' => ['wp_campaign_restore_next524', 'wp_campaign_restore_meta_next545'], 'indexes' => ['wp_campaign_restore_slug_next526'], 'file' => '/srv/wp/campaign-next573.sqlite'],
    'archive' => ['schema_cookie' => 569, 'tables' => ['wp_schema_archive_next568', 'wp_schema_archive_meta_next569'], 'indexes' => ['wp_schema_archive_key_next568', 'wp_schema_archive_meta_key_next569'], 'file' => '/srv/wp/archive-next573.sqlite'],
    'audit' => ['schema_cookie' => 555, 'tables' => ['wp_schema_audit_next553'], 'indexes' => ['wp_schema_audit_key_next553'], 'file' => '/srv/wp/audit-next573.sqlite'],
];

$statements = [
    ['name' => 'nav-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_receipt_next572 INDEXED BY wp_navigation_rule_locale_receipt_key_next572 WHERE receipt_key = ?', 'active' => true],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next558 INDEXED BY wp_theme_stage_publish_retries_key_next542 WHERE cache_key = ?', 'active' => true],
    ['name' => 'queue-lock-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_lock_next560 INDEXED BY wp_job_retry_checkpoint_lock_job_next560 SET attempts = attempts + 1 WHERE job_id = ?'],
    ['name' => 'campaign-restore-reader', 'sql' => 'SELECT restore_id FROM campaign.wp_campaign_restore_next524 INDEXED BY wp_campaign_restore_slug_next526 WHERE slug = ?'],
    ['name' => 'archive-meta-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_meta_next569 INDEXED BY wp_schema_archive_meta_key_next569 WHERE meta_key = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_next553 INDEXED BY wp_schema_audit_key_next553 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_next582 INDEXED BY wp_schema_handoff_key_next582 WHERE handoff_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext573588($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 573, 'table' => 'wp_navigation_rule_locale_handoff_next573', 'indexes' => ['wp_navigation_rule_locale_handoff_key_next573'], 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_retries_key_next542', 'to' => 'wp_theme_stage_publish_retries_key_next574'],
    ['op' => 'drop_table', 'schema' => 'campaign', 'table' => 'wp_campaign_restore_meta_next545'],
    ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 576, 'table' => 'wp_job_retry_checkpoint_cursor_next576', 'indexes' => ['wp_job_retry_checkpoint_cursor_job_next576'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'audit'],
    ['op' => 'attach', 'schema' => 'handoff', 'schema_cookie' => 582, 'tables' => ['wp_schema_handoff_next582'], 'indexes' => ['wp_schema_handoff_key_next582'], 'file' => '/srv/wp/handoff-next582.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'handoff', 'schema_cookie' => 583, 'table' => 'wp_schema_handoff_meta_next583', 'indexes' => ['wp_schema_handoff_meta_key_next583'], 'commit' => true],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_schema_archive_meta_key_next569'],
    ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 587, 'table' => 'wp_job_retry_checkpoint_receipt_next587', 'indexes' => ['wp_job_retry_checkpoint_receipt_job_next587'], 'commit' => false],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 588, 'table' => 'wp_navigation_rule_locale_final_next588', 'indexes' => ['wp_navigation_rule_locale_final_key_next588'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next573-588');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next573');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next588');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next572', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['changed_schemas'] === ['temp', 'main', 'archive', 'audit', 'campaign', 'handoff', 'queue']);
    assert($plan['schema_cookies_next']['main'] === 588);
    assert($plan['schema_cookies_next']['temp'] === 525);
    assert($plan['schema_cookies_next']['queue'] === 576);
    assert($plan['schema_cookies_next']['campaign'] === 543);
    assert($plan['schema_cookies_next']['archive'] === 570);
    assert($plan['schema_cookies_next']['handoff'] === 583);
    assert($plan['search_order_next'] === ['temp', 'main', 'archive', 'campaign', 'handoff', 'queue']);
    assert(in_array('temp-retry-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('queue-lock-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['audit-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['handoff-reader']['schema_transitions'][0]['next_schema'] === 'handoff');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next573-588 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
