<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas92 = [
    'main' => [
        'schema_cookie' => 11,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 12, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_posts', 'wp_optionmeta'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 3,
        'tables' => ['wp_options_stage'],
        'temp' => true,
        'file' => '',
    ],
    'site' => [
        'schema_cookie' => 6,
        'tables' => ['wp_options', 'wp_site_meta'],
        'file' => '/srv/wp/site.sqlite',
    ],
    'archive' => [
        'schema_cookie' => 4,
        'tables' => ['wp_options_archive', 'wp_legacy_meta'],
        'file' => '/srv/wp/archive.sqlite',
    ],
];

$statements92 = [
    ['name' => 'active-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?', 'active' => true],
    ['name' => 'stage-writer', 'sql' => 'INSERT INTO temp.wp_options_stage(option_name, option_value) VALUES (?, ?)'],
    ['name' => 'main-post-reader', 'sql' => 'SELECT post_title FROM main.wp_posts WHERE ID = ?'],
    ['name' => 'site-meta-reader', 'sql' => 'SELECT meta_value FROM site.wp_site_meta WHERE meta_key = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT option_value FROM archive.wp_options_archive WHERE option_name = ?'],
    ['name' => 'future-unqualified-reader', 'sql' => 'SELECT option_value FROM wp_plugin_state WHERE option_name = ?'],
    ['name' => 'delete-main-option', 'sql' => 'DELETE FROM main.wp_options WHERE option_name = ?'],
    ['name' => 'quoted-temp-reader', 'sql' => 'SELECT option_name FROM "temp"."wp_options_stage"'],
];

$events92 = [
    ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 13, 'table' => 'wp_plugin_state'],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'attach', 'schema' => 'analytics', 'schema_cookie' => 1, 'tables' => ['wp_events'], 'file' => '/srv/wp/analytics.sqlite'],
];

$plan92 = static fn (?array $events = null, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::plan(
    $schemas ?? $schemas92,
    $statements ?? $statements92,
    $events ?? $events92,
);

$value92 = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        $cursor = is_numeric($part) ? $cursor[(int) $part] : $cursor[$part];
    }

    return $cursor;
};

$pathCases92 = [
    'status expired' => ['status', 'schema_cache_expired'],
    'operation marker' => ['operation', 'attach-wal-temp-schema-cache-current-source-next92'],
    'source main' => ['source', 'main'],
    'event count' => ['event_count', 4],
    'statement count' => ['statement_count', 8],
    'search order current' => ['search_order_current', ['temp', 'main', 'archive', 'site']],
    'search order next includes attached analytics' => ['search_order_next', ['temp', 'main', 'analytics', 'site']],
    'current main cookie uses WAL page one' => ['schema_cookies_current.main', 12],
    'next main cookie explicit WAL commit' => ['schema_cookies_next.main', 13],
    'temp cookie advanced' => ['schema_cookies_next.temp', 4],
    'site cookie stable' => ['schema_cookies_next.site', 6],
    'archive detached changed' => ['changed_schemas', ['temp', 'main', 'analytics', 'archive']],
    'expired statements list' => ['expired_statements', ['active-options-reader', 'stage-writer', 'main-post-reader', 'archive-reader', 'future-unqualified-reader', 'delete-main-option', 'quoted-temp-reader']],
    'stable statements list' => ['stable_statements', ['site-meta-reader']],
    'active current snapshot list' => ['active_current_snapshot_statements', ['active-options-reader']],
    'retryable read list' => ['retryable_read_statements', ['active-options-reader', 'main-post-reader', 'archive-reader', 'future-unqualified-reader', 'quoted-temp-reader']],
    'write blocked list' => ['write_statements_blocked_before_retry', ['stage-writer', 'delete-main-option']],
    'requires reprepare' => ['requires_reprepare', true],
    'active reader current schema main' => ['statements.active-options-reader.schema_transitions.0.current_schema', 'main'],
    'active reader next schema temp' => ['statements.active-options-reader.schema_transitions.0.next_schema', 'temp'],
    'active reader current found' => ['statements.active-options-reader.schema_transitions.0.current_found', true],
    'active reader next found' => ['statements.active-options-reader.schema_transitions.0.next_found', true],
    'active reader resolution changed' => ['statements.active-options-reader.schema_transitions.0.resolution_changed', true],
    'active reader current step ok' => ['statements.active-options-reader.sqlite_result_on_current_step', 'SQLITE_OK'],
    'active reader reset action' => ['statements.active-options-reader.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'stage writer current temp' => ['statements.stage-writer.current_schemas', ['temp']],
    'stage writer next temp' => ['statements.stage-writer.next_schemas', ['temp']],
    'stage writer cookie changed' => ['statements.stage-writer.schema_transitions.0.schema_cookie_changed', true],
    'stage writer write action' => ['statements.stage-writer.next_step_action', 'sqlite_schema_before_write_retry'],
    'main post reader cookie changed' => ['statements.main-post-reader.schema_transitions.0.schema_cookie_changed', true],
    'main post reader read action' => ['statements.main-post-reader.next_step_action', 'sqlite_schema_then_reprepare_read_statement'],
    'site reader stable action' => ['statements.site-meta-reader.next_step_action', 'reuse_prepared_statement_current_source'],
    'site reader not expired' => ['statements.site-meta-reader.requires_reprepare', false],
    'archive reader detached next schema' => ['statements.archive-reader.schema_transitions.0.next_schema', '__detached__'],
    'archive reader next missing' => ['statements.archive-reader.schema_transitions.0.next_found', false],
    'archive reader expires on detach' => ['statements.archive-reader.requires_reprepare', true],
    'future reader current missing' => ['statements.future-unqualified-reader.schema_transitions.0.current_found', false],
    'future reader next main found' => ['statements.future-unqualified-reader.schema_transitions.0.next_found', true],
    'future reader next schema main' => ['statements.future-unqualified-reader.schema_transitions.0.next_schema', 'main'],
    'delete main read only false' => ['statements.delete-main-option.read_only', false],
    'delete main write action' => ['statements.delete-main-option.next_step_action', 'sqlite_schema_before_write_retry'],
    'quoted temp table parsed' => ['statements.quoted-temp-reader.tables', ['temp.wp_options_stage']],
    'quoted temp current schema' => ['statements.quoted-temp-reader.schema_transitions.0.current_schema', 'temp'],
    'quoted temp expires on temp schema write' => ['statements.quoted-temp-reader.requires_reprepare', true],
    'attach event logged' => ['events.3.op', 'attach'],
    'attach event schema' => ['events.3.schema', 'analytics'],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-wal-temp-schema-cache-current-source-next92'],
];

foreach ($pathCases92 as $name => [$path, $expected]) {
    $tests['attach wal temp schema cache current source next92 ' . $name] = static function (TestRunner $t) use ($plan92, $value92, $path, $expected): void {
        $t->same($expected, $value92($plan92(), $path));
    };
}

$tests['attach wal temp schema cache current source next92 temp drop reveals main source'] = static function (TestRunner $t) use ($plan92): void {
    $schemas = [
        'main' => ['schema_cookie' => 5, 'tables' => ['wp_options']],
        'temp' => ['schema_cookie' => 2, 'tables' => ['wp_options'], 'temp' => true],
    ];
    $result = $plan92([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ], [
        ['name' => 'reader', 'sql' => 'SELECT option_value FROM wp_options', 'active' => true],
    ], $schemas);
    $t->same('temp', $result['statements']['reader']['schema_transitions'][0]['current_schema']);
    $t->same('main', $result['statements']['reader']['schema_transitions'][0]['next_schema']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['reader']['next_step_action']);
};

$tests['attach wal temp schema cache current source next92 rollback-like uncommitted wal frame ignored'] = static function (TestRunner $t) use ($plan92): void {
    $result = $plan92([], [
        ['name' => 'reader', 'sql' => 'SELECT post_title FROM main.wp_posts'],
    ], [
        'main' => [
            'schema_cookie' => 7,
            'wal_frames' => [
                ['page' => 1, 'schema_cookie' => 99, 'commit' => false],
            ],
            'tables' => ['wp_posts'],
        ],
    ]);
    $t->same('schema_cache_stable', $result['status']);
    $t->same(7, $result['schema_cookies_current']['main']);
};

$tests['attach wal temp schema cache current source next92 committed later wal frame wins current source'] = static function (TestRunner $t) use ($plan92): void {
    $result = $plan92([], [
        ['name' => 'reader', 'sql' => 'SELECT post_title FROM main.wp_posts'],
    ], [
        'main' => [
            'schema_cookie' => 7,
            'wal_frames' => [
                ['page' => 2, 'schema_cookie' => 88, 'commit' => true],
                ['page' => 1, 'schema_cookie' => 8, 'commit' => true],
                ['page' => 1, 'schema_cookie' => 9, 'commit' => true],
            ],
            'tables' => ['wp_posts'],
        ],
    ]);
    $t->same('schema_cache_stable', $result['status']);
    $t->same(9, $result['schema_cookies_current']['main']);
};

$tests['attach wal temp schema cache current source next92 quoted attach schema normalizes'] = static function (TestRunner $t) use ($plan92): void {
    $result = $plan92([
        ['op' => 'attach', 'schema' => '"ArchiveTwo"', 'schema_cookie' => 1, 'tables' => ['Wp_Options']],
    ], [
        ['name' => 'future', 'sql' => 'SELECT option_value FROM archivetwo.wp_options'],
    ], [
        'main' => ['schema_cookie' => 1, 'tables' => []],
    ]);
    $t->same('__detached__', $result['statements']['future']['schema_transitions'][0]['current_schema']);
    $t->same('archivetwo', $result['statements']['future']['schema_transitions'][0]['next_schema']);
    $t->same(true, $result['statements']['future']['requires_reprepare']);
};

$tests['attach wal temp schema cache current source next92 rejects bad detach'] = static function (TestRunner $t) use ($plan92): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan92([
        ['op' => 'detach', 'schema' => 'main'],
    ]));
};

$tests['attach wal temp schema cache current source next92 rejects noninteger wal cookie'] = static function (TestRunner $t) use ($plan92): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan92([], null, [
        'main' => ['schema_cookie' => 1, 'wal_schema_cookie' => 'bad', 'tables' => ['wp_options']],
    ]));
};

$tests['attach wal temp schema cache current source next525-540 follow-on expires changed sources'] = static function (TestRunner $t): void {
    $plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext525540([
        'main' => ['schema_cookie' => 525, 'tables' => ['wp_options', 'wp_navigation_preview_next523'], 'indexes' => ['wp_options_name', 'wp_navigation_preview_slug_next523'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 525, 'commit' => true]]],
        'temp' => ['schema_cookie' => 521, 'tables' => ['wp_theme_stage_publish_retries_next521'], 'indexes' => ['wp_theme_stage_publish_retries_key_next521'], 'temp' => true],
        'analytics' => ['schema_cookie' => 518, 'tables' => ['wp_event_capacity_bucket_next518'], 'indexes' => ['wp_event_capacity_bucket_day_next518'], 'file' => '/srv/wp/analytics-next525.sqlite'],
        'queue' => ['schema_cookie' => 522, 'tables' => ['wp_job_retry_audit_next514'], 'indexes' => ['wp_job_retry_audit_job_next520'], 'file' => '/srv/wp/queue-next525.sqlite'],
        'campaign' => ['schema_cookie' => 524, 'tables' => ['wp_campaign_restore_next524'], 'indexes' => ['wp_campaign_restore_slug_next524'], 'file' => '/srv/wp/campaign-next525.sqlite'],
    ], [
        ['name' => 'nav-preview-reader', 'sql' => 'SELECT preview_id FROM main.wp_navigation_preview_next523 INDEXED BY wp_navigation_preview_slug_next523 WHERE slug = ?', 'active' => true],
        ['name' => 'temp-retry-writer', 'sql' => 'UPDATE temp.wp_theme_stage_publish_retries_next521 INDEXED BY wp_theme_stage_publish_retries_key_next521 SET tries = tries + 1 WHERE cache_key = ?'],
        ['name' => 'analytics-bucket-reader', 'sql' => 'SELECT bucket_id FROM analytics.wp_event_capacity_bucket_next518 INDEXED BY wp_event_capacity_bucket_day_next518 WHERE day = ?'],
        ['name' => 'queue-audit-reader', 'sql' => 'SELECT status FROM queue.wp_job_retry_audit_next514 INDEXED BY wp_job_retry_audit_job_next520 WHERE job_id = ?'],
        ['name' => 'campaign-restore-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaign_restore_next524 INDEXED BY wp_campaign_restore_slug_next524 WHERE slug = ?'],
        ['name' => 'cdn-cache-reader', 'sql' => 'SELECT cache_id FROM cdn.wp_edge_cache_next532 INDEXED BY wp_edge_cache_slug_next532 WHERE slug = ?'],
        ['name' => 'search-writer', 'sql' => 'UPDATE search.wp_search_queue_next537 INDEXED BY wp_search_queue_slug_next537 SET touched = 1 WHERE slug = ?'],
    ], [
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 525, 'table' => 'wp_navigation_rule_next525', 'indexes' => ['wp_navigation_rule_slug_next525'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'campaign', 'from' => 'wp_campaign_restore_slug_next524', 'to' => 'wp_campaign_restore_slug_next526'],
        ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_capacity_bucket_next518'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 528, 'table' => 'wp_job_retry_window_next528', 'indexes' => ['wp_job_retry_window_job_next528'], 'commit' => true],
        ['op' => 'rename_table', 'schema' => 'temp', 'from' => 'wp_theme_stage_publish_retries_next521', 'to' => 'wp_theme_stage_publish_retries_next529'],
        ['op' => 'attach', 'schema' => 'cdn', 'schema_cookie' => 532, 'tables' => ['wp_edge_cache_next532'], 'indexes' => ['wp_edge_cache_slug_next532'], 'file' => '/srv/wp/cdn-next532.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'cdn', 'schema_cookie' => 533, 'table' => 'wp_edge_cache_meta_next533', 'indexes' => ['wp_edge_cache_meta_key_next533'], 'commit' => true],
        ['op' => 'detach', 'schema' => 'cdn'],
        ['op' => 'attach', 'schema' => 'search', 'schema_cookie' => 537, 'tables' => ['wp_search_queue_next537'], 'indexes' => ['wp_search_queue_slug_next537'], 'file' => '/srv/wp/search-next537.sqlite'],
        ['op' => 'wal_commit', 'schema' => 'search', 'schema_cookie' => 538, 'table' => 'wp_search_queue_meta_next538', 'indexes' => ['wp_search_queue_meta_key_next538'], 'commit' => true],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 539, 'table' => 'wp_navigation_rule_shadow_next539', 'indexes' => ['wp_navigation_rule_shadow_slug_next539'], 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 540, 'table' => 'wp_campaign_restore_meta_next540', 'indexes' => ['wp_campaign_restore_meta_key_next540'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next525-540', $plan['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next525', $plan['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next540', $plan['dependencies'][15]);
    $t->same(11, $plan['event_count']);
    $t->same(['temp', 'analytics', 'campaign', 'queue', 'search'], $plan['changed_schemas']);
    $t->same(525, $plan['schema_cookies_next']['main']);
    $t->same(522, $plan['schema_cookies_next']['temp']);
    $t->same(519, $plan['schema_cookies_next']['analytics']);
    $t->same(528, $plan['schema_cookies_next']['queue']);
    $t->same(540, $plan['schema_cookies_next']['campaign']);
    $t->same(['temp', 'main', 'analytics', 'campaign', 'queue', 'search'], $plan['search_order_next']);
    $t->same('__detached__', $plan['statements']['cdn-cache-reader']['schema_transitions'][0]['next_schema']);
    $t->same('search', $plan['statements']['search-writer']['schema_transitions'][0]['next_schema']);
    $t->same(false, $plan['statements']['analytics-bucket-reader']['schema_transitions'][0]['next_found']);
    $t->same('sqlite_schema_before_write_retry', $plan['statements']['search-writer']['next_step_action']);
};

return $tests;
