<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => ['schema_cookie' => 988, 'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next988'], 'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next988'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 988, 'commit' => true]]],
    'temp' => ['schema_cookie' => 980, 'tables' => ['wp_theme_stage_publish_token_next980'], 'indexes' => [], 'temp' => true],
    'archive' => ['schema_cookie' => 954, 'tables' => ['wp_schema_archive_receipt_next986'], 'indexes' => ['wp_schema_archive_receipt_key_next966'], 'file' => '/srv/wp/archive-next989.sqlite'],
    'handoff' => ['schema_cookie' => 955, 'tables' => ['wp_schema_handoff_receipt_next970'], 'indexes' => ['wp_schema_handoff_receipt_key_next920'], 'file' => '/srv/wp/handoff-next989.sqlite'],
    'publish' => ['schema_cookie' => 987, 'tables' => ['wp_schema_publish_done_next987', 'wp_schema_publish_final_next955'], 'indexes' => ['wp_schema_publish_done_key_next987', 'wp_schema_publish_final_key_next955'], 'file' => '/srv/wp/publish-next989.sqlite'],
    'queue' => ['schema_cookie' => 921, 'tables' => ['wp_job_retry_checkpoint_meta_next989'], 'indexes' => ['wp_job_retry_checkpoint_meta_key_next989'], 'file' => '/srv/wp/queue-next989.sqlite'],
    'review' => ['schema_cookie' => 969, 'tables' => ['wp_schema_review_receipt_next968'], 'indexes' => ['wp_schema_review_receipt_key_next982'], 'file' => '/srv/wp/review-next989.sqlite'],
];

$statements = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next988 INDEXED BY wp_navigation_rule_locale_publish_final_key_next988 WHERE nav_key = ?', 'active' => true],
    ['name' => 'temp-token-writer', 'sql' => 'UPDATE temp.wp_theme_stage_publish_token_next980 SET touched = 1 WHERE token = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next986 INDEXED BY wp_schema_archive_receipt_key_next966 WHERE archive_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next970 INDEXED BY wp_schema_handoff_receipt_key_next920 WHERE handoff_key = ?'],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next987 INDEXED BY wp_schema_publish_done_key_next987 WHERE publish_key = ?'],
    ['name' => 'queue-meta-reader', 'sql' => 'SELECT meta_id FROM queue.wp_job_retry_checkpoint_meta_next989 INDEXED BY wp_job_retry_checkpoint_meta_key_next989 WHERE job_id = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next968 INDEXED BY wp_schema_review_receipt_key_next982 WHERE review_key = ?'],
    ['name' => 'seal-reader', 'sql' => 'SELECT seal_id FROM seal.wp_schema_seal_receipt_next996 INDEXED BY wp_schema_seal_receipt_key_next996 WHERE seal_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext9891004($schemas, $statements, [
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 990, 'table' => 'wp_theme_stage_publish_token_next990', 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'archive', 'from' => 'wp_schema_archive_receipt_key_next966', 'to' => 'wp_schema_archive_receipt_key_next992'],
    ['op' => 'attach', 'schema' => 'seal', 'schema_cookie' => 996, 'tables' => ['wp_schema_seal_receipt_next996'], 'indexes' => ['wp_schema_seal_receipt_key_next996'], 'file' => '/srv/wp/seal-next996.sqlite'],
    ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_meta_next989'],
    ['op' => 'detach', 'schema' => 'review'],
    ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 1002, 'table' => 'wp_schema_publish_done_next1002', 'indexes' => ['wp_schema_publish_done_key_next1002'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 1004, 'table' => 'wp_navigation_rule_locale_publish_final_next1004', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next1004'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'seal', 'schema_cookie' => 989, 'table' => 'wp_schema_seal_uncommitted_next989', 'indexes' => ['wp_schema_seal_uncommitted_key_next989'], 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next989-1004');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next989');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-current-source-next1004');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next988', $plan['dependencies'], true));
    assert($plan['event_count'] === 7);
    assert($plan['schema_cookies_next']['main'] === 1004);
    assert($plan['schema_cookies_next']['temp'] === 990);
    assert($plan['schema_cookies_next']['publish'] === 1002);
    assert($plan['schema_cookies_next']['archive'] === 955);
    assert($plan['schema_cookies_next']['seal'] === 996);
    assert(!isset($plan['schema_cookies_next']['review']));
    assert(in_array('temp-token-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['archive-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['queue-meta-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['review-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['seal-reader']['schema_transitions'][0]['next_schema'] === 'seal');
    assert($plan['stable_statements'] === ['handoff-reader']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next989-1004 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
