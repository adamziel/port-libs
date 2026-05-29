<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record232 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords232 = [
    $record232('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(blog_id INTEGER NOT NULL, post_id INTEGER NOT NULL, PRIMARY KEY(blog_id, post_id)) WITHOUT ROWID', 1),
    $record232('index', 'sqlite_autoindex_wp_posts_1', 'wp_posts', 3, null, 2),
    $record232('table', 'wp_termmeta_import', 'wp_termmeta_import', 4, "CREATE TABLE wp_termmeta_import(
        meta_id INTEGER PRIMARY KEY,
        blog_id INTEGER NOT NULL,
        post_id INTEGER NOT NULL,
        option_id INTEGER NOT NULL,
        parent_option INTEGER,
        FOREIGN KEY(blog_id, post_id) REFERENCES wp_posts(blog_id, post_id) ON DELETE CASCADE,
        FOREIGN KEY(parent_option) REFERENCES wp_termmeta_import(option_id) ON UPDATE SET NULL
    )", 3),
    $record232('index', 'wp_termmeta_import_post_reversed', 'wp_termmeta_import', 5, 'CREATE INDEX wp_termmeta_import_post_reversed ON wp_termmeta_import(post_id, blog_id)', 4),
    $record232('index', 'wp_termmeta_import_parent_option', 'wp_termmeta_import', 6, 'CREATE INDEX wp_termmeta_import_parent_option ON wp_termmeta_import(parent_option)', 5),
    $record232('index', 'wp_termmeta_import_option_id', 'wp_termmeta_import', 7, 'CREATE UNIQUE INDEX wp_termmeta_import_option_id ON wp_termmeta_import(option_id)', 6),
];

$nextRecords232 = [
    $currentRecords232[0],
    $currentRecords232[1],
    $currentRecords232[2],
    $record232('index', 'wp_termmeta_import_post_prefix', 'wp_termmeta_import', 8, 'CREATE INDEX wp_termmeta_import_post_prefix ON wp_termmeta_import(blog_id, post_id, meta_id)', 4),
    $currentRecords232[4],
    $currentRecords232[5],
];

$missingNextRecords232 = [
    $currentRecords232[0],
    $currentRecords232[1],
    $currentRecords232[2],
    $record232('index', 'wp_termmeta_import_post_id_only', 'wp_termmeta_import', 9, 'CREATE INDEX wp_termmeta_import_post_id_only ON wp_termmeta_import(post_id)', 4),
    $currentRecords232[5],
];

$page232 = static fn (
    int $offset = 0,
    int $limit = 140,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page232(
    $currentRecords232,
    $nextRecords ?? $nextRecords232,
    'PRAGMA main.index_xinfo(wp_termmeta_import_post_reversed)',
    'PRAGMA main.foreign_key_list(wp_termmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt232 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default232 = static fn (): array => $page232();
$blocked232 = static fn (): array => $page232(nextRecords: $missingNextRecords232);
$currentPrefix232 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionPrefixRows232($currentRecords232);
$nextPrefix232 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionPrefixRows232($nextRecords232, 'next');

$cases232 = [
    'status ok' => [$default232, 'status', 'ok'],
    'operation marker' => [$default232, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next232'],
    'source id length' => [static fn (): array => ['len' => strlen($page232()['source_id'])], 'len', 64],
    'offset default' => [$default232, 'offset', 0],
    'limit default' => [$default232, 'limit', 140],
    'dependency appended' => [$default232, 'dependencies.10', 'sqlite-pragma-foreign-key-child-leftmost-prefix-index'],
    'base exact arity retained' => [$default232, 'current.foreign_key_parent_key_exact_arity.rows', 3],
    'base collation retained' => [$default232, 'current.foreign_key_parent_key_collation.rows', 3],
    'prefix source current' => [$default232, 'current_source.foreign_key_child_action_prefix_source', 'pragma_foreign_key_list_actions_plus_pragma_index_xinfo_leftmost_child_prefix'],
    'prefix source next' => [$default232, 'next_source.foreign_key_child_action_prefix_source', 'pragma_foreign_key_list_actions_plus_pragma_index_xinfo_leftmost_child_prefix'],
    'current prefix rows' => [$default232, 'current.foreign_key_child_action_prefix.rows', 3],
    'current ok row' => [$default232, 'current.foreign_key_child_action_prefix.ok', 1],
    'current blocked rows' => [$default232, 'current.foreign_key_child_action_prefix.blocked', 2],
    'current misordered rows' => [$default232, 'current.foreign_key_child_action_prefix.misordered_child_action_index', 2],
    'current missing zero' => [$default232, 'current.foreign_key_child_action_prefix.missing_child_action_index', 0],
    'current cascade rows' => [$default232, 'current.foreign_key_child_action_prefix.cascade', 2],
    'current set null rows' => [$default232, 'current.foreign_key_child_action_prefix.set_null', 1],
    'current composite columns' => [$default232, 'current.foreign_key_child_action_prefix.composite_columns', 1],
    'current matched prefixes' => [$default232, 'current.foreign_key_child_action_prefix.matched_prefix_columns', 1],
    'next prefix rows' => [$default232, 'next_counts.foreign_key_child_action_prefix.rows', 3],
    'next ok rows' => [$default232, 'next_counts.foreign_key_child_action_prefix.ok', 3],
    'next blocked zero' => [$default232, 'next_counts.foreign_key_child_action_prefix.blocked', 0],
    'next misordered zero' => [$default232, 'next_counts.foreign_key_child_action_prefix.misordered_child_action_index', 0],
    'next matched prefixes' => [$default232, 'next_counts.foreign_key_child_action_prefix.matched_prefix_columns', 5],
    'delta rows unchanged' => [$default232, 'delta.foreign_key_child_action_prefix_rows', 0],
    'delta blockers negative' => [$default232, 'delta.foreign_key_child_action_prefix_blockers', -2],
    'delta repaired true' => [$default232, 'delta.foreign_key_child_action_prefix_repaired', true],
    'delta changed true' => [$default232, 'delta.foreign_key_child_action_prefix_changed', true],
    'total includes prefix rows' => [$default232, 'total', 47],
    'count complete' => [$default232, 'count', 47],
    'next complete null' => [$default232, 'next', null],
    'current summary misordered first' => [$default232, 'current_source.foreign_key_child_action_prefix.0', 'current:wp_termmeta_import#0.0:blog_id->wp_posts.blog_id:child=blog_id,post_id:leftmost=:misordered=wp_termmeta_import_post_reversed:index=post_id,blog_id:misordered_child_action_index'],
    'current summary self ok' => [$default232, 'current_source.foreign_key_child_action_prefix.2', 'current:wp_termmeta_import#1.0:parent_option->wp_termmeta_import.option_id:child=parent_option:leftmost=wp_termmeta_import_parent_option:misordered=:index=:ok'],
    'next summary repaired' => [$default232, 'next_source.foreign_key_child_action_prefix.0', 'next:wp_termmeta_import#0.0:blog_id->wp_posts.blog_id:child=blog_id,post_id:leftmost=wp_termmeta_import_post_prefix:misordered=:index=:ok'],
    'first appended row kind' => [$default232, 'rows.41.kind', 'foreign_key_child_action_prefix'],
    'first appended row status' => [$default232, 'rows.41.status', 'misordered_child_action_index'],
    'first misordered index' => [$default232, 'rows.41.misordered_child_index', 'wp_termmeta_import_post_reversed'],
    'first misordered first column' => [$default232, 'rows.41.misordered_index_columns.0', 'post_id'],
    'first child first column' => [$default232, 'rows.41.child_columns.0', 'blog_id'],
    'second composite seq' => [$default232, 'rows.42.seq', 1],
    'self row leftmost index' => [$default232, 'rows.43.leftmost_child_index', 'wp_termmeta_import_parent_option'],
    'next repaired leftmost index' => [$default232, 'rows.44.leftmost_child_index', 'wp_termmeta_import_post_prefix'],
    'next repaired second prefix count' => [$default232, 'rows.45.matched_prefix_columns', 2],
    'blocked next missing rows' => [$blocked232, 'next_counts.foreign_key_child_action_prefix.missing_child_action_index', 3],
    'blocked next ok zero' => [$blocked232, 'next_counts.foreign_key_child_action_prefix.ok', 0],
    'blocked repaired false' => [$blocked232, 'delta.foreign_key_child_action_prefix_repaired', false],
    'helper current first kind' => [$currentPrefix232, '0.kind', 'foreign_key_child_action_prefix'],
    'helper current first status' => [$currentPrefix232, '0.status', 'misordered_child_action_index'],
    'helper current first message' => [$currentPrefix232, '0.message', 'foreign key wp_termmeta_import action lookup has child columns in wp_termmeta_import_post_reversed but not as the leftmost prefix'],
    'helper current second seq' => [$currentPrefix232, '1.seq', 1],
    'helper current self ok' => [$currentPrefix232, '2.status', 'ok'],
    'helper next first phase' => [$nextPrefix232, '0.phase', 'next'],
    'helper next first status' => [$nextPrefix232, '0.status', 'ok'],
    'helper next first leftmost' => [$nextPrefix232, '0.leftmost_child_index', 'wp_termmeta_import_post_prefix'],
];

$tests = [];
foreach ($cases232 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey child action prefix current source next232 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt232): void {
        $t->same($expected, $valueAt232($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey child action prefix current source next232 paginates prefix rows'] = static function (TestRunner $t) use ($page232): void {
    $first = $page232(0, 41);
    $second = $page232(41, 3, $first['next']);
    $third = $page232(44, 3, $second['next']);

    $t->same(41, $first['count']);
    $t->same('foreign_key_child_action_prefix', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 41], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('misordered_child_action_index', $second['rows'][0]['status']);
    $t->same('ok', $second['rows'][2]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey child action prefix current source next232 reports missing child action index'] = static function (TestRunner $t) use ($record232): void {
    $records = [
        $record232('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record232('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER, FOREIGN KEY(parent_id) REFERENCES parent(id) ON DELETE CASCADE)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionPrefixRows232($records);
    $t->same(1, count($rows));
    $t->same('missing_child_action_index', $rows[0]['status']);
    $t->same(null, $rows[0]['misordered_child_index']);
};

$tests['pragma index xinfo foreignkey child action prefix current source next232 ignores partial misordered indexes'] = static function (TestRunner $t) use ($record232): void {
    $records = [
        $record232('table', 'parent', 'parent', 2, 'CREATE TABLE parent(a INTEGER, b INTEGER, PRIMARY KEY(a,b)) WITHOUT ROWID', 1),
        $record232('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record232('table', 'child', 'child', 4, 'CREATE TABLE child(a INTEGER, b INTEGER, active INTEGER, FOREIGN KEY(a,b) REFERENCES parent(a,b) ON UPDATE CASCADE)', 3),
        $record232('index', 'child_ba_partial', 'child', 5, 'CREATE INDEX child_ba_partial ON child(b,a) WHERE active = 1', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionPrefixRows232($records);
    $t->same(2, count($rows));
    $t->same('missing_child_action_index', $rows[0]['status']);
    $t->same(null, $rows[0]['misordered_child_index']);
};

$tests['pragma index xinfo foreignkey child action prefix current source next232 rejects stale cursor'] = static function (TestRunner $t) use ($page232, $missingNextRecords232): void {
    $first = $page232(0, 41);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page232(41, 3, $first['next'], $missingNextRecords232));
};

$tests['pragma index xinfo foreignkey child action prefix current source next232 rejects stale offset'] = static function (TestRunner $t) use ($page232): void {
    $first = $page232(0, 41);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page232(42, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey child action prefix current source next232 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionPrefixRows232([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey child action prefix current source next232 rejects invalid bounds'] = static function (TestRunner $t) use ($page232): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page232(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page232(0, 0));
};

return $tests;
