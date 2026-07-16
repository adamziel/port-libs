<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record233 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords233 = [
    $record233('table', 'wp_posts_stage', 'wp_posts_stage', 2, 'CREATE TABLE wp_posts_stage(post_id INTEGER PRIMARY KEY, blog_id INTEGER NOT NULL, post_name TEXT NOT NULL, UNIQUE(blog_id, post_name))', 1),
    $record233('index', 'sqlite_autoindex_wp_posts_stage_1', 'wp_posts_stage', 3, null, 2),
    $record233('table', 'wp_terms_stage', 'wp_terms_stage', 4, 'CREATE TABLE wp_terms_stage(term_id INTEGER PRIMARY KEY, slug TEXT NOT NULL)', 3),
    $record233('table', 'wp_postmeta_import', 'wp_postmeta_import', 5, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        blog_id INTEGER NOT NULL,
        post_name TEXT NOT NULL,
        term_id INTEGER NOT NULL,
        meta_key TEXT NOT NULL,
        FOREIGN KEY(post_id) REFERENCES wp_posts_stage(post_id),
        FOREIGN KEY(blog_id, post_name) REFERENCES wp_posts_stage(blog_id, post_name),
        FOREIGN KEY(term_id) REFERENCES wp_terms_stage(term_id)
    )", 4),
    $record233('index', 'wp_postmeta_expr_post', 'wp_postmeta_import', 6, 'CREATE INDEX wp_postmeta_expr_post ON wp_postmeta_import(lower(meta_key), post_id)', 5),
    $record233('index', 'wp_postmeta_expr_blog_name', 'wp_postmeta_import', 7, 'CREATE INDEX wp_postmeta_expr_blog_name ON wp_postmeta_import(coalesce(meta_key, ""), blog_id, post_name)', 6),
    $record233('index', 'wp_postmeta_term_plain_suffix', 'wp_postmeta_import', 8, 'CREATE INDEX wp_postmeta_term_plain_suffix ON wp_postmeta_import(meta_key, term_id)', 7),
];

$nextRecords233 = [
    $currentRecords233[0],
    $currentRecords233[1],
    $currentRecords233[2],
    $currentRecords233[3],
    $record233('index', 'wp_postmeta_post_fk', 'wp_postmeta_import', 9, 'CREATE INDEX wp_postmeta_post_fk ON wp_postmeta_import(post_id, lower(meta_key))', 8),
    $record233('index', 'wp_postmeta_blog_name_fk', 'wp_postmeta_import', 10, 'CREATE INDEX wp_postmeta_blog_name_fk ON wp_postmeta_import(blog_id, post_name, coalesce(meta_key, ""))', 9),
    $currentRecords233[6],
];

$blockedNextRecords233 = [
    $currentRecords233[0],
    $currentRecords233[1],
    $currentRecords233[2],
    $currentRecords233[3],
    $record233('index', 'wp_postmeta_expr_post_next', 'wp_postmeta_import', 11, 'CREATE INDEX wp_postmeta_expr_post_next ON wp_postmeta_import(substr(meta_key, 1, 1), post_id)', 8),
    $record233('index', 'wp_postmeta_expr_blog_name_next', 'wp_postmeta_import', 12, 'CREATE INDEX wp_postmeta_expr_blog_name_next ON wp_postmeta_import(json_extract(meta_key, "$.lang"), blog_id, post_name)', 9),
    $currentRecords233[6],
];

$partialOnlyRecords233 = [
    $currentRecords233[0],
    $currentRecords233[1],
    $currentRecords233[3],
    $record233('index', 'wp_postmeta_expr_partial', 'wp_postmeta_import', 13, "CREATE INDEX wp_postmeta_expr_partial ON wp_postmeta_import(lower(meta_key), post_id) WHERE meta_key <> ''", 8),
];

$page233 = static fn (
    int $offset = 0,
    int $limit = 140,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page233(
    $currentRecords233,
    $nextRecords ?? $nextRecords233,
    'PRAGMA main.index_xinfo(wp_postmeta_expr_post)',
    'PRAGMA main.foreign_key_list(wp_postmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt233 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default233 = static fn (): array => $page233();
$blocked233 = static fn (): array => $page233(nextRecords: $blockedNextRecords233);
$currentExpression233 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childExpressionPrefixRows233($currentRecords233);
$nextExpression233 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childExpressionPrefixRows233($nextRecords233, 'next');
$blockedExpression233 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childExpressionPrefixRows233($blockedNextRecords233, 'next');

$cases233 = [
    'status ok' => [$default233, 'status', 'ok'],
    'operation marker' => [$default233, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next233'],
    'source id length' => [static fn (): array => ['len' => strlen($page233()['source_id'])], 'len', 64],
    'offset default' => [$default233, 'offset', 0],
    'limit default' => [$default233, 'limit', 140],
    'dependency appended' => [$default233, 'dependencies.11', 'sqlite-pragma-foreign-key-child-index-expression-prefix'],
    'base pseudo retained' => [$default233, 'current.foreign_key_parent_pseudo_rowid.rows', 0],
    'expression source current' => [$default233, 'current_source.foreign_key_child_expression_prefix_source', 'pragma_foreign_key_list_child_columns_plus_pragma_index_xinfo_expression_prefix_terms'],
    'expression source next' => [$default233, 'next_source.foreign_key_child_expression_prefix_source', 'pragma_foreign_key_list_child_columns_plus_pragma_index_xinfo_expression_prefix_terms'],
    'current expression rows' => [$default233, 'current.foreign_key_child_expression_prefix.rows', 3],
    'current expression blockers' => [$default233, 'current.foreign_key_child_expression_prefix.expression_prefix_child_index', 3],
    'current expression foreign keys' => [$default233, 'current.foreign_key_child_expression_prefix.foreign_keys', 2],
    'current expression terms' => [$default233, 'current.foreign_key_child_expression_prefix.expression_terms', 3],
    'current max expression terms' => [$default233, 'current.foreign_key_child_expression_prefix.max_expression_terms', 1],
    'next expression rows repaired' => [$default233, 'next_counts.foreign_key_child_expression_prefix.rows', 0],
    'next expression blockers repaired' => [$default233, 'next_counts.foreign_key_child_expression_prefix.expression_prefix_child_index', 0],
    'delta expression rows' => [$default233, 'delta.foreign_key_child_expression_prefix_rows', -3],
    'delta expression blockers' => [$default233, 'delta.foreign_key_child_expression_prefix_blockers', -3],
    'delta repaired true' => [$default233, 'delta.foreign_key_child_expression_prefix_repaired', true],
    'delta changed true' => [$default233, 'delta.foreign_key_child_expression_prefix_changed', true],
    'current summary post id' => [$default233, 'current_source.foreign_key_child_expression_prefix.0', 'current:wp_postmeta_import#0.0:post_id->wp_posts_stage.post_id:child=post_id:wp_postmeta_expr_post:terms=lower(meta_key),post_id:expr=lower(meta_key):expected=0:actual=1:expression_prefix_child_index'],
    'current summary composite first' => [$default233, 'current_source.foreign_key_child_expression_prefix.1', 'current:wp_postmeta_import#1.0:blog_id->wp_posts_stage.blog_id:child=blog_id,post_name:wp_postmeta_expr_blog_name:terms=coalesce(meta_key, ""),blog_id,post_name:expr=coalesce(meta_key, ""):expected=0:actual=1:expression_prefix_child_index'],
    'current summary composite second' => [$default233, 'current_source.foreign_key_child_expression_prefix.2', 'current:wp_postmeta_import#1.1:post_name->wp_posts_stage.post_name:child=blog_id,post_name:wp_postmeta_expr_blog_name:terms=coalesce(meta_key, ""),blog_id,post_name:expr=coalesce(meta_key, ""):expected=1:actual=2:expression_prefix_child_index'],
    'next summary empty' => [$default233, 'next_source.foreign_key_child_expression_prefix', []],
    'blocked next rows' => [$blocked233, 'next_counts.foreign_key_child_expression_prefix.rows', 3],
    'blocked next blockers' => [$blocked233, 'next_counts.foreign_key_child_expression_prefix.expression_prefix_child_index', 3],
    'blocked delta blockers zero' => [$blocked233, 'delta.foreign_key_child_expression_prefix_blockers', 0],
    'blocked repaired false' => [$blocked233, 'delta.foreign_key_child_expression_prefix_repaired', false],
    'helper current first kind' => [$currentExpression233, '0.kind', 'foreign_key_child_expression_prefix'],
    'helper current first index' => [$currentExpression233, '0.expression_prefix_index', 'wp_postmeta_expr_post'],
    'helper current first expression' => [$currentExpression233, '0.expression_terms.0', 'lower(meta_key)'],
    'helper current first term count' => [$currentExpression233, '0.expression_term_count', 1],
    'helper current first actual position' => [$currentExpression233, '0.actual_position', 1],
    'helper current composite expression' => [$currentExpression233, '1.expression_terms.0', 'coalesce(meta_key, "")'],
    'helper current composite second actual' => [$currentExpression233, '2.actual_position', 2],
    'helper current composite collation default' => [$currentExpression233, '1.index_key_collations.0', 'BINARY'],
    'helper next repaired empty' => [static fn (): array => ['count' => count($nextExpression233())], 'count', 0],
    'helper blocked row count' => [static fn (): array => ['count' => count($blockedExpression233())], 'count', 3],
    'helper blocked first expression' => [$blockedExpression233, '0.expression_terms.0', 'substr(meta_key, 1, 1)'],
    'helper blocked composite expression' => [$blockedExpression233, '1.expression_terms.0', 'json_extract(meta_key, "$.lang")'],
];

$tests = [];
foreach ($cases233 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey child expression prefix current source next233 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt233): void {
        $t->same($expected, $valueAt233($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey child expression prefix current source next233 paginates expression rows'] = static function (TestRunner $t) use ($page233): void {
    $full = $page233();
    $expressionOffset = $full['total'] - 3;
    $first = $page233(0, $expressionOffset);
    $second = $page233($expressionOffset, 2, $first['next']);
    $third = $page233($expressionOffset + 2, 1, $second['next']);

    $t->same($expressionOffset, $first['count']);
    $t->same('foreign_key_child_expression_prefix', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $expressionOffset], $first['next']);
    $t->same('post_id', $second['rows'][0]['from']);
    $t->same('lower(meta_key)', $second['rows'][0]['expression_terms'][0]);
    $t->same('blog_id', $second['rows'][1]['from']);
    $t->same('coalesce(meta_key, "")', $second['rows'][1]['expression_terms'][0]);
    $t->same('post_name', $third['rows'][0]['from']);
    $t->same(2, $third['rows'][0]['actual_position']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey child expression prefix current source next233 ignores partial expression indexes'] = static function (TestRunner $t) use ($partialOnlyRecords233): void {
    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childExpressionPrefixRows233($partialOnlyRecords233));
};

$tests['pragma index xinfo foreignkey child expression prefix current source next233 ignores plain leading columns'] = static function (TestRunner $t) use ($currentRecords233): void {
    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childExpressionPrefixRows233($currentRecords233);

    $t->same(['wp_postmeta_expr_post', 'wp_postmeta_expr_blog_name', 'wp_postmeta_expr_blog_name'], array_column($rows, 'expression_prefix_index'));
};

$tests['pragma index xinfo foreignkey child expression prefix current source next233 reports two expression terms'] = static function (TestRunner $t) use ($record233): void {
    $records = [
        $record233('table', 'parent', 'parent', 2, 'CREATE TABLE parent(a TEXT, b TEXT, UNIQUE(a, b))', 1),
        $record233('index', 'sqlite_autoindex_parent_1', 'parent', 3, null, 2),
        $record233('table', 'child', 'child', 4, 'CREATE TABLE child(meta_key TEXT, status TEXT, a TEXT, b TEXT, FOREIGN KEY(a, b) REFERENCES parent(a, b))', 3),
        $record233('index', 'child_expr_ab', 'child', 5, 'CREATE INDEX child_expr_ab ON child(lower(meta_key), coalesce(status, ""), a, b)', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childExpressionPrefixRows233($records);
    $t->same(2, count($rows));
    $t->same(['lower(meta_key)', 'coalesce(status, "")'], $rows[0]['expression_terms']);
    $t->same(2, $rows[0]['expression_term_count']);
    $t->same([2, 3], array_column($rows, 'actual_position'));
};

$tests['pragma index xinfo foreignkey child expression prefix current source next233 rejects stale cursor'] = static function (TestRunner $t) use ($page233, $blockedNextRecords233): void {
    $first = $page233(0, 31);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page233(31, 1, $first['next'], $blockedNextRecords233));
};

$tests['pragma index xinfo foreignkey child expression prefix current source next233 rejects stale offset'] = static function (TestRunner $t) use ($page233): void {
    $first = $page233(0, 31);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page233(32, 1, $first['next']));
};

$tests['pragma index xinfo foreignkey child expression prefix current source next233 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childExpressionPrefixRows233([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey child expression prefix current source next233 rejects invalid bounds'] = static function (TestRunner $t) use ($page233): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page233(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page233(0, 0));
};

return $tests;
