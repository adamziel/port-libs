<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => ['schema_cookie' => 684, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next684'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next684'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 684, 'commit' => true]]],
    'temp' => ['schema_cookie' => 526, 'tables' => ['wp_theme_stage_publish_retries_next558'], 'indexes' => [], 'temp' => true],
    'audit' => ['schema_cookie' => 662, 'tables' => ['wp_schema_audit_next660'], 'indexes' => ['wp_schema_audit_key_next660'], 'file' => '/srv/wp/audit-next685.sqlite'],
    'handoff' => ['schema_cookie' => 676, 'tables' => ['wp_schema_handoff_next676'], 'indexes' => ['wp_schema_handoff_key_next676'], 'file' => '/srv/wp/handoff-next685.sqlite'],
    'publish' => ['schema_cookie' => 680, 'tables' => ['wp_schema_publish_next679', 'wp_schema_publish_meta_next680'], 'indexes' => ['wp_schema_publish_key_next679', 'wp_schema_publish_meta_key_next680'], 'file' => '/srv/wp/publish-next685.sqlite'],
    'queue' => ['schema_cookie' => 672, 'tables' => ['wp_job_retry_checkpoint_final_next672', 'wp_job_retry_checkpoint_preview_next620'], 'indexes' => ['wp_job_retry_checkpoint_final_key_next672'], 'file' => '/srv/wp/queue-next685.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT final_id FROM main.wp_navigation_rule_locale_publish_final_next684 INDEXED BY wp_navigation_rule_locale_publish_final_key_next684 WHERE final_key = ?', 'active' => true],
    ['name' => 'queue-final-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_final_next672 INDEXED BY wp_job_retry_checkpoint_final_key_next672 SET acked = 1 WHERE job_id = ?'],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_next679 INDEXED BY wp_schema_publish_key_next679 WHERE publish_key = ?', 'active' => true],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_next676 INDEXED BY wp_schema_handoff_key_next676 WHERE handoff_key = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_next660 INDEXED BY wp_schema_audit_key_next660 WHERE audit_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next690 INDEXED BY wp_schema_archive_receipt_key_next690 WHERE archive_key = ?'],
    ['name' => 'report-reader', 'sql' => 'SELECT report_id FROM report.wp_schema_report_next693 INDEXED BY wp_schema_report_key_next693 WHERE report_key = ?'],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next558 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext685700($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 685, 'table' => 'wp_navigation_rule_locale_publish_receipt_next685', 'indexes' => ['wp_navigation_rule_locale_publish_receipt_key_next685'], 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'publish', 'from' => 'wp_schema_publish_key_next679', 'to' => 'wp_schema_publish_key_next686'],
    ['op' => 'drop_table', 'schema' => 'handoff', 'table' => 'wp_schema_handoff_next676'],
    ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 688, 'table' => 'wp_job_retry_checkpoint_delivered_next688', 'indexes' => ['wp_job_retry_checkpoint_delivered_key_next688'], 'commit' => true],
    ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 690, 'tables' => ['wp_schema_archive_receipt_next690'], 'indexes' => ['wp_schema_archive_receipt_key_next690'], 'file' => '/srv/wp/archive-next690.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 691, 'table' => 'wp_schema_archive_meta_next691', 'indexes' => ['wp_schema_archive_meta_key_next691'], 'commit' => false],
    ['op' => 'attach', 'schema' => 'report', 'schema_cookie' => 693, 'tables' => ['wp_schema_report_next693'], 'indexes' => ['wp_schema_report_key_next693'], 'file' => '/srv/wp/report-next693.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'report', 'schema_cookie' => 694, 'table' => 'wp_schema_report_meta_next694', 'indexes' => ['wp_schema_report_meta_key_next694'], 'commit' => true],
    ['op' => 'detach', 'schema' => 'audit'],
    ['op' => 'rename_table', 'schema' => 'main', 'from' => 'wp_navigation_rule_locale_publish_final_next684', 'to' => 'wp_navigation_rule_locale_publish_final_next698'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 700, 'table' => 'wp_navigation_rule_locale_publish_final_next700', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next700'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next685-700');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next685');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next700');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next684', $plan['dependencies'], true));
    assert($plan['event_count'] === 10);
    assert($plan['schema_cookies_next']['main'] === 700);
    assert($plan['schema_cookies_next']['archive'] === 690);
    assert($plan['schema_cookies_next']['handoff'] === 677);
    assert($plan['schema_cookies_next']['publish'] === 681);
    assert($plan['schema_cookies_next']['queue'] === 688);
    assert($plan['schema_cookies_next']['report'] === 694);
    assert(in_array('publish-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('queue-final-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['publish-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['handoff-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['audit-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['archive-reader']['schema_transitions'][0]['next_schema'] === 'archive');
    assert($plan['statements']['report-reader']['schema_transitions'][0]['next_schema'] === 'report');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next685-700 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
