<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext247;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record247 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$currentRecords247 = [
    $record247('table', 'wp_sites', 'wp_sites', 2, "CREATE TABLE wp_sites(site_id INTEGER PRIMARY KEY, domain TEXT NOT NULL DEFAULT 'example.test')", 1),
    $record247('table', 'wp_option_defaults', 'wp_option_defaults', 3, "CREATE TABLE wp_option_defaults(option_name TEXT PRIMARY KEY, option_value TEXT NOT NULL DEFAULT '')", 2),
    $record247('index', 'wp_option_defaults_name_unique', 'wp_option_defaults', 4, 'CREATE UNIQUE INDEX wp_option_defaults_name_unique ON wp_option_defaults(option_name)', 3),
    $record247('table', 'wp_imported_options', 'wp_imported_options', 5, "CREATE TABLE wp_imported_options(
        option_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        option_name TEXT NOT NULL,
        option_value TEXT,
        fallback_name TEXT NOT NULL DEFAULT 'home',
        fallback_value TEXT DEFAULT NULL,
        FOREIGN KEY(site_id) REFERENCES wp_sites(site_id) ON DELETE SET DEFAULT,
        FOREIGN KEY(option_name) REFERENCES wp_option_defaults(option_name) ON UPDATE SET DEFAULT,
        FOREIGN KEY(fallback_name) REFERENCES wp_option_defaults(option_name) ON DELETE SET DEFAULT ON UPDATE CASCADE,
        FOREIGN KEY(fallback_value) REFERENCES wp_option_defaults(option_value) ON UPDATE SET DEFAULT
    )", 4),
];

$nextRecords247 = [
    $currentRecords247[0],
    $currentRecords247[1],
    $currentRecords247[2],
    $record247('table', 'wp_imported_options', 'wp_imported_options', 5, "CREATE TABLE wp_imported_options(
        option_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL DEFAULT 1,
        option_name TEXT NOT NULL DEFAULT 'home',
        option_value TEXT,
        fallback_name TEXT NOT NULL DEFAULT 'home',
        fallback_value TEXT DEFAULT NULL,
        FOREIGN KEY(site_id) REFERENCES wp_sites(site_id) ON DELETE SET DEFAULT,
        FOREIGN KEY(option_name) REFERENCES wp_option_defaults(option_name) ON UPDATE SET DEFAULT,
        FOREIGN KEY(fallback_name) REFERENCES wp_option_defaults(option_name) ON DELETE SET DEFAULT ON UPDATE CASCADE,
        FOREIGN KEY(fallback_value) REFERENCES wp_option_defaults(option_value) ON UPDATE SET DEFAULT
    )", 4),
];

$stillBlockedRecords247 = [
    $currentRecords247[0],
    $currentRecords247[1],
    $currentRecords247[2],
    $record247('table', 'wp_imported_options', 'wp_imported_options', 5, "CREATE TABLE wp_imported_options(
        option_id INTEGER PRIMARY KEY,
        site_id INTEGER NOT NULL,
        option_name TEXT NOT NULL,
        fallback_name TEXT NOT NULL,
        FOREIGN KEY(site_id) REFERENCES wp_sites(site_id) ON DELETE SET DEFAULT,
        FOREIGN KEY(option_name) REFERENCES wp_option_defaults(option_name) ON UPDATE SET DEFAULT,
        FOREIGN KEY(fallback_name) REFERENCES wp_option_defaults(option_name) ON DELETE SET DEFAULT
    )", 4),
];

$page247 = static fn (
    int $offset = 0,
    int $limit = 260,
    ?array $resume = null,
    ?array $nextRecords = null,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext247::page(
    $currentRecords247,
    $nextRecords ?? $nextRecords247,
    'PRAGMA main.index_xinfo(wp_option_defaults_name_unique)',
    'PRAGMA main.foreign_key_list(wp_imported_options)',
    $offset,
    $limit,
    $resume,
);

$valueAt247 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default247 = static fn (): array => $page247();
$blocked247 = static fn (): array => $page247(nextRecords: $stillBlockedRecords247);
$currentSetDefault247 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext247::setDefaultActionRows($currentRecords247);
$nextSetDefault247 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext247::setDefaultActionRows($nextRecords247, 'next');

$cases247 = [
    'status ok' => [$default247, 'status', 'ok'],
    'operation marker' => [$default247, 'operation', 'pragma-index-xinfo-foreignkey-current-source-next247'],
    'source id length' => [static fn (): array => ['len' => strlen($page247()['source_id'])], 'len', 64],
    'offset default' => [$default247, 'offset', 0],
    'limit default' => [$default247, 'limit', 260],
    'dependency appended' => [static fn (): array => ['has' => in_array('sqlite-pragma-foreign-key-set-default-child-defaults', $page247()['dependencies'], true)], 'has', true],
    'base expression source retained' => [$default247, 'current_source.foreign_key_parent_expression_index_source', 'pragma_foreign_key_list_parent_columns_plus_pragma_index_xinfo_expression_key_rows'],
    'set default source current' => [$default247, 'current_source.foreign_key_set_default_source', 'pragma_foreign_key_list_actions_plus_table_info_child_defaults'],
    'set default source next' => [$default247, 'next_source.foreign_key_set_default_source', 'pragma_foreign_key_list_actions_plus_table_info_child_defaults'],
    'current rows' => [$default247, 'current.foreign_key_set_default.rows', 4],
    'current blocked rows' => [$default247, 'current.foreign_key_set_default.blocked', 2],
    'current ok rows' => [$default247, 'current.foreign_key_set_default.ok', 2],
    'current notnull null defaults' => [$default247, 'current.foreign_key_set_default.notnull_default_null', 2],
    'current missing child zero' => [$default247, 'current.foreign_key_set_default.missing_child_column', 0],
    'current delete actions' => [$default247, 'current.foreign_key_set_default.set_default_delete', 2],
    'current update actions' => [$default247, 'current.foreign_key_set_default.set_default_update', 2],
    'current explicit defaults' => [$default247, 'current.foreign_key_set_default.explicit_default', 1],
    'current nullable null defaults' => [$default247, 'current.foreign_key_set_default.nullable_default_null', 1],
    'next rows' => [$default247, 'next_counts.foreign_key_set_default.rows', 4],
    'next blocked rows zero' => [$default247, 'next_counts.foreign_key_set_default.blocked', 0],
    'next ok rows' => [$default247, 'next_counts.foreign_key_set_default.ok', 4],
    'next explicit defaults' => [$default247, 'next_counts.foreign_key_set_default.explicit_default', 3],
    'next nullable null default retained' => [$default247, 'next_counts.foreign_key_set_default.nullable_default_null', 1],
    'delta rows zero' => [$default247, 'delta.foreign_key_set_default_rows', 0],
    'delta blockers repaired' => [$default247, 'delta.foreign_key_set_default_blockers', -2],
    'delta repaired true' => [$default247, 'delta.foreign_key_set_default_repaired', true],
    'delta changed true' => [$default247, 'delta.foreign_key_set_default_changed', true],
    'current summary site id blocked' => [$default247, 'current_source.foreign_key_set_default.0', 'current:wp_imported_options#0.0:site_id->wp_sites.site_id:NO ACTION/SET DEFAULT:notnull=1:default=NULL:notnull_default_null'],
    'current summary option blocked' => [$default247, 'current_source.foreign_key_set_default.1', 'current:wp_imported_options#1.0:option_name->wp_option_defaults.option_name:SET DEFAULT/NO ACTION:notnull=1:default=NULL:notnull_default_null'],
    'current summary fallback ok' => [$default247, 'current_source.foreign_key_set_default.2', "current:wp_imported_options#2.0:fallback_name->wp_option_defaults.option_name:CASCADE/SET DEFAULT:notnull=1:default='home':ok"],
    'next summary site id repaired' => [$default247, 'next_source.foreign_key_set_default.0', 'next:wp_imported_options#0.0:site_id->wp_sites.site_id:NO ACTION/SET DEFAULT:notnull=1:default=1:ok'],
    'blocked next still blocked' => [$blocked247, 'next_counts.foreign_key_set_default.blocked', 3],
    'blocked repaired false' => [$blocked247, 'delta.foreign_key_set_default_repaired', false],
    'complete no next page' => [$default247, 'next', null],
    'helper current count' => [static fn (): array => ['count' => count($currentSetDefault247())], 'count', 4],
    'helper current first kind' => [$currentSetDefault247, '0.kind', 'foreign_key_set_default'],
    'helper current first phase' => [$currentSetDefault247, '0.phase', 'current'],
    'helper current first table' => [$currentSetDefault247, '0.table', 'wp_imported_options'],
    'helper current first parent' => [$currentSetDefault247, '0.parent', 'wp_sites'],
    'helper current first from' => [$currentSetDefault247, '0.from', 'site_id'],
    'helper current first to' => [$currentSetDefault247, '0.to', 'site_id'],
    'helper current first delete flag' => [$currentSetDefault247, '0.set_default_on_delete', true],
    'helper current first update flag' => [$currentSetDefault247, '0.set_default_on_update', false],
    'helper current first notnull' => [$currentSetDefault247, '0.notnull', true],
    'helper current first default null' => [$currentSetDefault247, '0.default_is_null', true],
    'helper current first status' => [$currentSetDefault247, '0.status', 'notnull_default_null'],
    'helper current first blocked' => [$currentSetDefault247, '0.blocked', true],
    'helper current first message' => [$currentSetDefault247, '0.message', 'foreign key wp_imported_options.site_id SET DEFAULT action would store NULL into a NOT NULL child column'],
    'helper current fallback default' => [$currentSetDefault247, '2.default', "'home'"],
    'helper current fallback status' => [$currentSetDefault247, '2.status', 'ok'],
    'helper current nullable status' => [$currentSetDefault247, '3.status', 'ok'],
    'helper current nullable default null' => [$currentSetDefault247, '3.default_is_null', true],
    'helper next first phase' => [$nextSetDefault247, '0.phase', 'next'],
    'helper next first default' => [$nextSetDefault247, '0.default', '1'],
    'helper next first status' => [$nextSetDefault247, '0.status', 'ok'],
    'helper next option default' => [$nextSetDefault247, '1.default', "'home'"],
    'helper next all ok' => [static fn (): array => ['ok' => count(array_filter($nextSetDefault247(), static fn (array $row): bool => $row['status'] === 'ok'))], 'ok', 4],
];

$tests = [];
foreach ($cases247 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey set default current source next247 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt247): void {
        $t->same($expected, $valueAt247($factory(), $path));
    };
}

$tests['pragma index xinfo foreignkey set default current source next247 paginates appended rows'] = static function (TestRunner $t) use ($page247): void {
    $full = $page247();
    $baseCount = $full['total'] - 8;
    $first = $page247(0, $baseCount);
    $second = $page247($baseCount, 4, $first['next']);
    $third = $page247($baseCount + 4, 4, $second['next']);

    $t->same($baseCount, $first['count']);
    $t->same('foreign_key_set_default', $first['next_row']['kind']);
    $t->same(['source_id' => $first['source_id'], 'offset' => $baseCount], $first['next']);
    $t->same('current', $second['rows'][0]['phase']);
    $t->same('notnull_default_null', $second['rows'][0]['status']);
    $t->same('ok', $second['rows'][3]['status']);
    $t->same('next', $third['rows'][0]['phase']);
    $t->same('ok', $third['rows'][3]['status']);
    $t->same(null, $third['next']);
};

$tests['pragma index xinfo foreignkey set default current source next247 reports explicit NULL default as blocker on not null column'] = static function (TestRunner $t) use ($record247): void {
    $records = [
        $record247('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record247('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER NOT NULL DEFAULT NULL REFERENCES parent(id) ON DELETE SET DEFAULT)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext247::setDefaultActionRows($records);
    $t->same(1, count($rows));
    $t->same('notnull_default_null', $rows[0]['status']);
    $t->same('NULL', $rows[0]['default']);
    $t->same(true, $rows[0]['default_is_null']);
    $t->same(true, $rows[0]['blocked']);
};

$tests['pragma index xinfo foreignkey set default current source next247 allows nullable implicit NULL default'] = static function (TestRunner $t) use ($record247): void {
    $records = [
        $record247('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record247('table', 'child', 'child', 3, 'CREATE TABLE child(parent_id INTEGER REFERENCES parent(id) ON UPDATE SET DEFAULT)', 2),
    ];

    $rows = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext247::setDefaultActionRows($records);
    $t->same(1, count($rows));
    $t->same('ok', $rows[0]['status']);
    $t->same('', $rows[0]['default']);
    $t->same(true, $rows[0]['default_is_null']);
    $t->same(false, $rows[0]['blocked']);
};

$tests['pragma index xinfo foreignkey set default current source next247 ignores cascade and no action foreign keys'] = static function (TestRunner $t) use ($record247): void {
    $records = [
        $record247('table', 'parent', 'parent', 2, 'CREATE TABLE parent(id INTEGER PRIMARY KEY)', 1),
        $record247('table', 'child', 'child', 3, 'CREATE TABLE child(a INTEGER REFERENCES parent(id) ON DELETE CASCADE, b INTEGER REFERENCES parent(id))', 2),
    ];

    $t->same([], SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext247::setDefaultActionRows($records));
};

$tests['pragma index xinfo foreignkey set default current source next247 rejects stale cursor'] = static function (TestRunner $t) use ($page247, $stillBlockedRecords247): void {
    $full = $page247();
    $first = $page247(0, $full['total'] - 8);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page247($full['total'] - 8, 4, $first['next'], $stillBlockedRecords247));
};

$tests['pragma index xinfo foreignkey set default current source next247 rejects stale offset'] = static function (TestRunner $t) use ($page247): void {
    $full = $page247();
    $first = $page247(0, $full['total'] - 8);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page247($full['total'] - 7, 4, $first['next']));
};

$tests['pragma index xinfo foreignkey set default current source next247 rejects invalid records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext247::setDefaultActionRows([['bad' => true]]));
};

$tests['pragma index xinfo foreignkey set default current source next247 rejects invalid bounds'] = static function (TestRunner $t) use ($page247): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page247(-1, 10));
    $t->throws(InvalidArgumentException::class, static fn (): array => $page247(0, 0));
};

return $tests;
