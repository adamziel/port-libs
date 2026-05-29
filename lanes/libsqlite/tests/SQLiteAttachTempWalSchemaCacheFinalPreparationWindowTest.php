<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemasFinalPreparationWindow = [
    'main' => [
        'schema_cookie' => 988,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next988'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next988'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 988, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 980,
        'tables' => ['wp_theme_stage_publish_token_next980'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 954,
        'tables' => ['wp_schema_archive_receipt_next986'],
        'indexes' => ['wp_schema_archive_receipt_key_next966'],
        'file' => '/srv/wp/archive-next989.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 955,
        'tables' => ['wp_schema_handoff_receipt_next970'],
        'indexes' => ['wp_schema_handoff_receipt_key_next920'],
        'file' => '/srv/wp/handoff-next989.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 987,
        'tables' => ['wp_schema_publish_done_next987', 'wp_schema_publish_final_next955'],
        'indexes' => ['wp_schema_publish_done_key_next987', 'wp_schema_publish_final_key_next955'],
        'file' => '/srv/wp/publish-next989.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 921,
        'tables' => ['wp_job_retry_checkpoint_meta_next989'],
        'indexes' => ['wp_job_retry_checkpoint_meta_key_next989'],
        'file' => '/srv/wp/queue-next989.sqlite',
    ],
    'review' => [
        'schema_cookie' => 969,
        'tables' => ['wp_schema_review_receipt_next968'],
        'indexes' => ['wp_schema_review_receipt_key_next982'],
        'file' => '/srv/wp/review-next989.sqlite',
    ],
];

$statementsFinalPreparationWindow = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next988 INDEXED BY wp_navigation_rule_locale_publish_final_key_next988 WHERE nav_key = ?', 'active' => true],
    ['name' => 'temp-token-writer', 'sql' => 'UPDATE temp.wp_theme_stage_publish_token_next980 SET touched = 1 WHERE token = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next986 INDEXED BY wp_schema_archive_receipt_key_next966 WHERE archive_key = ?'],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_receipt_next970 INDEXED BY wp_schema_handoff_receipt_key_next920 WHERE handoff_key = ?'],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next987 INDEXED BY wp_schema_publish_done_key_next987 WHERE publish_key = ?'],
    ['name' => 'queue-meta-reader', 'sql' => 'SELECT meta_id FROM queue.wp_job_retry_checkpoint_meta_next989 INDEXED BY wp_job_retry_checkpoint_meta_key_next989 WHERE job_id = ?'],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next968 INDEXED BY wp_schema_review_receipt_key_next982 WHERE review_key = ?'],
    ['name' => 'seal-reader', 'sql' => 'SELECT seal_id FROM seal.wp_schema_seal_receipt_next996 INDEXED BY wp_schema_seal_receipt_key_next996 WHERE seal_key = ?'],
];

$planFinalPreparationWindow = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::finalSchemaCachePreparationWindow(
    $schemas ?? $schemasFinalPreparationWindow,
    $statements ?? $statementsFinalPreparationWindow,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache final preparation window extends predecessor handoff'] = static function (TestRunner $t) use ($planFinalPreparationWindow): void {
    $result = $planFinalPreparationWindow([
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 990, 'table' => 'wp_theme_stage_publish_token_next990', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'archive', 'from' => 'wp_schema_archive_receipt_key_next966', 'to' => 'wp_schema_archive_receipt_key_next992'],
        ['op' => 'attach', 'schema' => 'seal', 'schema_cookie' => 996, 'tables' => ['wp_schema_seal_receipt_next996'], 'indexes' => ['wp_schema_seal_receipt_key_next996'], 'file' => '/srv/wp/seal-next996.sqlite'],
        ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_meta_next989'],
        ['op' => 'detach', 'schema' => 'review'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 1002, 'table' => 'wp_schema_publish_done_next1002', 'indexes' => ['wp_schema_publish_done_key_next1002'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 1004, 'table' => 'wp_navigation_rule_locale_publish_final_next1004', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next1004'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'seal', 'schema_cookie' => 989, 'table' => 'wp_schema_seal_uncommitted_next989', 'indexes' => ['wp_schema_seal_uncommitted_key_next989'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-final-preparation-window', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(7, $result['event_count']);
    $t->same(1004, $result['schema_cookies_next']['main']);
    $t->same(990, $result['schema_cookies_next']['temp']);
    $t->same(955, $result['schema_cookies_next']['archive']);
    $t->same(955, $result['schema_cookies_next']['handoff']);
    $t->same(1002, $result['schema_cookies_next']['publish']);
    $t->same(922, $result['schema_cookies_next']['queue']);
    $t->same(996, $result['schema_cookies_next']['seal']);
    $t->same(false, isset($result['schema_cookies_next']['review']));
    $t->same(['main-final-reader'], $result['active_current_snapshot_statements']);
    $t->same(['temp-token-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['archive-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-meta-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['review-reader']['schema_transitions'][0]['next_found']);
    $t->same('seal', $result['statements']['seal-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['handoff-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache final preparation window ignores detached scratch seal'] = static function (TestRunner $t) use ($planFinalPreparationWindow): void {
    $result = $planFinalPreparationWindow([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 989, 'tables' => ['wp_schema_scratch_seal_next989'], 'indexes' => ['wp_schema_scratch_seal_key_next989'], 'file' => '/srv/wp/scratch-next989.sqlite'],
        ['op' => 'schema_write', 'schema' => 'scratch', 'schema_cookie' => 990, 'table' => 'wp_schema_scratch_seal_meta_next990', 'indexes' => ['wp_schema_scratch_seal_meta_key_next990'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'scratch'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'handoff', 'publish', 'queue', 'review'], $result['search_order_next']);
};

return $tests;
