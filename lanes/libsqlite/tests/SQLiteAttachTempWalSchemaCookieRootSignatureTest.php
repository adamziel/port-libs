<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaCookieCurrentSourceNextPlan;

$tests = [];

$schemas = static fn (): array => [
    'main' => [
        'schema_cookie' => 44,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 44, 'commit' => true],
        ],
        'wal_schema_cookie' => 44,
        'tables' => ['wp_options', 'wp_posts'],
        'next_tables' => ['wp_options', 'wp_posts'],
        'schema_roots' => ['wp_options' => 2, 'wp_posts' => 5],
        'next_schema_roots' => ['wp_options' => 2, 'wp_posts' => 5],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 8,
        'temp_schema_cookie' => 8,
        'tables' => ['wp_options_stage'],
        'next_tables' => ['wp_options_stage', 'wp_options'],
        'schema_roots' => ['wp_options_stage' => 3],
        'next_schema_roots' => ['wp_options_stage' => 3, 'wp_options' => 9],
        'file' => '',
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 17,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 17, 'commit' => true],
        ],
        'tables' => ['wp_archive_options', 'wp_comments'],
        'next_tables' => ['wp_archive_options', 'wp_comments'],
        'schema_roots' => ['wp_archive_options' => 6, 'wp_comments' => 7],
        'next_schema_roots' => ['wp_archive_options' => 10, 'wp_comments' => 7],
        'file' => '/srv/wp/archive.sqlite',
    ],
    'network' => [
        'schema_cookie' => 21,
        'wal_schema_cookie' => 22,
        'tables' => ['wp_blogs'],
        'next_tables' => ['wp_blogs'],
        'schema_roots' => ['wp_blogs' => 4],
        'next_schema_roots' => ['wp_blogs' => 4],
        'file' => '/srv/wp/network.sqlite',
    ],
];

$statements = static fn (): array => [
    ['name' => 'active-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options WHERE option_name = ?', 'active' => true],
    ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_value FROM wp_options'],
    ['name' => 'stage-writer', 'sql' => 'INSERT INTO temp.wp_options_stage(option_name, option_value) VALUES (?, ?)'],
    ['name' => 'archive-reader', 'sql' => 'SELECT option_name FROM archive.wp_archive_options'],
    ['name' => 'archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments'],
    ['name' => 'network-reader', 'sql' => 'SELECT blog_id FROM network.wp_blogs'],
    ['name' => 'posts-update', 'sql' => 'UPDATE main.wp_posts SET post_title = ? WHERE ID = ?'],
];

$plan = static fn (?array $schemasArg = null, ?array $statementsArg = null): array => SQLiteAttachTempWalSchemaCookieCurrentSourceNextPlan::plan(
    $schemasArg ?? $schemas(),
    $statementsArg ?? $statements(),
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
    'operation marker' => ['operation', 'attach-temp-wal-schema-cookie-root-signature'],
    'status expired' => ['status', 'schema_cache_expired'],
    'search order' => ['search_order', ['temp', 'main', 'archive', 'network']],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-temp-wal-schema-cookie-root-signature'],
    'main current cookie' => ['schema_cookies_current.main', 44],
    'main next cookie' => ['schema_cookies_next.main', 44],
    'main current source wal' => ['schema_cookie_sources.main.current_source', 'wal_page1_frame'],
    'main next source wal header' => ['schema_cookie_sources.main.next_source', 'wal_commit_header'],
    'main same cookie' => ['schema_root_signatures.main.same_cookie', true],
    'main source changed' => ['schema_root_signatures.main.source_changed', true],
    'main root signature unchanged' => ['schema_root_signatures.main.root_signature_changed', false],
    'main source only move' => ['schema_root_signatures.main.source_only_cookie_move', true],
    'main schema unchanged' => ['schema_root_signatures.main.schema_changed', false],
    'main current root signature' => ['schema_root_signatures.main.current_root_signature', 'wp_options:2|wp_posts:5'],
    'main next root signature' => ['schema_root_signatures.main.next_root_signature', 'wp_options:2|wp_posts:5'],
    'source only schemas' => ['source_only_cookie_move_schemas', ['main']],
    'temp same cookie' => ['schema_root_signatures.temp.same_cookie', true],
    'temp root signature changed' => ['schema_root_signatures.temp.root_signature_changed', true],
    'temp source changed to rollback journal' => ['schema_root_signatures.temp.source_changed', true],
    'temp schema changed by roots' => ['schema_root_signatures.temp.schema_changed', true],
    'temp current signature' => ['schema_root_signatures.temp.current_root_signature', 'wp_options_stage:3'],
    'temp next signature' => ['schema_root_signatures.temp.next_root_signature', 'wp_options:9|wp_options_stage:3'],
    'archive same cookie' => ['schema_root_signatures.archive.same_cookie', true],
    'archive root signature changed' => ['schema_root_signatures.archive.root_signature_changed', true],
    'archive schema changed by roots' => ['schema_root_signatures.archive.schema_changed', true],
    'network cookie changed' => ['schema_root_signatures.network.same_cookie', false],
    'network roots unchanged' => ['schema_root_signatures.network.root_signature_changed', false],
    'changed root schemas' => ['changed_root_schemas', ['temp', 'archive']],
    'expired statements' => ['expired_statements', ['unqualified-options-reader', 'stage-writer', 'archive-reader', 'archive-comments-reader', 'network-reader']],
    'stable statements' => ['stable_statements', ['active-options-reader', 'posts-update']],
    'retryable reads' => ['retryable_read_statements', ['unqualified-options-reader', 'archive-reader', 'archive-comments-reader', 'network-reader']],
    'write blocks' => ['write_statements_blocked_before_retry', ['stage-writer']],
    'active current empty' => ['active_current_snapshot_statements', []],
    'active options reader stable' => ['statements.0.requires_reprepare', false],
    'active options current step ok' => ['statements.0.sqlite_result_on_current_step', 'SQLITE_OK'],
    'active options action reuse' => ['statements.0.next_step_action', 'reuse_prepared_statement'],
    'active options prepare root' => ['statements.0.schema_transitions.0.prepare_root_page', 2],
    'active options next root' => ['statements.0.schema_transitions.0.next_root_page', 2],
    'active options source only move' => ['statements.0.schema_transitions.0.source_only_cookie_move', true],
    'active options no root change' => ['statements.0.schema_transitions.0.root_signature_changed', false],
    'unqualified options expires on temp shadow' => ['statements.1.requires_reprepare', true],
    'unqualified options prepare schema main' => ['statements.1.schema_transitions.0.prepare_schema', 'main'],
    'unqualified options next schema temp' => ['statements.1.schema_transitions.0.next_schema', 'temp'],
    'unqualified options next root' => ['statements.1.schema_transitions.0.next_root_page', 9],
    'stage writer expires on temp root signature' => ['statements.2.requires_reprepare', true],
    'stage writer prepare root' => ['statements.2.schema_transitions.0.prepare_root_page', 3],
    'stage writer next root' => ['statements.2.schema_transitions.0.next_root_page', 3],
    'stage writer action' => ['statements.2.next_step_action', 'sqlite_schema_before_write_retry'],
    'archive reader root changes' => ['statements.3.schema_transitions.0.root_signature_changed', true],
    'archive reader prepare root' => ['statements.3.schema_transitions.0.prepare_root_page', 6],
    'archive reader next root' => ['statements.3.schema_transitions.0.next_root_page', 10],
    'archive reader action' => ['statements.3.next_step_action', 'sqlite_schema_then_reprepare'],
    'archive comments expires from schema signature' => ['statements.4.requires_reprepare', true],
    'archive comments stable table root' => ['statements.4.schema_transitions.0.prepare_root_page', 7],
    'network reader cookie change remains expired' => ['statements.5.requires_reprepare', true],
    'posts update stable source move' => ['statements.6.requires_reprepare', false],
    'posts update action reuse' => ['statements.6.next_step_action', 'reuse_prepared_statement'],
    'requires reprepare' => ['requires_reprepare', true],
];

foreach ($pathCases as $name => [$path, $expected]) {
    $tests['attach temp wal schema cookie root signature ' . $name] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
        $t->same($expected, $value($plan(), $path));
    };
}

$tests['attach temp wal schema cookie root signature source only movement remains stable'] = static function (TestRunner $t) use ($schemas): void {
    $schemas = $schemas();
    $schemas['temp']['next_tables'] = $schemas['temp']['tables'];
    $schemas['temp']['next_schema_roots'] = $schemas['temp']['schema_roots'];
    $schemas['archive']['next_schema_roots'] = $schemas['archive']['schema_roots'];
    unset($schemas['network']['wal_schema_cookie']);

    $result = SQLiteAttachTempWalSchemaCookieCurrentSourceNextPlan::plan($schemas, [
        ['name' => 'options', 'sql' => 'SELECT option_value FROM main.wp_options'],
        ['name' => 'posts', 'sql' => 'UPDATE main.wp_posts SET post_title = ? WHERE ID = ?'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same(['options', 'posts'], $result['stable_statements']);
};

$tests['attach temp wal schema cookie root signature detects same cookie root change'] = static function (TestRunner $t) use ($schemas): void {
    $schemas = $schemas();
    $schemas['archive']['next_schema_roots']['wp_comments'] = 19;

    $result = SQLiteAttachTempWalSchemaCookieCurrentSourceNextPlan::plan($schemas, [
        ['name' => 'comments', 'sql' => 'SELECT comment_ID FROM archive.wp_comments'],
    ]);

    $t->same(['comments'], $result['expired_statements']);
    $t->same(7, $result['statements'][0]['schema_transitions'][0]['prepare_root_page']);
    $t->same(19, $result['statements'][0]['schema_transitions'][0]['next_root_page']);
};

$tests['attach temp wal schema cookie root signature validates root map'] = static function (TestRunner $t) use ($schemas, $statements): void {
    $schemas = $schemas();
    $schemas['main']['schema_roots']['wp_options'] = -1;

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempWalSchemaCookieCurrentSourceNextPlan::plan($schemas, $statements()));
};

$tests['attach temp wal schema cookie root signature rejects non array root map'] = static function (TestRunner $t) use ($schemas, $statements): void {
    $schemas = $schemas();
    $schemas['main']['schema_roots'] = 'wp_options';

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempWalSchemaCookieCurrentSourceNextPlan::plan($schemas, $statements()));
};

return $tests;
