<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record213 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords213 = [
    $record213('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL)', 1),
    $record213('table', 'wp_option_defaults', 'wp_option_defaults', 3, 'CREATE TABLE wp_option_defaults(blog_id INTEGER NOT NULL, option_name TEXT NOT NULL, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID', 2),
    $record213('index', 'sqlite_autoindex_wp_option_defaults_1', 'wp_option_defaults', 4, null, 3),
    $record213('table', 'wp_postmeta_import', 'wp_postmeta_import', 5, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        blog_id INTEGER NOT NULL,
        option_name TEXT NOT NULL,
        nullable_term_id INTEGER,
        default_blog_id INTEGER DEFAULT 1,
        default_option_name TEXT DEFAULT 'autoload',
        null_default_option TEXT NOT NULL DEFAULT NULL,
        meta_key TEXT,
        option_value TEXT,
        FOREIGN KEY(term_id) REFERENCES wp_terms(term_id) ON DELETE SET NULL,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_defaults(blog_id, option_name) ON UPDATE SET DEFAULT,
        FOREIGN KEY(nullable_term_id) REFERENCES wp_terms(term_id) ON DELETE SET NULL,
        FOREIGN KEY(default_blog_id, default_option_name) REFERENCES wp_option_defaults(blog_id, option_name) ON UPDATE SET DEFAULT,
        FOREIGN KEY(null_default_option) REFERENCES wp_terms(slug) ON DELETE SET DEFAULT
    )", 4),
    $record213('index', 'wp_postmeta_term_action', 'wp_postmeta_import', 6, 'CREATE INDEX wp_postmeta_term_action ON wp_postmeta_import(term_id)', 5),
    $record213('index', 'wp_postmeta_option_action', 'wp_postmeta_import', 7, 'CREATE INDEX wp_postmeta_option_action ON wp_postmeta_import(blog_id, option_name)', 6),
];

$nextRecords213 = [
    $currentRecords213[0],
    $currentRecords213[1],
    $currentRecords213[2],
    $record213('table', 'wp_postmeta_import', 'wp_postmeta_import', 5, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_id INTEGER DEFAULT NULL,
        blog_id INTEGER NOT NULL DEFAULT 1,
        option_name TEXT NOT NULL DEFAULT 'autoload',
        nullable_term_id INTEGER,
        default_blog_id INTEGER DEFAULT 1,
        default_option_name TEXT DEFAULT 'autoload',
        null_default_option TEXT DEFAULT 'uncategorized',
        meta_key TEXT,
        option_value TEXT,
        FOREIGN KEY(term_id) REFERENCES wp_terms(term_id) ON DELETE SET NULL,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_defaults(blog_id, option_name) ON UPDATE SET DEFAULT,
        FOREIGN KEY(nullable_term_id) REFERENCES wp_terms(term_id) ON DELETE SET NULL,
        FOREIGN KEY(default_blog_id, default_option_name) REFERENCES wp_option_defaults(blog_id, option_name) ON UPDATE SET DEFAULT,
        FOREIGN KEY(null_default_option) REFERENCES wp_terms(slug) ON DELETE SET DEFAULT
    )", 4),
    $currentRecords213[4],
    $currentRecords213[5],
];

$missingNextRecords213 = [
    $currentRecords213[0],
    $currentRecords213[1],
    $currentRecords213[2],
    $record213('table', 'wp_postmeta_import', 'wp_postmeta_import', 5, 'CREATE TABLE wp_postmeta_import(meta_id INTEGER PRIMARY KEY, term_id INTEGER NOT NULL, FOREIGN KEY(term_id) REFERENCES wp_terms(term_id) ON DELETE SET NULL)', 4),
];

$page213 = static fn (
    int $offset = 0,
    int $limit = 120,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page213(
    $currentRecords213,
    $nextRecords ?? $nextRecords213,
    'PRAGMA main.index_xinfo(wp_postmeta_option_action)',
    'PRAGMA main.foreign_key_list(wp_postmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt213 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default213 = static fn (): array => $page213();
$blocked213 = static fn (): array => $page213(nextRecords: $missingNextRecords213);
$currentActionColumns213 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionColumnRows213($currentRecords213);
$nextActionColumns213 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionColumnRows213($nextRecords213, 'next');

$cases213 = [
    'status ok' => [$default213, 'status', 'ok'],
    'operation marker' => [$default213, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next213'],
    'source id length' => [static fn (): array => ['len' => strlen($page213()['source_id'])], 'len', 64],
    'offset default' => [$default213, 'offset', 0],
    'limit default' => [$default213, 'limit', 120],
    'dependency appended' => [$default213, 'dependencies.7', 'sqlite-pragma-foreign-key-action-column-defaults'],
    'action source current' => [$default213, 'current_source.foreign_key_action_column_source', 'pragma_foreign_key_list_actions_plus_pragma_table_info_notnull_default'],
    'action source next' => [$default213, 'next_source.foreign_key_action_column_source', 'pragma_foreign_key_list_actions_plus_pragma_table_info_notnull_default'],
    'base action lookup retained' => [$default213, 'current.foreign_key_child_action_lookup.rows', 7],
    'current action rows' => [$default213, 'current.foreign_key_action_columns.rows', 7],
    'current ok rows' => [$default213, 'current.foreign_key_action_columns.ok', 3],
    'current blocked rows' => [$default213, 'current.foreign_key_action_columns.blocked', 4],
    'current set null rows' => [$default213, 'current.foreign_key_action_columns.set_null', 2],
    'current set default rows' => [$default213, 'current.foreign_key_action_columns.set_default', 5],
    'current set null notnull blockers' => [$default213, 'current.foreign_key_action_columns.set_null_notnull_child', 1],
    'current missing default blockers' => [$default213, 'current.foreign_key_action_columns.set_default_missing_child_default', 2],
    'current null default blockers' => [$default213, 'current.foreign_key_action_columns.set_default_null_notnull_child', 1],
    'current missing column zero' => [$default213, 'current.foreign_key_action_columns.missing_child_column', 0],
    'next action rows' => [$default213, 'next_counts.foreign_key_action_columns.rows', 7],
    'next ok rows' => [$default213, 'next_counts.foreign_key_action_columns.ok', 7],
    'next blocked cleared' => [$default213, 'next_counts.foreign_key_action_columns.blocked', 0],
    'next set null rows' => [$default213, 'next_counts.foreign_key_action_columns.set_null', 2],
    'next set default rows' => [$default213, 'next_counts.foreign_key_action_columns.set_default', 5],
    'delta rows unchanged' => [$default213, 'delta.foreign_key_action_column_rows', 0],
    'delta blockers negative' => [$default213, 'delta.foreign_key_action_column_blockers', -4],
    'delta repaired true' => [$default213, 'delta.foreign_key_action_column_repaired', true],
    'delta changed true' => [$default213, 'delta.foreign_key_action_column_changed', true],
    'total includes action columns' => [$default213, 'total', 68],
    'count complete' => [$default213, 'count', 68],
    'next complete null' => [$default213, 'next', null],
    'current summary set null blocker' => [$default213, 'current_source.foreign_key_action_columns.0', 'current:wp_postmeta_import#0.0:term_id->wp_terms.term_id:on_delete=SET NULL:notnull=1:default=NULL:set_null_notnull_child'],
    'current summary set default missing first' => [$default213, 'current_source.foreign_key_action_columns.1', 'current:wp_postmeta_import#1.0:blog_id->wp_option_defaults.blog_id:on_update=SET DEFAULT:notnull=1:default=NULL:set_default_missing_child_default'],
    'current summary set default missing second' => [$default213, 'current_source.foreign_key_action_columns.2', 'current:wp_postmeta_import#1.1:option_name->wp_option_defaults.option_name:on_update=SET DEFAULT:notnull=1:default=NULL:set_default_missing_child_default'],
    'current summary null default blocker' => [$default213, 'current_source.foreign_key_action_columns.6', 'current:wp_postmeta_import#4.0:null_default_option->wp_terms.slug:on_delete=SET DEFAULT:notnull=1:default=NULL:set_default_null_notnull_child'],
    'next summary set null repaired' => [$default213, 'next_source.foreign_key_action_columns.0', 'next:wp_postmeta_import#0.0:term_id->wp_terms.term_id:on_delete=SET NULL:notnull=0:default=NULL:ok'],
    'next summary set default repaired' => [$default213, 'next_source.foreign_key_action_columns.1', 'next:wp_postmeta_import#1.0:blog_id->wp_option_defaults.blog_id:on_update=SET DEFAULT:notnull=1:default=1:ok'],
    'blocked next rows' => [$blocked213, 'next_counts.foreign_key_action_columns.rows', 1],
    'blocked next blockers' => [$blocked213, 'next_counts.foreign_key_action_columns.blocked', 1],
    'blocked repaired false' => [$blocked213, 'delta.foreign_key_action_column_repaired', false],
    'helper current first kind' => [$currentActionColumns213, '0.kind', 'foreign_key_action_column'],
    'helper current first action key' => [$currentActionColumns213, '0.action_key', 'on_delete'],
    'helper current first status' => [$currentActionColumns213, '0.status', 'set_null_notnull_child'],
    'helper current first notnull' => [$currentActionColumns213, '0.notnull', 1],
    'helper current first default' => [$currentActionColumns213, '0.dflt_value', null],
    'helper current second action' => [$currentActionColumns213, '1.action', 'SET DEFAULT'],
    'helper current second status' => [$currentActionColumns213, '1.status', 'set_default_missing_child_default'],
    'helper current repaired nullable ok' => [$currentActionColumns213, '3.status', 'ok'],
    'helper current composite default ok' => [$currentActionColumns213, '4.status', 'ok'],
    'helper current null default status' => [$currentActionColumns213, '6.status', 'set_default_null_notnull_child'],
    'helper next first phase' => [$nextActionColumns213, '0.phase', 'next'],
    'helper next first status' => [$nextActionColumns213, '0.status', 'ok'],
    'helper next second default' => [$nextActionColumns213, '1.dflt_value', '1'],
    'helper next third default' => [$nextActionColumns213, '2.dflt_value', "'autoload'"],
    'helper next last status' => [$nextActionColumns213, '6.status', 'ok'],
];

$tests = [];
foreach ($cases213 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey action column current source next213 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt213): void {
        $t->same($expected, $valueAt213($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey action column current source next213 paginates action rows'] = static function (TestRunner $t) use ($page213): void {
    $first = $page213(0, 54);
    $second = $page213(54, 7, $first['next']);
    $third = $page213(61, 7, $second['next']);

    $t->same(54, $first['count']);
    $t->same('foreign_key_action_column', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 54], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('set_null_notnull_child', $second['rows'][0]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('ok', $third['rows'][6]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey action column current source next213 omits cascade actions'] = static function (TestRunner $t) use ($currentActionColumns213): void {
    $rows = $currentActionColumns213();

    $t->same(7, count($rows));
    $t->same(false, in_array('CASCADE', array_column($rows, 'action'), true));
};

$tests['pragma index xinfo foreignkey action column current source next213 rejects stale cursor'] = static function (TestRunner $t) use ($page213, $missingNextRecords213): void {
    $first = $page213(0, 54);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page213(54, 5, $first['next'], $missingNextRecords213));
};

$tests['pragma index xinfo foreignkey action column current source next213 rejects stale offset'] = static function (TestRunner $t) use ($page213): void {
    $first = $page213(0, 54);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page213(55, 5, $first['next']));
};

$tests['pragma index xinfo foreignkey action column current source next213 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionColumnRows213([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey action column current source next213 rejects invalid bounds'] = static function (TestRunner $t) use ($page213): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page213(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page213(0, 0));
};

return $tests;
