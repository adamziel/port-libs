<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteInsertDefaultValuesSql;

$schema = <<<'SQL'
CREATE TABLE app_setting_defaults(
    setting_id INTEGER PRIMARY KEY,
    key_name TEXT NOT NULL DEFAULT 'display_name',
    key_value TEXT DEFAULT (upper('example site')),
    load_policy TEXT NOT NULL DEFAULT (coalesce(NULL, 'yes')),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    key_name_lc TEXT GENERATED ALWAYS AS (lower(key_name)) VIRTUAL,
    key_value_len INTEGER GENERATED ALWAYS AS (length(key_value)) STORED,
    setting_cache_key TEXT AS (key_name || ':' || load_policy) VIRTUAL,
    setting_rank INTEGER GENERATED ALWAYS AS (setting_id + 10) STORED,
    nullable_note TEXT DEFAULT NULL
)
SQL;

$tables = static fn (): array => [
    'app_setting_defaults' => [
        [
            'setting_id' => 7,
            'key_name' => 'service_url',
            'key_value' => 'https://example.test',
            'load_policy' => 'yes',
            'created_at' => '2026-05-26 00:00:00',
            'key_name_lc' => 'service_url',
            'key_value_len' => 20,
            'setting_cache_key' => 'service_url:yes',
            'setting_rank' => 17,
            'nullable_note' => null,
        ],
    ],
];

$run = static fn (?string $sql = null): array => SQLiteInsertDefaultValuesSql::execute(
    $sql ?? 'INSERT INTO app_setting_defaults DEFAULT VALUES',
    $tables(),
    ['app_setting_defaults' => $schema],
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
    'target table parsed' => ['target', 'app_setting_defaults'],
    'default conflict action is abort' => ['conflict_action', 'abort'],
    'before row preserved' => ['before.0.key_name', 'service_url'],
    'after appends one row' => ['after.1.key_name', 'display_name'],
    'changes count one inserted row' => ['changes', 1],
    'integer primary key uses next rowid' => ['inserted_row.setting_id', 8],
    'text default literal applied' => ['inserted_row.key_name', 'display_name'],
    'parenthesized function default applied' => ['inserted_row.key_value', 'EXAMPLE SITE'],
    'coalesce default expression applied' => ['inserted_row.load_policy', 'yes'],
    'current timestamp default is injectable' => ['inserted_row.created_at', '2026-05-27 06:30:45'],
    'virtual lower generated column applied' => ['inserted_row.key_name_lc', 'display_name'],
    'stored length generated column applied' => ['inserted_row.key_value_len', 12],
    'shorthand generated concat column applied' => ['inserted_row.setting_cache_key', 'display_name:yes'],
    'generated arithmetic column can read rowid alias' => ['inserted_row.setting_rank', 18],
    'explicit null default stays null' => ['inserted_row.nullable_note', null],
    'after keeps generated columns' => ['after.1.key_value_len', 12],
    'column metadata includes rowid alias' => ['columns.0.rowid_alias', true],
    'column metadata keeps stored flag' => ['columns.6.stored', true],
    'column metadata keeps virtual shorthand generated flag' => ['columns.7.generated', 'key_name || \':\' || load_policy'],
    'not-null metadata survives default expression' => ['columns.3.not_null', true],
];

$tests = [];

foreach ($cases as $name => [$path, $expected]) {
    $tests['insert default values generated default ' . $name] = static function (TestRunner $t) use ($run, $value, $path, $expected): void {
        $t->same($expected, $value($run(), $path));
    };
}

$tests['insert default values generated default honors OR REPLACE spelling'] = static function (TestRunner $t) use ($run): void {
    $t->same('replace', $run('INSERT OR REPLACE INTO app_setting_defaults DEFAULT VALUES')['conflict_action']);
};

$tests['insert default values generated default honors OR IGNORE spelling'] = static function (TestRunner $t) use ($run): void {
    $t->same('ignore', $run('INSERT OR IGNORE INTO app_setting_defaults DEFAULT VALUES')['conflict_action']);
};

$tests['insert default values generated default date keyword truncates timestamp'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE app_defaults(id INTEGER PRIMARY KEY, day TEXT DEFAULT CURRENT_DATE)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => []], ['app_defaults' => $schema], '2026-05-27 06:30:45');
    $t->same('2026-05-27', $result['inserted_row']['day']);
};

$tests['insert default values generated default time keyword truncates timestamp'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE app_defaults(id INTEGER PRIMARY KEY, clock TEXT DEFAULT CURRENT_TIME)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => []], ['app_defaults' => $schema], '2026-05-27 06:30:45');
    $t->same('06:30:45', $result['inserted_row']['clock']);
};

$tests['insert default values generated default numeric defaults preserve signs'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE app_defaults(id INTEGER PRIMARY KEY, retries INTEGER DEFAULT -1, weight REAL DEFAULT +2.5, total INTEGER AS (retries + 3) STORED)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => []], ['app_defaults' => $schema]);
    $t->same(-1, $result['inserted_row']['retries']);
    $t->same(2.5, $result['inserted_row']['weight']);
    $t->same(2, $result['inserted_row']['total']);
};

$tests['insert default values generated default quoted defaults preserve embedded quotes'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE app_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT 'can''t', copy TEXT AS (label || '!') VIRTUAL)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => []], ['app_defaults' => $schema]);
    $t->same("can't", $result['inserted_row']['label']);
    $t->same("can't!", $result['inserted_row']['copy']);
};

$tests['insert default values generated default no rows still starts rowid at one'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE app_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT 'first')";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => []], ['app_defaults' => $schema]);
    $t->same(1, $result['inserted_row']['id']);
};

$tests['insert default values generated default sparse rowids use largest existing integer'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE app_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT 'next')";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => [['id' => 3], ['id' => 21], ['id' => -2]]], ['app_defaults' => $schema]);
    $t->same(22, $result['inserted_row']['id']);
};

$tests['insert default values generated default nullable omitted column becomes null'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE app_defaults(id INTEGER PRIMARY KEY, label TEXT)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => []], ['app_defaults' => $schema]);
    $t->same(null, $result['inserted_row']['label']);
};

$tests['insert default values generated default rejects missing not null default'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE app_defaults(id INTEGER PRIMARY KEY, label TEXT NOT NULL)";
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => []], ['app_defaults' => $schema]));
};

$tests['insert default values generated default rejects unsupported values source'] = static function (TestRunner $t) use ($tables, $schema): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_setting_defaults VALUES (1)', $tables(), ['app_setting_defaults' => $schema]));
};

$tests['insert default values generated default rejects missing target rows'] = static function (TestRunner $t) use ($schema): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_setting_defaults DEFAULT VALUES', [], ['app_setting_defaults' => $schema]));
};

$tests['insert default values generated default rejects missing schema'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_setting_defaults DEFAULT VALUES', $tables(), []));
};

$tests['insert default values generated default rejects unsupported generated expression'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE app_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT 'x', bad TEXT AS (substr(label, 1, 1)) VIRTUAL)";
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => []], ['app_defaults' => $schema]));
};

$tests['insert default values generated default ignores table constraints'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE app_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT 'home', CHECK(label <> ''), UNIQUE(label))";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => []], ['app_defaults' => $schema]);
    $t->same('home', $result['inserted_row']['label']);
};

$tests['insert default values generated default double quoted text default works'] = static function (TestRunner $t): void {
    $schema = 'CREATE TABLE app_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT "Home Page", folded TEXT AS (upper(label)) STORED)';
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => []], ['app_defaults' => $schema]);
    $t->same('Home Page', $result['inserted_row']['label']);
    $t->same('HOME PAGE', $result['inserted_row']['folded']);
};

$tests['insert default values generated default nested parenthesized literal works'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE app_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT ((('nested'))), copy TEXT AS ((label || ':copy')) VIRTUAL)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => []], ['app_defaults' => $schema]);
    $t->same('nested', $result['inserted_row']['label']);
    $t->same('nested:copy', $result['inserted_row']['copy']);
};

$tests['insert default values generated default generated column can read null default'] = static function (TestRunner $t): void {
    $schema = "CREATE TABLE app_defaults(id INTEGER PRIMARY KEY, label TEXT DEFAULT NULL, label_len INTEGER AS (length(label)) VIRTUAL)";
    $result = SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => []], ['app_defaults' => $schema]);
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
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteInsertDefaultValuesSql::execute('INSERT INTO app_defaults DEFAULT VALUES', ['app_defaults' => []], ['app_defaults' => 'CREATE TABLE app_defaults id INTEGER']));
};

return $tests;
