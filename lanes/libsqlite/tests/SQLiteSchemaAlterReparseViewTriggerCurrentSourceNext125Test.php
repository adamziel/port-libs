<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$records = static fn (): array => [
    $record(
        'table',
        'wp_options',
        'wp_options',
        2,
        "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT 'yes')",
        1,
    ),
    $record(
        'index',
        'wp_options_autoload',
        'wp_options',
        3,
        "CREATE INDEX wp_options_autoload ON wp_options(autoload, option_name) WHERE autoload = 'yes'",
        2,
    ),
    $record(
        'view',
        'wp_autoloaded_options',
        'wp_autoloaded_options',
        0,
        "CREATE VIEW wp_autoloaded_options AS SELECT option_id, option_name FROM wp_options WHERE autoload = 'yes' AND option_value <> 'wp_options'",
        3,
    ),
    $record(
        'view',
        'wp_option_pairs',
        'wp_option_pairs',
        0,
        'CREATE VIEW wp_option_pairs AS SELECT a.option_name, b.option_value FROM wp_options AS a JOIN wp_options AS b ON a.option_id = b.option_id',
        4,
    ),
    $record(
        'view',
        'wp_optionmeta_labels',
        'wp_optionmeta_labels',
        0,
        'CREATE VIEW wp_optionmeta_labels AS SELECT label FROM wp_optionmeta',
        5,
    ),
    $record(
        'trigger',
        'wp_options_ai',
        'wp_options',
        0,
        "CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN INSERT INTO wp_option_audit(label) VALUES('wp_options'); SELECT count(*) FROM wp_options; END",
        6,
    ),
    $record(
        'trigger',
        'wp_options_au',
        'wp_options',
        0,
        "CREATE TRIGGER wp_options_au AFTER UPDATE ON wp_options WHEN EXISTS(SELECT 1 FROM wp_options WHERE option_id = new.option_id) BEGIN UPDATE wp_options SET autoload = new.autoload WHERE option_id = old.option_id; END",
        7,
    ),
    $record(
        'trigger',
        'wp_optionmeta_ai',
        'wp_optionmeta',
        0,
        'CREATE TRIGGER wp_optionmeta_ai AFTER INSERT ON wp_optionmeta BEGIN SELECT 1; END',
        8,
    ),
];

$plan = static fn (): array => SQLiteSchemaDdlReparsePlan::apply(
    $records(),
    ['ALTER TABLE wp_options RENAME TO wp_site_options'],
    125,
    'main',
    [
        ['id' => 'select-autoloaded-options', 'schema_cookie' => 125, 'sql' => 'SELECT option_name FROM wp_options WHERE autoload = ?'],
        ['id' => 'select-optionmeta-labels', 'schema_cookie' => 125, 'sql' => 'SELECT label FROM wp_optionmeta'],
    ],
);

$byName = static function (array $plan, string $name): SQLiteSchemaRecord {
    foreach ($plan['records'] as $record) {
        if ($record instanceof SQLiteSchemaRecord && $record->name === $name) {
            return $record;
        }
    }

    throw new RuntimeException("Missing schema record {$name}");
};

$valueAt = static function (array $value, string $path): mixed {
    $cursor = $value;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }

        return null;
    }

    return $cursor;
};

$hasRecordNamed = static function (array $schemaRecords, string $name): bool {
    foreach ($schemaRecords as $record) {
        if ($record instanceof SQLiteSchemaRecord && $record->name === $name) {
            return true;
        }
    }

    return false;
};

$checks = [
    'operation kind' => ['operations.0.kind', 'alter_table_rename'],
    'old table name' => ['operations.0.old_name', 'wp_options'],
    'new table name' => ['operations.0.new_name', 'wp_site_options'],
    'dependent reparse count' => ['operations.0.dependent_reparse_count', 5],
    'schema cookie before' => ['before_schema_cookie', 125],
    'schema cookie after' => ['after_schema_cookie', 126],
    'schema changed' => ['schema_changed', true],
    'table count preserved' => ['table_count', 1],
    'index count preserved' => ['index_count', 1],
    'invalidates prepared statements' => ['invalidated_prepared', ['select-autoloaded-options', 'select-optionmeta-labels']],
    'dependencies include schema reparse' => ['dependencies.0', 'schema-sql-reparse'],
    'dependencies include cookie' => ['dependencies.1', 'sqlite-schema-cookie'],
    'dependencies include catalog' => ['dependencies.2', 'pragma-schema-catalog'],
    'rewrites table record' => ['operations.0.rewritten_records.0', 'table:wp_options'],
    'rewrites index record' => ['operations.0.rewritten_records.1', 'index:wp_options_autoload'],
    'rewrites first view record' => ['operations.0.rewritten_records.2', 'view:wp_autoloaded_options'],
    'rewrites join view record' => ['operations.0.rewritten_records.3', 'view:wp_option_pairs'],
    'rewrites insert trigger record' => ['operations.0.rewritten_records.4', 'trigger:wp_options_ai'],
    'rewrites update trigger record' => ['operations.0.rewritten_records.5', 'trigger:wp_options_au'],
];

$tests = [];

foreach ($checks as $name => [$path, $expected]) {
    $tests['schema alter reparse view trigger current source next125 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$recordChecks = [
    'table record name changed' => static fn (array $p): mixed => $byName($p, 'wp_site_options')->name,
    'table record tbl name changed' => static fn (array $p): mixed => $byName($p, 'wp_site_options')->tableName,
    'table record sql changed' => static fn (array $p): mixed => $byName($p, 'wp_site_options')->sql,
    'index tbl name changed' => static fn (array $p): mixed => $byName($p, 'wp_options_autoload')->tableName,
    'index sql changed' => static fn (array $p): mixed => $byName($p, 'wp_options_autoload')->sql,
    'view tbl name remains view name' => static fn (array $p): mixed => $byName($p, 'wp_autoloaded_options')->tableName,
    'view sql from table changed' => static fn (array $p): mixed => str_contains((string) $byName($p, 'wp_autoloaded_options')->sql, 'FROM wp_site_options'),
    'view string literal preserved' => static fn (array $p): mixed => str_contains((string) $byName($p, 'wp_autoloaded_options')->sql, "'wp_options'"),
    'join view first source changed' => static fn (array $p): mixed => str_contains((string) $byName($p, 'wp_option_pairs')->sql, 'FROM wp_site_options AS a'),
    'join view second source changed' => static fn (array $p): mixed => str_contains((string) $byName($p, 'wp_option_pairs')->sql, 'JOIN wp_site_options AS b'),
    'unrelated view left unchanged' => static fn (array $p): mixed => $byName($p, 'wp_optionmeta_labels')->sql,
    'insert trigger target changed' => static fn (array $p): mixed => $byName($p, 'wp_options_ai')->tableName,
    'insert trigger on clause changed' => static fn (array $p): mixed => str_contains((string) $byName($p, 'wp_options_ai')->sql, 'ON wp_site_options'),
    'insert trigger body select changed' => static fn (array $p): mixed => str_contains((string) $byName($p, 'wp_options_ai')->sql, 'FROM wp_site_options'),
    'insert trigger literal preserved' => static fn (array $p): mixed => str_contains((string) $byName($p, 'wp_options_ai')->sql, "VALUES('wp_options')"),
    'update trigger target changed' => static fn (array $p): mixed => $byName($p, 'wp_options_au')->tableName,
    'update trigger when subquery changed' => static fn (array $p): mixed => str_contains((string) $byName($p, 'wp_options_au')->sql, 'FROM wp_site_options WHERE'),
    'update trigger body update changed' => static fn (array $p): mixed => str_contains((string) $byName($p, 'wp_options_au')->sql, 'UPDATE wp_site_options SET'),
    'unrelated trigger left unchanged' => static fn (array $p): mixed => $byName($p, 'wp_optionmeta_ai')->sql,
    'old table record removed' => static fn (array $p): mixed => $hasRecordNamed($p['records'], 'wp_options'),
    'pragma old table is gone' => static fn (array $p): mixed => (new SQLitePragmaSchemaCatalog($p['records']))->execute('PRAGMA table_info(wp_options)')['rows'],
    'pragma new table has columns' => static fn (array $p): mixed => count((new SQLitePragmaSchemaCatalog($p['records']))->execute('PRAGMA table_info(wp_site_options)')['rows']),
    'pragma new table option name preserved' => static fn (array $p): mixed => (new SQLitePragmaSchemaCatalog($p['records']))->execute('PRAGMA table_info(wp_site_options)')['rows'][1]['name'] ?? null,
    'pragma index list follows new table' => static fn (array $p): mixed => (new SQLitePragmaSchemaCatalog($p['records']))->execute('PRAGMA index_list(wp_site_options)')['rows'][0]['name'] ?? null,
    'pragma sample key targets new table' => static fn (array $p): mixed => array_key_exists('table_xinfo:wp_site_options', $p['pragma_samples']),
    'unrelated schema record count stable' => static fn (array $p): mixed => count($p['records']),
    'unrelated view not reparsed' => static fn (array $p): mixed => in_array('view:wp_optionmeta_labels', $p['operations'][0]['rewritten_records'], true),
    'unrelated trigger not reparsed' => static fn (array $p): mixed => in_array('trigger:wp_optionmeta_ai', $p['operations'][0]['rewritten_records'], true),
];

$expectedRecordChecks = [
    'table record name changed' => 'wp_site_options',
    'table record tbl name changed' => 'wp_site_options',
    'table record sql changed' => "CREATE TABLE wp_site_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT 'yes')",
    'index tbl name changed' => 'wp_site_options',
    'index sql changed' => "CREATE INDEX wp_options_autoload ON wp_site_options(autoload, option_name) WHERE autoload = 'yes'",
    'view tbl name remains view name' => 'wp_autoloaded_options',
    'view sql from table changed' => true,
    'view string literal preserved' => true,
    'join view first source changed' => true,
    'join view second source changed' => true,
    'unrelated view left unchanged' => 'CREATE VIEW wp_optionmeta_labels AS SELECT label FROM wp_optionmeta',
    'insert trigger target changed' => 'wp_site_options',
    'insert trigger on clause changed' => true,
    'insert trigger body select changed' => true,
    'insert trigger literal preserved' => true,
    'update trigger target changed' => 'wp_site_options',
    'update trigger when subquery changed' => true,
    'update trigger body update changed' => true,
    'unrelated trigger left unchanged' => 'CREATE TRIGGER wp_optionmeta_ai AFTER INSERT ON wp_optionmeta BEGIN SELECT 1; END',
    'old table record removed' => false,
    'pragma old table is gone' => [],
    'pragma new table has columns' => 4,
    'pragma new table option name preserved' => 'option_name',
    'pragma index list follows new table' => 'wp_options_autoload',
    'pragma sample key targets new table' => true,
    'unrelated schema record count stable' => 8,
    'unrelated view not reparsed' => false,
    'unrelated trigger not reparsed' => false,
];

foreach ($recordChecks as $name => $actual) {
    $tests['schema alter reparse view trigger current source next125 ' . $name] = static function (TestRunner $t) use ($plan, $actual, $expectedRecordChecks, $name): void {
        $t->same($expectedRecordChecks[$name], $actual($plan()));
    };
}

$tests['schema alter reparse view trigger current source next125 rejects rename to existing table'] = static function (TestRunner $t) use ($records): void {
    $nextRecords = $records();
    $nextRecords[] = new SQLiteSchemaRecord('table', 'wp_site_options', 'wp_site_options', 9, 'CREATE TABLE wp_site_options(id INTEGER)', 9);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteSchemaDdlReparsePlan::apply($nextRecords, ['ALTER TABLE wp_options RENAME TO wp_site_options']));
};

$tests['schema alter reparse view trigger current source next125 rejects missing table'] = static function (TestRunner $t) use ($records): void {
    $nextRecords = array_values(array_filter($records(), static fn (SQLiteSchemaRecord $r): bool => $r->name !== 'wp_options'));
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteSchemaDdlReparsePlan::apply($nextRecords, ['ALTER TABLE wp_options RENAME TO wp_site_options']));
};

return $tests;
