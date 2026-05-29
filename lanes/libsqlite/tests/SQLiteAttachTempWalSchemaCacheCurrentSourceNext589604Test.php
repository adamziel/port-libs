<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas589604 = [
    'main' => [
        'schema_cookie' => 588,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_receipt_next572', 'wp_navigation_rule_locale_handoff_next573', 'wp_navigation_rule_locale_final_next588'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_receipt_key_next572', 'wp_navigation_rule_locale_handoff_key_next573', 'wp_navigation_rule_locale_final_key_next588'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 588, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 525,
        'tables' => ['wp_theme_stage_publish_retries_next558'],
        'indexes' => ['wp_theme_stage_publish_retries_key_next574'],
        'temp' => true,
    ],
    'queue' => [
        'schema_cookie' => 576,
        'tables' => ['wp_job_retry_checkpoint_lock_next560', 'wp_job_retry_checkpoint_cursor_next576', 'wp_job_retry_checkpoint_receipt_next587'],
        'indexes' => ['wp_job_retry_checkpoint_lock_job_next560', 'wp_job_retry_checkpoint_cursor_job_next576', 'wp_job_retry_checkpoint_receipt_job_next587'],
        'file' => '/srv/wp/queue-next589.sqlite',
    ],
    'campaign' => [
        'schema_cookie' => 543,
        'tables' => ['wp_campaign_restore_next524'],
        'indexes' => ['wp_campaign_restore_slug_next526'],
        'file' => '/srv/wp/campaign-next589.sqlite',
    ],
    'archive' => [
        'schema_cookie' => 570,
        'tables' => ['wp_schema_archive_next568', 'wp_schema_archive_meta_next569'],
        'indexes' => ['wp_schema_archive_key_next568'],
        'file' => '/srv/wp/archive-next589.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 583,
        'tables' => ['wp_schema_handoff_next582', 'wp_schema_handoff_meta_next583'],
        'indexes' => ['wp_schema_handoff_key_next582', 'wp_schema_handoff_meta_key_next583'],
        'file' => '/srv/wp/handoff-next589.sqlite',
    ],
];

$statements589604 = [
    ['name' => 'nav-final-reader', 'sql' => 'SELECT final_id FROM main.wp_navigation_rule_locale_final_next588 INDEXED BY wp_navigation_rule_locale_final_key_next588 WHERE final_key = ?', 'active' => true],
    ['name' => 'temp-retry-reader', 'sql' => 'SELECT tries FROM temp.wp_theme_stage_publish_retries_next558 INDEXED BY wp_theme_stage_publish_retries_key_next574 WHERE cache_key = ?', 'active' => true],
    ['name' => 'queue-receipt-writer', 'sql' => 'UPDATE queue.wp_job_retry_checkpoint_receipt_next587 INDEXED BY wp_job_retry_checkpoint_receipt_job_next587 SET acked = 1 WHERE job_id = ?'],
    ['name' => 'handoff-meta-reader', 'sql' => 'SELECT meta_id FROM handoff.wp_schema_handoff_meta_next583 INDEXED BY wp_schema_handoff_meta_key_next583 WHERE meta_key = ?'],
    ['name' => 'archive-root-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_next568 INDEXED BY wp_schema_archive_key_next568 WHERE archive_key = ?'],
    ['name' => 'campaign-restore-reader', 'sql' => 'SELECT restore_id FROM campaign.wp_campaign_restore_next524 INDEXED BY wp_campaign_restore_slug_next526 WHERE slug = ?'],
    ['name' => 'publish-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_next598 INDEXED BY wp_schema_publish_key_next598 WHERE publish_key = ?'],
];

$plan589604 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext589604(
    $schemas ?? $schemas589604,
    $statements ?? $statements589604,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next589-604 extends next573-588 handoff'] = static function (TestRunner $t) use ($plan589604): void {
    $result = $plan589604([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 589, 'table' => 'wp_navigation_rule_locale_publish_next589', 'indexes' => ['wp_navigation_rule_locale_publish_key_next589'], 'commit' => true],
        ['op' => 'drop_index', 'schema' => 'temp', 'index' => 'wp_theme_stage_publish_retries_key_next574'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 592, 'table' => 'wp_job_retry_checkpoint_handoff_next592', 'indexes' => ['wp_job_retry_checkpoint_handoff_job_next592'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_meta_key_next583', 'to' => 'wp_schema_handoff_meta_key_next594'],
        ['op' => 'drop_table', 'schema' => 'archive', 'table' => 'wp_schema_archive_next568'],
        ['op' => 'attach', 'schema' => 'publish', 'schema_cookie' => 598, 'tables' => ['wp_schema_publish_next598'], 'indexes' => ['wp_schema_publish_key_next598'], 'file' => '/srv/wp/publish-next598.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 599, 'table' => 'wp_schema_publish_meta_next599', 'indexes' => ['wp_schema_publish_meta_key_next599'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'campaign'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 602, 'table' => 'wp_job_retry_checkpoint_publish_next602', 'indexes' => ['wp_job_retry_checkpoint_publish_job_next602'], 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 604, 'table' => 'wp_navigation_rule_locale_publish_final_next604', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next604'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next589-604', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next589', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next604', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next573', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next588', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(604, $result['schema_cookies_next']['main']);
    $t->same(526, $result['schema_cookies_next']['temp']);
    $t->same(592, $result['schema_cookies_next']['queue']);
    $t->same(584, $result['schema_cookies_next']['handoff']);
    $t->same(571, $result['schema_cookies_next']['archive']);
    $t->same(599, $result['schema_cookies_next']['publish']);
    $t->same(['nav-final-reader', 'temp-retry-reader'], $result['active_current_snapshot_statements']);
    $t->same(['queue-receipt-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['temp-retry-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-meta-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['archive-root-reader']['schema_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['campaign-restore-reader']['schema_transitions'][0]['next_schema']);
    $t->same('publish', $result['statements']['publish-reader']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next589-604 ignores transient preview attach'] = static function (TestRunner $t) use ($plan589604): void {
    $result = $plan589604([
        ['op' => 'attach', 'schema' => 'preview', 'schema_cookie' => 589, 'tables' => ['wp_preview_next589'], 'indexes' => ['wp_preview_key_next589'], 'file' => '/srv/wp/preview-next589.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'preview', 'schema_cookie' => 590, 'table' => 'wp_preview_meta_next590', 'indexes' => ['wp_preview_meta_key_next590'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'preview'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'archive', 'campaign', 'handoff', 'queue'], $result['search_order_next']);
};

return $tests;
