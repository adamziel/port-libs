<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$baseRecords = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes")', 1),
    $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
    $record('index', 'wp_options_autoload', 'wp_options', 4, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 3),
];

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'transient_timeout_feed', 'option_value' => '1700000000', 'autoload' => 'no'],
];

$plan = static fn (array $rows = null): array => SQLiteSchemaDdlReparsePlan::apply(
    $baseRecords(),
    ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> "")'],
    126,
    'main',
    [
        ['id' => 'wp-options-import-current', 'schema_cookie' => 126, 'sql' => 'SELECT option_name FROM wp_options'],
        ['id' => 'already-reparsed', 'schema_cookie' => 127, 'sql' => 'SELECT option_name_lc FROM wp_options'],
    ],
    ['wp_options' => $rows ?? $currentRows],
);

$byName = static function (array $records, string $name): SQLiteSchemaRecord {
    foreach ($records as $record) {
        if ($record instanceof SQLiteSchemaRecord && $record->name === $name) {
            return $record;
        }
    }

    throw new RuntimeException("Missing record {$name}");
};

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $part === 'count' ? count($value) : $value[$part];
    }

    return $value;
};

$tests = [
    'schema alter generated check reparse current source next126 reports ok' => static fn (TestRunner $t) => $t->same('ok', $plan()['status']),
    'schema alter generated check reparse current source next126 records before cookie' => static fn (TestRunner $t) => $t->same(126, $plan()['before_schema_cookie']),
    'schema alter generated check reparse current source next126 advances cookie once' => static fn (TestRunner $t) => $t->same(127, $plan()['after_schema_cookie']),
    'schema alter generated check reparse current source next126 marks schema changed' => static fn (TestRunner $t) => $t->same(true, $plan()['schema_changed']),
    'schema alter generated check reparse current source next126 operation kind' => static fn (TestRunner $t) => $t->same('alter_table_add_column', $plan()['operations'][0]['kind']),
    'schema alter generated check reparse current source next126 operation table' => static fn (TestRunner $t) => $t->same('wp_options', $plan()['operations'][0]['table']),
    'schema alter generated check reparse current source next126 operation column' => static fn (TestRunner $t) => $t->same('option_name_lc', $plan()['operations'][0]['column']),
    'schema alter generated check reparse current source next126 operation column count' => static fn (TestRunner $t) => $t->same(5, $plan()['operations'][0]['column_count']),
    'schema alter generated check reparse current source next126 operation checked rows' => static fn (TestRunner $t) => $t->same(3, $plan()['operations'][0]['checked_rows']),
    'schema alter generated check reparse current source next126 operation current row count' => static fn (TestRunner $t) => $t->same(3, $plan()['operations'][0]['current_row_count']),
    'schema alter generated check reparse current source next126 operation generated flag' => static fn (TestRunner $t) => $t->same(true, $plan()['operations'][0]['generated']),
    'schema alter generated check reparse current source next126 operation changed' => static fn (TestRunner $t) => $t->same(true, $plan()['operations'][0]['changed']),
    'schema alter generated check reparse current source next126 keeps table count' => static fn (TestRunner $t) => $t->same(1, $plan()['table_count']),
    'schema alter generated check reparse current source next126 keeps index count' => static fn (TestRunner $t) => $t->same(2, $plan()['index_count']),
    'schema alter generated check reparse current source next126 invalidates stale prepared only' => static fn (TestRunner $t) => $t->same(['wp-options-import-current'], $plan()['invalidated_prepared']),
    'schema alter generated check reparse current source next126 dependencies include schema reparse' => static fn (TestRunner $t) => $t->same(['schema-sql-reparse', 'sqlite-schema-cookie', 'pragma-schema-catalog'], $plan()['dependencies']),
    'schema alter generated check reparse current source next126 rewrites table sql' => static function (TestRunner $t) use ($plan, $byName): void {
        $table = $byName($plan()['records'], 'wp_options');
        $t->same('CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes", option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> ""))', $table->sql);
    },
    'schema alter generated check reparse current source next126 preserves table root page' => static function (TestRunner $t) use ($plan, $byName): void {
        $t->same(2, $byName($plan()['records'], 'wp_options')->rootPage);
    },
    'schema alter generated check reparse current source next126 preserves table rowid' => static function (TestRunner $t) use ($plan, $byName): void {
        $t->same(1, $byName($plan()['records'], 'wp_options')->rowId);
    },
    'schema alter generated check reparse current source next126 preserves explicit index sql' => static function (TestRunner $t) use ($plan, $byName): void {
        $t->same('CREATE INDEX wp_options_autoload ON wp_options(autoload)', $byName($plan()['records'], 'wp_options_autoload')->sql);
    },
    'schema alter generated check reparse current source next126 preserves autoindex sql' => static function (TestRunner $t) use ($plan, $byName): void {
        $t->same(null, $byName($plan()['records'], 'sqlite_autoindex_wp_options_1')->sql);
    },
    'schema alter generated check reparse current source next126 table xinfo sample pragma' => static fn (TestRunner $t) => $t->same('table_xinfo', $plan()['pragma_samples']['table_xinfo:wp_options']['pragma']),
    'schema alter generated check reparse current source next126 table xinfo sample count' => static fn (TestRunner $t) => $t->same(5, count($plan()['pragma_samples']['table_xinfo:wp_options']['rows'])),
    'schema alter generated check reparse current source next126 table xinfo generated name' => static fn (TestRunner $t) => $t->same('option_name_lc', $plan()['pragma_samples']['table_xinfo:wp_options']['rows'][4]['name']),
    'schema alter generated check reparse current source next126 table xinfo generated hidden' => static fn (TestRunner $t) => $t->same(2, $plan()['pragma_samples']['table_xinfo:wp_options']['rows'][4]['hidden']),
    'schema alter generated check reparse current source next126 table info omits generated' => static function (TestRunner $t) use ($plan): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan()['records']);
        $t->same(4, count($catalog->execute('PRAGMA table_info(wp_options)')['rows']));
    },
    'schema alter generated check reparse current source next126 table xinfo includes generated' => static function (TestRunner $t) use ($plan): void {
        $catalog = new SQLitePragmaSchemaCatalog($plan()['records']);
        $t->same('option_name_lc', $catalog->execute('PRAGMA table_xinfo(wp_options)')['rows'][4]['name']);
    },
    'schema alter generated check reparse current source next126 current row key is case insensitive' => static function (TestRunner $t) use ($baseRecords): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords(),
            ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> "")'],
            126,
            'main',
            [],
            ['WP_OPTIONS' => [['option_name' => 'siteurl']]],
        );
        $t->same(1, $plan['operations'][0]['checked_rows']);
    },
];

$ordinaryCases = [
    'ordinary default check column' => [
        "ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT 'core' CHECK(option_source <> '')",
        'operations.0.column',
        'option_source',
    ],
    'ordinary default check generated flag false' => [
        "ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT 'core' CHECK(option_source <> '')",
        'operations.0.generated',
        false,
    ],
    'ordinary default check scans current rows' => [
        "ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT 'core' CHECK(option_source <> '')",
        'operations.0.checked_rows',
        3,
    ],
    'ordinary not null default scans current rows' => [
        "ALTER TABLE wp_options ADD COLUMN site_id INTEGER NOT NULL DEFAULT 1",
        'operations.0.checked_rows',
        3,
    ],
    'ordinary no check does not scan rows' => [
        'ALTER TABLE wp_options ADD COLUMN option_note TEXT',
        'operations.0.checked_rows',
        0,
    ],
    'generated length lower bound scans rows' => [
        'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len >= 5)',
        'operations.0.checked_rows',
        3,
    ],
    'generated concat route scans rows' => [
        'ALTER TABLE wp_options ADD COLUMN option_route TEXT AS (autoload || ":" || option_name) VIRTUAL CHECK(length(option_route) > 4)',
        'operations.0.checked_rows',
        3,
    ],
    'generated nullable unknown permits null check' => [
        "ALTER TABLE wp_options ADD COLUMN optional_copy TEXT AS (missing_source) VIRTUAL CHECK(optional_copy <> 'blocked')",
        'operations.0.checked_rows',
        3,
    ],
];

foreach ($ordinaryCases as $name => [$sql, $path, $expected]) {
    $tests['schema alter generated check reparse current source next126 ' . $name] = static function (TestRunner $t) use ($baseRecords, $currentRows, $valueAt, $sql, $path, $expected): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords(), [$sql], 126, 'main', [], ['wp_options' => $currentRows]);
        $t->same($expected, $valueAt($plan, $path));
    };
}

$rejectCases = [
    'generated lower rejects blank current row' => [
        "ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> '')",
        [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'ok', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => '', 'option_value' => 'bad', 'autoload' => 'yes'],
        ],
    ],
    'generated length rejects short current value' => [
        'ALTER TABLE wp_options ADD COLUMN option_value_len INTEGER AS (length(option_value)) VIRTUAL CHECK(option_value_len > 3)',
        [['option_id' => 1, 'option_name' => 'short', 'option_value' => 'abc', 'autoload' => 'no']],
    ],
    'ordinary default check rejects existing rows' => [
        "ALTER TABLE wp_options ADD COLUMN option_source TEXT DEFAULT '' CHECK(option_source <> '')",
        $currentRows,
    ],
    'generated not null rejects null expression' => [
        'ALTER TABLE wp_options ADD COLUMN copied_missing TEXT AS (missing_source) VIRTUAL NOT NULL',
        $currentRows,
    ],
];

foreach ($rejectCases as $name => [$sql, $rows]) {
    $tests['schema alter generated check reparse current source next126 rejects ' . $name] = static function (TestRunner $t) use ($baseRecords, $sql, $rows): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), [$sql], 126, 'main', [], ['wp_options' => $rows]));
    };
}

$tests['schema alter generated check reparse current source next126 rejects missing table'] = static function (TestRunner $t) use ($baseRecords): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE missing_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL']));
};

$tests['schema alter generated check reparse current source next126 rejects duplicate column'] = static function (TestRunner $t) use ($baseRecords, $currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE wp_options ADD COLUMN option_name TEXT'], 126, 'main', [], ['wp_options' => $currentRows]));
};

$tests['schema alter generated check reparse current source next126 rejects stored generated column'] = static function (TestRunner $t) use ($baseRecords, $currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ["ALTER TABLE wp_options ADD COLUMN stored_name TEXT AS (lower(option_name)) STORED CHECK(stored_name <> '')"], 126, 'main', [], ['wp_options' => $currentRows]));
};

$tests['schema alter generated check reparse current source next126 rejects primary key add'] = static function (TestRunner $t) use ($baseRecords): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE wp_options ADD COLUMN option_hash TEXT PRIMARY KEY']));
};

$tests['schema alter generated check reparse current source next126 rejects unique add'] = static function (TestRunner $t) use ($baseRecords): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE wp_options ADD COLUMN option_hash TEXT UNIQUE']));
};

$tests['schema alter generated check reparse current source next126 rejects not null without default'] = static function (TestRunner $t) use ($baseRecords, $currentRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE wp_options ADD COLUMN option_scope TEXT NOT NULL'], 126, 'main', [], ['wp_options' => $currentRows]));
};

$tests['schema alter generated check reparse current source next126 rejects current timestamp default'] = static function (TestRunner $t) use ($baseRecords): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE wp_options ADD COLUMN created_at TEXT DEFAULT CURRENT_TIMESTAMP']));
};

$tests['schema alter generated check reparse current source next126 accepts quoted table and column'] = static function (TestRunner $t) use ($baseRecords, $currentRows): void {
    $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE "wp_options" ADD COLUMN "cache key" TEXT DEFAULT "" CHECK("cache key" = "")'], 126, 'main', [], ['wp_options' => $currentRows]);
    $t->same('cache key', $plan['operations'][0]['column']);
};

$tests['schema alter generated check reparse current source next126 keeps empty table unscanned'] = static function (TestRunner $t) use ($baseRecords): void {
    $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> "")'], 126, 'main', [], ['wp_options' => []]);
    $t->same(0, $plan['operations'][0]['checked_rows']);
};

$tests['schema alter generated check reparse current source next126 mixed batch advances after create index and alter'] = static function (TestRunner $t) use ($baseRecords, $currentRows): void {
    $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['CREATE INDEX wp_options_name ON wp_options(option_name)', 'ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> "")'], 126, 'main', [], ['wp_options' => $currentRows]);
    $t->same(128, $plan['after_schema_cookie']);
};

$tests['schema alter generated check reparse current source next126 mixed batch operation order'] = static function (TestRunner $t) use ($baseRecords, $currentRows): void {
    $plan = SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['CREATE INDEX wp_options_name ON wp_options(option_name)', 'ALTER TABLE wp_options ADD COLUMN option_name_lc TEXT AS (lower(option_name)) VIRTUAL CHECK(option_name_lc <> "")'], 126, 'main', [], ['wp_options' => $currentRows]);
    $t->same(['create_index', 'alter_table_add_column'], [$plan['operations'][0]['kind'], $plan['operations'][1]['kind']]);
};

return $tests;
