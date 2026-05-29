<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => ['schema_cookie' => 892, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next892', 'wp_navigation_rule_locale_publish_final_next908'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next892', 'wp_navigation_rule_locale_publish_final_key_next908'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 892, 'commit' => true]]],
    'temp' => ['schema_cookie' => 896, 'tables' => ['wp_theme_stage_publish_gate_next880', 'wp_theme_stage_publish_notice_next896'], 'indexes' => [], 'temp' => true],
    'audit' => ['schema_cookie' => 885, 'tables' => ['wp_schema_audit_replay_next885', 'wp_schema_audit_seal_next901'], 'indexes' => ['wp_schema_audit_replay_key_next885', 'wp_schema_audit_seal_key_next901'], 'file' => '/srv/wp/audit-next893.sqlite'],
    'handoff' => ['schema_cookie' => 888, 'tables' => ['wp_schema_handoff_receipt_next888'], 'indexes' => ['wp_schema_handoff_receipt_key_next888'], 'file' => '/srv/wp/handoff-next893.sqlite'],
    'publish' => ['schema_cookie' => 890, 'tables' => ['wp_schema_publish_seal_next890', 'wp_schema_publish_done_next890'], 'indexes' => ['wp_schema_publish_seal_key_next890', 'wp_schema_publish_done_key_next890'], 'file' => '/srv/wp/publish-next893.sqlite'],
    'queue' => ['schema_cookie' => 886, 'tables' => ['wp_job_retry_checkpoint_archive_next882'], 'indexes' => ['wp_job_retry_checkpoint_archive_key_next886'], 'file' => '/srv/wp/queue-next893.sqlite'],
    'rollout' => ['schema_cookie' => 888, 'tables' => ['wp_schema_rollout_receipt_next888'], 'indexes' => ['wp_schema_rollout_receipt_key_next888'], 'file' => '/srv/wp/rollout-next893.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next908 INDEXED BY wp_navigation_rule_locale_publish_final_key_next908 WHERE nav_key = ?', 'active' => true],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next890 INDEXED BY wp_schema_publish_done_key_next890 WHERE publish_key = ?'],
    ['name' => 'queue-archive-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_archive_next882 INDEXED BY wp_job_retry_checkpoint_archive_key_next886 WHERE job_id = ?'],
    ['name' => 'audit-seal-reader', 'sql' => 'SELECT seal_id FROM audit.wp_schema_audit_seal_next901 INDEXED BY wp_schema_audit_seal_key_next901 WHERE seal_key = ?'],
    ['name' => 'handoff-writer', 'sql' => 'UPDATE handoff.wp_schema_handoff_receipt_next888 INDEXED BY wp_schema_handoff_receipt_key_next888 SET accepted = 1 WHERE handoff_key = ?', 'active' => true],
    ['name' => 'rollout-reader', 'sql' => 'SELECT rollout_id FROM rollout.wp_schema_rollout_receipt_next888 INDEXED BY wp_schema_rollout_receipt_key_next888 WHERE rollout_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next904 INDEXED BY wp_schema_archive_receipt_key_next904 WHERE archive_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next896 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext893908($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 894, 'table' => 'wp_navigation_rule_locale_publish_delta_next894', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next894'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 896, 'table' => 'wp_theme_stage_publish_notice_next896', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_schema_audit_seal_key_next901', 'to' => 'wp_schema_audit_seal_key_next902'],
    ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 904, 'tables' => ['wp_schema_archive_receipt_next904'], 'indexes' => ['wp_schema_archive_receipt_key_next904'], 'file' => '/srv/wp/archive-next904.sqlite'],
    ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_archive_next882'],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next888', 'to' => 'wp_schema_handoff_receipt_next906'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 907, 'table' => 'wp_schema_publish_final_next907', 'indexes' => ['wp_schema_publish_final_key_next907'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'rollout'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 908, 'table' => 'wp_navigation_rule_locale_publish_final_next908', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next908'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 893, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next893', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next893'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next893-908');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next893');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next908');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next892', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 908);
    assert($plan['schema_cookies_next']['temp'] === 896);
    assert($plan['schema_cookies_next']['publish'] === 907);
    assert($plan['schema_cookies_next']['archive'] === 904);
    assert(!isset($plan['schema_cookies_next']['rollout']));
    assert(in_array('handoff-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['audit-seal-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['queue-archive-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['handoff-writer']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['rollout-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['archive-reader']['schema_transitions'][0]['next_schema'] === 'archive');
    assert($plan['stable_statements'] === ['temp-notice-reader']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next893-908 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
