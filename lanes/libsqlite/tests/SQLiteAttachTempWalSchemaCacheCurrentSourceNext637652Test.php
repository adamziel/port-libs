<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas637652 = [
    'main' => [
        'schema_cookie' => 636,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next636'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next636'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 636, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 526,
        'tables' => ['wp_theme_stage_publish_retries_next558'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 573,
        'tables' => ['wp_schema_archive_preview_next628'],
        'indexes' => ['wp_schema_archive_preview_key_next628'],
        'file' => '/srv/wp/archive-next637.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 586,
        'tables' => ['wp_schema_handoff_next582', 'wp_schema_handoff_meta_next621'],
        'indexes' => ['wp_schema_handoff_key_next582'],
        'file' => '/srv/wp/handoff-next637.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 594,
        'tables' => ['wp_job_retry_checkpoint_sealed_next623', 'wp_job_retry_checkpoint_preview_next620'],
        'indexes' => ['wp_job_retry_checkpoint_preview_job_next620'],
        'file' => '/srv/wp/queue-next637.sqlite',
    ],
    'review' => [
        'schema_cookie' => 627,
        'tables' => ['wp_schema_review_next626', 'wp_schema_review_meta_next627'],
        'indexes' => ['wp_schema_review_key_next626', 'wp_schema_review_meta_key_next627'],
        'file' => '/srv/wp/review-next637.sqlite',
    ],
];

$statements637652 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT final_id FROM main.wp_navigation_rule_locale_publish_final_next636 INDEXED BY wp_navigation_rule_locale_publish_final_key_next636 WHERE final_key = ?', 'active' => true],
    ['name' => 'queue-preview-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_preview_next620 INDEXED BY wp_job_retry_checkpoint_preview_job_next620 SET acked = 1 WHERE job_id = ?'],
    ['name' => 'review-meta-reader', 'sql' => 'SELECT meta_id FROM review.wp_schema_review_meta_next627 INDEXED BY wp_schema_review_meta_key_next627 WHERE meta_key = ?', 'active' => true],
    ['name' => 'handoff-reader', 'sql' => 'SELECT handoff_id FROM handoff.wp_schema_handoff_next582 INDEXED BY wp_schema_handoff_key_next582 WHERE handoff_key = ?'],
    ['name' => 'archive-preview-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_preview_next628 INDEXED BY wp_schema_archive_preview_key_next628 WHERE archive_key = ?'],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_next642 INDEXED BY wp_schema_publish_key_next642 WHERE publish_key = ?'],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next558 WHERE cache_key = ?'],
];

$plan637652 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas637652,
    $statements ?? $statements637652,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next637-652 extends next621-636 handoff'] = static function (TestRunner $t) use ($plan637652): void {
    $result = $plan637652([
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

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(652, $result['schema_cookies_next']['main']);
    $t->same(526, $result['schema_cookies_next']['temp']);
    $t->same(595, $result['schema_cookies_next']['queue']);
    $t->same(587, $result['schema_cookies_next']['handoff']);
    $t->same(574, $result['schema_cookies_next']['archive']);
    $t->same(643, $result['schema_cookies_next']['publish']);
    $t->same(['main-final-reader', 'review-meta-reader'], $result['active_current_snapshot_statements']);
    $t->same(['queue-preview-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['queue-preview-writer']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['archive-preview-reader']['index_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['review-meta-reader']['schema_transitions'][0]['next_schema']);
    $t->same('publish', $result['statements']['publish-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['temp-retry-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next637-652 keeps transient publish preview stable'] = static function (TestRunner $t) use ($plan637652): void {
    $result = $plan637652([
        ['op' => 'attach', 'schema' => 'preview', 'schema_cookie' => 637, 'tables' => ['wp_preview_next637'], 'indexes' => ['wp_preview_key_next637'], 'file' => '/srv/wp/preview-next637.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'preview', 'schema_cookie' => 638, 'table' => 'wp_preview_meta_next638', 'indexes' => ['wp_preview_meta_key_next638'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'preview'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'handoff', 'queue', 'review'], $result['search_order_next']);
};

return $tests;
