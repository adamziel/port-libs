<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => ['schema_cookie' => 956, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next956', 'wp_navigation_rule_locale_publish_gate_next960'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next956', 'wp_navigation_rule_locale_publish_gate_key_next960'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 956, 'commit' => true]]],
    'temp' => ['schema_cookie' => 960, 'tables' => ['wp_theme_stage_publish_notice_next944', 'wp_theme_stage_publish_token_next960'], 'indexes' => [], 'temp' => true],
    'audit' => ['schema_cookie' => 950, 'tables' => ['wp_schema_audit_replay_next917', 'wp_schema_audit_seal_next949'], 'indexes' => ['wp_schema_audit_replay_key_next917', 'wp_schema_audit_seal_key_next950'], 'file' => '/srv/wp/audit-next957.sqlite'],
    'archive' => ['schema_cookie' => 952, 'tables' => ['wp_schema_archive_receipt_next952'], 'indexes' => ['wp_schema_archive_receipt_key_next952'], 'file' => '/srv/wp/archive-next952.sqlite'],
    'handoff' => ['schema_cookie' => 954, 'tables' => ['wp_schema_handoff_receipt_next954'], 'indexes' => ['wp_schema_handoff_receipt_key_next920'], 'file' => '/srv/wp/handoff-next957.sqlite'],
    'publish' => ['schema_cookie' => 955, 'tables' => ['wp_schema_publish_final_next955'], 'indexes' => ['wp_schema_publish_final_key_next955'], 'file' => '/srv/wp/publish-next957.sqlite'],
    'queue' => ['schema_cookie' => 919, 'tables' => ['wp_job_retry_checkpoint_ready_next919'], 'indexes' => ['wp_job_retry_checkpoint_ready_key_next919'], 'file' => '/srv/wp/queue-next957.sqlite'],
    'report' => ['schema_cookie' => 957, 'tables' => ['wp_schema_report_receipt_next957'], 'indexes' => ['wp_schema_report_receipt_key_next957'], 'file' => '/srv/wp/report-next957.sqlite'],
];

$statements = [
    ['name' => 'main-gate-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_gate_next960 INDEXED BY wp_navigation_rule_locale_publish_gate_key_next960 WHERE nav_key = ?', 'active' => true],
    ['name' => 'audit-seal-reader', 'sql' => 'SELECT seal_id FROM audit.wp_schema_audit_seal_next949 INDEXED BY wp_schema_audit_seal_key_next950 WHERE seal_key = ?'],
    ['name' => 'archive-receipt-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next952 INDEXED BY wp_schema_archive_receipt_key_next952 WHERE archive_key = ?'],
    ['name' => 'handoff-writer', 'sql' => 'UPDATE handoff.wp_schema_handoff_receipt_next954 INDEXED BY wp_schema_handoff_receipt_key_next920 SET accepted = 1 WHERE handoff_key = ?', 'active' => true],
    ['name' => 'publish-final-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_final_next955 INDEXED BY wp_schema_publish_final_key_next955 WHERE publish_key = ?'],
    ['name' => 'queue-ready-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_ready_next919 INDEXED BY wp_job_retry_checkpoint_ready_key_next919 WHERE job_id = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next968 INDEXED BY wp_schema_review_receipt_key_next968 WHERE review_key = ?'],
    ['name' => 'report-reader', 'sql' => 'SELECT report_id FROM report.wp_schema_report_receipt_next957 INDEXED BY wp_schema_report_receipt_key_next957 WHERE report_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext957972($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 962, 'table' => 'wp_navigation_rule_locale_publish_batch_next962', 'indexes' => ['wp_navigation_rule_locale_publish_batch_key_next962'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 964, 'table' => 'wp_theme_stage_publish_token_next964', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'archive', 'from' => 'wp_schema_archive_receipt_key_next952', 'to' => 'wp_schema_archive_receipt_key_next966'],
    ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 968, 'tables' => ['wp_schema_review_receipt_next968'], 'indexes' => ['wp_schema_review_receipt_key_next968'], 'file' => '/srv/wp/review-next968.sqlite'],
    ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_ready_next919'],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next954', 'to' => 'wp_schema_handoff_receipt_next970'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 971, 'table' => 'wp_schema_publish_done_next971', 'indexes' => ['wp_schema_publish_done_key_next971'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'audit'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 972, 'table' => 'wp_navigation_rule_locale_publish_final_next972', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next972'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 957, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next957', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next957'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next957-972');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next957');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next972');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next956', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 972);
    assert($plan['schema_cookies_next']['temp'] === 964);
    assert($plan['schema_cookies_next']['publish'] === 971);
    assert($plan['schema_cookies_next']['review'] === 968);
    assert(!isset($plan['schema_cookies_next']['audit']));
    assert(in_array('handoff-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['audit-seal-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['archive-receipt-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['queue-ready-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['handoff-writer']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['review-reader']['schema_transitions'][0]['next_schema'] === 'review');
    assert($plan['stable_statements'] === ['report-reader']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next957-972 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
