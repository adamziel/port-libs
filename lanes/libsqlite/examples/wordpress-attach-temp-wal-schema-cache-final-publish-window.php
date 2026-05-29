<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 1004, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 1004, 'commit' => true]]],
    'temp' => ['schema_cookie' => 990, 'tables' => ['wp_theme_stage_publish_token'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 955, 'tables' => ['wp_schema_archive_receipt'], 'indexes' => ['wp_schema_archive_receipt_key'], 'file' => '/srv/wp/archive-final.sqlite'],
    'handoff' => ['schema_cookie' => 955, 'tables' => ['wp_schema_handoff_receipt'], 'indexes' => ['wp_schema_handoff_receipt_key'], 'file' => '/srv/wp/handoff-final.sqlite'],
    'publish' => ['schema_cookie' => 1002, 'tables' => ['wp_schema_publish_done', 'wp_schema_publish_final'], 'indexes' => ['wp_schema_publish_done_key', 'wp_schema_publish_final_key'], 'file' => '/srv/wp/publish-final.sqlite'],
    'queue' => ['schema_cookie' => 922, 'tables' => ['wp_job_retry_dispatch'], 'indexes' => ['wp_job_retry_dispatch_key'], 'file' => '/srv/wp/queue-final.sqlite'],
    'seal' => ['schema_cookie' => 996, 'tables' => ['wp_schema_seal_receipt'], 'indexes' => ['wp_schema_seal_receipt_key'], 'file' => '/srv/wp/seal-final.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final INDEXED BY wp_navigation_rule_locale_publish_final_key WHERE nav_key = ?', 'active' => true],
    ['name' => 'temp-token-writer', 'sql' => 'UPDATE temp.wp_theme_stage_publish_token SET touched = 1 WHERE token = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt INDEXED BY wp_schema_archive_receipt_key WHERE archive_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt INDEXED BY wp_schema_handoff_receipt_key WHERE handoff_key = ?'],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done INDEXED BY wp_schema_publish_done_key WHERE publish_key = ?'],
    ['name' => 'queue-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_dispatch INDEXED BY wp_job_retry_dispatch_key WHERE job_key = ?'],
    ['name' => 'seal-reader', 'sql' => 'SELECT seal_id FROM seal.wp_schema_seal_receipt INDEXED BY wp_schema_seal_receipt_key WHERE seal_key = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt INDEXED BY wp_schema_review_receipt_key WHERE review_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::finalSchemaCachePublishWindow($schemas, $statements, [
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 1008, 'table' => 'wp_theme_stage_publish_token_ready', 'commit' => true],
    ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt', 'to' => 'wp_schema_handoff_receipt_published'],
    ['op' => 'attach', 'schema' => 'review', 'schema_cookie' => 1016, 'tables' => ['wp_schema_review_receipt'], 'indexes' => ['wp_schema_review_receipt_key'], 'file' => '/srv/wp/review-final.sqlite'],
    ['op' => 'drop_index', 'schema' => 'queue', 'index' => 'wp_job_retry_dispatch_key'],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'wal_commit', 'schema' => 'seal', 'schema_cookie' => 1018, 'table' => 'wp_schema_seal_receipt_ready', 'indexes' => ['wp_schema_seal_receipt_ready_key'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 1020, 'table' => 'wp_navigation_rule_locale_publish_ready', 'indexes' => ['wp_navigation_rule_locale_publish_ready_key'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'review', 'schema_cookie' => 1005, 'table' => 'wp_schema_review_uncommitted', 'indexes' => ['wp_schema_review_uncommitted_key'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-final-publish-window');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 7);
    assert($plan['schema_cookies_next']['main'] === 1020);
    assert($plan['schema_cookies_next']['temp'] === 1008);
    assert($plan['schema_cookies_next']['handoff'] === 956);
    assert($plan['schema_cookies_next']['queue'] === 923);
    assert($plan['schema_cookies_next']['seal'] === 1018);
    assert($plan['schema_cookies_next']['review'] === 1016);
    assert(!isset($plan['schema_cookies_next']['archive']));
    assert(in_array('temp-token-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['archive-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['handoff-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['queue-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['review-reader']['schema_transitions'][0]['next_schema'] === 'review');
    assert($plan['stable_statements'] === ['publish-reader']);

    echo "wordpress-attach-temp-wal-schema-cache-final-publish-window self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
