<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => ['schema_cookie' => 652, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next652'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next652'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 652, 'commit' => true]]],
    'temp' => ['schema_cookie' => 526, 'tables' => ['wp_theme_stage_publish_retries_next558'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 574, 'tables' => ['wp_schema_archive_preview_next628', 'wp_schema_archive_receipt_next644'], 'indexes' => ['wp_schema_archive_receipt_key_next644'], 'file' => '/srv/wp/archive-next653.sqlite'],
    'handoff' => ['schema_cookie' => 587, 'tables' => ['wp_schema_handoff_meta_next621'], 'indexes' => [], 'file' => '/srv/wp/handoff-next653.sqlite'],
    'publish' => ['schema_cookie' => 643, 'tables' => ['wp_schema_publish_next642', 'wp_schema_publish_meta_next643'], 'indexes' => ['wp_schema_publish_key_next642', 'wp_schema_publish_meta_key_next643'], 'file' => '/srv/wp/publish-next653.sqlite'],
    'queue' => ['schema_cookie' => 595, 'tables' => ['wp_job_retry_checkpoint_sealed_next623', 'wp_job_retry_checkpoint_preview_next620'], 'indexes' => ['wp_job_retry_checkpoint_preview_job_next639'], 'file' => '/srv/wp/queue-next653.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT final_id FROM main.wp_navigation_rule_locale_publish_final_next652 INDEXED BY wp_navigation_rule_locale_publish_final_key_next652 WHERE final_key = ?', 'active' => true],
    ['name' => 'queue-preview-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_preview_next620 INDEXED BY wp_job_retry_checkpoint_preview_job_next639 SET acked = 1 WHERE job_id = ?'],
    ['name' => 'publish-meta-reader', 'sql' => 'SELECT meta_id FROM publish.wp_schema_publish_meta_next643 INDEXED BY wp_schema_publish_meta_key_next643 WHERE meta_key = ?', 'active' => true],
    ['name' => 'archive-receipt-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next644 INDEXED BY wp_schema_archive_receipt_key_next644 WHERE archive_key = ?'],
    ['name' => 'handoff-meta-reader', 'sql' => 'SELECT meta_id FROM handoff.wp_schema_handoff_meta_next621 WHERE meta_key = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT audit_id FROM audit.wp_schema_audit_next660 INDEXED BY wp_schema_audit_key_next660 WHERE audit_key = ?'],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next558 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext653668($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 653, 'table' => 'wp_navigation_rule_locale_publish_receipt_next653', 'indexes' => ['wp_navigation_rule_locale_publish_receipt_key_next653'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 654, 'table' => 'wp_schema_publish_audit_next654', 'indexes' => ['wp_schema_publish_audit_key_next654'], 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_preview_job_next639', 'to' => 'wp_job_retry_checkpoint_preview_job_next655'],
    ['op' => 'drop_table', 'schema' => 'handoff', 'table' => 'wp_schema_handoff_meta_next621'],
    ['op' => 'attach', 'schema' => 'audit', 'schema_cookie' => 660, 'tables' => ['wp_schema_audit_next660'], 'indexes' => ['wp_schema_audit_key_next660'], 'file' => '/srv/wp/audit-next660.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'audit', 'schema_cookie' => 661, 'table' => 'wp_schema_audit_meta_next661', 'indexes' => ['wp_schema_audit_meta_key_next661'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 662, 'table' => 'wp_schema_archive_receipt_next662', 'indexes' => ['wp_schema_archive_receipt_key_next662'], 'commit' => false],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_schema_archive_receipt_key_next644'],
    ['op' => 'detach', 'schema' => 'publish'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 668, 'table' => 'wp_navigation_rule_locale_publish_final_next668', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next668'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next653-668');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next653');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next668');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next652', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 668);
    assert($plan['schema_cookies_next']['queue'] === 596);
    assert($plan['schema_cookies_next']['handoff'] === 588);
    assert($plan['schema_cookies_next']['archive'] === 575);
    assert($plan['schema_cookies_next']['audit'] === 661);
    assert(in_array('publish-meta-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('queue-preview-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['queue-preview-writer']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['publish-meta-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['audit-reader']['schema_transitions'][0]['next_schema'] === 'audit');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next653-668 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
