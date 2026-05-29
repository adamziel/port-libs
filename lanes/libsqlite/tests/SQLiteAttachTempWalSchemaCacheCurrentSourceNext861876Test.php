<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas861876 = [
    'main' => [
        'schema_cookie' => 860,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_final_next860', 'wp_navigation_rule_locale_publish_receipt_next876'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_final_key_next860', 'wp_navigation_rule_locale_publish_receipt_key_next876'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 860, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 864,
        'tables' => ['wp_theme_stage_publish_notice_next848', 'wp_theme_stage_publish_gate_next864'],
        'indexes' => [],
        'temp' => true,
    ],
    'audit' => [
        'schema_cookie' => 853,
        'tables' => ['wp_schema_audit_receipt_next853', 'wp_schema_audit_replay_next869'],
        'indexes' => ['wp_schema_audit_receipt_key_next853', 'wp_schema_audit_replay_key_next869'],
        'file' => '/srv/wp/audit-next861.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 856,
        'tables' => ['wp_schema_handoff_receipt_next856'],
        'indexes' => ['wp_schema_handoff_receipt_key_next856'],
        'file' => '/srv/wp/handoff-next861.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 858,
        'tables' => ['wp_schema_publish_done_next858', 'wp_schema_publish_receipt_next858'],
        'indexes' => ['wp_schema_publish_done_key_next858', 'wp_schema_publish_receipt_key_next858'],
        'file' => '/srv/wp/publish-next861.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 854,
        'tables' => ['wp_job_retry_checkpoint_archive_next850'],
        'indexes' => ['wp_job_retry_checkpoint_archive_key_next854'],
        'file' => '/srv/wp/queue-next861.sqlite',
    ],
    'review' => [
        'schema_cookie' => 853,
        'tables' => ['wp_schema_review_receipt_next853'],
        'indexes' => ['wp_schema_review_receipt_key_next853'],
        'file' => '/srv/wp/review-next861.sqlite',
    ],
];

$statements861876 = [
    ['name' => 'main-receipt-reader', 'sql' => 'SELECT receipt_id FROM main.wp_navigation_rule_locale_publish_receipt_next876 INDEXED BY wp_navigation_rule_locale_publish_receipt_key_next876 WHERE receipt_key = ?', 'active' => true],
    ['name' => 'publish-receipt-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_receipt_next858 INDEXED BY wp_schema_publish_receipt_key_next858 WHERE publish_key = ?'],
    ['name' => 'queue-archive-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_archive_next850 INDEXED BY wp_job_retry_checkpoint_archive_key_next854 WHERE job_id = ?'],
    ['name' => 'audit-replay-reader', 'sql' => 'SELECT replay_id FROM audit.wp_schema_audit_replay_next869 INDEXED BY wp_schema_audit_replay_key_next869 WHERE replay_key = ?'],
    ['name' => 'handoff-writer', 'sql' => 'UPDATE handoff.wp_schema_handoff_receipt_next856 INDEXED BY wp_schema_handoff_receipt_key_next856 SET accepted = 1 WHERE handoff_key = ?', 'active' => true],
    ['name' => 'review-reader', 'sql' => 'SELECT review_id FROM review.wp_schema_review_receipt_next853 INDEXED BY wp_schema_review_receipt_key_next853 WHERE review_key = ?'],
    ['name' => 'rollout-reader', 'sql' => 'SELECT rollout_id FROM rollout.wp_schema_rollout_receipt_next872 INDEXED BY wp_schema_rollout_receipt_key_next872 WHERE rollout_key = ?'],
    ['name' => 'temp-gate-reader', 'sql' => 'SELECT gate_id FROM temp.wp_theme_stage_publish_gate_next864 WHERE cache_key = ?'],
];

$plan861876 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext861876(
    $schemas ?? $schemas861876,
    $statements ?? $statements861876,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next861-876 extends next845-860 handoff'] = static function (TestRunner $t) use ($plan861876): void {
    $result = $plan861876([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 862, 'table' => 'wp_navigation_rule_locale_publish_delta_next862', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next862'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 864, 'table' => 'wp_theme_stage_publish_gate_next864', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_schema_audit_replay_key_next869', 'to' => 'wp_schema_audit_replay_key_next870'],
        ['op' => 'attach', 'schema' => 'rollout', 'schema_cookie' => 872, 'tables' => ['wp_schema_rollout_receipt_next872'], 'indexes' => ['wp_schema_rollout_receipt_key_next872'], 'file' => '/srv/wp/rollout-next872.sqlite'],
        ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_archive_next850'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next856', 'to' => 'wp_schema_handoff_receipt_next874'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 875, 'table' => 'wp_schema_publish_seal_next875', 'indexes' => ['wp_schema_publish_seal_key_next875'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'review'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 876, 'table' => 'wp_navigation_rule_locale_publish_receipt_next876', 'indexes' => ['wp_navigation_rule_locale_publish_receipt_key_next876'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 861, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next861', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next861'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next861-876', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next861', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next876', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next845', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next860', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(876, $result['schema_cookies_next']['main']);
    $t->same(864, $result['schema_cookies_next']['temp']);
    $t->same(854, $result['schema_cookies_next']['audit']);
    $t->same(857, $result['schema_cookies_next']['handoff']);
    $t->same(875, $result['schema_cookies_next']['publish']);
    $t->same(855, $result['schema_cookies_next']['queue']);
    $t->same(872, $result['schema_cookies_next']['rollout']);
    $t->same(false, isset($result['schema_cookies_next']['review']));
    $t->same(['main-receipt-reader', 'handoff-writer'], $result['active_current_snapshot_statements']);
    $t->same(['handoff-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['audit-replay-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['queue-archive-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['handoff-writer']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['review-reader']['schema_transitions'][0]['next_found']);
    $t->same('rollout', $result['statements']['rollout-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['temp-gate-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next861-876 ignores detached transient rollout'] = static function (TestRunner $t) use ($plan861876): void {
    $result = $plan861876([
        ['op' => 'attach', 'schema' => 'transient', 'schema_cookie' => 861, 'tables' => ['wp_transient_rollout_next861'], 'indexes' => ['wp_transient_rollout_key_next861'], 'file' => '/srv/wp/transient-next861.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'transient', 'schema_cookie' => 862, 'table' => 'wp_transient_rollout_meta_next862', 'indexes' => ['wp_transient_rollout_meta_key_next862'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'transient'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'audit', 'handoff', 'publish', 'queue', 'review'], $result['search_order_next']);
};

return $tests;
