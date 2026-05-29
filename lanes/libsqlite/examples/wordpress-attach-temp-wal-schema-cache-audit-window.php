<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => ['schema_cookie' => 924, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next924', 'wp_navigation_rule_locale_publish_final_next940'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next924', 'wp_navigation_rule_locale_publish_final_key_next940'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 924, 'commit' => true]]],
    'temp' => ['schema_cookie' => 928, 'tables' => ['wp_theme_stage_publish_gate_next912', 'wp_theme_stage_publish_notice_next928'], 'indexes' => [], 'temp' => true],
    'audit' => ['schema_cookie' => 917, 'tables' => ['wp_schema_audit_replay_next917', 'wp_schema_audit_seal_next933'], 'indexes' => ['wp_schema_audit_replay_key_next917', 'wp_schema_audit_seal_key_next933'], 'file' => '/srv/wp/audit-next925.sqlite'],
    'handoff' => ['schema_cookie' => 920, 'tables' => ['wp_schema_handoff_receipt_next920'], 'indexes' => ['wp_schema_handoff_receipt_key_next920'], 'file' => '/srv/wp/handoff-next925.sqlite'],
    'publish' => ['schema_cookie' => 922, 'tables' => ['wp_schema_publish_seal_next922', 'wp_schema_publish_done_next922'], 'indexes' => ['wp_schema_publish_seal_key_next922', 'wp_schema_publish_done_key_next922'], 'file' => '/srv/wp/publish-next925.sqlite'],
    'queue' => ['schema_cookie' => 918, 'tables' => ['wp_job_retry_checkpoint_archive_next914'], 'indexes' => ['wp_job_retry_checkpoint_archive_key_next918'], 'file' => '/srv/wp/queue-next925.sqlite'],
    'rollout' => ['schema_cookie' => 920, 'tables' => ['wp_schema_rollout_receipt_next920'], 'indexes' => ['wp_schema_rollout_receipt_key_next920'], 'file' => '/srv/wp/rollout-next925.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next940 INDEXED BY wp_navigation_rule_locale_publish_final_key_next940 WHERE nav_key = ?', 'active' => true],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next922 INDEXED BY wp_schema_publish_done_key_next922 WHERE publish_key = ?'],
    ['name' => 'queue-archive-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_archive_next914 INDEXED BY wp_job_retry_checkpoint_archive_key_next918 WHERE job_id = ?'],
    ['name' => 'audit-seal-reader', 'sql' => 'SELECT seal_id FROM audit.wp_schema_audit_seal_next933 INDEXED BY wp_schema_audit_seal_key_next933 WHERE seal_key = ?'],
    ['name' => 'handoff-writer', 'sql' => 'UPDATE handoff.wp_schema_handoff_receipt_next920 INDEXED BY wp_schema_handoff_receipt_key_next920 SET accepted = 1 WHERE handoff_key = ?', 'active' => true],
    ['name' => 'rollout-reader', 'sql' => 'SELECT rollout_id FROM rollout.wp_schema_rollout_receipt_next920 INDEXED BY wp_schema_rollout_receipt_key_next920 WHERE rollout_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next936 INDEXED BY wp_schema_archive_receipt_key_next936 WHERE archive_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next928 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::schemaCacheAuditWindow($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 926, 'table' => 'wp_navigation_rule_locale_publish_delta_next926', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next926'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 928, 'table' => 'wp_theme_stage_publish_notice_next928', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_schema_audit_seal_key_next933', 'to' => 'wp_schema_audit_seal_key_next934'],
    ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 936, 'tables' => ['wp_schema_archive_receipt_next936'], 'indexes' => ['wp_schema_archive_receipt_key_next936'], 'file' => '/srv/wp/archive-next936.sqlite'],
    ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_archive_next914'],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next920', 'to' => 'wp_schema_handoff_receipt_next938'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 939, 'table' => 'wp_schema_publish_final_next939', 'indexes' => ['wp_schema_publish_final_key_next939'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'rollout'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 940, 'table' => 'wp_navigation_rule_locale_publish_final_next940', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next940'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 925, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next925', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next925'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next925-940');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next925');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next940');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next924', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 940);
    assert($plan['schema_cookies_next']['temp'] === 928);
    assert($plan['schema_cookies_next']['publish'] === 939);
    assert($plan['schema_cookies_next']['archive'] === 936);
    assert(!isset($plan['schema_cookies_next']['rollout']));
    assert(in_array('handoff-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['audit-seal-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['queue-archive-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['handoff-writer']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['rollout-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['archive-reader']['schema_transitions'][0]['next_schema'] === 'archive');
    assert($plan['stable_statements'] === ['temp-notice-reader']);

    echo "wordpress-attach-temp-wal-schema-cache-audit-window self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
