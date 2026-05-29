<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => ['schema_cookie' => 844, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next860', 'wp_navigation_rule_locale_publish_final_next860'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next860', 'wp_navigation_rule_locale_publish_final_key_next860'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 844, 'commit' => true]]],
    'temp' => ['schema_cookie' => 848, 'tables' => ['wp_theme_stage_publish_review_next848', 'wp_theme_stage_publish_notice_next848'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 845, 'tables' => ['wp_schema_archive_done_next845'], 'indexes' => ['wp_schema_archive_done_key_next845'], 'file' => '/srv/wp/archive-next845.sqlite'],
    'audit' => ['schema_cookie' => 853, 'tables' => ['wp_schema_audit_receipt_next853'], 'indexes' => ['wp_schema_audit_receipt_key_next853'], 'file' => '/srv/wp/audit-next845.sqlite'],
    'handoff' => ['schema_cookie' => 825, 'tables' => ['wp_schema_handoff_receipt_next809'], 'indexes' => ['wp_schema_handoff_receipt_key_next809'], 'file' => '/srv/wp/handoff-next845.sqlite'],
    'publish' => ['schema_cookie' => 858, 'tables' => ['wp_schema_publish_next845', 'wp_schema_publish_done_next858'], 'indexes' => ['wp_schema_publish_key_next850', 'wp_schema_publish_done_key_next858'], 'file' => '/srv/wp/publish-next845.sqlite'],
    'queue' => ['schema_cookie' => 850, 'tables' => ['wp_job_retry_checkpoint_delivered_next846', 'wp_job_retry_checkpoint_archive_next850'], 'indexes' => ['wp_job_retry_checkpoint_delivered_key_next846', 'wp_job_retry_checkpoint_archive_key_next850'], 'file' => '/srv/wp/queue-next845.sqlite'],
    'report' => ['schema_cookie' => 827, 'tables' => ['wp_schema_report_next746', 'wp_schema_report_meta_next811'], 'indexes' => ['wp_schema_report_key_next709', 'wp_schema_report_meta_key_next811'], 'file' => '/srv/wp/report-next845.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next860 INDEXED BY wp_navigation_rule_locale_publish_final_key_next860 WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next860 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next860 WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next858 INDEXED BY wp_schema_publish_done_key_next858 WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_next850 INDEXED BY wp_job_retry_checkpoint_archive_key_next850 SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next853 INDEXED BY wp_schema_audit_receipt_key_next853 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next809 INDEXED BY wp_schema_handoff_receipt_key_next809 WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_next811 INDEXED BY wp_schema_report_meta_key_next811 WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next853 INDEXED BY wp_schema_review_receipt_key_next853 WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next848 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext845860($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 846, 'table' => 'wp_navigation_rule_locale_publish_delta_next846', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next846'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 848, 'table' => 'wp_theme_stage_publish_notice_next848', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_next850', 'to' => 'wp_job_retry_checkpoint_archive_key_next854'],
    ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 853, 'tables' => ['wp_schema_review_receipt_next853'], 'indexes' => ['wp_schema_review_receipt_key_next853'], 'file' => '/srv/wp/review-next853.sqlite'],
    ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_next853'],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next809', 'to' => 'wp_schema_handoff_receipt_next856'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 858, 'table' => 'wp_schema_publish_receipt_next858', 'indexes' => ['wp_schema_publish_receipt_key_next858'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 860, 'table' => 'wp_navigation_rule_locale_publish_final_next860', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next860'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 845, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next845', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next845'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next845-860');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next845');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next860');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next844', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 860);
    assert($plan['schema_cookies_next']['temp'] === 848);
    assert($plan['schema_cookies_next']['publish'] === 858);
    assert($plan['schema_cookies_next']['review'] === 853);
    assert(!isset($plan['schema_cookies_next']['archive']));
    assert(in_array('queue-archive-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['queue-archive-writer']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['audit-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['handoff-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['review-reader']['schema_transitions'][0]['next_schema'] === 'review');
    assert($plan['stable_statements'] === ['publish-done-reader', 'report-meta-reader', 'temp-notice-reader']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next845-860 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
