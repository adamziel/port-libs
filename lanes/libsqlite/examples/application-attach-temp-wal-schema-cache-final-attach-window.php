<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 1020, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next1020'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next1020'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 1020, 'commit' => true]]],
    'temp' => ['schema_cookie' => 1008, 'tables' => ['wp_theme_stage_publish_token_next1008', 'wp_import_stage_shadow_next1021'], 'indexes' => ['wp_import_stage_shadow_key_next1021'], 'temp' => true],
    'review' => ['schema_cookie' => 1016, 'tables' => ['wp_schema_review_receipt_next1016'], 'indexes' => ['wp_schema_review_receipt_key_next1016'], 'file' => '/srv/wp/review-next1021.sqlite'],
    'publish' => ['schema_cookie' => 1018, 'tables' => ['wp_schema_publish_final_next1018'], 'indexes' => ['wp_schema_publish_final_key_next1018'], 'file' => '/srv/wp/publish-next1021.sqlite'],
    'queue' => ['schema_cookie' => 923, 'tables' => ['wp_job_retry_dispatch_next1021'], 'indexes' => ['wp_job_retry_dispatch_key_next1021'], 'file' => '/srv/wp/queue-next1021.sqlite'],
    'metrics' => ['schema_cookie' => 1024, 'tables' => ['wp_schema_metrics_receipt_next1024'], 'indexes' => ['wp_schema_metrics_receipt_key_next1024'], 'file' => '/srv/wp/metrics-next1021.sqlite'],
    'audit' => ['schema_cookie' => 1028, 'tables' => ['wp_schema_audit_log_next1028'], 'indexes' => ['wp_schema_audit_log_key_next1028'], 'file' => '/srv/wp/audit-next1021.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next1020 INDEXED BY wp_navigation_rule_locale_publish_final_key_next1020 WHERE nav_key = ?', 'active' => true],
    ['name' => 'temp-shadow-reader', 'sql' => 'SELECT shadow_id FROM temp.wp_import_stage_shadow_next1021 INDEXED BY wp_import_stage_shadow_key_next1021 WHERE shadow_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next1016 INDEXED BY wp_schema_review_receipt_key_next1016 WHERE review_key = ?'],
    ['name' => 'publish-writer', 'sql' => 'UPDATE publish.wp_schema_publish_final_next1018 INDEXED BY wp_schema_publish_final_key_next1018 SET accepted = 1 WHERE publish_key = ?', 'active' => true],
    ['name' => 'queue-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_dispatch_next1021 INDEXED BY wp_job_retry_dispatch_key_next1021 WHERE job_key = ?'],
    ['name' => 'metrics-reader', 'sql' => 'SELECT metric_id FROM metrics.wp_schema_metrics_receipt_next1024 INDEXED BY wp_schema_metrics_receipt_key_next1024 WHERE metric_key = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_log_next1028 INDEXED BY wp_schema_audit_log_key_next1028 WHERE audit_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_final_next1036 INDEXED BY wp_schema_archive_final_key_next1036 WHERE archive_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::finalSchemaCacheAttachWindow($schemas, $statements, [
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_stage_shadow_next1021'],
    ['op' => 'rename_index', 'schema' => 'review', 'from' => 'wp_schema_review_receipt_key_next1016', 'to' => 'wp_schema_review_receipt_key_next1026'],
    ['op' => 'rename_table', 'schema' => 'publish', 'from' => 'wp_schema_publish_final_next1018', 'to' => 'wp_schema_publish_final_next1030'],
    ['op' => 'drop_index', 'schema' => 'queue', 'index' => 'wp_job_retry_dispatch_key_next1021'],
    ['op' => 'wal_commit', 'schema' => 'metrics', 'schema_cookie' => 1032, 'table' => 'wp_schema_metrics_receipt_next1032', 'indexes' => ['wp_schema_metrics_receipt_key_next1032'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'audit'],
    ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 1036, 'tables' => ['wp_schema_archive_final_next1036'], 'indexes' => ['wp_schema_archive_final_key_next1036'], 'file' => '/srv/wp/archive-next1036.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 1036, 'table' => 'wp_navigation_rule_locale_publish_final_next1036', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next1036'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 1021, 'table' => 'wp_schema_archive_uncommitted_next1021', 'indexes' => ['wp_schema_archive_uncommitted_key_next1021'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-final-attach-window');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 8);
    assert($plan['schema_cookies_next']['main'] === 1036);
    assert($plan['schema_cookies_next']['temp'] === 1009);
    assert($plan['schema_cookies_next']['metrics'] === 1032);
    assert($plan['schema_cookies_next']['archive'] === 1036);
    assert(!isset($plan['schema_cookies_next']['audit']));
    assert(in_array('publish-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['temp-shadow-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['review-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['publish-writer']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['queue-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['audit-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['archive-reader']['schema_transitions'][0]['next_schema'] === 'archive');
    assert($plan['stable_statements'] === []);

    echo "application-attach-temp-wal-schema-cache-final-attach-window self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
