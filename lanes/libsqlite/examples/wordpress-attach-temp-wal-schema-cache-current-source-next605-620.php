<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => ['schema_cookie' => 604, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next604'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next604'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 604, 'commit' => true]]],
    'temp' => ['schema_cookie' => 526, 'tables' => ['wp_theme_stage_publish_retries_next558'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 571, 'tables' => ['wp_schema_archive_meta_next569'], 'indexes' => ['wp_schema_archive_key_next568'], 'file' => '/srv/wp/archive-next605.sqlite'],
    'handoff' => ['schema_cookie' => 584, 'tables' => ['wp_schema_handoff_next582', 'wp_schema_handoff_meta_next583'], 'indexes' => ['wp_schema_handoff_key_next582', 'wp_schema_handoff_meta_key_next594'], 'file' => '/srv/wp/handoff-next605.sqlite'],
    'publish' => ['schema_cookie' => 599, 'tables' => ['wp_schema_publish_next598', 'wp_schema_publish_meta_next599'], 'indexes' => ['wp_schema_publish_key_next598', 'wp_schema_publish_meta_key_next599'], 'file' => '/srv/wp/publish-next605.sqlite'],
    'queue' => ['schema_cookie' => 592, 'tables' => ['wp_job_retry_checkpoint_receipt_next587', 'wp_job_retry_checkpoint_handoff_next592'], 'indexes' => ['wp_job_retry_checkpoint_receipt_job_next587', 'wp_job_retry_checkpoint_handoff_job_next592'], 'file' => '/srv/wp/queue-next605.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT final_id FROM main.wp_navigation_rule_locale_publish_final_next604 INDEXED BY wp_navigation_rule_locale_publish_final_key_next604 WHERE final_key = ?', 'active' => true],
    ['name' => 'queue-handoff-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_handoff_next592 INDEXED BY wp_job_retry_checkpoint_handoff_job_next592 SET acked = 1 WHERE job_id = ?'],
    ['name' => 'handoff-meta-reader', 'sql' => 'SELECT meta_id FROM handoff.wp_schema_handoff_meta_next583 INDEXED BY wp_schema_handoff_meta_key_next594 WHERE meta_key = ?'],
    ['name' => 'publish-meta-reader', 'sql' => 'SELECT meta_id FROM publish.wp_schema_publish_meta_next599 INDEXED BY wp_schema_publish_meta_key_next599 WHERE meta_key = ?', 'active' => true],
    ['name' => 'archive-meta-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_meta_next569 WHERE archive_key = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_next610 INDEXED BY wp_schema_audit_key_next610 WHERE audit_key = ?'],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next558 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext605620($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 605, 'table' => 'wp_navigation_rule_locale_publish_receipt_next605', 'indexes' => ['wp_navigation_rule_locale_publish_receipt_key_next605'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 606, 'table' => 'wp_schema_publish_audit_next606', 'indexes' => ['wp_schema_publish_audit_key_next606'], 'commit' => true],
    ['op' => 'rename_table', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_handoff_next592', 'to' => 'wp_job_retry_checkpoint_final_next607'],
    ['op' => 'drop_index', 'schema' => 'handoff', 'index' => 'wp_schema_handoff_meta_key_next594'],
    ['op' => 'attach', 'schema' => 'audit', 'schema_cookie' => 610, 'tables' => ['wp_schema_audit_next610'], 'indexes' => ['wp_schema_audit_key_next610'], 'file' => '/srv/wp/audit-next610.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'audit', 'schema_cookie' => 611, 'table' => 'wp_schema_audit_meta_next611', 'indexes' => ['wp_schema_audit_meta_key_next611'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 612, 'table' => 'wp_job_retry_checkpoint_preview_next612', 'indexes' => ['wp_job_retry_checkpoint_preview_job_next612'], 'commit' => false],
    ['op' => 'drop_table', 'schema' => 'archive', 'table' => 'wp_schema_archive_meta_next569'],
    ['op' => 'detach', 'schema' => 'publish'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 620, 'table' => 'wp_navigation_rule_locale_publish_final_next620', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next620'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next605-620');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next605');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next620');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next604', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 620);
    assert($plan['schema_cookies_next']['queue'] === 593);
    assert($plan['schema_cookies_next']['handoff'] === 585);
    assert($plan['schema_cookies_next']['archive'] === 572);
    assert($plan['schema_cookies_next']['audit'] === 611);
    assert(in_array('publish-meta-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('queue-handoff-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['handoff-meta-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['publish-meta-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['audit-reader']['schema_transitions'][0]['next_schema'] === 'audit');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next605-620 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
