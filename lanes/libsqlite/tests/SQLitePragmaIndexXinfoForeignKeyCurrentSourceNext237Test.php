<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record237 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords237 = [
    $record237('table', 'wp_parent_options', 'wp_parent_options', 2, 'CREATE TABLE wp_parent_options(blog_id INTEGER NOT NULL, option_name TEXT NOT NULL, autoload TEXT NOT NULL, locale TEXT NOT NULL)', 1),
    $record237('index', 'wp_parent_options_blog_name_autoload_unique', 'wp_parent_options', 3, 'CREATE UNIQUE INDEX wp_parent_options_blog_name_autoload_unique ON wp_parent_options(blog_id, option_name, autoload)', 2),
    $record237('index', 'wp_parent_options_locale_name_autoload_unique', 'wp_parent_options', 4, 'CREATE UNIQUE INDEX wp_parent_options_locale_name_autoload_unique ON wp_parent_options(locale, option_name, autoload)', 3),
    $record237('index', 'wp_parent_options_name_autoload_unique', 'wp_parent_options', 5, 'CREATE UNIQUE INDEX wp_parent_options_name_autoload_unique ON wp_parent_options(option_name, autoload)', 4),
    $record237('table', 'wp_child_option_refs', 'wp_child_option_refs', 6, "CREATE TABLE wp_child_option_refs(
        ref_id INTEGER PRIMARY KEY,
        blog_id INTEGER NOT NULL,
        option_name TEXT NOT NULL,
        locale TEXT NOT NULL,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_parent_options(blog_id, option_name),
        FOREIGN KEY(locale, option_name) REFERENCES wp_parent_options(locale, option_name),
        FOREIGN KEY(option_name) REFERENCES wp_parent_options(option_name)
    )", 5),
];

$nextRecords237 = [
    $currentRecords237[0],
    $record237('index', 'wp_parent_options_blog_name_unique', 'wp_parent_options', 7, 'CREATE UNIQUE INDEX wp_parent_options_blog_name_unique ON wp_parent_options(blog_id, option_name)', 6),
    $record237('index', 'wp_parent_options_locale_name_unique', 'wp_parent_options', 8, 'CREATE UNIQUE INDEX wp_parent_options_locale_name_unique ON wp_parent_options(locale, option_name)', 7),
    $record237('index', 'wp_parent_options_name_unique', 'wp_parent_options', 9, 'CREATE UNIQUE INDEX wp_parent_options_name_unique ON wp_parent_options(option_name)', 8),
    $currentRecords237[4],
];

$missingNextRecords237 = [
    $currentRecords237[0],
    $record237('index', 'wp_parent_options_blog_name_partial_unique', 'wp_parent_options', 10, "CREATE UNIQUE INDEX wp_parent_options_blog_name_partial_unique ON wp_parent_options(blog_id, option_name) WHERE autoload = 'yes'", 6),
    $currentRecords237[4],
];

$page237 = static fn (
    int $offset = 0,
    int $limit = 180,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page237(
    $currentRecords237,
    $nextRecords ?? $nextRecords237,
    'PRAGMA main.index_xinfo(wp_parent_options_blog_name_autoload_unique)',
    'PRAGMA main.foreign_key_list(wp_child_option_refs)',
    $offset,
    $limit,
    $resume,
);

$valueAt237 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default237 = static fn (): array => $page237();
$blocked237 = static fn (): array => $page237(nextRecords: $missingNextRecords237);
$currentPrefix237 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentPrefixUniqueRows237($currentRecords237);
$nextPrefix237 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentPrefixUniqueRows237($nextRecords237, 'next');

$cases237 = [
    'status ok' => [$default237, 'status', 'ok'],
    'operation marker' => [$default237, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next237'],
    'source id length' => [static fn (): array => ['len' => strlen($page237()['source_id'])], 'len', 64],
    'offset default' => [$default237, 'offset', 0],
    'limit default' => [$default237, 'limit', 180],
    'base expression rows retained' => [$default237, 'current.foreign_key_expression_parent_key.rows', 5],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-foreign-key-exact-parent-unique-arity', $page237()['dependencies'], true)], 'has', true],
    'prefix source current' => [$default237, 'current_source.foreign_key_parent_prefix_unique_source', 'pragma_foreign_key_list_parent_columns_plus_exact_pragma_index_xinfo_key_arity'],
    'prefix source next' => [$default237, 'next_source.foreign_key_parent_prefix_unique_source', 'pragma_foreign_key_list_parent_columns_plus_exact_pragma_index_xinfo_key_arity'],
    'current prefix rows' => [$default237, 'current.foreign_key_parent_prefix_unique.rows', 5],
    'current ok zero' => [$default237, 'current.foreign_key_parent_prefix_unique.ok', 0],
    'current blocked rows' => [$default237, 'current.foreign_key_parent_prefix_unique.blocked', 5],
    'current prefix blockers' => [$default237, 'current.foreign_key_parent_prefix_unique.prefix_only_parent_unique_index', 5],
    'current missing zero' => [$default237, 'current.foreign_key_parent_prefix_unique.missing_parent_unique_index', 0],
    'current extra columns' => [$default237, 'current.foreign_key_parent_prefix_unique.extra_columns', 5],
    'current composite columns' => [$default237, 'current.foreign_key_parent_prefix_unique.composite_columns', 2],
    'next rows' => [$default237, 'next_counts.foreign_key_parent_prefix_unique.rows', 5],
    'next ok rows' => [$default237, 'next_counts.foreign_key_parent_prefix_unique.ok', 5],
    'next blocked zero' => [$default237, 'next_counts.foreign_key_parent_prefix_unique.blocked', 0],
    'next prefix blockers zero' => [$default237, 'next_counts.foreign_key_parent_prefix_unique.prefix_only_parent_unique_index', 0],
    'delta rows unchanged' => [$default237, 'delta.foreign_key_parent_prefix_unique_rows', 0],
    'delta blockers negative' => [$default237, 'delta.foreign_key_parent_prefix_unique_blockers', -5],
    'delta repaired true' => [$default237, 'delta.foreign_key_parent_prefix_unique_repaired', true],
    'delta changed true' => [$default237, 'delta.foreign_key_parent_prefix_unique_changed', true],
    'total includes prefix rows' => [$default237, 'total', 76],
    'count complete' => [$default237, 'count', 76],
    'next complete null' => [$default237, 'next', null],
    'current summary first' => [$default237, 'current_source.foreign_key_parent_prefix_unique.0', 'current:wp_child_option_refs#0.0:blog_id->wp_parent_options.blog_id:exact=:prefix=wp_parent_options_blog_name_autoload_unique:extra=autoload:prefix_only_parent_unique_index'],
    'current summary composite second' => [$default237, 'current_source.foreign_key_parent_prefix_unique.1', 'current:wp_child_option_refs#0.1:option_name->wp_parent_options.option_name:exact=:prefix=wp_parent_options_blog_name_autoload_unique:extra=autoload:prefix_only_parent_unique_index'],
    'next summary exact' => [$default237, 'next_source.foreign_key_parent_prefix_unique.0', 'next:wp_child_option_refs#0.0:blog_id->wp_parent_options.blog_id:exact=wp_parent_options_blog_name_unique:prefix=:extra=:ok'],
    'first appended row kind' => [$default237, 'rows.66.kind', 'foreign_key_parent_prefix_unique'],
    'first appended prefix index' => [$default237, 'rows.66.prefix_unique_index', 'wp_parent_options_blog_name_autoload_unique'],
    'first appended exact null' => [$default237, 'rows.66.parent_unique_index', null],
    'first appended extra column' => [$default237, 'rows.66.prefix_extra_columns.0', 'autoload'],
    'first appended parent arity' => [$default237, 'rows.66.parent_key_arity', 2],
    'first appended index arity' => [$default237, 'rows.66.index_key_arity', 3],
    'single parent extra column' => [$default237, 'rows.70.prefix_extra_columns.0', 'autoload'],
    'next repaired exact index' => [$default237, 'rows.71.parent_unique_index', 'wp_parent_options_blog_name_unique'],
    'next repaired prefix null' => [$default237, 'rows.71.prefix_unique_index', null],
    'next repaired index arity' => [$default237, 'rows.71.index_key_arity', 2],
    'blocked next missing rows' => [$blocked237, 'next_counts.foreign_key_parent_prefix_unique.missing_parent_unique_index', 5],
    'blocked next ok zero' => [$blocked237, 'next_counts.foreign_key_parent_prefix_unique.ok', 0],
    'blocked repaired false' => [$blocked237, 'delta.foreign_key_parent_prefix_unique_repaired', false],
    'helper current first kind' => [$currentPrefix237, '0.kind', 'foreign_key_parent_prefix_unique'],
    'helper current first status' => [$currentPrefix237, '0.status', 'prefix_only_parent_unique_index'],
    'helper current first message' => [$currentPrefix237, '0.message', 'foreign key wp_child_option_refs->wp_parent_options cannot use UNIQUE index wp_parent_options_blog_name_autoload_unique because it has extra key columns'],
    'helper current first extra' => [$currentPrefix237, '0.prefix_extra_columns.0', 'autoload'],
    'helper current composite second status' => [$currentPrefix237, '1.status', 'prefix_only_parent_unique_index'],
    'helper current single arity' => [$currentPrefix237, '4.parent_key_arity', 1],
    'helper next first phase' => [$nextPrefix237, '0.phase', 'next'],
    'helper next first status' => [$nextPrefix237, '0.status', 'ok'],
    'helper next first exact index' => [$nextPrefix237, '0.parent_unique_index', 'wp_parent_options_blog_name_unique'],
];

$tests = [];
foreach ($cases237 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey parent prefix unique current source next237 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt237): void {
        $t->same($expected, $valueAt237($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey parent prefix unique current source next237 paginates appended rows'] = static function (TestRunner $t) use ($page237): void {
    $first = $page237(0, 66);
    $second = $page237(66, 5, $first['next']);
    $third = $page237(71, 5, $second['next']);

    $t->same(66, $first['count']);
    $t->same('foreign_key_parent_prefix_unique', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 66], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('prefix_only_parent_unique_index', $second['rows'][0]['status']);
    $t->same('prefix_only_parent_unique_index', $second['rows'][4]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('ok', $third['rows'][4]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey parent prefix unique current source next237 reports pure missing parent key'] = static function (TestRunner $t) use ($record237): void {
    $records = [
        $record237('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT)', 1),
        $record237('index', 'parent_other_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_other_unique ON parent(other_column)', 2),
        $record237('table', 'child', 'child', 4, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentPrefixUniqueRows237($records);
    $t->same(1, count($rows));
    $t->same('missing_parent_unique_index', $rows[0]['status']);
    $t->same(null, $rows[0]['prefix_unique_index']);
};

$tests['pragma index xinfo foreignkey parent prefix unique current source next237 ignores partial exact indexes'] = static function (TestRunner $t) use ($record237): void {
    $records = [
        $record237('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT, active INTEGER)', 1),
        $record237('index', 'parent_code_partial', 'parent', 3, 'CREATE UNIQUE INDEX parent_code_partial ON parent(code) WHERE active = 1', 2),
        $record237('table', 'child', 'child', 4, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 3),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentPrefixUniqueRows237($records);
    $t->same(1, count($rows));
    $t->same('missing_parent_unique_index', $rows[0]['status']);
    $t->same(null, $rows[0]['prefix_unique_index']);
};

$tests['pragma index xinfo foreignkey parent prefix unique current source next237 prefers exact key over longer prefix candidate'] = static function (TestRunner $t) use ($record237): void {
    $records = [
        $record237('table', 'parent', 'parent', 2, 'CREATE TABLE parent(code TEXT, locale TEXT)', 1),
        $record237('index', 'parent_code_locale_unique', 'parent', 3, 'CREATE UNIQUE INDEX parent_code_locale_unique ON parent(code, locale)', 2),
        $record237('index', 'parent_code_unique', 'parent', 4, 'CREATE UNIQUE INDEX parent_code_unique ON parent(code)', 3),
        $record237('table', 'child', 'child', 5, 'CREATE TABLE child(code TEXT, FOREIGN KEY(code) REFERENCES parent(code))', 4),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentPrefixUniqueRows237($records);
    $t->same(1, count($rows));
    $t->same('ok', $rows[0]['status']);
    $t->same('parent_code_unique', $rows[0]['parent_unique_index']);
    $t->same('parent_code_locale_unique', $rows[0]['prefix_unique_index']);
};

$tests['pragma index xinfo foreignkey parent prefix unique current source next237 rejects stale cursor'] = static function (TestRunner $t) use ($page237, $missingNextRecords237): void {
    $first = $page237(0, 66);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page237(66, 5, $first['next'], $missingNextRecords237));
};

$tests['pragma index xinfo foreignkey parent prefix unique current source next237 rejects stale offset'] = static function (TestRunner $t) use ($page237): void {
    $first = $page237(0, 66);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page237(67, 5, $first['next']));
};

$tests['pragma index xinfo foreignkey parent prefix unique current source next237 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::parentPrefixUniqueRows237([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey parent prefix unique current source next237 rejects invalid bounds'] = static function (TestRunner $t) use ($page237): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page237(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page237(0, 0));
};

return $tests;
