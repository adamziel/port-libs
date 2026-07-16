<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 828, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_final', 'wp_navigation_rule_locale_publish_final_final'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_final', 'wp_navigation_rule_locale_publish_final_key_final'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 828, 'commit' => true]]],
    'temp' => ['schema_cookie' => 832, 'tables' => ['wp_theme_stage_publish_review_notice', 'wp_theme_stage_publish_notice_notice'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 829, 'tables' => ['wp_schema_archive_done_archive'], 'indexes' => ['wp_schema_archive_done_key_archive'], 'file' => '/srv/wp/archive-archive.sqlite'],
    'audit' => ['schema_cookie' => 837, 'tables' => ['wp_schema_audit_receipt_review'], 'indexes' => ['wp_schema_audit_receipt_key_review'], 'file' => '/srv/wp/audit-archive.sqlite'],
    'handoff' => ['schema_cookie' => 825, 'tables' => ['wp_schema_handoff_receipt_handoff'], 'indexes' => ['wp_schema_handoff_receipt_key_handoff'], 'file' => '/srv/wp/handoff-archive.sqlite'],
    'publish' => ['schema_cookie' => 842, 'tables' => ['wp_schema_publish_archive', 'wp_schema_publish_done_done'], 'indexes' => ['wp_schema_publish_key_archive', 'wp_schema_publish_done_key_done'], 'file' => '/srv/wp/publish-archive.sqlite'],
    'queue' => ['schema_cookie' => 834, 'tables' => ['wp_job_retry_checkpoint_delivered_delta', 'wp_job_retry_checkpoint_archive_archive'], 'indexes' => ['wp_job_retry_checkpoint_delivered_key_delta', 'wp_job_retry_checkpoint_archive_key_archive'], 'file' => '/srv/wp/queue-archive.sqlite'],
    'report' => ['schema_cookie' => 827, 'tables' => ['wp_schema_report_report', 'wp_schema_report_meta_meta'], 'indexes' => ['wp_schema_report_key_report', 'wp_schema_report_meta_key_meta'], 'file' => '/srv/wp/report-archive.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_final INDEXED BY wp_navigation_rule_locale_publish_final_key_final WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_final INDEXED BY wp_navigation_rule_locale_publish_receipt_key_final WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_done INDEXED BY wp_schema_publish_done_key_done WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_archive INDEXED BY wp_job_retry_checkpoint_archive_key_archive SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_review INDEXED BY wp_schema_audit_receipt_key_review WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_handoff INDEXED BY wp_schema_handoff_receipt_key_handoff WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_meta INDEXED BY wp_schema_report_meta_key_meta WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_review INDEXED BY wp_schema_review_receipt_key_review WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_notice WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 830, 'table' => 'wp_navigation_rule_locale_publish_delta_delta', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_delta'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 832, 'table' => 'wp_theme_stage_publish_notice_notice', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_archive', 'to' => 'wp_job_retry_checkpoint_archive_key_renamed'],
    ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 837, 'tables' => ['wp_schema_review_receipt_review'], 'indexes' => ['wp_schema_review_receipt_key_review'], 'file' => '/srv/wp/review-review.sqlite'],
    ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_review'],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_handoff', 'to' => 'wp_schema_handoff_receipt_published'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 842, 'table' => 'wp_schema_publish_receipt_done', 'indexes' => ['wp_schema_publish_receipt_key_done'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 844, 'table' => 'wp_navigation_rule_locale_publish_final_final', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_final'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 829, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_archive', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_archive'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 844);
    assert($plan['schema_cookies_next']['temp'] === 832);
    assert($plan['schema_cookies_next']['publish'] === 842);
    assert($plan['schema_cookies_next']['review'] === 837);
    assert(!isset($plan['schema_cookies_next']['archive']));
    assert(in_array('queue-archive-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['queue-archive-writer']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['audit-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['handoff-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['review-reader']['schema_transitions'][0]['next_schema'] === 'review');
    assert($plan['stable_statements'] === ['publish-done-reader', 'report-meta-reader', 'temp-notice-reader']);

    echo "application-attach-temp-wal-schema-cache-navigation-publish self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
