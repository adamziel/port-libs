<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCookieSourcePlan;

$tests = [];

$schemas = static fn (): array => [
    'main' => [
        'schema_cookie' => 100,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 101, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 107, 'commit' => false],
        ],
        'wal_schema_cookie' => 102,
        'tables' => ['sqlite_schema', 'wp_options', 'wp_posts', 'recent_options'],
        'next_tables' => ['sqlite_schema', 'wp_options', 'wp_posts', 'recent_options', 'wp_plugin_state'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 11,
        'temp_schema_cookie' => 12,
        'tables' => ['sqlite_schema', 'wp_options_stage', 'recent_options'],
        'next_tables' => ['sqlite_schema', 'wp_options_stage', 'recent_options', 'wp_options'],
        'file' => '',
        'temp' => true,
    ],
    'site' => [
        'schema_cookie' => 20,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 21, 'commit' => true],
        ],
        'tables' => ['sqlite_schema', 'wp_2_options', 'wp_blogs'],
        'next_tables' => ['sqlite_schema', 'wp_2_options', 'wp_blogs'],
        'file' => '/srv/wp/site.sqlite',
    ],
    'archive' => [
        'schema_cookie' => 30,
        'wal_schema_cookie' => 31,
        'tables' => ['sqlite_schema', 'wp_archive_options'],
        'next_tables' => ['sqlite_schema', 'wp_archive_options'],
        'file' => '/srv/wp/archive.sqlite',
    ],
];

$statements = static fn (): array => [
    [
        'name' => 'cte-main-options',
        'sql' => 'WITH recent_options AS (SELECT option_name FROM main.wp_options WHERE autoload = ?) SELECT option_name FROM recent_options ORDER BY option_name',
    ],
    [
        'name' => 'cte-temp-stage',
        'sql' => 'WITH staged(name) AS (SELECT option_name FROM temp.wp_options_stage), ranked AS (SELECT name FROM staged) SELECT name FROM ranked',
    ],
    [
        'name' => 'recursive-site-blogs',
        'sql' => 'WITH RECURSIVE blog_tree(id) AS (SELECT blog_id FROM site.wp_blogs UNION ALL SELECT id + 1 FROM blog_tree WHERE id < 3) SELECT id FROM blog_tree',
    ],
    [
        'name' => 'qualified-real-table-not-cte',
        'sql' => 'WITH recent_options AS (SELECT option_name FROM main.wp_options) SELECT option_name FROM main.recent_options',
    ],
    [
        'name' => 'insert-from-cte',
        'sql' => 'WITH incoming AS (SELECT option_name FROM main.wp_options) INSERT INTO temp.wp_options_stage(option_name) SELECT option_name FROM incoming',
    ],
    [
        'name' => 'delete-with-cte',
        'sql' => 'WITH stale AS (SELECT option_name FROM archive.wp_archive_options) DELETE FROM main.wp_plugin_state WHERE option_name IN (SELECT option_name FROM stale)',
    ],
    [
        'name' => 'quoted-cte',
        'sql' => 'WITH "Plugin Rows" AS (SELECT option_name FROM [main].[wp_options]) SELECT option_name FROM "Plugin Rows"',
    ],
];

$plan = static fn (?array $schemasArg = null, ?array $statementsArg = null, string $source = 'main'): array => SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan(
    $schemasArg ?? $schemas(),
    $statementsArg ?? $statements(),
    $source,
);

$value = static function (array $data, string $path): mixed {
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

$pathCases = [
    'operation marker' => ['operation', 'attach-temp-wal-schema-cookie-cte-source'],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-temp-wal-schema-cookie-cte-source'],
    'cte dependency marker' => ['dependencies.3', 'sqlite-with-cte-schema-cookie-source-filter'],
    'recursive dependency marker' => ['dependencies.4', 'sqlite-recursive-cte-attach-temp-wal-source'],
    'search order' => ['search_order', ['temp', 'main', 'site', 'archive']],
    'main current cookie from committed wal frame' => ['schema_cookies_current.main', 101],
    'main next cookie from wal commit header' => ['schema_cookies_next.main', 102],
    'main uncommitted page one ignored' => ['schema_cookie_sources.main.wal_tail_ignored', true],
    'temp next cookie from rollback journal' => ['schema_cookies_next.temp', 12],
    'site current cookie from wal page one' => ['schema_cookies_current.site', 21],
    'archive next cookie from wal header' => ['schema_cookies_next.archive', 31],
    'changed schemas include temp main archive' => ['changed_schemas', ['temp', 'main', 'archive']],
    'statement count' => ['statement_count', 7],
    'cte main options only tracks real table' => ['statements.0.tables', ['main.wp_options']],
    'cte main prepare schema' => ['statements.0.schema_transitions.0.prepare_schema', 'main'],
    'cte main next cookie source' => ['statements.0.schema_transitions.0.next_cookie_source', 'wal_commit_header'],
    'cte main reprepare' => ['statements.0.requires_reprepare', true],
    'temp stage CTE ignores staged and ranked names' => ['statements.1.tables', ['temp.wp_options_stage']],
    'temp stage source is rollback journal' => ['statements.1.schema_transitions.0.next_cookie_source', 'temp_rollback_journal'],
    'temp stage read retryable' => ['statements.1.next_step_action', 'sqlite_schema_then_reprepare'],
    'recursive CTE ignores self reference' => ['statements.2.tables', ['site.wp_blogs']],
    'recursive site query stable' => ['statements.2.requires_reprepare', false],
    'recursive site action' => ['statements.2.next_step_action', 'reuse_prepared_statement'],
    'qualified real table still tracked after CTE name collision' => ['statements.3.tables', ['main.wp_options', 'main.recent_options']],
    'qualified table transition count' => ['statements.3.schema_transitions.1.table', 'main.recent_options'],
    'insert CTE tracks source and target' => ['statements.4.tables', ['main.wp_options', 'temp.wp_options_stage']],
    'insert CTE is write' => ['statements.4.read_only', false],
    'insert CTE blocks write retry' => ['statements.4.next_step_action', 'sqlite_schema_before_write_retry'],
    'delete CTE tracks archive and missing main target' => ['statements.5.tables', ['archive.wp_archive_options', 'main.wp_plugin_state']],
    'delete target next exists' => ['statements.5.schema_transitions.1.next_found', true],
    'delete target changed' => ['statements.5.schema_transitions.1.requires_reprepare', true],
    'quoted CTE ignores quoted source name' => ['statements.6.tables', ['main.wp_options']],
    'quoted CTE normalized source table' => ['statements.6.schema_transitions.0.table', 'main.wp_options'],
    'expired statements' => ['expired_statements', ['cte-main-options', 'cte-temp-stage', 'qualified-real-table-not-cte', 'insert-from-cte', 'delete-with-cte', 'quoted-cte']],
    'stable statements' => ['stable_statements', ['recursive-site-blogs']],
    'retryable read statements' => ['retryable_read_statements', ['cte-main-options', 'cte-temp-stage', 'qualified-real-table-not-cte', 'quoted-cte']],
    'write blocked statements' => ['write_statements_blocked_before_retry', ['insert-from-cte', 'delete-with-cte']],
    'database archive next cookie' => ['database_list.3.next_schema_cookie', 31],
    'database temp file' => ['database_list.0.file', ''],
];

foreach ($pathCases as $name => [$path, $expected]) {
    $tests['attach temp wal schema cookie cte source ' . $name] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
        $t->same($expected, $value($plan(), $path));
    };
}

$predicateCases99 = [
    'cte only query is rejected when no real table exists' => static function () use ($schemas): bool {
        try {
            SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan($schemas(), [
                ['name' => 'cte-only', 'sql' => 'WITH only_cte AS (SELECT 1) SELECT * FROM only_cte'],
            ]);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'unqualified table matching CTE name is ignored but qualified table remains real' => static function () use ($schemas): bool {
        $result = SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan($schemas(), [
            ['name' => 'shadow', 'sql' => 'WITH recent_options AS (SELECT option_name FROM main.wp_options) SELECT option_name FROM recent_options JOIN main.recent_options USING(option_name)'],
        ]);

        return $result['statements'][0]['tables'] === ['main.wp_options', 'main.recent_options'];
    },
    'multiple CTE definitions are ignored in later joins' => static function () use ($schemas): bool {
        $result = SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan($schemas(), [
            ['name' => 'multi', 'sql' => 'WITH a AS (SELECT option_name FROM main.wp_options), b AS (SELECT option_name FROM a) SELECT * FROM b JOIN archive.wp_archive_options USING(option_name)'],
        ]);

        return $result['statements'][0]['tables'] === ['main.wp_options', 'archive.wp_archive_options'];
    },
    'recursive CTE self reference does not add missing main table' => static function () use ($schemas): bool {
        $result = SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan($schemas(), [
            ['name' => 'recursive', 'sql' => 'WITH RECURSIVE q(x) AS (SELECT blog_id FROM site.wp_blogs UNION ALL SELECT x + 1 FROM q WHERE x < 9) SELECT x FROM q'],
        ]);

        return $result['statements'][0]['tables'] === ['site.wp_blogs']
            && $result['statements'][0]['requires_reprepare'] === false;
    },
    'cte insert target remains write blocked by temp cookie' => static function () use ($schemas): bool {
        $result = SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan($schemas(), [
            ['name' => 'insert-stage', 'sql' => 'WITH rows AS (SELECT option_name FROM main.wp_options) INSERT INTO temp.wp_options_stage SELECT option_name FROM rows'],
        ]);

        return $result['write_statements_blocked_before_retry'] === ['insert-stage'];
    },
    'active CTE reader finishes current snapshot before schema reset' => static function () use ($schemas): bool {
        $result = SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan($schemas(), [
            ['name' => 'active', 'active' => true, 'sql' => 'WITH rows AS (SELECT option_name FROM main.wp_options) SELECT option_name FROM rows'],
        ]);

        return $result['active_current_snapshot_statements'] === ['active']
            && $result['statements'][0]['next_step_action'] === 'finish_current_snapshot_then_sqlite_schema_on_reset';
    },
    'quoted CTE name with column list is ignored' => static function () use ($schemas): bool {
        $result = SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan($schemas(), [
            ['name' => 'quoted-columns', 'sql' => 'WITH [Plugin Rows](name) AS (SELECT option_name FROM main.wp_options) SELECT name FROM [Plugin Rows]'],
        ]);

        return $result['statements'][0]['tables'] === ['main.wp_options'];
    },
    'stable attached source remains stable when CTE shadows temp table name' => static function () use ($schemas): bool {
        $result = SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan($schemas(), [
            ['name' => 'stable-site', 'sql' => 'WITH recent_options AS (SELECT blog_id FROM site.wp_blogs) SELECT blog_id FROM recent_options'],
        ]);

        return $result['status'] === 'schema_cache_stable';
    },
    'source schema may be bracket quoted' => static function () use ($schemas): bool {
        return SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan($schemas(), [
            ['name' => 'source', 'sql' => 'WITH rows AS (SELECT option_name FROM main.wp_options) SELECT option_name FROM rows'],
        ], '[archive]')['source'] === 'archive';
    },
    'missing source schema still rejected' => static function () use ($schemas): bool {
        try {
            SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan($schemas(), [
                ['name' => 'source', 'sql' => 'WITH rows AS (SELECT option_name FROM main.wp_options) SELECT option_name FROM rows'],
            ], 'missing');
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'empty statement list rejected' => static function () use ($schemas): bool {
        try {
            SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan($schemas(), []);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'cte name does not suppress same-name table in schema-qualified update' => static function () use ($schemas): bool {
        $result = SQLiteAttachWalTempSchemaCookieSourcePlan::cteSchemaCookieSourcePlan($schemas(), [
            ['name' => 'qualified-update', 'sql' => 'WITH recent_options AS (SELECT option_name FROM main.wp_options) UPDATE main.recent_options SET option_name = option_name'],
        ]);

        return $result['statements'][0]['tables'] === ['main.wp_options', 'main.recent_options']
            && $result['statements'][0]['read_only'] === false;
    },
];

foreach ($predicateCases99 as $name => $predicate) {
    $tests['attach temp wal schema cookie cte source ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->true($predicate());
    };
}

return $tests;
