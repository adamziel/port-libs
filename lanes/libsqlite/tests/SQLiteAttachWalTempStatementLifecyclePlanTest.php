<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempStatementLifecyclePlan;

$tests = [];

$schemas = [
    'main' => [
        'schema_cookie' => 40,
        'wal_schema_cookie' => 41,
        'tables' => ['wp_options', 'wp_posts'],
        'next_tables' => ['wp_posts'],
        'indexes' => ['wp_options_name'],
        'next_indexes' => [],
        'file' => '/srv/wp/current.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 7,
        'tables' => ['wp_options_stage'],
        'next_tables' => ['wp_options'],
        'indexes' => ['wp_options_stage_name'],
        'next_indexes' => ['wp_options_name_temp'],
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 10,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 11, 'commit' => true],
        ],
        'tables' => ['wp_archive_options'],
        'next_tables' => ['wp_archive_options', 'wp_options'],
        'indexes' => [],
        'next_indexes' => ['wp_options_name'],
        'file' => '/srv/wp/archive.sqlite',
        'cache' => 'shared',
    ],
    'network' => [
        'schema_cookie' => 2,
        'tables' => ['wp_blogs'],
        'next_tables' => ['wp_blogs'],
        'indexes' => [],
        'next_indexes' => [],
        'file' => '/srv/wp/network.sqlite',
    ],
];

$statements = [
    ['name' => 'active-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?', 'active' => true],
    ['name' => 'stage-insert', 'sql' => 'INSERT INTO wp_options_stage(option_name, option_value) VALUES (?, ?)'],
    ['name' => 'archive-reader', 'sql' => 'SELECT option_name FROM archive.wp_options'],
    ['name' => 'network-reader', 'sql' => 'SELECT blog_id FROM network.wp_blogs WHERE domain = ?'],
    ['name' => 'main-post-update', 'sql' => 'UPDATE main.wp_posts SET post_title = ? WHERE ID = ?'],
    ['name' => 'delete-options', 'sql' => 'DELETE FROM wp_options WHERE option_name LIKE ?'],
];

$plan = static fn (?array $overrideSchemas = null, ?array $overrideStatements = null): array => SQLiteAttachWalTempStatementLifecyclePlan::plan(
    $overrideSchemas ?? $schemas,
    $overrideStatements ?? $statements,
);

$value = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        $cursor = is_numeric($part) ? $cursor[(int) $part] : $cursor[$part];
    }

    return $cursor;
};

$cases = [
    'status is schema changed' => ['status', 'schema_changed'],
    'operation name' => ['operation', 'attach-wal-temp-statement-lifecycle'],
    'source schema' => ['source', 'main'],
    'search order' => ['search_order', ['temp', 'main', 'archive', 'network']],
    'current main cookie' => ['schema_cookies_current.main', 40],
    'next main cookie from wal schema cookie' => ['schema_cookies_next.main', 41],
    'current archive cookie' => ['schema_cookies_current.archive', 10],
    'next archive cookie from committed wal page one' => ['schema_cookies_next.archive', 11],
    'changed schemas' => ['changed_schemas', ['main', 'archive']],
    'object changed schemas' => ['object_changed_schemas', ['temp', 'main', 'archive']],
    'statement count' => ['statement_count', 6],
    'requires reprepare' => ['requires_reprepare', true],
    'expired statements' => ['expired_statements', ['active-options-reader', 'stage-insert', 'archive-reader', 'main-post-update', 'delete-options']],
    'stable statements' => ['stable_statements', ['network-reader']],
    'dependency slice marker' => ['dependencies.0', 'sqlite-attach-wal-temp-statement-lifecycle'],
    'dependency schema cookie expiry' => ['dependencies.1', 'sqlite-schema-cookie-expire-prepared-statements'],
    'dependency wal ddl lifecycle' => ['dependencies.2', 'sqlite-wal-ddl-statement-lifecycle'],
    'active reader active flag' => ['statements.0.active', true],
    'active reader read only' => ['statements.0.read_only', true],
    'active reader table' => ['statements.0.tables.0', 'wp_options'],
    'active reader current schema main' => ['statements.0.current_schemas.0', 'main'],
    'active reader next schema temp' => ['statements.0.next_schemas.0', 'temp'],
    'active reader current step continues' => ['statements.0.current_step_action', 'continue_current_snapshot'],
    'active reader reset action' => ['statements.0.next_step_action', 'finish_current_snapshot_then_sqlite_schema_on_reset'],
    'active reader current result ok' => ['statements.0.sqlite_result', 'SQLITE_OK'],
    'active reader retryable' => ['statements.0.retryable_after_reprepare', true],
    'active reader resolution changes' => ['statements.0.schema_transitions.0.resolution_changed', true],
    'stage insert write statement' => ['statements.1.read_only', false],
    'stage insert current schema temp' => ['statements.1.current_schemas.0', 'temp'],
    'stage insert next schema main after temp rename' => ['statements.1.next_schemas.0', 'main'],
    'stage insert next action' => ['statements.1.next_step_action', 'sqlite_schema_before_write_retry'],
    'stage insert result' => ['statements.1.sqlite_result', 'SQLITE_SCHEMA'],
    'stage insert not retryable by read reprepare' => ['statements.1.retryable_after_reprepare', false],
    'archive reader current missing' => ['statements.2.schema_transitions.0.current_found', false],
    'archive reader next found' => ['statements.2.schema_transitions.0.next_found', true],
    'archive reader current schema archive' => ['statements.2.current_schemas.0', 'archive'],
    'archive reader next schema archive' => ['statements.2.next_schemas.0', 'archive'],
    'archive reader reprepare action' => ['statements.2.next_step_action', 'sqlite_schema_then_reprepare_read_statement'],
    'network reader stable action' => ['statements.3.next_step_action', 'reuse_prepared_statement'],
    'network reader sqlite ok' => ['statements.3.sqlite_result', 'SQLITE_OK'],
    'network reader no reprepare' => ['statements.3.requires_reprepare', false],
    'network reader transition stable' => ['statements.3.schema_transitions.0.resolution_changed', false],
    'main post update has main schema' => ['statements.4.current_schemas.0', 'main'],
    'main post update expires from main cookie' => ['statements.4.requires_reprepare', true],
    'main post update write action' => ['statements.4.next_step_action', 'sqlite_schema_before_write_retry'],
    'delete options current main' => ['statements.5.schema_transitions.0.current_schema', 'main'],
    'delete options next temp' => ['statements.5.schema_transitions.0.next_schema', 'temp'],
    'delete options write result' => ['statements.5.sqlite_result', 'SQLITE_SCHEMA'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['attach wal temp statement lifecycle plan ' . $name] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
        $t->same($expected, $value($plan(), $path));
    };
}

$predicateCases = [
    'stable schema set keeps prepared statement reusable' => static function () use ($schemas): bool {
        $stable = $schemas;
        foreach ($stable as $name => $schema) {
            $stable[$name]['next_tables'] = $schema['tables'];
            $stable[$name]['next_indexes'] = $schema['indexes'];
            unset($stable[$name]['wal_schema_cookie'], $stable[$name]['wal_frames']);
        }
        $result = SQLiteAttachWalTempStatementLifecyclePlan::plan($stable, [
            ['name' => 'options-reader', 'sql' => 'SELECT option_value FROM wp_options'],
        ]);
        return $result['status'] === 'stable'
            && $result['statements']['0']['next_step_action'] === 'reuse_prepared_statement'
            && $result['expired_statements'] === [];
    },
    'uncommitted wal page one does not expire attached reader' => static function () use ($schemas): bool {
        $copy = $schemas;
        $copy['archive']['wal_frames'][0]['commit'] = false;
        $copy['archive']['next_tables'] = $copy['archive']['tables'];
        $copy['archive']['next_indexes'] = $copy['archive']['indexes'];
        $result = SQLiteAttachWalTempStatementLifecyclePlan::plan($copy, [
            ['name' => 'archive-reader', 'sql' => 'SELECT option_name FROM archive.wp_archive_options'],
        ]);
        return $result['status'] === 'stable'
            && $result['schema_cookies_next']['archive'] === 10
            && $result['stable_statements'] === ['archive-reader'];
    },
    'committed wal page one expires attached reader without object changes' => static function () use ($schemas): bool {
        $copy = $schemas;
        $copy['archive']['next_tables'] = $copy['archive']['tables'];
        $copy['archive']['next_indexes'] = $copy['archive']['indexes'];
        $result = SQLiteAttachWalTempStatementLifecyclePlan::plan($copy, [
            ['name' => 'archive-reader', 'sql' => 'SELECT option_name FROM archive.wp_archive_options'],
        ]);
        return $result['status'] === 'schema_changed'
            && $result['changed_schemas'] === ['main', 'archive']
            && $result['expired_statements'] === ['archive-reader'];
    },
    'quoted source schema accepted' => static function () use ($schemas, $statements): bool {
        return SQLiteAttachWalTempStatementLifecyclePlan::plan($schemas, [$statements[3]], '"MAIN"')['source'] === 'main';
    },
    'attached source schema accepted' => static function () use ($schemas): bool {
        return SQLiteAttachWalTempStatementLifecyclePlan::plan($schemas, [
            ['name' => 'network-reader', 'sql' => 'SELECT blog_id FROM network.wp_blogs'],
        ], 'network')['source'] === 'network';
    },
    'case insensitive table extraction resolves temp current' => static function () use ($schemas): bool {
        return SQLiteAttachWalTempStatementLifecyclePlan::plan($schemas, [
            ['name' => 'stage-reader', 'sql' => 'select * from WP_OPTIONS_STAGE'],
        ])['statements']['0']['current_schemas'] === ['temp'];
    },
    'join statement expires when either table changes' => static function () use ($schemas): bool {
        $result = SQLiteAttachWalTempStatementLifecyclePlan::plan($schemas, [
            ['name' => 'join', 'sql' => 'SELECT * FROM wp_options o JOIN network.wp_blogs b ON b.blog_id = o.option_id'],
        ]);
        return $result['statements']['0']['tables'] === ['wp_options', 'network.wp_blogs']
            && $result['statements']['0']['requires_reprepare'] === true
            && $result['statements']['0']['next_schemas'] === ['temp', 'network'];
    },
    'insert into next temp table expires write statement' => static function () use ($schemas): bool {
        $result = SQLiteAttachWalTempStatementLifecyclePlan::plan($schemas, [
            ['name' => 'insert-options', 'sql' => 'INSERT INTO wp_options(option_name) VALUES (?)'],
        ]);
        return $result['statements']['0']['current_schemas'] === ['main']
            && $result['statements']['0']['next_schemas'] === ['temp']
            && $result['statements']['0']['read_only'] === false;
    },
    'delete from qualified stable network remains reusable' => static function () use ($schemas): bool {
        $result = SQLiteAttachWalTempStatementLifecyclePlan::plan($schemas, [
            ['name' => 'delete-network', 'sql' => 'DELETE FROM network.wp_blogs WHERE blog_id = ?'],
        ]);
        return $result['statements']['0']['next_step_action'] === 'reuse_prepared_statement'
            && $result['statements']['0']['read_only'] === false;
    },
    'missing source schema rejected' => static function () use ($schemas): bool {
        try {
            SQLiteAttachWalTempStatementLifecyclePlan::plan($schemas, [
                ['sql' => 'SELECT * FROM wp_options'],
            ], 'missing');
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'empty statement list rejected' => static function () use ($schemas): bool {
        try {
            SQLiteAttachWalTempStatementLifecyclePlan::plan($schemas, []);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'empty sql rejected' => static function () use ($schemas): bool {
        try {
            SQLiteAttachWalTempStatementLifecyclePlan::plan($schemas, [['sql' => '']]);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'sql without bounded table rejected' => static function () use ($schemas): bool {
        try {
            SQLiteAttachWalTempStatementLifecyclePlan::plan($schemas, [['sql' => 'SELECT 1']]);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
];

foreach ($predicateCases as $name => $callback) {
    $tests['attach wal temp statement lifecycle plan ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->true($callback());
    };
}

return $tests;
