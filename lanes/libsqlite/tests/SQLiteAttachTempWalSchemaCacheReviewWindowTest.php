<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemasReviewWindow = [
    'main' => [
        'schema_cookie' => 876,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next876', 'wp_navigation_rule_locale_publish_final_next892'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next876', 'wp_navigation_rule_locale_publish_final_key_next892'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 876, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 880,
        'tables' => ['wp_theme_stage_publish_gate_next864', 'wp_theme_stage_publish_notice_next880'],
        'indexes' => [],
        'temp' => true,
    ],
    'audit' => [
        'schema_cookie' => 869,
        'tables' => ['wp_schema_audit_replay_next869', 'wp_schema_audit_seal_next885'],
        'indexes' => ['wp_schema_audit_replay_key_next869', 'wp_schema_audit_seal_key_next885'],
        'file' => '/srv/wp/audit-next877.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 872,
        'tables' => ['wp_schema_handoff_receipt_next872'],
        'indexes' => ['wp_schema_handoff_receipt_key_next872'],
        'file' => '/srv/wp/handoff-next877.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 874,
        'tables' => ['wp_schema_publish_seal_next874', 'wp_schema_publish_done_next874'],
        'indexes' => ['wp_schema_publish_seal_key_next874', 'wp_schema_publish_done_key_next874'],
        'file' => '/srv/wp/publish-next877.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 870,
        'tables' => ['wp_job_retry_checkpoint_archive_next866'],
        'indexes' => ['wp_job_retry_checkpoint_archive_key_next870'],
        'file' => '/srv/wp/queue-next877.sqlite',
    ],
    'rollout' => [
        'schema_cookie' => 872,
        'tables' => ['wp_schema_rollout_receipt_next872'],
        'indexes' => ['wp_schema_rollout_receipt_key_next872'],
        'file' => '/srv/wp/rollout-next877.sqlite',
    ],
];

$statementsReviewWindow = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next892 INDEXED BY wp_navigation_rule_locale_publish_final_key_next892 WHERE nav_key = ?', 'active' => true],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next874 INDEXED BY wp_schema_publish_done_key_next874 WHERE publish_key = ?'],
    ['name' => 'queue-archive-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_archive_next866 INDEXED BY wp_job_retry_checkpoint_archive_key_next870 WHERE job_id = ?'],
    ['name' => 'audit-seal-reader', 'sql' => 'SELECT seal_id FROM audit.wp_schema_audit_seal_next885 INDEXED BY wp_schema_audit_seal_key_next885 WHERE seal_key = ?'],
    ['name' => 'handoff-writer', 'sql' => 'UPDATE handoff.wp_schema_handoff_receipt_next872 INDEXED BY wp_schema_handoff_receipt_key_next872 SET accepted = 1 WHERE handoff_key = ?', 'active' => true],
    ['name' => 'rollout-reader', 'sql' => 'SELECT rollout_id FROM rollout.wp_schema_rollout_receipt_next872 INDEXED BY wp_schema_rollout_receipt_key_next872 WHERE rollout_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next888 INDEXED BY wp_schema_archive_receipt_key_next888 WHERE archive_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next880 WHERE cache_key = ?'],
];

$planReviewWindow = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheReviewWindow(
    $schemas ?? $schemasReviewWindow,
    $statements ?? $statementsReviewWindow,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache review window extends rollout-window handoff'] = static function (TestRunner $t) use ($planReviewWindow): void {
    $result = $planReviewWindow([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 878, 'table' => 'wp_navigation_rule_locale_publish_delta_next878', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next878'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 880, 'table' => 'wp_theme_stage_publish_notice_next880', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_schema_audit_seal_key_next885', 'to' => 'wp_schema_audit_seal_key_next886'],
        ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 888, 'tables' => ['wp_schema_archive_receipt_next888'], 'indexes' => ['wp_schema_archive_receipt_key_next888'], 'file' => '/srv/wp/archive-next888.sqlite'],
        ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_archive_next866'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next872', 'to' => 'wp_schema_handoff_receipt_next890'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 891, 'table' => 'wp_schema_publish_final_next891', 'indexes' => ['wp_schema_publish_final_key_next891'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'rollout'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 892, 'table' => 'wp_navigation_rule_locale_publish_final_next892', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next892'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 877, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next877', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next877'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(892, $result['schema_cookies_next']['main']);
    $t->same(880, $result['schema_cookies_next']['temp']);
    $t->same(870, $result['schema_cookies_next']['audit']);
    $t->same(873, $result['schema_cookies_next']['handoff']);
    $t->same(891, $result['schema_cookies_next']['publish']);
    $t->same(871, $result['schema_cookies_next']['queue']);
    $t->same(888, $result['schema_cookies_next']['archive']);
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

$tests['attach temp wal schema cache review window ignores detached transient archive'] = static function (TestRunner $t) use ($planReviewWindow): void {
    $result = $planReviewWindow([
        ['op' => 'attach', 'schema' => 'transient', 'schema_cookie' => 877, 'tables' => ['wp_transient_archive_next877'], 'indexes' => ['wp_transient_archive_key_next877'], 'file' => '/srv/wp/transient-next877.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'transient', 'schema_cookie' => 878, 'table' => 'wp_transient_archive_meta_next878', 'indexes' => ['wp_transient_archive_meta_key_next878'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'transient'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'audit', 'handoff', 'publish', 'queue', 'rollout'], $result['search_order_next']);
};

return $tests;
