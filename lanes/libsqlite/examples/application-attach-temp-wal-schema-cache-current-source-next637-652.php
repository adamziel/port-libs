<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 636, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next636'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next636'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 636, 'commit' => true]]],
    'temp' => ['schema_cookie' => 526, 'tables' => ['wp_theme_stage_publish_retries_next558'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 573, 'tables' => ['wp_schema_archive_preview_next628'], 'indexes' => ['wp_schema_archive_preview_key_next628'], 'file' => '/srv/wp/archive-next637.sqlite'],
    'handoff' => ['schema_cookie' => 586, 'tables' => ['wp_schema_handoff_next582', 'wp_schema_handoff_meta_next621'], 'indexes' => ['wp_schema_handoff_key_next582'], 'file' => '/srv/wp/handoff-next637.sqlite'],
    'queue' => ['schema_cookie' => 594, 'tables' => ['wp_job_retry_checkpoint_sealed_next623', 'wp_job_retry_checkpoint_preview_next620'], 'indexes' => ['wp_job_retry_checkpoint_preview_job_next620'], 'file' => '/srv/wp/queue-next637.sqlite'],
    'review' => ['schema_cookie' => 627, 'tables' => ['wp_schema_review_next626', 'wp_schema_review_meta_next627'], 'indexes' => ['wp_schema_review_key_next626', 'wp_schema_review_meta_key_next627'], 'file' => '/srv/wp/review-next637.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT final_id FROM main.wp_navigation_rule_locale_publish_final_next636 INDEXED BY wp_navigation_rule_locale_publish_final_key_next636 WHERE final_key = ?', 'active' => true],
    ['name' => 'queue-preview-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_preview_next620 INDEXED BY wp_job_retry_checkpoint_preview_job_next620 SET acked = 1 WHERE job_id = ?'],
    ['name' => 'review-meta-reader', 'sql' => 'SELECT meta_id FROM review.wp_schema_review_meta_next627 INDEXED BY wp_schema_review_meta_key_next627 WHERE meta_key = ?', 'active' => true],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_next582 INDEXED BY wp_schema_handoff_key_next582 WHERE handoff_key = ?'],
    ['name' => 'archive-preview-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_preview_next628 INDEXED BY wp_schema_archive_preview_key_next628 WHERE archive_key = ?'],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_next642 INDEXED BY wp_schema_publish_key_next642 WHERE publish_key = ?'],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next558 WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 637, 'table' => 'wp_navigation_rule_locale_publish_receipt_next637', 'indexes' => ['wp_navigation_rule_locale_publish_receipt_key_next637'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'review', 'schema_cookie' => 638, 'table' => 'wp_schema_review_publish_next638', 'indexes' => ['wp_schema_review_publish_key_next638'], 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'queue', 'from' => 'wp_job_retry_checkpoint_preview_job_next620', 'to' => 'wp_job_retry_checkpoint_preview_job_next639'],
    ['op' => 'drop_table', 'schema' => 'handoff', 'table' => 'wp_schema_handoff_next582'],
    ['op' => 'attach', 'schema' => 'publish', 'schema_cookie' => 642, 'tables' => ['wp_schema_publish_next642'], 'indexes' => ['wp_schema_publish_key_next642'], 'file' => '/srv/wp/publish-next642.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 643, 'table' => 'wp_schema_publish_meta_next643', 'indexes' => ['wp_schema_publish_meta_key_next643'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 644, 'table' => 'wp_schema_archive_receipt_next644', 'indexes' => ['wp_schema_archive_receipt_key_next644'], 'commit' => false],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_schema_archive_preview_key_next628'],
    ['op' => 'detach', 'schema' => 'review'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 652, 'table' => 'wp_navigation_rule_locale_publish_final_next652', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next652'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['schema_cookies_next']['main'] === 652);
    assert($plan['schema_cookies_next']['queue'] === 595);
    assert($plan['schema_cookies_next']['handoff'] === 587);
    assert($plan['schema_cookies_next']['archive'] === 574);
    assert($plan['schema_cookies_next']['publish'] === 643);
    assert(in_array('review-meta-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('queue-preview-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['queue-preview-writer']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['review-meta-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['publish-reader']['schema_transitions'][0]['next_schema'] === 'publish');

    echo "application-attach-temp-wal-schema-cache-current-source-next637-652 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
