<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    1,
);

$makeCatalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    return new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_option_dependency', 'wp_option_dependency', 2, "CREATE TABLE wp_option_dependency(
            option_name TEXT PRIMARY KEY,
            parent_option TEXT REFERENCES wp_options(option_name) ON UPDATE CASCADE ON DELETE SET NULL,
            autoload TEXT NOT NULL DEFAULT 'no'
        )"),
        $record('table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(option_name TEXT PRIMARY KEY, option_value TEXT)'),
    ]);
};

$nextRecords = static function () use ($record): array {
    return [
        $record('table', 'wp_option_dependency', 'wp_option_dependency', 2, "CREATE TABLE wp_option_dependency(
            option_name TEXT PRIMARY KEY,
            parent_option TEXT REFERENCES wp_options(option_name) ON UPDATE CASCADE ON DELETE SET NULL,
            previous_option TEXT,
            fallback_option TEXT,
            CONSTRAINT option_dependency_parent
                FOREIGN KEY(previous_option, fallback_option)
                REFERENCES wp_option_dependency(option_name, parent_option)
                ON UPDATE SET DEFAULT
                ON DELETE CASCADE
                MATCH recursive
        )"),
        $record('table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(option_name TEXT PRIMARY KEY, option_value TEXT)'),
    ];
};

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'status ok' => ['status', 'ok'],
    'operation name' => ['operation', 'pragma-foreign-key-list-after-schema-reparse'],
    'pragma name' => ['pragma', 'foreign_key_list'],
    'target table' => ['target', 'wp_option_dependency'],
    'current schema main' => ['current_schema', 'main'],
    'next schema main' => ['next_schema', 'main'],
    'current generation zero' => ['current_generation', 0],
    'next generation one' => ['next_generation', 1],
    'current has one row' => ['current_rows.count', 1],
    'next has three rows' => ['next_rows.count', 3],
    'current recursive empty' => ['current_recursive_rows.count', 0],
    'next recursive two rows' => ['next_recursive_rows.count', 2],
    'current row id zero' => ['current_rows.0.id', 0],
    'current row seq zero' => ['current_rows.0.seq', 0],
    'current row target table' => ['current_rows.0.table', 'wp_options'],
    'current row from column' => ['current_rows.0.from', 'parent_option'],
    'current row to column' => ['current_rows.0.to', 'option_name'],
    'current row update cascade' => ['current_rows.0.on_update', 'CASCADE'],
    'current row delete set null' => ['current_rows.0.on_delete', 'SET NULL'],
    'current row match none' => ['current_rows.0.match', 'NONE'],
    'next first row keeps external target' => ['next_rows.0.table', 'wp_options'],
    'next first row keeps from column' => ['next_rows.0.from', 'parent_option'],
    'next first row keeps delete action' => ['next_rows.0.on_delete', 'SET NULL'],
    'next recursive first id one' => ['next_recursive_rows.0.id', 1],
    'next recursive first seq zero' => ['next_recursive_rows.0.seq', 0],
    'next recursive first table self' => ['next_recursive_rows.0.table', 'wp_option_dependency'],
    'next recursive first from previous' => ['next_recursive_rows.0.from', 'previous_option'],
    'next recursive first to option name' => ['next_recursive_rows.0.to', 'option_name'],
    'next recursive first update set default' => ['next_recursive_rows.0.on_update', 'SET DEFAULT'],
    'next recursive first delete cascade' => ['next_recursive_rows.0.on_delete', 'CASCADE'],
    'next recursive first match recursive' => ['next_recursive_rows.0.match', 'RECURSIVE'],
    'next recursive second shared id' => ['next_recursive_rows.1.id', 1],
    'next recursive second seq one' => ['next_recursive_rows.1.seq', 1],
    'next recursive second from fallback' => ['next_recursive_rows.1.from', 'fallback_option'],
    'next recursive second to parent option' => ['next_recursive_rows.1.to', 'parent_option'],
    'next full recursive second is third row' => ['next_rows.2.from', 'fallback_option'],
    'dependency marker present' => ['dependencies.0', 'sqlite-pragma-foreign-key-list-after-schema-reparse'],
];

$tests = [];
foreach ($cases as $name => [$path, $expected]) {
    $tests['pragma foreign key recursive schema reparse ' . $name] = static function (TestRunner $t) use ($makeCatalog, $nextRecords, $valueAt, $path, $expected): void {
        $plan = $makeCatalog()->foreignKeyListAfterSchemaReparse("pragma_foreign_key_list('wp_option_dependency')", $nextRecords());
        $t->same($expected, $valueAt($plan, $path));
    };
}

$tests['pragma foreign key recursive schema reparse cursor current stays stable after schema replace'] = static function (TestRunner $t) use ($makeCatalog, $nextRecords): void {
    $plan = $makeCatalog()->foreignKeyListAfterSchemaReparse("pragma_foreign_key_list('wp_option_dependency')", $nextRecords());

    $t->same($plan['current_rows'][0], $plan['current_cursor']->current());
    $t->same(0, $plan['current_cursor']->key());
    $t->same(false, $plan['current_cursor']->metadata()['eof']);
    $t->same('wp_options', $plan['current_cursor']->current()['table']);
    $t->same(null, $plan['current_cursor']->next());
    $t->same(false, $plan['current_cursor']->valid());
    $t->same(true, $plan['current_cursor']->metadata()['eof']);
    $t->same(1, $plan['current_cursor']->metadata()['position']);
    $plan['current_cursor']->rewind();
    $t->same($plan['current_rows'][0], $plan['current_cursor']->current());
};

$tests['pragma foreign key recursive schema reparse cursor next exposes recursive rows'] = static function (TestRunner $t) use ($makeCatalog, $nextRecords): void {
    $plan = $makeCatalog()->foreignKeyListAfterSchemaReparse("pragma_foreign_key_list('wp_option_dependency')", $nextRecords());
    $cursor = $plan['next_cursor'];

    $t->same(3, $cursor->metadata()['row_count']);
    $t->same($plan['next_rows'][0], $cursor->current());
    $t->same($plan['next_rows'][1], $cursor->next());
    $t->same('wp_option_dependency', $cursor->current()['table']);
    $t->same('previous_option', $cursor->current()['from']);
    $t->same($plan['next_rows'][2], $cursor->next());
    $t->same(1, $cursor->current()['id']);
    $t->same(1, $cursor->current()['seq']);
    $t->same('fallback_option', $cursor->current()['from']);
    $t->same(null, $cursor->next());
    $t->same([], $cursor->remainingRows());
};

$tests['pragma foreign key recursive schema reparse schema-pinned archive table'] = static function (TestRunner $t) use ($record): void {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_option_dependency', 'wp_option_dependency', 2, 'CREATE TABLE wp_option_dependency(option_name TEXT PRIMARY KEY)'),
    ]);
    $catalog->attach('archive', '/srv/wp/archive.sqlite', [
        $record('table', 'wp_option_dependency', 'wp_option_dependency', 7, 'CREATE TABLE wp_option_dependency(option_name TEXT PRIMARY KEY, parent_option TEXT REFERENCES wp_options(option_name))'),
        $record('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(option_name TEXT PRIMARY KEY)'),
    ]);

    $plan = $catalog->foreignKeyListAfterSchemaReparse(
        "pragma_foreign_key_list('wp_option_dependency', 'archive')",
        [
            $record('table', 'wp_option_dependency', 'wp_option_dependency', 9, 'CREATE TABLE wp_option_dependency(option_name TEXT PRIMARY KEY, parent_option TEXT, FOREIGN KEY(parent_option) REFERENCES wp_option_dependency(option_name) ON DELETE CASCADE)'),
            $record('table', 'wp_options', 'wp_options', 10, 'CREATE TABLE wp_options(option_name TEXT PRIMARY KEY)'),
        ],
    );

    $t->same('archive', $plan['current_schema']);
    $t->same('archive', $plan['next_schema']);
    $t->same('wp_options', $plan['current_rows'][0]['table']);
    $t->same(1, count($plan['next_recursive_rows']));
    $t->same('wp_option_dependency', $plan['next_recursive_rows'][0]['table']);
    $t->same('CASCADE', $plan['next_recursive_rows'][0]['on_delete']);
};

$tests['pragma foreign key recursive schema reparse rejects non foreign-key pragma'] = static function (TestRunner $t) use ($makeCatalog, $nextRecords): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeCatalog()->foreignKeyListAfterSchemaReparse("pragma_table_info('wp_option_dependency')", $nextRecords()));
};

return $tests;
