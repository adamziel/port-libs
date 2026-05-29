<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => ['schema_cookie' => 796, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next812', 'wp_navigation_rule_locale_publish_final_next812'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next812', 'wp_navigation_rule_locale_publish_final_key_next812'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 796, 'commit' => true]]],
    'temp' => ['schema_cookie' => 800, 'tables' => ['wp_theme_stage_publish_review_next800', 'wp_theme_stage_publish_notice_next800'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 797, 'tables' => ['wp_schema_archive_done_next797'], 'indexes' => ['wp_schema_archive_done_key_next797'], 'file' => '/srv/wp/archive-next797.sqlite'],
    'audit' => ['schema_cookie' => 805, 'tables' => ['wp_schema_audit_receipt_next805'], 'indexes' => ['wp_schema_audit_receipt_key_next805'], 'file' => '/srv/wp/audit-next797.sqlite'],
    'handoff' => ['schema_cookie' => 793, 'tables' => ['wp_schema_handoff_receipt_next793'], 'indexes' => ['wp_schema_handoff_receipt_key_next793'], 'file' => '/srv/wp/handoff-next797.sqlite'],
    'publish' => ['schema_cookie' => 810, 'tables' => ['wp_schema_publish_next797', 'wp_schema_publish_done_next810'], 'indexes' => ['wp_schema_publish_key_next802', 'wp_schema_publish_done_key_next810'], 'file' => '/srv/wp/publish-next797.sqlite'],
    'queue' => ['schema_cookie' => 802, 'tables' => ['wp_job_retry_checkpoint_delivered_next798', 'wp_job_retry_checkpoint_archive_next802'], 'indexes' => ['wp_job_retry_checkpoint_delivered_key_next798', 'wp_job_retry_checkpoint_archive_key_next802'], 'file' => '/srv/wp/queue-next797.sqlite'],
    'report' => ['schema_cookie' => 795, 'tables' => ['wp_schema_report_next730', 'wp_schema_report_meta_next795'], 'indexes' => ['wp_schema_report_key_next693', 'wp_schema_report_meta_key_next795'], 'file' => '/srv/wp/report-next797.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next812 INDEXED BY wp_navigation_rule_locale_publish_final_key_next812 WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next812 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next812 WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next810 INDEXED BY wp_schema_publish_done_key_next810 WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_next802 INDEXED BY wp_job_retry_checkpoint_archive_key_next802 SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next805 INDEXED BY wp_schema_audit_receipt_key_next805 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next793 INDEXED BY wp_schema_handoff_receipt_key_next793 WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_next795 INDEXED BY wp_schema_report_meta_key_next795 WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next805 INDEXED BY wp_schema_review_receipt_key_next805 WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next800 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext797812($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 798, 'table' => 'wp_navigation_rule_locale_publish_delta_next798', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next798'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 800, 'table' => 'wp_theme_stage_publish_notice_next800', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_next802', 'to' => 'wp_job_retry_checkpoint_archive_key_next806'],
    ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 805, 'tables' => ['wp_schema_review_receipt_next805'], 'indexes' => ['wp_schema_review_receipt_key_next805'], 'file' => '/srv/wp/review-next805.sqlite'],
    ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_next805'],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next793', 'to' => 'wp_schema_handoff_receipt_next808'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 810, 'table' => 'wp_schema_publish_receipt_next810', 'indexes' => ['wp_schema_publish_receipt_key_next810'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 812, 'table' => 'wp_navigation_rule_locale_publish_final_next812', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next812'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 797, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next797', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next797'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next797-812');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next797');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next812');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next796', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 812);
    assert($plan['schema_cookies_next']['temp'] === 800);
    assert($plan['schema_cookies_next']['publish'] === 810);
    assert($plan['schema_cookies_next']['review'] === 805);
    assert(!isset($plan['schema_cookies_next']['archive']));
    assert(in_array('queue-archive-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['queue-archive-writer']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['audit-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['handoff-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['review-reader']['schema_transitions'][0]['next_schema'] === 'review');
    assert($plan['stable_statements'] === ['publish-done-reader', 'report-meta-reader', 'temp-notice-reader']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next797-812 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
