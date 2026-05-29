<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record225 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords225 = [
    $record225('table', 'wp_posts_parent', 'wp_posts_parent', 2, 'CREATE TABLE wp_posts_parent(post_id INTEGER PRIMARY KEY, author_id INTEGER NOT NULL, slug TEXT NOT NULL, UNIQUE(author_id, slug))', 1),
    $record225('index', 'sqlite_autoindex_wp_posts_parent_1', 'wp_posts_parent', 3, null, 2),
    $record225('table', 'wp_import_comments', 'wp_import_comments', 4, "CREATE TABLE wp_import_comments(
        comment_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        author_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        parent_comment_id INTEGER DEFAULT 0,
        FOREIGN KEY(post_id) REFERENCES wp_posts_parent(post_id) ON UPDATE CASCADE ON DELETE SET NULL,
        FOREIGN KEY(author_id, slug) REFERENCES wp_posts_parent(author_id, slug) ON UPDATE SET DEFAULT ON DELETE RESTRICT,
        FOREIGN KEY(parent_comment_id) REFERENCES wp_import_comments(comment_id)
    )", 3),
];

$nextRecords225 = [
    $currentRecords225[0],
    $currentRecords225[1],
    $record225('table', 'wp_import_comments', 'wp_import_comments', 5, "CREATE TABLE wp_import_comments(
        comment_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        author_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        parent_comment_id INTEGER DEFAULT 0,
        FOREIGN KEY(post_id) REFERENCES wp_posts_parent(post_id) ON UPDATE NO ACTION ON DELETE NO ACTION,
        FOREIGN KEY(author_id, slug) REFERENCES wp_posts_parent(author_id, slug) ON UPDATE CASCADE ON DELETE CASCADE,
        FOREIGN KEY(parent_comment_id) REFERENCES wp_import_comments(comment_id) ON DELETE SET DEFAULT
    )", 3),
];

$page225 = static fn (
    int $offset = 0,
    int $limit = 100,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page225(
    $currentRecords225,
    $nextRecords ?? $nextRecords225,
    'PRAGMA main.index_xinfo(sqlite_autoindex_wp_posts_parent_1)',
    'PRAGMA main.foreign_key_list(wp_import_comments)',
    $offset,
    $limit,
    $resume,
);

$valueAt225 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default225 = static fn (): array => $page225();
$currentActions225 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionClauseRows225($currentRecords225);
$nextActions225 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionClauseRows225($nextRecords225, 'next');

$cases225 = [
    'status ok' => [$default225, 'status', 'ok'],
    'operation marker' => [$default225, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next225'],
    'source id length' => [static fn (): array => ['len' => strlen($page225()['source_id'])], 'len', 64],
    'offset default' => [$default225, 'offset', 0],
    'limit default' => [$default225, 'limit', 100],
    'dependency appended' => [$default225, 'dependencies.9', 'sqlite-pragma-foreign-key-action-clauses'],
    'base match retained' => [$default225, 'current.foreign_key_match_clause.rows', 4],
    'action source current' => [$default225, 'current_source.foreign_key_action_clause_source', 'pragma_foreign_key_list_on_update_on_delete_columns'],
    'action source next' => [$default225, 'next_source.foreign_key_action_clause_source', 'pragma_foreign_key_list_on_update_on_delete_columns'],
    'current rows' => [$default225, 'current.foreign_key_action_clause.rows', 4],
    'current no action rows' => [$default225, 'current.foreign_key_action_clause.no_action', 1],
    'current action rows' => [$default225, 'current.foreign_key_action_clause.action_clause', 3],
    'current cascade actions' => [$default225, 'current.foreign_key_action_clause.cascade_actions', 1],
    'current set null actions' => [$default225, 'current.foreign_key_action_clause.set_null_actions', 1],
    'current set default actions' => [$default225, 'current.foreign_key_action_clause.set_default_actions', 2],
    'current restrict actions' => [$default225, 'current.foreign_key_action_clause.restrict_actions', 2],
    'current composite columns' => [$default225, 'current.foreign_key_action_clause.composite_columns', 1],
    'next rows' => [$default225, 'next_counts.foreign_key_action_clause.rows', 4],
    'next no action rows' => [$default225, 'next_counts.foreign_key_action_clause.no_action', 1],
    'next cascade actions' => [$default225, 'next_counts.foreign_key_action_clause.cascade_actions', 2],
    'next set default actions' => [$default225, 'next_counts.foreign_key_action_clause.set_default_actions', 1],
    'delta rows unchanged' => [$default225, 'delta.foreign_key_action_clause_rows', 0],
    'delta cascades' => [$default225, 'delta.foreign_key_action_clause_cascades', 1],
    'delta defaults' => [$default225, 'delta.foreign_key_action_clause_defaults', -1],
    'delta restricts' => [$default225, 'delta.foreign_key_action_clause_restricts', -2],
    'delta changed' => [$default225, 'delta.foreign_key_action_clause_changed', true],
    'current summary cascade' => [$default225, 'current_source.foreign_key_action_clause.0', 'current:wp_import_comments#0.0:post_id->wp_posts_parent.post_id:update=CASCADE:delete=SET NULL:action_clause'],
    'next summary no action' => [$default225, 'next_source.foreign_key_action_clause.0', 'next:wp_import_comments#0.0:post_id->wp_posts_parent.post_id:update=NO ACTION:delete=NO ACTION:no_action'],
    'helper current first kind' => [$currentActions225, '0.kind', 'foreign_key_action_clause'],
    'helper current first status' => [$currentActions225, '0.status', 'action_clause'],
    'helper current cascade update' => [$currentActions225, '0.on_update', 'CASCADE'],
    'helper current set null delete' => [$currentActions225, '0.on_delete', 'SET NULL'],
    'helper current composite default' => [$currentActions225, '1.uses_set_default', true],
    'helper current composite restrict' => [$currentActions225, '2.uses_restrict', true],
    'helper next phase' => [$nextActions225, '0.phase', 'next'],
    'helper next cascade delete' => [$nextActions225, '2.on_delete', 'CASCADE'],
];

$tests = [];
foreach ($cases225 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey action clause current source next225 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt225): void {
        $t->same($expected, $valueAt225($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey action clause current source next225 paginates action rows'] = static function (TestRunner $t) use ($page225): void {
    $full = $page225();
    $actionOffset = $full['total'] - 8;
    $first = $page225(0, $actionOffset);
    $second = $page225($actionOffset, 4, $first['next']);
    $third = $page225($actionOffset + 4, 4, $second['next']);

    $t->same($actionOffset, $first['count']);
    $t->same('foreign_key_action_clause', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $actionOffset], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $secondUpdates = array_values(array_unique(array_column($second['rows'], 'on_update')));
    sort($secondUpdates);
    $t->same(['CASCADE', 'NO ACTION', 'SET DEFAULT'], $secondUpdates);
    $t->same('next', $third['rows'][0]['phase']);
    $thirdDeletes = array_values(array_unique(array_column($third['rows'], 'on_delete')));
    sort($thirdDeletes);
    $t->same(['CASCADE', 'NO ACTION', 'SET DEFAULT'], $thirdDeletes);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey action clause current source next225 normalizes lower-case actions'] = static function (TestRunner $t) use ($record225): void {
    $records = [
        $record225('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record225('table', 'child', 'child', 3, 'CREATE TABLE child(id INTEGER REFERENCES parent(id) ON UPDATE cascade ON DELETE set default)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionClauseRows225($records);
    $t->same(1, count($rows));
    $t->same('CASCADE', $rows[0]['on_update']);
    $t->same('SET DEFAULT', $rows[0]['on_delete']);
};

$tests['pragma index xinfo foreignkey action clause current source next225 rejects stale cursor'] = static function (TestRunner $t) use ($page225, $currentRecords225): void {
    $first = $page225(0, 25);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page225(25, 1, $first['next'], $currentRecords225));
};

$tests['pragma index xinfo foreignkey action clause current source next225 rejects stale offset'] = static function (TestRunner $t) use ($page225): void {
    $first = $page225(0, 25);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page225(26, 1, $first['next']));
};

$tests['pragma index xinfo foreignkey action clause current source next225 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionClauseRows225([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey action clause current source next225 rejects invalid bounds'] = static function (TestRunner $t) use ($page225): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page225(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page225(0, 0));
};

return $tests;
