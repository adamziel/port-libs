<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteInsertDefaultValuesSql;

$schema = <<<'SQL'
CREATE TABLE wp_option_defaults(
    option_id INTEGER PRIMARY KEY,
    option_name TEXT NOT NULL DEFAULT 'blogname',
    option_value TEXT DEFAULT (upper('example site')),
    autoload TEXT NOT NULL DEFAULT (coalesce(NULL, 'yes')),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    option_name_lc TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL,
    option_value_len INTEGER GENERATED ALWAYS AS (length(option_value)) STORED,
    option_cache_key TEXT AS (option_name || ':' || autoload) VIRTUAL,
    option_rank INTEGER GENERATED ALWAYS AS (option_id + 10) STORED,
    nullable_note TEXT DEFAULT NULL
)
SQL;

$tables = static fn (): array => [
    'wp_option_defaults' => [
        [
            'option_id' => 7,
            'option_name' => 'siteurl',
            'option_value' => 'https://example.test',
            'autoload' => 'yes',
            'created_at' => '2026-05-26 00:00:00',
            'option_name_lc' => 'siteurl',
            'option_value_len' => 20,
            'option_cache_key' => 'siteurl:yes',
            'option_rank' => 17,
            'nullable_note' => null,
        ],
    ],
];

$run = static fn (?string $sql = null): array => SQLiteInsertDefaultValuesSql::execute(
    $sql ?? 'INSERT INTO wp_option_defaults DEFAULT VALUES',
    $tables(),
    ['wp_option_defaults' => $schema],
    '2026-05-27 06:30:45',
);

$value = static function (array $result, string $path): mixed {
    $current = $result;
    foreach (explode('.', $path) as $part) {
        $current = is_numeric($part) ? $current[(int) $part] : $current[$part];
    }

    return $current;
};

$cases = [
    'target table parsed' => ['target', 'wp_option_defaults'],
    'default conflict action is abort' => ['conflict_action', 'abort'],
    'before row preserved' => ['before.0.option_name', 'siteurl'],
    'after appends one row' => ['after.1.option_name', 'blogname'],
    'changes count one inserted row' => ['changes', 1],
    'integer primary key uses next rowid' => ['inserted_row.option_id', 8],
    'text default literal applied' => ['inserted_row.option_name', 'blogname'],
    'parenthesized function default applied' => ['inserted_row.option_value', 'EXAMPLE SITE'],
    'coalesce default expression applied' => ['inserted_row.autoload', 'yes'],
    'current timestamp default is injectable' => ['inserted_row.created_at', '2026-05-27 06:30:45'],
    'virtual lower generated column applied' => ['inserted_row.option_name_lc', 'blogname'],
    'stored length generated column applied' => ['inserted_row.option_value_len', 12],
    'shorthand generated concat column applied' => ['inserted_row.option_cache_key', 'blogname:yes'],
    'generated arithmetic column can read rowid alias' => ['inserted_row.option_rank', 18],
    'explicit null default stays null' => ['inserted_row.nullable_note', null],
    'after keeps generated columns' => ['after.1.option_value_len', 12],
    'column metadata includes rowid alias' => ['columns.0.rowid_alias', true],
    'column metadata keeps stored flag' => ['columns.6.stored', true],
    'column metadata keeps virtual shorthand generated flag' => ['columns.7.generated', 'option_name || \':\' || autoload'],
    'not-null metadata survives default expression' => ['columns.3.not_null', true],
];

$tests = [];

foreach ($cases as $name => [$path, $expected]) {
    $tests['insert default values generated default ' . $name] = static function (TestRunner $t) use ($run, $value, $path, $expected): void {
        $t->same($expected, $value($run(), $path));
    };
}

$tests['insert default values generated default honors OR REPLACE spelling'] = static function (TestRunner $t) use ($run): void {
    $t->same('replace', $run('INSERT OR REPLACE INTO wp_option_defaults DEFAULT VALUES')['conflict_action']);
};

$tests['insert default values generated default honors OR IGNORE spelling'] = static function (TestRunner $t) use ($run): void {
    $t->same('ignore', $run('INSERT OR IGNORE INTO wp_option_defaults DEFAULT VALUES')['conflict_action']);
};

$tests['insert default values generated default date keyword truncates timestamp'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE wp_defaults(id INTEGER PRIMARY KEY, day TEXT DEFAULT CURRENT_DATE)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => []], ['wp_defaults' => $schema], '2026-05-27 06:30:45');
    $t->same('2026-05-27', $result['inserted_row']['day']);
};

$tests['insert default values generated default time keyword truncates timestamp'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE wp_defaults(id INTEGER PRIMARY KEY, clock TEXT DEFAULT CURRENT_TIME)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => []], ['wp_defaults' => $schema], '2026-05-27 06:30:45');
    $t->same('06:30:45', $result['inserted_row']['clock']);
};

$tests['insert default values generated default numeric defaults preserve signs'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE wp_defaults(id INTEGER PRIMARY KEY, retries INTEGER DEFAULT -1, weight REAL DEFAULT +2.5, total INTEGER AS (retries + 3) STORED)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => []], ['wp_defaults' => $schema]);
    $t->same(-1, $result['inserted_row']['retries']);
    $t->same(2.5, $result['inserted_row']['weight']);
    $t->same(2, $result['inserted_row']['total']);
};

$tests['insert default values generated default quoted defaults preserve embedded quotes'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE wp_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT 'can''t', copy TEXT AS (label || '!') VIRTUAL)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => []], ['wp_defaults' => $schema]);
    $t->same("can't", $result['inserted_row']['label']);
    $t->same("can't!", $result['inserted_row']['copy']);
};

$tests['insert default values generated default no rows still starts rowid at one'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE wp_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT 'first')";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => []], ['wp_defaults' => $schema]);
    $t->same(1, $result['inserted_row']['id']);
};

$tests['insert default values generated default sparse rowids use largest existing integer'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE wp_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT 'next')";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => [['id' => 3], ['id' => 21], ['id' => -2]]], ['wp_defaults' => $schema]);
    $t->same(22, $result['inserted_row']['id']);
};

$tests['insert default values generated default nullable omitted column becomes null'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE wp_defaults(id INTEGER PRIMARY KEY, label TEXT)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => []], ['wp_defaults' => $schema]);
    $t->same(null, $result['inserted_row']['label']);
};

$tests['insert default values generated default rejects missing not null default'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE wp_defaults(id INTEGER PRIMARY KEY, label TEXT NOT NULL)";
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => []], ['wp_defaults' => $schema]));
};

$tests['insert default values generated default rejects unsupported values source'] = static function (TestRunner $t) use ($tables, $schema): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_option_defaults VALUES (1)', $tables(), ['wp_option_defaults' => $schema]));
};

$tests['insert default values generated default rejects missing target rows'] = static function (TestRunner $t) use ($schema): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_option_defaults DEFAULT VALUES', [], ['wp_option_defaults' => $schema]));
};

$tests['insert default values generated default rejects missing schema'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_option_defaults DEFAULT VALUES', $tables(), []));
};

$tests['insert default values generated default rejects unsupported generated expression'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE wp_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT 'x', bad TEXT AS (substr(label, 1, 1)) VIRTUAL)";
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => []], ['wp_defaults' => $schema]));
};

$tests['insert default values generated default ignores table constraints'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE wp_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT 'home', CHECK(label <> ''), UNIQUE(label))";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => []], ['wp_defaults' => $schema]);
    $t->same('home', $result['inserted_row']['label']);
};

$tests['insert default values generated default double quoted text default works'] = static function (TestRunner $t): void {
    $schema = 'CREATE TABLE wp_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT "Home Page", folded TEXT AS (upper(label)) STORED)';
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => []], ['wp_defaults' => $schema]);
    $t->same('Home Page', $result['inserted_row']['label']);
    $t->same('HOME PAGE', $result['inserted_row']['folded']);
};

$tests['insert default values generated default nested parenthesized literal works'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE wp_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT ((('nested'))), copy TEXT AS ((label || ':copy')) VIRTUAL)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => []], ['wp_defaults' => $schema]);
    $t->same('nested', $result['inserted_row']['label']);
    $t->same('nested:copy', $result['inserted_row']['copy']);
};

$tests['insert default values generated default generated column can read null default'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE wp_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT NULL, label_len INTEGER AS (length(label)) VIRTUAL)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => []], ['wp_defaults' => $schema]);
    $t->same(null, $result['inserted_row']['label']);
    $t->same(0, $result['inserted_row']['label_len']);
};

$tests['insert default values generated default keeps declared type metadata'] = static function (TestRunner $t) use ($run): void {
    $columns = $run()['columns'];
    $t->same('INTEGER', $columns[0]['type']);
    $t->same('TEXT', $columns[1]['type']);
    $t->same('TEXT', $columns[5]['type']);
};

$tests['insert default values generated default records generated stored metadata separately'] = static function (TestRunner $t) use ($run): void {
    $columns = $run()['columns'];
    $t->same(false, $columns[5]['stored']);
    $t->same(true, $columns[6]['stored']);
    $t->same(true, $columns[8]['stored']);
};

$tests['insert default values generated default rejects malformed create table'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertDefaultValuesSql::execute('INSERT INTO wp_defaults DEFAULT VALUES', ['wp_defaults' => []], ['wp_defaults' => 'CREATE TABLE wp_defaults id INTEGER']));
};

return $tests;
