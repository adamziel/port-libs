<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas909924 = [
    'main' => [
        'schema_cookie' => 908,
        'tables' => ['wp_options', 'wp_navigation_rule_locale_publish_receipt_next908', 'wp_navigation_rule_locale_publish_final_next924'],
        'indexes' => ['wp_options_name', 'wp_navigation_rule_locale_publish_receipt_key_next908', 'wp_navigation_rule_locale_publish_final_key_next924'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 908, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 912,
        'tables' => ['wp_theme_stage_publish_gate_next896', 'wp_theme_stage_publish_notice_next912'],
        'indexes' => [],
        'temp' => true,
    ],
    'audit' => [
        'schema_cookie' => 901,
        'tables' => ['wp_schema_audit_replay_next901', 'wp_schema_audit_seal_next917'],
        'indexes' => ['wp_schema_audit_replay_key_next901', 'wp_schema_audit_seal_key_next917'],
        'file' => '/srv/wp/audit-next909.sqlite',
    ],
    'handoff' => [
        'schema_cookie' => 904,
        'tables' => ['wp_schema_handoff_receipt_next904'],
        'indexes' => ['wp_schema_handoff_receipt_key_next904'],
        'file' => '/srv/wp/handoff-next909.sqlite',
    ],
    'publish' => [
        'schema_cookie' => 906,
        'tables' => ['wp_schema_publish_seal_next906', 'wp_schema_publish_done_next906'],
        'indexes' => ['wp_schema_publish_seal_key_next906', 'wp_schema_publish_done_key_next906'],
        'file' => '/srv/wp/publish-next909.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 902,
        'tables' => ['wp_job_retry_checkpoint_archive_next898'],
        'indexes' => ['wp_job_retry_checkpoint_archive_key_next902'],
        'file' => '/srv/wp/queue-next909.sqlite',
    ],
    'rollout' => [
        'schema_cookie' => 904,
        'tables' => ['wp_schema_rollout_receipt_next904'],
        'indexes' => ['wp_schema_rollout_receipt_key_next904'],
        'file' => '/srv/wp/rollout-next909.sqlite',
    ],
];

$statements909924 = [
    ['name' => 'main-final-reader', 'sql' => 'SELECT nav_id FROM main.wp_navigation_rule_locale_publish_final_next924 INDEXED BY wp_navigation_rule_locale_publish_final_key_next924 WHERE nav_key = ?', 'active' => true],
    ['name' => 'publish-done-reader', 'sql' => 'SELECT publish_id FROM publish.wp_schema_publish_done_next906 INDEXED BY wp_schema_publish_done_key_next906 WHERE publish_key = ?'],
    ['name' => 'queue-archive-reader', 'sql' => 'SELECT job_id FROM queue.wp_job_retry_checkpoint_archive_next898 INDEXED BY wp_job_retry_checkpoint_archive_key_next902 WHERE job_id = ?'],
    ['name' => 'audit-seal-reader', 'sql' => 'SELECT seal_id FROM audit.wp_schema_audit_seal_next917 INDEXED BY wp_schema_audit_seal_key_next917 WHERE seal_key = ?'],
    ['name' => 'handoff-writer', 'sql' => 'UPDATE handoff.wp_schema_handoff_receipt_next904 INDEXED BY wp_schema_handoff_receipt_key_next904 SET accepted = 1 WHERE handoff_key = ?', 'active' => true],
    ['name' => 'rollout-reader', 'sql' => 'SELECT rollout_id FROM rollout.wp_schema_rollout_receipt_next904 INDEXED BY wp_schema_rollout_receipt_key_next904 WHERE rollout_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT archive_id FROM archive.wp_schema_archive_receipt_next920 INDEXED BY wp_schema_archive_receipt_key_next920 WHERE archive_key = ?'],
    ['name' => 'temp-notice-reader', 'sql' => 'SELECT notice_id FROM temp.wp_theme_stage_publish_notice_next912 WHERE cache_key = ?'],
];

$plan909924 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::schemaCacheMetricsWindow(
    $schemas ?? $schemas909924,
    $statements ?? $statements909924,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next909-924 extends next893-908 handoff'] = static function (TestRunner $t) use ($plan909924): void {
    $result = $plan909924([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 910, 'table' => 'wp_navigation_rule_locale_publish_delta_next910', 'indexes' => ['wp_navigation_rule_locale_publish_delta_key_next910'], 'commit' => true],
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 912, 'table' => 'wp_theme_stage_publish_notice_next912', 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_schema_audit_seal_key_next917', 'to' => 'wp_schema_audit_seal_key_next918'],
        ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 920, 'tables' => ['wp_schema_archive_receipt_next920'], 'indexes' => ['wp_schema_archive_receipt_key_next920'], 'file' => '/srv/wp/archive-next920.sqlite'],
        ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_retry_checkpoint_archive_next898'],
        ['op' => 'rename_table', 'schema' => 'handoff', 'from' => 'wp_schema_handoff_receipt_next904', 'to' => 'wp_schema_handoff_receipt_next922'],
        ['op' => 'wal_commit', 'schema' => 'publish', 'schema_cookie' => 923, 'table' => 'wp_schema_publish_final_next923', 'indexes' => ['wp_schema_publish_final_key_next923'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'rollout'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 924, 'table' => 'wp_navigation_rule_locale_publish_final_next924', 'indexes' => ['wp_navigation_rule_locale_publish_final_key_next924'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 909, 'table' => 'wp_navigation_rule_locale_publish_uncommitted_next909', 'indexes' => ['wp_navigation_rule_locale_publish_uncommitted_key_next909'], 'commit' => false],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next909-924', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next909', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next924', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next893', $result['dependencies'][16]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next908', $result['dependencies'][31]);
    $t->same(9, $result['event_count']);
    $t->same(924, $result['schema_cookies_next']['main']);
    $t->same(912, $result['schema_cookies_next']['temp']);
    $t->same(902, $result['schema_cookies_next']['audit']);
    $t->same(905, $result['schema_cookies_next']['handoff']);
    $t->same(923, $result['schema_cookies_next']['publish']);
    $t->same(903, $result['schema_cookies_next']['queue']);
    $t->same(920, $result['schema_cookies_next']['archive']);
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

$tests['attach temp wal schema cache current source next909-924 ignores detached transient archive'] = static function (TestRunner $t) use ($plan909924): void {
    $result = $plan909924([
        ['op' => 'attach', 'schema' => 'transient', 'schema_cookie' => 909, 'tables' => ['wp_transient_archive_next909'], 'indexes' => ['wp_transient_archive_key_next909'], 'file' => '/srv/wp/transient-next909.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'transient', 'schema_cookie' => 910, 'table' => 'wp_transient_archive_meta_next910', 'indexes' => ['wp_transient_archive_meta_key_next910'], 'commit' => false],
        ['op' => 'detach', 'schema' => 'transient'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'audit', 'handoff', 'publish', 'queue', 'rollout'], $result['search_order_next']);
};

return $tests;
