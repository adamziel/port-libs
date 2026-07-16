<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemasArchiveWindow = [
    'main' => [
        'schema_cookie' => 892,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next892', 'wp_navigation_rule_locale_publish_final_next908'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next892', 'wp_navigation_rule_locale_publish_final_key_next908'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 892, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 896,
        'tables' => ['wp_theme_stage_publish_gate_next880', 'wp_theme_stage_publish_notice_next896'],
        'indexes' => [],
        'temp' => true,
    ],
    'audit' => [
        'schema_cookie' => 885,
        'tables' => ['wp_schema_audit_replay_next885', 'wp_schema_audit_seal_next901'],
        'indexes' => ['wp_schema_audit_replay_key_next885', 'wp_schema_audit_seal_key_next901'],
        'file' => '/srv/wp/audit-next893.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 888,
        'tables' => ['wp_schema_handoff_receipt_next888'],
        'indexes' => ['wp_schema_handoff_receipt_key_next888'],
        'file' => '/srv/wp/handoff-next893.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 890,
        'tables' => ['wp_schema_publish_seal_next890', 'wp_schema_publish_done_next890'],
        'indexes' => ['wp_schema_publish_seal_key_next890', 'wp_schema_publish_done_key_next890'],
        'file' => '/srv/wp/publish-next893.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 886,
        'tables' => ['wp_job_retry_checkpoint_archive_next882'],
        'indexes' => ['wp_job_retry_checkpoint_archive_key_next886'],
        'file' => '/srv/wp/queue-next893.sqlite',
    ],
    'rollout' => [
        'schema_cookie' => 888,
        'tables' => ['wp_schema_rollout_receipt_next888'],
        'indexes' => ['wp_schema_rollout_receipt_key_next888'],
        'file' => '/srv/wp/rollout-next893.sqlite',
    ],
];

$statementsArchiveWindow = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next908 INDEXED BY wp_navigation_rule_locale_publish_final_key_next908 WHERE nav_key = ?', 'active' => true],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next890 INDEXED BY wp_schema_publish_done_key_next890 WHERE publish_key = ?'],
    ['name' => 'queue-archive-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_archive_next882 INDEXED BY wp_job_retry_checkpoint_archive_key_next886 WHERE job_id = ?'],
    ['name' => 'audit-seal-reader', 'sql' => 'SELECT seal_id FROM audit.wp_schema_audit_seal_next901 INDEXED BY wp_schema_audit_seal_key_next901 WHERE seal_key = ?'],
    ['name' => 'handoff-writer', 'sql' => 'UPDATE handoff.wp_schema_handoff_receipt_next888 INDEXED BY wp_schema_handoff_receipt_key_next888 SET accepted = 1 WHERE handoff_key = ?', 'active' => true],
    ['name' => 'rollout-reader', 'sql' => 'SELECT rollout_id FROM rollout.wp_schema_rollout_receipt_next888 INDEXED BY wp_schema_rollout_receipt_key_next888 WHERE rollout_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next904 INDEXED BY wp_schema_archive_receipt_key_next904 WHERE archive_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next896 WHERE cache_key = ?'],
];

$planArchiveWindow = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheArchiveWindow(
    $schemas ?? $schemasArchiveWindow,
    $statements ?? $statementsArchiveWindow,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache archive window extends review handoff'] = static function (TestRunner $t) use ($planArchiveWindow): void {
    $result = $planArchiveWindow([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 894, 'table' => 'wp_navigation_rule_locale_publish_delta_next894', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next894'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 896, 'table' => 'wp_theme_stage_publish_notice_next896', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_schema_audit_seal_key_next901', 'to' => 'wp_schema_audit_seal_key_next902'],
        ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 904, 'tables' => ['wp_schema_archive_receipt_next904'], 'indexes' => ['wp_schema_archive_receipt_key_next904'], 'file' => '/srv/wp/archive-next904.sqlite'],
        ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_archive_next882'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next888', 'to' => 'wp_schema_handoff_receipt_next906'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 907, 'table' => 'wp_schema_publish_final_next907', 'indexes' => ['wp_schema_publish_final_key_next907'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'rollout'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 908, 'table' => 'wp_navigation_rule_locale_publish_final_next908', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next908'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 893, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next893', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next893'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(908, $result['schema_cookies_next']['main']);
    $t->same(896, $result['schema_cookies_next']['temp']);
    $t->same(886, $result['schema_cookies_next']['audit']);
    $t->same(889, $result['schema_cookies_next']['handoff']);
    $t->same(907, $result['schema_cookies_next']['publish']);
    $t->same(887, $result['schema_cookies_next']['queue']);
    $t->same(904, $result['schema_cookies_next']['archive']);
    $t->same(false, isset($result['schema_cookies_next']['rollout']));
    $t->same(['main-final-reader', 'handoff-writer'], $result['active_current_snapshot_statements']);
    $t->same(['handoff-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['audit-seal-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-archive-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-writer']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['rollout-reader']['schema_transitions'][0]['next_found']);
    $t->same('archive', $result['statements']['archive-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['temp-notice-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache archive window ignores detached transient archive'] = static function (TestRunner $t) use ($planArchiveWindow): void {
    $result = $planArchiveWindow([
        ['op' => 'attach', 'schema' => 'transient', 'schema_cookie' => 893, 'tables' => ['wp_transient_archive_next893'], 'indexes' => ['wp_transient_archive_key_next893'], 'file' => '/srv/wp/transient-next893.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'transient', 'schema_cookie' => 894, 'table' => 'wp_transient_archive_meta_next894', 'indexes' => ['wp_transient_archive_meta_key_next894'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'transient'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'audit', 'handoff', 'publish', 'queue', 'rollout'], $result['search_order_next']);
};

return $tests;
