<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 876, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next876', 'wp_navigation_rule_locale_publish_final_next892'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next876', 'wp_navigation_rule_locale_publish_final_key_next892'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 876, 'commit' => true]]],
    'temp' => ['schema_cookie' => 880, 'tables' => ['wp_theme_stage_publish_gate_next864', 'wp_theme_stage_publish_notice_next880'], 'indexes' => [], 'temp' => true],
    'audit' => ['schema_cookie' => 869, 'tables' => ['wp_schema_audit_replay_next869', 'wp_schema_audit_seal_next885'], 'indexes' => ['wp_schema_audit_replay_key_next869', 'wp_schema_audit_seal_key_next885'], 'file' => '/srv/wp/audit-next877.sqlite'],
    'handoff' => ['schema_cookie' => 872, 'tables' => ['wp_schema_handoff_receipt_next872'], 'indexes' => ['wp_schema_handoff_receipt_key_next872'], 'file' => '/srv/wp/handoff-next877.sqlite'],
    'publish' => ['schema_cookie' => 874, 'tables' => ['wp_schema_publish_seal_next874', 'wp_schema_publish_done_next874'], 'indexes' => ['wp_schema_publish_seal_key_next874', 'wp_schema_publish_done_key_next874'], 'file' => '/srv/wp/publish-next877.sqlite'],
    'queue' => ['schema_cookie' => 870, 'tables' => ['wp_job_retry_checkpoint_archive_next866'], 'indexes' => ['wp_job_retry_checkpoint_archive_key_next870'], 'file' => '/srv/wp/queue-next877.sqlite'],
    'rollout' => ['schema_cookie' => 872, 'tables' => ['wp_schema_rollout_receipt_next872'], 'indexes' => ['wp_schema_rollout_receipt_key_next872'], 'file' => '/srv/wp/rollout-next877.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next892 INDEXED BY wp_navigation_rule_locale_publish_final_key_next892 WHERE nav_key = ?', 'active' => true],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next874 INDEXED BY wp_schema_publish_done_key_next874 WHERE publish_key = ?'],
    ['name' => 'queue-archive-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_archive_next866 INDEXED BY wp_job_retry_checkpoint_archive_key_next870 WHERE job_id = ?'],
    ['name' => 'audit-seal-reader', 'sql' => 'SELECT seal_id FROM audit.wp_schema_audit_seal_next885 INDEXED BY wp_schema_audit_seal_key_next885 WHERE seal_key = ?'],
    ['name' => 'handoff-writer', 'sql' => 'UPDATE handoff.wp_schema_handoff_receipt_next872 INDEXED BY wp_schema_handoff_receipt_key_next872 SET accepted = 1 WHERE handoff_key = ?', 'active' => true],
    ['name' => 'rollout-reader', 'sql' => 'SELECT rollout_id FROM rollout.wp_schema_rollout_receipt_next872 INDEXED BY wp_schema_rollout_receipt_key_next872 WHERE rollout_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next888 INDEXED BY wp_schema_archive_receipt_key_next888 WHERE archive_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next880 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheReviewWindow($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 878, 'table' => 'wp_navigation_rule_locale_publish_delta_next878', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next878'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 880, 'table' => 'wp_theme_stage_publish_notice_next880', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_schema_audit_seal_key_next885', 'to' => 'wp_schema_audit_seal_key_next886'],
    ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 888, 'tables' => ['wp_schema_archive_receipt_next888'], 'indexes' => ['wp_schema_archive_receipt_key_next888'], 'file' => '/srv/wp/archive-next888.sqlite'],
    ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_archive_next866'],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next872', 'to' => 'wp_schema_handoff_receipt_next890'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 891, 'table' => 'wp_schema_publish_final_next891', 'indexes' => ['wp_schema_publish_final_key_next891'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'rollout'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 892, 'table' => 'wp_navigation_rule_locale_publish_final_next892', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next892'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 877, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next877', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next877'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 892);
    assert($plan['schema_cookies_next']['temp'] === 880);
    assert($plan['schema_cookies_next']['publish'] === 891);
    assert($plan['schema_cookies_next']['archive'] === 888);
    assert(!isset($plan['schema_cookies_next']['rollout']));
    assert(in_array('handoff-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['audit-seal-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['queue-archive-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['handoff-writer']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['rollout-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['archive-reader']['schema_transitions'][0]['next_schema'] === 'archive');
    assert($plan['stable_statements'] === ['temp-notice-reader']);

    echo "application-attach-temp-wal-schema-cache-review-window self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
