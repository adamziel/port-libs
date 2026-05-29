<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => ['schema_cookie' => 972, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_batch_next962', 'wp_navigation_rule_locale_publish_final_next972', 'wp_navigation_rule_locale_publish_gate_next960'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_batch_key_next962', 'wp_navigation_rule_locale_publish_final_key_next972', 'wp_navigation_rule_locale_publish_gate_key_next960'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 972, 'commit' => true]]],
    'temp' => ['schema_cookie' => 964, 'tables' => ['wp_theme_stage_publish_notice_next944', 'wp_theme_stage_publish_token_next964'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 953, 'tables' => ['wp_schema_archive_receipt_next952'], 'indexes' => ['wp_schema_archive_receipt_key_next966'], 'file' => '/srv/wp/archive-next973.sqlite'],
    'handoff' => ['schema_cookie' => 955, 'tables' => ['wp_schema_handoff_receipt_next970'], 'indexes' => ['wp_schema_handoff_receipt_key_next920'], 'file' => '/srv/wp/handoff-next973.sqlite'],
    'publish' => ['schema_cookie' => 971, 'tables' => ['wp_schema_publish_done_next971', 'wp_schema_publish_final_next955'], 'indexes' => ['wp_schema_publish_done_key_next971', 'wp_schema_publish_final_key_next955'], 'file' => '/srv/wp/publish-next973.sqlite'],
    'queue' => ['schema_cookie' => 920, 'tables' => ['wp_job_retry_checkpoint_ready_next919'], 'indexes' => ['wp_job_retry_checkpoint_ready_key_next919'], 'file' => '/srv/wp/queue-next973.sqlite'],
    'report' => ['schema_cookie' => 957, 'tables' => ['wp_schema_report_receipt_next957'], 'indexes' => ['wp_schema_report_receipt_key_next957'], 'file' => '/srv/wp/report-next973.sqlite'],
    'review' => ['schema_cookie' => 968, 'tables' => ['wp_schema_review_receipt_next968'], 'indexes' => ['wp_schema_review_receipt_key_next968'], 'file' => '/srv/wp/review-next973.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next972 INDEXED BY wp_navigation_rule_locale_publish_final_key_next972 WHERE nav_key = ?', 'active' => true],
    ['name' => 'archive-receipt-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next952 INDEXED BY wp_schema_archive_receipt_key_next966 WHERE archive_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next970 INDEXED BY wp_schema_handoff_receipt_key_next920 WHERE handoff_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next971 INDEXED BY wp_schema_publish_done_key_next971 WHERE publish_key = ?'],
    ['name' => 'queue-ready-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_ready_next919 INDEXED BY wp_job_retry_checkpoint_ready_key_next919 SET locked = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'report-reader', 'sql' => 'SELECT report_id FROM report.wp_schema_report_receipt_next957 INDEXED BY wp_schema_report_receipt_key_next957 WHERE report_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next968 INDEXED BY wp_schema_review_receipt_key_next968 WHERE review_key = ?'],
    ['name' => 'verify-reader', 'sql' => 'SELECT verify_id FROM verify.wp_schema_verify_receipt_next984 INDEXED BY wp_schema_verify_receipt_key_next984 WHERE verify_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext973988($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 976, 'table' => 'wp_navigation_rule_locale_publish_batch_next976', 'indexes' => ['wp_navigation_rule_locale_publish_batch_key_next976'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 980, 'table' => 'wp_theme_stage_publish_token_next980', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'review', 'from' => 'wp_schema_review_receipt_key_next968', 'to' => 'wp_schema_review_receipt_key_next982'],
    ['op' => 'attach', 'schema' => 'verify', 'schema_cookie' => 984, 'tables' => ['wp_schema_verify_receipt_next984'], 'indexes' => ['wp_schema_verify_receipt_key_next984'], 'file' => '/srv/wp/verify-next984.sqlite'],
    ['op' => 'detach', 'schema' => 'report'],
    ['op' => 'rename_table', 'schema' => 'archive', 'from' => 'wp_schema_archive_receipt_next952', 'to' => 'wp_schema_archive_receipt_next986'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 987, 'table' => 'wp_schema_publish_done_next987', 'indexes' => ['wp_schema_publish_done_key_next987'], 'commit' => true],
    ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_ready_next919'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 988, 'table' => 'wp_navigation_rule_locale_publish_final_next988', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next988'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'verify', 'schema_cookie' => 973, 'table' => 'wp_schema_verify_uncommitted_next973', 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next973-988');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next973');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next988');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next972', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 988);
    assert($plan['schema_cookies_next']['temp'] === 980);
    assert($plan['schema_cookies_next']['publish'] === 987);
    assert($plan['schema_cookies_next']['review'] === 969);
    assert($plan['schema_cookies_next']['verify'] === 984);
    assert(!isset($plan['schema_cookies_next']['report']));
    assert(in_array('queue-ready-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['archive-receipt-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['queue-ready-writer']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['report-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['review-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['verify-reader']['schema_transitions'][0]['next_schema'] === 'verify');
    assert($plan['stable_statements'] === ['handoff-reader']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next973-988 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
