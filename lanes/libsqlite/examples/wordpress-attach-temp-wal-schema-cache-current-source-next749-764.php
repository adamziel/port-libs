<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => ['schema_cookie' => 748, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next732', 'wp_navigation_rule_locale_publish_final_next748'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next732', 'wp_navigation_rule_locale_publish_final_key_next748'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 748, 'commit' => true]]],
    'temp' => ['schema_cookie' => 736, 'tables' => ['wp_theme_stage_publish_review_next736', 'wp_theme_stage_publish_notice_next752'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 734, 'tables' => ['wp_schema_archive_done_next734'], 'indexes' => ['wp_schema_archive_done_key_next734'], 'file' => '/srv/wp/archive-next749.sqlite'],
    'audit' => ['schema_cookie' => 757, 'tables' => ['wp_schema_audit_receipt_next757'], 'indexes' => ['wp_schema_audit_receipt_key_next757'], 'file' => '/srv/wp/audit-next749.sqlite'],
    'handoff' => ['schema_cookie' => 745, 'tables' => ['wp_schema_handoff_receipt_next745'], 'indexes' => ['wp_schema_handoff_receipt_key_next745'], 'file' => '/srv/wp/handoff-next749.sqlite'],
    'publish' => ['schema_cookie' => 723, 'tables' => ['wp_schema_publish_next679', 'wp_schema_publish_done_next706'], 'indexes' => ['wp_schema_publish_key_next686', 'wp_schema_publish_done_key_next722'], 'file' => '/srv/wp/publish-next749.sqlite'],
    'queue' => ['schema_cookie' => 740, 'tables' => ['wp_job_retry_checkpoint_delivered_next688', 'wp_job_retry_checkpoint_archive_next740'], 'indexes' => ['wp_job_retry_checkpoint_delivered_key_next702', 'wp_job_retry_checkpoint_archive_key_next740'], 'file' => '/srv/wp/queue-next749.sqlite'],
    'report' => ['schema_cookie' => 712, 'tables' => ['wp_schema_report_next730', 'wp_schema_report_meta_next694'], 'indexes' => ['wp_schema_report_key_next693', 'wp_schema_report_meta_key_next694'], 'file' => '/srv/wp/report-next749.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next748 INDEXED BY wp_navigation_rule_locale_publish_final_key_next748 WHERE nav_key = ?', 'active' => true],
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next732 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next732 WHERE receipt_key = ?'],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next706 INDEXED BY wp_schema_publish_done_key_next722 WHERE publish_key = ?'],
    ['name' => 'queue-archive-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_archive_next740 INDEXED BY wp_job_retry_checkpoint_archive_key_next740 SET delivered = 1 WHERE job_id = ?', 'active' => true],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next757 INDEXED BY wp_schema_audit_receipt_key_next757 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next745 INDEXED BY wp_schema_handoff_receipt_key_next745 WHERE handoff_key = ?'],
    ['name' => 'report-meta-reader', 'sql' => 'SELECT meta_id FROM report.wp_schema_report_meta_next694 INDEXED BY wp_schema_report_meta_key_next694 WHERE meta_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next757 INDEXED BY wp_schema_review_receipt_key_next757 WHERE review_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next752 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext749764($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 750, 'table' => 'wp_navigation_rule_locale_publish_delta_next750', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next750'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 752, 'table' => 'wp_theme_stage_publish_notice_next752', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_archive_key_next740', 'to' => 'wp_job_retry_checkpoint_archive_key_next754'],
    ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 757, 'tables' => ['wp_schema_review_receipt_next757'], 'indexes' => ['wp_schema_review_receipt_key_next757'], 'file' => '/srv/wp/review-next757.sqlite'],
    ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_schema_audit_receipt_next757'],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next745', 'to' => 'wp_schema_handoff_receipt_next760'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 762, 'table' => 'wp_schema_publish_receipt_next762', 'indexes' => ['wp_schema_publish_receipt_key_next762'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 764, 'table' => 'wp_navigation_rule_locale_publish_final_next764', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next764'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 749, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next749', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next749'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next749-764');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next749');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next764');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next748', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 764);
    assert($plan['schema_cookies_next']['temp'] === 752);
    assert($plan['schema_cookies_next']['publish'] === 762);
    assert($plan['schema_cookies_next']['review'] === 757);
    assert(!isset($plan['schema_cookies_next']['archive']));
    assert(in_array('queue-archive-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['queue-archive-writer']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['audit-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['handoff-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['review-reader']['schema_transitions'][0]['next_schema'] === 'review');
    assert($plan['stable_statements'] === ['report-meta-reader']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next749-764 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
