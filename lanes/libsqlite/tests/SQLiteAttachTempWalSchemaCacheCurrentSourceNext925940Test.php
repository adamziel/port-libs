<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas925940 = [
    'main' => [
        'schema_cookie' => 924,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next924', 'wp_navigation_rule_locale_publish_final_next940'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next924', 'wp_navigation_rule_locale_publish_final_key_next940'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 924, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 928,
        'tables' => ['wp_theme_stage_publish_gate_next912', 'wp_theme_stage_publish_notice_next928'],
        'indexes' => [],
        'temp' => true,
    ],
    'audit' => [
        'schema_cookie' => 917,
        'tables' => ['wp_schema_audit_replay_next917', 'wp_schema_audit_seal_next933'],
        'indexes' => ['wp_schema_audit_replay_key_next917', 'wp_schema_audit_seal_key_next933'],
        'file' => '/srv/wp/audit-next925.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 920,
        'tables' => ['wp_schema_handoff_receipt_next920'],
        'indexes' => ['wp_schema_handoff_receipt_key_next920'],
        'file' => '/srv/wp/handoff-next925.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 922,
        'tables' => ['wp_schema_publish_seal_next922', 'wp_schema_publish_done_next922'],
        'indexes' => ['wp_schema_publish_seal_key_next922', 'wp_schema_publish_done_key_next922'],
        'file' => '/srv/wp/publish-next925.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 918,
        'tables' => ['wp_job_retry_checkpoint_archive_next914'],
        'indexes' => ['wp_job_retry_checkpoint_archive_key_next918'],
        'file' => '/srv/wp/queue-next925.sqlite',
    ],
    'rollout' => [
        'schema_cookie' => 920,
        'tables' => ['wp_schema_rollout_receipt_next920'],
        'indexes' => ['wp_schema_rollout_receipt_key_next920'],
        'file' => '/srv/wp/rollout-next925.sqlite',
    ],
];

$statements925940 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next940 INDEXED BY wp_navigation_rule_locale_publish_final_key_next940 WHERE nav_key = ?', 'active' => true],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next922 INDEXED BY wp_schema_publish_done_key_next922 WHERE publish_key = ?'],
    ['name' => 'queue-archive-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_archive_next914 INDEXED BY wp_job_retry_checkpoint_archive_key_next918 WHERE job_id = ?'],
    ['name' => 'audit-seal-reader', 'sql' => 'SELECT seal_id FROM audit.wp_schema_audit_seal_next933 INDEXED BY wp_schema_audit_seal_key_next933 WHERE seal_key = ?'],
    ['name' => 'handoff-writer', 'sql' => 'UPDATE handoff.wp_schema_handoff_receipt_next920 INDEXED BY wp_schema_handoff_receipt_key_next920 SET accepted = 1 WHERE handoff_key = ?', 'active' => true],
    ['name' => 'rollout-reader', 'sql' => 'SELECT rollout_id FROM rollout.wp_schema_rollout_receipt_next920 INDEXED BY wp_schema_rollout_receipt_key_next920 WHERE rollout_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next936 INDEXED BY wp_schema_archive_receipt_key_next936 WHERE archive_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next928 WHERE cache_key = ?'],
];

$plan925940 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext925940(
    $schemas ?? $schemas925940,
    $statements ?? $statements925940,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next925-940 extends next909-924 handoff'] = static function (TestRunner $t) use ($plan925940): void {
    $result = $plan925940([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 926, 'table' => 'wp_navigation_rule_locale_publish_delta_next926', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next926'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 928, 'table' => 'wp_theme_stage_publish_notice_next928', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_schema_audit_seal_key_next933', 'to' => 'wp_schema_audit_seal_key_next934'],
        ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 936, 'tables' => ['wp_schema_archive_receipt_next936'], 'indexes' => ['wp_schema_archive_receipt_key_next936'], 'file' => '/srv/wp/archive-next936.sqlite'],
        ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_archive_next914'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next920', 'to' => 'wp_schema_handoff_receipt_next938'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 939, 'table' => 'wp_schema_publish_final_next939', 'indexes' => ['wp_schema_publish_final_key_next939'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'rollout'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 940, 'table' => 'wp_navigation_rule_locale_publish_final_next940', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next940'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 925, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next925', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next925'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next925-940', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next925', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next940', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next909', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next924', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(940, $result['schema_cookies_next']['main']);
    $t->same(928, $result['schema_cookies_next']['temp']);
    $t->same(918, $result['schema_cookies_next']['audit']);
    $t->same(921, $result['schema_cookies_next']['handoff']);
    $t->same(939, $result['schema_cookies_next']['publish']);
    $t->same(919, $result['schema_cookies_next']['queue']);
    $t->same(936, $result['schema_cookies_next']['archive']);
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

$tests['attach temp wal schema cache current source next925-940 ignores detached transient archive'] = static function (TestRunner $t) use ($plan925940): void {
    $result = $plan925940([
        ['op' => 'attach', 'schema' => 'transient', 'schema_cookie' => 925, 'tables' => ['wp_transient_archive_next925'], 'indexes' => ['wp_transient_archive_key_next925'], 'file' => '/srv/wp/transient-next925.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'transient', 'schema_cookie' => 926, 'table' => 'wp_transient_archive_meta_next926', 'indexes' => ['wp_transient_archive_meta_key_next926'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'transient'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'audit', 'handoff', 'publish', 'queue', 'rollout'], $result['search_order_next']);
};

return $tests;
