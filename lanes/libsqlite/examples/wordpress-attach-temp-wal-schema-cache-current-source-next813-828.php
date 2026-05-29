<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => ['schema_cookie' => 812, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next828', 'wp_navigation_rule_locale_publish_final_next828'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next828', 'wp_navigation_rule_locale_publish_final_key_next828'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 812, 'commit' => true]]],
    'temp' => ['schema_cookie' => 816, 'tables' => ['wp_theme_stage_publish_review_next816', 'wp_theme_stage_publish_notice_next816'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 813, 'tables' => ['wp_schema_archive_done_next813'], 'indexes' => ['wp_schema_archive_done_key_next813'], 'file' => '/srv/wp/archive-next813.sqlite'],
    'audit' => ['schema_cookie' => 821, 'tables' => ['wp_schema_audit_receipt_next821'], 'indexes' => ['wp_schema_audit_receipt_key_next821'], 'file' => '/srv/wp/audit-next813.sqlite'],
    'handoff' => ['schema_cookie' => 809, 'tables' => ['wp_schema_handoff_receipt_next793'], 'indexes' => ['wp_schema_handoff_receipt_key_next793'], 'file' => '/srv/wp/handoff-next813.sqlite'],
    'publish' => ['schema_cookie' => 826, 'tables' => ['wp_schema_publish_next813', 'wp_schema_publish_done_next826'], 'indexes' => ['wp_schema_publish_key_next818', 'wp_schema_publish_done_key_next826'], 'file' => '/srv/wp/publish-next813.sqlite'],
    'queue' => ['schema_cookie' => 818, 'tables' => ['wp_job_retry_checkpoint_delivered_next814', 'wp_job_retry_checkpoint_archive_next818'], 'indexes' => ['wp_job_retry_checkpoint_delivered_key_next814', 'wp_job_retry_checkpoint_archive_key_next818'], 'file' => '/srv/wp/queue-next813.sqlite'],
    'report' => ['schema_cookie' => 811, 'tables' => ['wp_schema_report_next730', 'wp_schema_report_meta_next795'], 'indexes' => ['wp_schema_report_key_next693', 'wp_schema_report_meta_key_next795'], 'file' => '/srv/wp/report-next813.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next828 INDEXED BY wp_navigation_rule_locale_publish_final_key_next828 WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next828 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next828 WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next826 INDEXED BY wp_schema_publish_done_key_next826 WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_next818 INDEXED BY wp_job_retry_checkpoint_archive_key_next818 SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next821 INDEXED BY wp_schema_audit_receipt_key_next821 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next793 INDEXED BY wp_schema_handoff_receipt_key_next793 WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_next795 INDEXED BY wp_schema_report_meta_key_next795 WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next821 INDEXED BY wp_schema_review_receipt_key_next821 WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next816 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext813828($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 814, 'table' => 'wp_navigation_rule_locale_publish_delta_next814', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next814'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 816, 'table' => 'wp_theme_stage_publish_notice_next816', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_next818', 'to' => 'wp_job_retry_checkpoint_archive_key_next822'],
    ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 821, 'tables' => ['wp_schema_review_receipt_next821'], 'indexes' => ['wp_schema_review_receipt_key_next821'], 'file' => '/srv/wp/review-next821.sqlite'],
    ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_next821'],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next793', 'to' => 'wp_schema_handoff_receipt_next824'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 826, 'table' => 'wp_schema_publish_receipt_next826', 'indexes' => ['wp_schema_publish_receipt_key_next826'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 828, 'table' => 'wp_navigation_rule_locale_publish_final_next828', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next828'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 813, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next813', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next813'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next813-828');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next813');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next828');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next812', $plan['dependencies'], true));
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

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next813-828 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
