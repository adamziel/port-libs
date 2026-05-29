<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 860, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next860', 'wp_navigation_rule_locale_publish_receipt_next876'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next860', 'wp_navigation_rule_locale_publish_receipt_key_next876'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 860, 'commit' => true]]],
    'temp' => ['schema_cookie' => 864, 'tables' => ['wp_theme_stage_publish_notice_next848', 'wp_theme_stage_publish_gate_next864'], 'indexes' => [], 'temp' => true],
    'audit' => ['schema_cookie' => 853, 'tables' => ['wp_schema_audit_receipt_next853', 'wp_schema_audit_replay_next869'], 'indexes' => ['wp_schema_audit_receipt_key_next853', 'wp_schema_audit_replay_key_next869'], 'file' => '/srv/wp/audit-next861.sqlite'],
    'handoff' => ['schema_cookie' => 856, 'tables' => ['wp_schema_handoff_receipt_next856'], 'indexes' => ['wp_schema_handoff_receipt_key_next856'], 'file' => '/srv/wp/handoff-next861.sqlite'],
    'publish' => ['schema_cookie' => 858, 'tables' => ['wp_schema_publish_done_next858', 'wp_schema_publish_receipt_next858'], 'indexes' => ['wp_schema_publish_done_key_next858', 'wp_schema_publish_receipt_key_next858'], 'file' => '/srv/wp/publish-next861.sqlite'],
    'queue' => ['schema_cookie' => 854, 'tables' => ['wp_job_retry_checkpoint_archive_next850'], 'indexes' => ['wp_job_retry_checkpoint_archive_key_next854'], 'file' => '/srv/wp/queue-next861.sqlite'],
    'review' => ['schema_cookie' => 853, 'tables' => ['wp_schema_review_receipt_next853'], 'indexes' => ['wp_schema_review_receipt_key_next853'], 'file' => '/srv/wp/review-next861.sqlite'],
];

$statements = [
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next876 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next876 WHERE receipt_key = ?', 'active' => true],
    ['name' => 'publish-receipt-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_receipt_next858 INDEXED BY wp_schema_publish_receipt_key_next858 WHERE publish_key = ?'],
    ['name' => 'queue-archive-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_archive_next850 INDEXED BY wp_job_retry_checkpoint_archive_key_next854 WHERE job_id = ?'],
    ['name' => 'audit-replay-reader', 'sql' => 'SELECT replay_id FROM audit.wp_schema_audit_replay_next869 INDEXED BY wp_schema_audit_replay_key_next869 WHERE replay_key = ?'],
    ['name' => 'handoff-writer', 'sql' => 'UPDATE handoff.wp_schema_handoff_receipt_next856 INDEXED BY wp_schema_handoff_receipt_key_next856 SET accepted = 1 WHERE handoff_key = ?', 'active' => true],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next853 INDEXED BY wp_schema_review_receipt_key_next853 WHERE review_key = ?'],
    ['name' => 'rollout-reader', 'sql' => 'SELECT rollout_id FROM rollout.wp_schema_rollout_receipt_next872 INDEXED BY wp_schema_rollout_receipt_key_next872 WHERE rollout_key = ?'],
    ['name' => 'temp-gate-reader', 'sql' => 'SELECT gate_id FROM temp.wp_theme_stage_publish_gate_next864 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 862, 'table' => 'wp_navigation_rule_locale_publish_delta_next862', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next862'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 864, 'table' => 'wp_theme_stage_publish_gate_next864', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_schema_audit_replay_key_next869', 'to' => 'wp_schema_audit_replay_key_next870'],
    ['op' => 'attach', 'schema' => 'rollout', 'schema_cookie' => 872, 'tables' => ['wp_schema_rollout_receipt_next872'], 'indexes' => ['wp_schema_rollout_receipt_key_next872'], 'file' => '/srv/wp/rollout-next872.sqlite'],
    ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_archive_next850'],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next856', 'to' => 'wp_schema_handoff_receipt_next874'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 875, 'table' => 'wp_schema_publish_seal_next875', 'indexes' => ['wp_schema_publish_seal_key_next875'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'review'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 876, 'table' => 'wp_navigation_rule_locale_publish_receipt_next876', 'indexes' => ['wp_navigation_rule_locale_publish_receipt_key_next876'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 861, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next861', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next861'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 876);
    assert($plan['schema_cookies_next']['temp'] === 864);
    assert($plan['schema_cookies_next']['publish'] === 875);
    assert($plan['schema_cookies_next']['rollout'] === 872);
    assert(!isset($plan['schema_cookies_next']['review']));
    assert(in_array('handoff-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['audit-replay-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['queue-archive-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['handoff-writer']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['review-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['rollout-reader']['schema_transitions'][0]['next_schema'] === 'rollout');
    assert($plan['stable_statements'] === ['temp-gate-reader']);

    echo "wordpress-attach-temp-wal-schema-cache-rollout-window self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
