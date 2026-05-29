<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 812, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_final_navigation', 'wp_navigation_rule_locale_publish_final_final_navigation'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_final_navigation', 'wp_navigation_rule_locale_publish_final_key_final_navigation'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 812, 'commit' => true]]],
    'temp' => ['schema_cookie' => 816, 'tables' => ['wp_theme_stage_publish_review_final_temp', 'wp_theme_stage_publish_notice_final_temp'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 813, 'tables' => ['wp_schema_archive_done_final_archive'], 'indexes' => ['wp_schema_archive_done_key_final_archive'], 'file' => '/srv/wp/archive-final_archive.sqlite'],
    'audit' => ['schema_cookie' => 821, 'tables' => ['wp_schema_audit_receipt_final_audit'], 'indexes' => ['wp_schema_audit_receipt_key_final_audit'], 'file' => '/srv/wp/audit-final_archive.sqlite'],
    'handoff' => ['schema_cookie' => 809, 'tables' => ['wp_schema_handoff_receipt_prior_handoff'], 'indexes' => ['wp_schema_handoff_receipt_key_prior_handoff'], 'file' => '/srv/wp/handoff-final_archive.sqlite'],
    'publish' => ['schema_cookie' => 826, 'tables' => ['wp_schema_publish_final_archive', 'wp_schema_publish_done_final_publish'], 'indexes' => ['wp_schema_publish_key_final_queue', 'wp_schema_publish_done_key_final_publish'], 'file' => '/srv/wp/publish-final_archive.sqlite'],
    'queue' => ['schema_cookie' => 818, 'tables' => ['wp_job_retry_checkpoint_delivered_final_delta', 'wp_job_retry_checkpoint_archive_final_queue'], 'indexes' => ['wp_job_retry_checkpoint_delivered_key_final_delta', 'wp_job_retry_checkpoint_archive_key_final_queue'], 'file' => '/srv/wp/queue-final_archive.sqlite'],
    'report' => ['schema_cookie' => 811, 'tables' => ['wp_schema_report_prior_report', 'wp_schema_report_meta_prior_report'], 'indexes' => ['wp_schema_report_key_prior_report', 'wp_schema_report_meta_key_prior_report'], 'file' => '/srv/wp/report-final_archive.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_final_navigation INDEXED BY wp_navigation_rule_locale_publish_final_key_final_navigation WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_final_navigation INDEXED BY wp_navigation_rule_locale_publish_receipt_key_final_navigation WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_final_publish INDEXED BY wp_schema_publish_done_key_final_publish WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_final_queue INDEXED BY wp_job_retry_checkpoint_archive_key_final_queue SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_final_audit INDEXED BY wp_schema_audit_receipt_key_final_audit WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_prior_handoff INDEXED BY wp_schema_handoff_receipt_key_prior_handoff WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_prior_report INDEXED BY wp_schema_report_meta_key_prior_report WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_final_audit INDEXED BY wp_schema_review_receipt_key_final_audit WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_final_temp WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 814, 'table' => 'wp_navigation_rule_locale_publish_delta_final_delta', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_final_delta'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 816, 'table' => 'wp_theme_stage_publish_notice_final_temp', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_final_queue', 'to' => 'wp_job_retry_checkpoint_archive_key_final_queue_replacement'],
    ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 821, 'tables' => ['wp_schema_review_receipt_final_audit'], 'indexes' => ['wp_schema_review_receipt_key_final_audit'], 'file' => '/srv/wp/review-final_audit.sqlite'],
    ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_final_audit'],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_prior_handoff', 'to' => 'wp_schema_handoff_receipt_final_handoff'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 826, 'table' => 'wp_schema_publish_receipt_final_publish', 'indexes' => ['wp_schema_publish_receipt_key_final_publish'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 828, 'table' => 'wp_navigation_rule_locale_publish_final_final_navigation', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_final_navigation'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 813, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_final_archive', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_final_archive'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 828);
    assert($plan['schema_cookies_next']['temp'] === 816);
    assert($plan['schema_cookies_next']['publish'] === 826);
    assert($plan['schema_cookies_next']['review'] === 821);
    assert(!isset($plan['schema_cookies_next']['archive']));
    assert(in_array('queue-archive-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['queue-archive-writer']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['audit-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['handoff-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['review-reader']['schema_transitions'][0]['next_schema'] === 'review');
    assert($plan['stable_statements'] === ['publish-done-reader', 'report-meta-reader', 'temp-notice-reader']);

    echo "wordpress-attach-temp-wal-schema-cache-final-stability-window self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
