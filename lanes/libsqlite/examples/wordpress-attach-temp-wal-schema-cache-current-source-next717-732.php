<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => ['schema_cookie' => 716, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next700', 'wp_navigation_rule_locale_publish_receipt_next716'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next700', 'wp_navigation_rule_locale_publish_receipt_key_next716'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 716, 'commit' => true]]],
    'temp' => ['schema_cookie' => 704, 'tables' => ['wp_theme_stage_publish_preview_next704', 'wp_theme_stage_publish_review_next720'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 691, 'tables' => ['wp_schema_archive_done_next718'], 'indexes' => ['wp_schema_archive_done_key_next718'], 'file' => '/srv/wp/archive-next717.sqlite'],
    'metrics' => ['schema_cookie' => 713, 'tables' => ['wp_schema_metrics_next713'], 'indexes' => ['wp_schema_metrics_key_next713'], 'file' => '/srv/wp/metrics-next717.sqlite'],
    'publish' => ['schema_cookie' => 706, 'tables' => ['wp_schema_publish_next679', 'wp_schema_publish_done_next706'], 'indexes' => ['wp_schema_publish_key_next686', 'wp_schema_publish_done_key_next706'], 'file' => '/srv/wp/publish-next717.sqlite'],
    'queue' => ['schema_cookie' => 689, 'tables' => ['wp_job_retry_checkpoint_delivered_next688', 'wp_job_retry_checkpoint_preview_next620'], 'indexes' => ['wp_job_retry_checkpoint_delivered_key_next702'], 'file' => '/srv/wp/queue-next717.sqlite'],
    'report' => ['schema_cookie' => 695, 'tables' => ['wp_schema_report_next711', 'wp_schema_report_meta_next694'], 'indexes' => ['wp_schema_report_key_next693', 'wp_schema_report_meta_key_next694'], 'file' => '/srv/wp/report-next717.sqlite'],
    'review' => ['schema_cookie' => 709, 'tables' => ['wp_schema_review_receipt_next709'], 'indexes' => ['wp_schema_review_receipt_key_next709'], 'file' => '/srv/wp/review-next717.sqlite'],
];

$statements = [
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next716 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next716 WHERE receipt_key = ?', 'active' => true],
    ['name' => 'publish-done-writer', 'sql' => 'UPDATE publish.wp_schema_publish_done_next706 INDEXED BY wp_schema_publish_done_key_next706 SET delivered = 1 WHERE publish_key = ?'],
    ['name' => 'queue-delivery-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_delivered_next688 INDEXED BY wp_job_retry_checkpoint_delivered_key_next702 WHERE job_id = ?', 'active' => true],
    ['name' => 'archive-done-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_done_next718 INDEXED BY wp_schema_archive_done_key_next718 WHERE archive_key = ?'],
    ['name' => 'report-reader', 'sql' => 'SELECT report_id FROM report.wp_schema_report_next711 INDEXED BY wp_schema_report_key_next693 WHERE report_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next709 INDEXED BY wp_schema_review_receipt_key_next709 WHERE review_key = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_receipt_next725 INDEXED BY wp_schema_audit_receipt_key_next725 WHERE audit_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next729 INDEXED BY wp_schema_handoff_receipt_key_next729 WHERE handoff_key = ?'],
    ['name' => 'temp-review-reader', 'sql' => 'SELECT review_id FROM temp.wp_theme_stage_publish_review_next720 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext717732($schemas, $statements, [
    ['op' => 'schema_write', 'schema' => 'archive', 'schema_cookie' => 718, 'table' => 'wp_schema_archive_done_next718', 'indexes' => ['wp_schema_archive_done_key_next718'], 'commit' => true],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 720, 'table' => 'wp_theme_stage_publish_review_next720', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'publish', 'from' => 'wp_schema_publish_done_key_next706', 'to' => 'wp_schema_publish_done_key_next722'],
    ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 724, 'table' => 'wp_job_retry_checkpoint_archive_next724', 'indexes' => ['wp_job_retry_checkpoint_archive_key_next724'], 'commit' => true],
    ['op' => 'attach', 'schema' => 'audit', 'schema_cookie' => 725, 'tables' => ['wp_schema_audit_receipt_next725'], 'indexes' => ['wp_schema_audit_receipt_key_next725'], 'file' => '/srv/wp/audit-next725.sqlite'],
    ['op' => 'drop_table', 'schema' => 'review', 'table' => 'wp_schema_review_receipt_next709'],
    ['op' => 'attach', 'schema' => 'handoff', 'schema_cookie' => 729, 'tables' => ['wp_schema_handoff_receipt_next729'], 'indexes' => ['wp_schema_handoff_receipt_key_next729'], 'file' => '/srv/wp/handoff-next729.sqlite'],
    ['op' => 'rename_table', 'schema' => 'report', 'from' => 'wp_schema_report_next711', 'to' => 'wp_schema_report_next730'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 732, 'table' => 'wp_navigation_rule_locale_publish_final_next732', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next732'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 733, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next733', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next733'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next717-732');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next717');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next732');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next716', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 732);
    assert($plan['schema_cookies_next']['temp'] === 720);
    assert($plan['schema_cookies_next']['audit'] === 725);
    assert($plan['schema_cookies_next']['handoff'] === 729);
    assert($plan['schema_cookies_next']['publish'] === 707);
    assert($plan['schema_cookies_next']['queue'] === 724);
    assert($plan['schema_cookies_next']['report'] === 696);
    assert($plan['schema_cookies_next']['review'] === 710);
    assert(in_array('queue-delivery-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('publish-done-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['review-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['report-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['audit-reader']['schema_transitions'][0]['next_schema'] === 'audit');
    assert($plan['statements']['handoff-reader']['schema_transitions'][0]['next_schema'] === 'handoff');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next717-732 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
