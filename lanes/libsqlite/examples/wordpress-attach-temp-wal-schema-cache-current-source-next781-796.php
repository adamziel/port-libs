<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => ['schema_cookie' => 780, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next780', 'wp_navigation_rule_locale_publish_final_next780'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next780', 'wp_navigation_rule_locale_publish_final_key_next780'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 780, 'commit' => true]]],
    'temp' => ['schema_cookie' => 784, 'tables' => ['wp_theme_stage_publish_review_next784', 'wp_theme_stage_publish_notice_next784'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 781, 'tables' => ['wp_schema_archive_done_next781'], 'indexes' => ['wp_schema_archive_done_key_next781'], 'file' => '/srv/wp/archive-next781.sqlite'],
    'audit' => ['schema_cookie' => 789, 'tables' => ['wp_schema_audit_receipt_next789'], 'indexes' => ['wp_schema_audit_receipt_key_next789'], 'file' => '/srv/wp/audit-next781.sqlite'],
    'handoff' => ['schema_cookie' => 777, 'tables' => ['wp_schema_handoff_receipt_next777'], 'indexes' => ['wp_schema_handoff_receipt_key_next777'], 'file' => '/srv/wp/handoff-next781.sqlite'],
    'publish' => ['schema_cookie' => 794, 'tables' => ['wp_schema_publish_next781', 'wp_schema_publish_done_next794'], 'indexes' => ['wp_schema_publish_key_next786', 'wp_schema_publish_done_key_next794'], 'file' => '/srv/wp/publish-next781.sqlite'],
    'queue' => ['schema_cookie' => 786, 'tables' => ['wp_job_retry_checkpoint_delivered_next782', 'wp_job_retry_checkpoint_archive_next786'], 'indexes' => ['wp_job_retry_checkpoint_delivered_key_next782', 'wp_job_retry_checkpoint_archive_key_next786'], 'file' => '/srv/wp/queue-next781.sqlite'],
    'report' => ['schema_cookie' => 779, 'tables' => ['wp_schema_report_next730', 'wp_schema_report_meta_next779'], 'indexes' => ['wp_schema_report_key_next693', 'wp_schema_report_meta_key_next779'], 'file' => '/srv/wp/report-next781.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next780 INDEXED BY wp_navigation_rule_locale_publish_final_key_next780 WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next780 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next780 WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next794 INDEXED BY wp_schema_publish_done_key_next794 WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_next786 INDEXED BY wp_job_retry_checkpoint_archive_key_next786 SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next789 INDEXED BY wp_schema_audit_receipt_key_next789 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next777 INDEXED BY wp_schema_handoff_receipt_key_next777 WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_next779 INDEXED BY wp_schema_report_meta_key_next779 WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next789 INDEXED BY wp_schema_review_receipt_key_next789 WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next784 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext781796($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 782, 'table' => 'wp_navigation_rule_locale_publish_delta_next782', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next782'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 784, 'table' => 'wp_theme_stage_publish_notice_next784', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_next786', 'to' => 'wp_job_retry_checkpoint_archive_key_next790'],
    ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 789, 'tables' => ['wp_schema_review_receipt_next789'], 'indexes' => ['wp_schema_review_receipt_key_next789'], 'file' => '/srv/wp/review-next789.sqlite'],
    ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_next789'],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next777', 'to' => 'wp_schema_handoff_receipt_next792'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 794, 'table' => 'wp_schema_publish_receipt_next794', 'indexes' => ['wp_schema_publish_receipt_key_next794'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 796, 'table' => 'wp_navigation_rule_locale_publish_final_next796', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next796'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 781, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next781', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next781'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next781-796');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next781');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next796');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next780', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 796);
    assert($plan['schema_cookies_next']['temp'] === 784);
    assert($plan['schema_cookies_next']['publish'] === 794);
    assert($plan['schema_cookies_next']['review'] === 789);
    assert(!isset($plan['schema_cookies_next']['archive']));
    assert(in_array('queue-archive-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['queue-archive-writer']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['audit-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['handoff-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['review-reader']['schema_transitions'][0]['next_schema'] === 'review');
    assert($plan['stable_statements'] === ['publish-done-reader', 'report-meta-reader', 'temp-notice-reader']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next781-796 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
