<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record212 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords212 = [
    $record212('table', 'wp_terms', 'wp_terms', 2, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE NOT NULL)', 1),
    $record212('table', 'wp_option_keys', 'wp_option_keys', 3, 'CREATE TABLE wp_option_keys(blog_id INTEGER NOT NULL, option_name TEXT COLLATE NOCASE NOT NULL, PRIMARY KEY(blog_id, option_name)) WITHOUT ROWID', 2),
    $record212('index', 'sqlite_autoindex_wp_option_keys_1', 'wp_option_keys', 4, null, 3),
    $record212('table', 'wp_postmeta_import', 'wp_postmeta_import', 5, "CREATE TABLE wp_postmeta_import(
        meta_id INTEGER PRIMARY KEY,
        term_id INTEGER NOT NULL,
        blog_id INTEGER NOT NULL,
        option_name TEXT NOT NULL,
        meta_key TEXT,
        option_value TEXT,
        legacy_parent INTEGER REFERENCES wp_terms(term_id),
        FOREIGN KEY(term_id) REFERENCES wp_terms(term_id) ON DELETE CASCADE,
        FOREIGN KEY(blog_id, option_name) REFERENCES wp_option_keys(blog_id, option_name) ON UPDATE CASCADE ON DELETE SET NULL
    )", 4),
    $record212('index', 'wp_postmeta_term_partial', 'wp_postmeta_import', 6, 'CREATE INDEX wp_postmeta_term_partial ON wp_postmeta_import(term_id) WHERE meta_key IS NOT NULL', 5),
    $record212('index', 'wp_postmeta_option_partial', 'wp_postmeta_import', 7, 'CREATE INDEX wp_postmeta_option_partial ON wp_postmeta_import(blog_id, option_name) WHERE option_value IS NOT NULL', 6),
];

$nextRecords212 = [
    $currentRecords212[0],
    $currentRecords212[1],
    $currentRecords212[2],
    $currentRecords212[3],
    $currentRecords212[4],
    $currentRecords212[5],
    $record212('index', 'wp_postmeta_term_action', 'wp_postmeta_import', 8, 'CREATE INDEX wp_postmeta_term_action ON wp_postmeta_import(term_id)', 7),
    $record212('index', 'wp_postmeta_option_action', 'wp_postmeta_import', 9, 'CREATE INDEX wp_postmeta_option_action ON wp_postmeta_import(blog_id, option_name)', 8),
];

$missingNextRecords212 = [
    $currentRecords212[0],
    $currentRecords212[1],
    $currentRecords212[2],
    $currentRecords212[3],
];

$page212 = static fn (
    int $offset = 0,
    int $limit = 90,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page212(
    $currentRecords212,
    $nextRecords ?? $nextRecords212,
    'PRAGMA main.index_xinfo(wp_postmeta_option_partial)',
    'PRAGMA main.foreign_key_list(wp_postmeta_import)',
    $offset,
    $limit,
    $resume,
);

$valueAt212 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default212 = static fn (): array => $page212();
$blocked212 = static fn (): array => $page212(nextRecords: $missingNextRecords212);
$currentAction212 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionLookupRows212($currentRecords212);
$nextAction212 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionLookupRows212($nextRecords212, 'next');

$cases212 = [
    'status ok' => [$default212, 'status', 'ok'],
    'operation marker' => [$default212, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next212'],
    'source id length' => [static fn (): array => ['len' => strlen($page212()['source_id'])], 'len', 64],
    'offset default' => [$default212, 'offset', 0],
    'limit default' => [$default212, 'limit', 90],
    'dependency appended' => [$default212, 'dependencies.6', 'sqlite-pragma-foreign-key-child-action-nonpartial-index'],
    'action lookup source current' => [$default212, 'current_source.foreign_key_child_action_lookup_source', 'pragma_foreign_key_list_actions_plus_pragma_index_xinfo_nonpartial_child_prefix'],
    'action lookup source next' => [$default212, 'next_source.foreign_key_child_action_lookup_source', 'pragma_foreign_key_list_actions_plus_pragma_index_xinfo_nonpartial_child_prefix'],
    'base implicit parent retained' => [$default212, 'current.foreign_key_implicit_parent_primary_key.rows', 0],
    'base foreign key list retained' => [$default212, 'current.foreign_key_list', 4],
    'current action rows' => [$default212, 'current.foreign_key_child_action_lookup.rows', 3],
    'current action ok zero' => [$default212, 'current.foreign_key_child_action_lookup.ok', 0],
    'current action blocked' => [$default212, 'current.foreign_key_child_action_lookup.blocked', 3],
    'current partial blockers' => [$default212, 'current.foreign_key_child_action_lookup.partial_child_action_index', 3],
    'current missing blockers zero' => [$default212, 'current.foreign_key_child_action_lookup.missing_child_action_index', 0],
    'current cascade action rows' => [$default212, 'current.foreign_key_child_action_lookup.cascade', 3],
    'current set null action rows' => [$default212, 'current.foreign_key_child_action_lookup.set_null', 2],
    'current restrict action rows' => [$default212, 'current.foreign_key_child_action_lookup.restrict', 0],
    'next action rows' => [$default212, 'next_counts.foreign_key_child_action_lookup.rows', 3],
    'next action ok' => [$default212, 'next_counts.foreign_key_child_action_lookup.ok', 3],
    'next blocked cleared' => [$default212, 'next_counts.foreign_key_child_action_lookup.blocked', 0],
    'next partial blockers cleared' => [$default212, 'next_counts.foreign_key_child_action_lookup.partial_child_action_index', 0],
    'delta rows unchanged' => [$default212, 'delta.foreign_key_child_action_lookup_rows', 0],
    'delta blockers negative' => [$default212, 'delta.foreign_key_child_action_lookup_blockers', -3],
    'delta repaired true' => [$default212, 'delta.foreign_key_child_action_lookup_repaired', true],
    'delta changed true' => [$default212, 'delta.foreign_key_child_action_lookup_changed', true],
    'total includes action rows' => [$default212, 'total', 32],
    'count complete' => [$default212, 'count', 32],
    'next complete null' => [$default212, 'next', null],
    'current summary first partial' => [$default212, 'current_source.foreign_key_child_action_lookup.0', 'current:wp_postmeta_import#1.0:term_id->wp_terms.term_id:CASCADE/NO ACTION:missing-full:wp_postmeta_term_partial:partial_child_action_index'],
    'current summary composite partial' => [$default212, 'current_source.foreign_key_child_action_lookup.1', 'current:wp_postmeta_import#2.0:blog_id->wp_option_keys.blog_id:SET NULL/CASCADE:missing-full:wp_postmeta_option_partial:partial_child_action_index'],
    'next summary first ok' => [$default212, 'next_source.foreign_key_child_action_lookup.0', 'next:wp_postmeta_import#1.0:term_id->wp_terms.term_id:CASCADE/NO ACTION:wp_postmeta_term_action:wp_postmeta_term_partial:ok'],
    'blocked next missing rows' => [$blocked212, 'next_counts.foreign_key_child_action_lookup.missing_child_action_index', 3],
    'blocked next partial zero' => [$blocked212, 'next_counts.foreign_key_child_action_lookup.partial_child_action_index', 0],
    'blocked repaired false' => [$blocked212, 'delta.foreign_key_child_action_lookup_repaired', false],
    'helper current first kind' => [$currentAction212, '0.kind', 'foreign_key_child_action_lookup'],
    'helper current first status' => [$currentAction212, '0.status', 'partial_child_action_index'],
    'helper current first partial index' => [$currentAction212, '0.partial_child_index', 'wp_postmeta_term_partial'],
    'helper current first where' => [$currentAction212, '0.partial_child_index_where', 'meta_key IS NOT NULL'],
    'helper current composite first partial' => [$currentAction212, '1.partial_child_index', 'wp_postmeta_option_partial'],
    'helper current composite where' => [$currentAction212, '1.partial_child_index_where', 'option_value IS NOT NULL'],
    'helper current composite second seq' => [$currentAction212, '2.seq', 1],
    'helper next first phase' => [$nextAction212, '0.phase', 'next'],
    'helper next first ok' => [$nextAction212, '0.status', 'ok'],
    'helper next first full index' => [$nextAction212, '0.full_child_index', 'wp_postmeta_term_action'],
    'helper next composite full index' => [$nextAction212, '1.full_child_index', 'wp_postmeta_option_action'],
    'helper next composite prefix columns' => [$nextAction212, '1.covered_prefix_columns', 2],
];

$tests = [];
foreach ($cases212 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey child action lookup current source next212 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt212): void {
        $t->same($expected, $valueAt212($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey child action lookup current source next212 paginates action rows'] = static function (TestRunner $t) use ($page212): void {
    $first = $page212(0, 26);
    $second = $page212(26, 3, $first['next']);
    $third = $page212(29, 3, $second['next']);

    $t->same(26, $first['count']);
    $t->same('foreign_key_child_action_lookup', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 26], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('partial_child_action_index', $second['rows'][2]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('ok', $third['rows'][2]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey child action lookup current source next212 ignores no action constraints'] = static function (TestRunner $t) use ($currentAction212): void {
    $rows = $currentAction212();

    $t->same(3, count($rows));
    $t->same(false, in_array('legacy_parent', array_column($rows, 'from'), true));
};

$tests['pragma index xinfo foreignkey child action lookup current source next212 rejects stale cursor'] = static function (TestRunner $t) use ($page212, $missingNextRecords212): void {
    $first = $page212(0, 26);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page212(26, 3, $first['next'], $missingNextRecords212));
};

$tests['pragma index xinfo foreignkey child action lookup current source next212 rejects stale offset'] = static function (TestRunner $t) use ($page212): void {
    $first = $page212(0, 26);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page212(27, 3, $first['next']));
};

$tests['pragma index xinfo foreignkey child action lookup current source next212 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::childActionLookupRows212([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey child action lookup current source next212 rejects invalid bounds'] = static function (TestRunner $t) use ($page212): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page212(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page212(0, 0));
};

return $tests;
