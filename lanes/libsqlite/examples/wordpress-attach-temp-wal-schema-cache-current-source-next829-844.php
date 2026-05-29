<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => ['schema_cookie' => 828, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next844', 'wp_navigation_rule_locale_publish_final_next844'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next844', 'wp_navigation_rule_locale_publish_final_key_next844'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 828, 'commit' => true]]],
    'temp' => ['schema_cookie' => 832, 'tables' => ['wp_theme_stage_publish_review_next832', 'wp_theme_stage_publish_notice_next832'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 829, 'tables' => ['wp_schema_archive_done_next829'], 'indexes' => ['wp_schema_archive_done_key_next829'], 'file' => '/srv/wp/archive-next829.sqlite'],
    'audit' => ['schema_cookie' => 837, 'tables' => ['wp_schema_audit_receipt_next837'], 'indexes' => ['wp_schema_audit_receipt_key_next837'], 'file' => '/srv/wp/audit-next829.sqlite'],
    'handoff' => ['schema_cookie' => 825, 'tables' => ['wp_schema_handoff_receipt_next809'], 'indexes' => ['wp_schema_handoff_receipt_key_next809'], 'file' => '/srv/wp/handoff-next829.sqlite'],
    'publish' => ['schema_cookie' => 842, 'tables' => ['wp_schema_publish_next829', 'wp_schema_publish_done_next842'], 'indexes' => ['wp_schema_publish_key_next834', 'wp_schema_publish_done_key_next842'], 'file' => '/srv/wp/publish-next829.sqlite'],
    'queue' => ['schema_cookie' => 834, 'tables' => ['wp_job_retry_checkpoint_delivered_next830', 'wp_job_retry_checkpoint_archive_next834'], 'indexes' => ['wp_job_retry_checkpoint_delivered_key_next830', 'wp_job_retry_checkpoint_archive_key_next834'], 'file' => '/srv/wp/queue-next829.sqlite'],
    'report' => ['schema_cookie' => 827, 'tables' => ['wp_schema_report_next746', 'wp_schema_report_meta_next811'], 'indexes' => ['wp_schema_report_key_next709', 'wp_schema_report_meta_key_next811'], 'file' => '/srv/wp/report-next829.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next844 INDEXED BY wp_navigation_rule_locale_publish_final_key_next844 WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next844 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next844 WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next842 INDEXED BY wp_schema_publish_done_key_next842 WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_next834 INDEXED BY wp_job_retry_checkpoint_archive_key_next834 SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next837 INDEXED BY wp_schema_audit_receipt_key_next837 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next809 INDEXED BY wp_schema_handoff_receipt_key_next809 WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_next811 INDEXED BY wp_schema_report_meta_key_next811 WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next837 INDEXED BY wp_schema_review_receipt_key_next837 WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next832 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext829844($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 830, 'table' => 'wp_navigation_rule_locale_publish_delta_next830', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next830'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 832, 'table' => 'wp_theme_stage_publish_notice_next832', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_next834', 'to' => 'wp_job_retry_checkpoint_archive_key_next838'],
    ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 837, 'tables' => ['wp_schema_review_receipt_next837'], 'indexes' => ['wp_schema_review_receipt_key_next837'], 'file' => '/srv/wp/review-next837.sqlite'],
    ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_next837'],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next809', 'to' => 'wp_schema_handoff_receipt_next840'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 842, 'table' => 'wp_schema_publish_receipt_next842', 'indexes' => ['wp_schema_publish_receipt_key_next842'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 844, 'table' => 'wp_navigation_rule_locale_publish_final_next844', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next844'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 829, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next829', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next829'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next829-844');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next829');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next844');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next828', $plan['dependencies'], true));
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

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next829-844 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
