<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachSchemaCookieRepreparePlan;

$schemas84 = static fn (): array => [
    'main' => [
        'schema_cookie' => 81,
        'wal_schema_cookie' => 82,
        'tables' => ['wp_options', 'wp_posts'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 4,
        'tables' => ['wp_stage_options'],
        'file' => '',
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 12,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 13, 'commit' => true],
        ],
        'tables' => ['wp_archive_options'],
        'file' => '/srv/wp/archive.sqlite',
    ],
    'network' => [
        'schema_cookie' => 3,
        'tables' => ['wp_blogs'],
        'file' => '/srv/wp/network.sqlite',
    ],
];

$statements84 = static fn (): array => [
    ['name' => 'active-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?', 'active' => true],
    ['name' => 'archive-reader', 'sql' => 'SELECT option_name FROM archive.wp_archive_options'],
    ['name' => 'network-reader', 'sql' => 'SELECT blog_id FROM network.wp_blogs WHERE domain = ?'],
    ['name' => 'stage-insert', 'sql' => 'INSERT INTO wp_stage_options(option_name, option_value) VALUES (?, ?)'],
    ['name' => 'theme-update', 'sql' => 'UPDATE main.wp_posts SET post_title = ? WHERE ID = ?'],
    ['name' => 'new-blog-reader', 'sql' => 'SELECT option_value FROM blog42.wp_options WHERE option_name = ?'],
];

$events84 = static fn (): array => [
    ['op' => 'attach', 'schema' => 'blog42', 'schema_cookie' => 1, 'tables' => ['wp_options'], 'file' => '/srv/wp/blog42.sqlite'],
    ['op' => 'schema_write', 'schema' => 'main', 'object' => 'wp_plugin_state'],
    ['op' => 'detach', 'schema' => 'network'],
    ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 14],
];

$plan84 = static fn (?array $schemas = null, ?array $statements = null, ?array $events = null): array => SQLiteAttachSchemaCookieRepreparePlan::plan(
    $schemas ?? $schemas84(),
    $statements ?? $statements84(),
    $events ?? $events84(),
);

$value84 = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }

        return null;
    }

    return $cursor;
};

$pathCases84 = [
    'status expired' => ['status', 'schema_cache_expired'],
    'operation marker' => ['operation', 'attach-schema-cookie-reprepare-current-source'],
    'source main' => ['source', 'main'],
    'event count' => ['event_count', 4],
    'statement count' => ['statement_count', 6],
    'search order before' => ['search_order_before', ['temp', 'main', 'archive', 'network']],
    'search order after includes attached blog before network removal' => ['search_order_after', ['temp', 'main', 'archive', 'blog42']],
    'current main cookie from wal schema cookie' => ['schema_cookies_current.main', 82],
    'current archive cookie from committed page one frame' => ['schema_cookies_current.archive', 13],
    'next main cookie after schema write' => ['schema_cookies_next.main', 83],
    'next archive cookie after wal commit' => ['schema_cookies_next.archive', 14],
    'next blog attached cookie' => ['schema_cookies_next.blog42', 1],
    'detached network cookie removed' => ['schema_cookies_next.network', null],
    'changed schemas sorted' => ['changed_schemas', ['archive', 'blog42', 'main', 'network']],
    'attached schema list' => ['attached_schemas', ['blog42']],
    'detached schema list' => ['detached_schemas', ['network']],
    'written schemas include main archive' => ['written_schemas', ['main', 'archive']],
    'first event attach' => ['events.0.op', 'attach'],
    'first event schema' => ['events.0.schema', 'blog42'],
    'first event cookie' => ['events.0.schema_cookie', 1],
    'detach event has no cookie' => ['events.2.schema_cookie', null],
    'expired statements' => ['expired_statements', ['active-options-reader', 'archive-reader', 'network-reader', 'theme-update', 'new-blog-reader']],
    'stable statements' => ['stable_statements', ['stage-insert']],
    'active current snapshots' => ['active_current_snapshot_statements', ['active-options-reader']],
    'retryable reads' => ['retryable_read_statements', ['active-options-reader', 'archive-reader', 'network-reader', 'new-blog-reader']],
    'write blocked' => ['write_statements_blocked_before_retry', ['theme-update']],
    'global reprepare flag' => ['requires_reprepare', true],
    'active reader current sqlite ok' => ['statements.0.sqlite_result_on_current_step', 'SQLITE_OK'],
    'active reader next action' => ['statements.0.next_step_action', 'finish_current_source_then_sqlite_schema_on_reset'],
    'active reader prepare schema' => ['statements.0.prepare_schemas', ['main']],
    'active reader next schema' => ['statements.0.next_schemas', ['main']],
    'active reader cookie before' => ['statements.0.transitions.0.prepare_schema_cookie', 82],
    'active reader cookie after' => ['statements.0.transitions.0.next_schema_cookie', 83],
    'active reader cookie reprepare' => ['statements.0.transitions.0.requires_reprepare', true],
    'archive reader prepare schema' => ['statements.1.prepare_schemas', ['archive']],
    'archive reader cookie after' => ['statements.1.transitions.0.next_schema_cookie', 14],
    'archive reader action' => ['statements.1.next_step_action', 'sqlite_schema_then_reprepare_and_retry'],
    'network reader prepare found' => ['statements.2.transitions.0.prepare_found', true],
    'network reader next missing after detach' => ['statements.2.transitions.0.next_found', false],
    'network reader resolution changed' => ['statements.2.transitions.0.resolution_changed', true],
    'stage insert remains temp' => ['statements.3.next_schemas', ['temp']],
    'stage insert action' => ['statements.3.next_step_action', 'reuse_prepared_statement'],
    'theme update write action' => ['statements.4.next_step_action', 'sqlite_schema_before_write_retry'],
    'new blog prepare missing' => ['statements.5.transitions.0.prepare_found', false],
    'new blog next found after attach' => ['statements.5.transitions.0.next_found', true],
    'new blog prepare cookie null' => ['statements.5.transitions.0.prepare_schema_cookie', null],
    'new blog next cookie' => ['statements.5.transitions.0.next_schema_cookie', 1],
    'database before count' => ['database_list_before.3.name', 'network'],
    'database after attached file' => ['database_list_after.3.file', '/srv/wp/blog42.sqlite'],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-schema-cookie-reprepare-current-source'],
    'dependency current source expiry' => ['dependencies.1', 'sqlite-schema-cookie-current-source-expiry'],
    'dependency attach detach search order' => ['dependencies.2', 'sqlite-attach-detach-search-order-reprepare'],
];

foreach ($pathCases84 as $name => [$path, $expected]) {
    $tests['attach schema cookie reprepare current source next84 ' . $name] = static function (TestRunner $t) use ($plan84, $value84, $path, $expected): void {
        $t->same($expected, $value84($plan84(), $path));
    };
}

$predicateCases84 = [
    'no events keeps all statements stable' => static function () use ($schemas84, $statements84): bool {
        $result = SQLiteAttachSchemaCookieRepreparePlan::plan($schemas84(), $statements84(), []);
        return $result['status'] === 'schema_cache_stable'
            && $result['expired_statements'] === []
            && $result['stable_statements'] === array_column($statements84(), 'name');
    },
    'uncommitted wal commit event is ignored' => static function () use ($schemas84, $statements84): bool {
        $result = SQLiteAttachSchemaCookieRepreparePlan::plan($schemas84(), [$statements84()[1]], [
            ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 14, 'commit' => false],
        ]);
        return $result['requires_reprepare'] === false && $result['schema_cookies_next']['archive'] === 13;
    },
    'wal commit without explicit cookie increments current cookie' => static function () use ($schemas84, $statements84): bool {
        return SQLiteAttachSchemaCookieRepreparePlan::plan($schemas84(), [$statements84()[1]], [
            ['op' => 'wal_commit', 'schema' => 'archive'],
        ])['schema_cookies_next']['archive'] === 14;
    },
    'attach table changes unqualified missing table from main to attached' => static function () use ($schemas84): bool {
        $result = SQLiteAttachSchemaCookieRepreparePlan::plan($schemas84(), [
            ['name' => 'future-reader', 'sql' => 'SELECT * FROM plugin.wp_new'],
        ], [
            ['op' => 'attach', 'schema' => 'plugin', 'schema_cookie' => 5, 'tables' => ['wp_new']],
        ]);

        return $result['statements'][0]['transitions'][0]['prepare_found'] === false
            && $result['statements'][0]['transitions'][0]['next_found'] === true
            && $result['expired_statements'] === ['future-reader'];
    },
    'attach existing schema rejected' => static function () use ($schemas84, $statements84): bool {
        try {
            SQLiteAttachSchemaCookieRepreparePlan::plan($schemas84(), [$statements84()[0]], [
                ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 1],
            ]);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'detach main rejected' => static function () use ($schemas84, $statements84): bool {
        try {
            SQLiteAttachSchemaCookieRepreparePlan::plan($schemas84(), [$statements84()[0]], [
                ['op' => 'detach', 'schema' => 'main'],
            ]);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'missing source rejected' => static function () use ($schemas84, $statements84, $events84): bool {
        try {
            SQLiteAttachSchemaCookieRepreparePlan::plan($schemas84(), $statements84(), $events84(), 'missing');
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'empty prepared list rejected' => static function () use ($schemas84, $events84): bool {
        try {
            SQLiteAttachSchemaCookieRepreparePlan::plan($schemas84(), [], $events84());
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'empty sql rejected' => static function () use ($schemas84, $events84): bool {
        try {
            SQLiteAttachSchemaCookieRepreparePlan::plan($schemas84(), [['name' => 'empty', 'sql' => '']], $events84());
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'select without table rejected' => static function () use ($schemas84, $events84): bool {
        try {
            SQLiteAttachSchemaCookieRepreparePlan::plan($schemas84(), [['name' => 'scalar', 'sql' => 'SELECT 1']], $events84());
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'non integer schema cookie rejected' => static function () use ($statements84, $events84): bool {
        try {
            SQLiteAttachSchemaCookieRepreparePlan::plan(['main' => ['schema_cookie' => '1']], [$statements84()[0]], $events84());
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'wal frame without integer page rejected' => static function () use ($statements84, $events84): bool {
        try {
            SQLiteAttachSchemaCookieRepreparePlan::plan(['main' => ['schema_cookie' => 1, 'tables' => ['wp_options'], 'wal_frames' => [['page' => '1']]]], [$statements84()[0]], $events84());
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'unsupported event rejected' => static function () use ($schemas84, $statements84): bool {
        try {
            SQLiteAttachSchemaCookieRepreparePlan::plan($schemas84(), [$statements84()[0]], [['op' => 'vacuum']]);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'quoted schema names normalize' => static function () use ($schemas84): bool {
        $result = SQLiteAttachSchemaCookieRepreparePlan::plan($schemas84(), [
            ['name' => 'quoted', 'sql' => 'SELECT * FROM "ARCHIVE"."WP_ARCHIVE_OPTIONS"'],
        ], [
            ['op' => 'wal_commit', 'schema' => '`ARCHIVE`', 'schema_cookie' => 16],
        ]);

        return $result['statements'][0]['transitions'][0]['next_schema'] === 'archive'
            && $result['schema_cookies_next']['archive'] === 16;
    },
    'delete statement is write blocked after cookie change' => static function () use ($schemas84): bool {
        $result = SQLiteAttachSchemaCookieRepreparePlan::plan($schemas84(), [
            ['name' => 'delete-options', 'sql' => 'DELETE FROM main.wp_options WHERE option_name = ?'],
        ], [
            ['op' => 'schema_write', 'schema' => 'main'],
        ]);

        return $result['write_statements_blocked_before_retry'] === ['delete-options']
            && $result['statements'][0]['read_only'] === false;
    },
];

foreach ($predicateCases84 as $name => $predicate) {
    $tests['attach schema cookie reprepare current source next84 ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

return $tests;
