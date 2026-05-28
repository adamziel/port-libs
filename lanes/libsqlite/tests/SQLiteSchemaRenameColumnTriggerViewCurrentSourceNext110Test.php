<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$baseRecords = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT 'yes', CHECK(length(option_name) > 0))", 1),
    $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null, 2),
    $record('index', 'wp_options_name_active', 'wp_options', 4, "CREATE INDEX wp_options_name_active ON wp_options(option_name COLLATE nocase, option_id) WHERE option_name IS NOT NULL AND autoload = 'yes'", 3),
    $record('view', 'active_options', 'active_options', 0, "CREATE VIEW active_options AS SELECT option_id, option_name AS option_name FROM wp_options WHERE option_name LIKE 'site%' ORDER BY lower(option_name)", 4),
    $record('trigger', 'wp_options_au', 'wp_options', 0, "CREATE TRIGGER wp_options_au AFTER UPDATE OF option_name ON wp_options WHEN old.option_name <> new.option_name BEGIN INSERT INTO audit(name, old_name) VALUES(new.option_name, old.option_name); UPDATE wp_options SET option_value = new.option_value WHERE option_name = new.option_name; END", 5),
    $record('view', 'joined_options', 'joined_options', 0, "CREATE VIEW joined_options AS SELECT o.option_name FROM wp_options AS o LEFT JOIN wp_postmeta m ON m.meta_key = o.option_name WHERE EXISTS(SELECT 1 FROM wp_options i WHERE i.option_name = o.option_name)", 6),
    $record('trigger', 'active_options_io', 'active_options', 0, "CREATE TRIGGER active_options_io INSTEAD OF UPDATE OF option_name ON active_options BEGIN UPDATE wp_options AS option_name SET option_name = new.option_name WHERE option_name.option_name = old.option_name; END", 7),
    $record('table', 'wp_postmeta', 'wp_postmeta', 8, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, option_name TEXT, meta_key TEXT)', 8),
    $record('view', 'postmeta_names', 'postmeta_names', 0, 'CREATE VIEW postmeta_names AS SELECT option_name FROM wp_postmeta', 9),
];

$apply = static fn (array $records = null): array => SQLiteSchemaDdlReparsePlan::apply(
    $records ?? $baseRecords(),
    ['ALTER TABLE wp_options RENAME COLUMN option_name TO option_key'],
    110,
    'main',
    [
        ['id' => 'select-active-options-current', 'schema_cookie' => 110, 'sql' => 'SELECT option_name FROM active_options'],
        ['id' => 'stale-trigger-source-before-rename', 'schema_cookie' => 109, 'sql' => 'UPDATE wp_options SET option_name = ?'],
    ],
);

$byName = static function (array $records, string $name): SQLiteSchemaRecord {
    foreach ($records as $record) {
        if ($record instanceof SQLiteSchemaRecord && $record->name === $name) {
            return $record;
        }
    }

    throw new RuntimeException("Missing record {$name}");
};

$tests = [
    'schema rename column trigger view current source next110 reports operation' => static function (TestRunner $t) use ($apply): void {
        $plan = $apply();
        $t->same('ok', $plan['status']);
        $t->same(110, $plan['before_schema_cookie']);
        $t->same(111, $plan['after_schema_cookie']);
        $t->same(true, $plan['schema_changed']);
        $t->same('alter_table_rename_column', $plan['operations'][0]['kind']);
        $t->same('wp_options', $plan['operations'][0]['table']);
        $t->same('option_name', $plan['operations'][0]['old_name']);
        $t->same('option_key', $plan['operations'][0]['new_name']);
    },
    'schema rename column trigger view current source next110 rewrites table create sql' => static function (TestRunner $t) use ($apply, $byName): void {
        $table = $byName($apply()['records'], 'wp_options');
        $t->contains('option_key TEXT NOT NULL', $table->sql);
        $t->contains('CHECK(length(option_key) > 0)', $table->sql);
        $t->same(false, str_contains((string) $table->sql, 'option_name TEXT NOT NULL'));
    },
    'schema rename column trigger view current source next110 keeps table identity stable' => static function (TestRunner $t) use ($apply, $byName): void {
        $table = $byName($apply()['records'], 'wp_options');
        $t->same('table', $table->type);
        $t->same('wp_options', $table->name);
        $t->same('wp_options', $table->tableName);
        $t->same(2, $table->rootPage);
        $t->same(1, $table->rowId);
    },
    'schema rename column trigger view current source next110 preserves autoindex record' => static function (TestRunner $t) use ($apply, $byName): void {
        $autoindex = $byName($apply()['records'], 'sqlite_autoindex_wp_options_1');
        $t->same(null, $autoindex->sql);
        $t->same('wp_options', $autoindex->tableName);
    },
    'schema rename column trigger view current source next110 rewrites explicit index columns' => static function (TestRunner $t) use ($apply, $byName): void {
        $index = $byName($apply()['records'], 'wp_options_name_active');
        $t->same("CREATE INDEX wp_options_name_active ON wp_options(option_key COLLATE nocase, option_id) WHERE option_key IS NOT NULL AND autoload = 'yes'", $index->sql);
    },
    'schema rename column trigger view current source next110 rewrites view projection and alias source' => static function (TestRunner $t) use ($apply, $byName): void {
        $view = $byName($apply()['records'], 'active_options');
        $t->contains('SELECT option_id, option_key AS option_name', $view->sql);
        $t->contains("WHERE option_key LIKE 'site%'", $view->sql);
        $t->contains('ORDER BY lower(option_key)', $view->sql);
    },
    'schema rename column trigger view current source next110 preserves explicit view output alias' => static function (TestRunner $t) use ($apply, $byName): void {
        $view = $byName($apply()['records'], 'active_options');
        $t->contains('AS option_name', $view->sql);
        $t->same(false, str_contains((string) $view->sql, 'AS option_key'));
    },
    'schema rename column trigger view current source next110 rewrites trigger update-of list' => static function (TestRunner $t) use ($apply, $byName): void {
        $trigger = $byName($apply()['records'], 'wp_options_au');
        $t->contains('AFTER UPDATE OF option_key ON wp_options', $trigger->sql);
    },
    'schema rename column trigger view current source next110 rewrites old and new trigger references' => static function (TestRunner $t) use ($apply, $byName): void {
        $trigger = $byName($apply()['records'], 'wp_options_au');
        $t->contains('old.option_key <> new.option_key', $trigger->sql);
        $t->contains('VALUES(new.option_key, old.option_key)', $trigger->sql);
    },
    'schema rename column trigger view current source next110 rewrites trigger body update assignment' => static function (TestRunner $t) use ($apply, $byName): void {
        $trigger = $byName($apply()['records'], 'wp_options_au');
        $t->contains('WHERE option_key = new.option_key', $trigger->sql);
        $t->same(false, str_contains((string) $trigger->sql, 'WHERE option_name = new.option_name'));
    },
    'schema rename column trigger view current source next110 rewrites joined view aliases' => static function (TestRunner $t) use ($apply, $byName): void {
        $view = $byName($apply()['records'], 'joined_options');
        $t->contains('SELECT o.option_key FROM wp_options AS o', $view->sql);
        $t->contains('m.meta_key = o.option_key', $view->sql);
    },
    'schema rename column trigger view current source next110 rewrites correlated subquery source' => static function (TestRunner $t) use ($apply, $byName): void {
        $view = $byName($apply()['records'], 'joined_options');
        $t->contains('SELECT 1 FROM wp_options i WHERE i.option_key = o.option_key', $view->sql);
    },
    'schema rename column trigger view current source next110 preserves table alias named as old column' => static function (TestRunner $t) use ($apply, $byName): void {
        $trigger = $byName($apply()['records'], 'active_options_io');
        $t->contains('UPDATE wp_options AS option_name SET option_key = new.option_key', $trigger->sql);
        $t->contains('WHERE option_name.option_key = old.option_key', $trigger->sql);
    },
    'schema rename column trigger view current source next110 rewrites instead-of trigger update list' => static function (TestRunner $t) use ($apply, $byName): void {
        $trigger = $byName($apply()['records'], 'active_options_io');
        $t->contains('INSTEAD OF UPDATE OF option_key ON active_options', $trigger->sql);
    },
    'schema rename column trigger view current source next110 leaves unrelated table column current source unchanged' => static function (TestRunner $t) use ($apply, $byName): void {
        $table = $byName($apply()['records'], 'wp_postmeta');
        $t->same('CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, option_name TEXT, meta_key TEXT)', $table->sql);
    },
    'schema rename column trigger view current source next110 leaves unrelated view current source unchanged' => static function (TestRunner $t) use ($apply, $byName): void {
        $view = $byName($apply()['records'], 'postmeta_names');
        $t->same('CREATE VIEW postmeta_names AS SELECT option_name FROM wp_postmeta', $view->sql);
    },
    'schema rename column trigger view current source next110 lists rewritten records only' => static function (TestRunner $t) use ($apply): void {
        $t->same(['table:wp_options', 'index:wp_options_name_active', 'view:active_options', 'trigger:wp_options_au', 'view:joined_options', 'trigger:active_options_io'], $apply()['operations'][0]['rewritten_records']);
    },
    'schema rename column trigger view current source next110 invalidates current and stale statements' => static function (TestRunner $t) use ($apply): void {
        $t->same(['select-active-options-current', 'stale-trigger-source-before-rename'], $apply()['invalidated_prepared']);
    },
    'schema rename column trigger view current source next110 exposes current table pragma sample' => static function (TestRunner $t) use ($apply): void {
        $sample = $apply()['pragma_samples']['table_xinfo:wp_options'];
        $t->same('table_xinfo', $sample['pragma']);
        $t->same('option_key', $sample['rows'][1]['name']);
        $t->same(1, $sample['rows'][1]['notnull']);
    },
    'schema rename column trigger view current source next110 catalog resolves renamed column' => static function (TestRunner $t) use ($apply): void {
        $catalog = new SQLitePragmaSchemaCatalog($apply()['records']);
        $rows = $catalog->execute('PRAGMA table_info(wp_options)')['rows'];
        $t->same('option_key', $rows[1]['name']);
        $t->same('option_value', $rows[2]['name']);
    },
    'schema rename column trigger view current source next110 catalog index sql is current source' => static function (TestRunner $t) use ($apply): void {
        $catalog = new SQLitePragmaSchemaCatalog($apply()['records']);
        $indexes = $catalog->execute('PRAGMA index_list(wp_options)')['rows'];
        $t->same('sqlite_autoindex_wp_options_1', $indexes[0]['name']);
        $t->same('wp_options_name_active', $indexes[1]['name']);
    },
    'schema rename column trigger view current source next110 rejects missing table' => static function (TestRunner $t) use ($baseRecords): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE missing_options RENAME COLUMN option_name TO option_key']));
    },
    'schema rename column trigger view current source next110 rejects missing column' => static function (TestRunner $t) use ($baseRecords): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE wp_options RENAME COLUMN missing_name TO option_key']));
    },
    'schema rename column trigger view current source next110 rejects duplicate new column' => static function (TestRunner $t) use ($baseRecords): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE wp_options RENAME COLUMN option_name TO autoload']));
    },
    'schema rename column trigger view current source next110 rejects malformed rename column sql' => static function (TestRunner $t) use ($baseRecords): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSchemaDdlReparsePlan::apply($baseRecords(), ['ALTER TABLE wp_options RENAME COLUMN option_name']));
    },
];

$quotedCases = [
    'quoted old column in table' => [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options("option_name" TEXT, autoload TEXT)', 1),
        'CREATE TABLE wp_options("option_key" TEXT, autoload TEXT)',
    ],
    'bracket old column in view' => [
        $record('view', 'v', 'v', 0, 'CREATE VIEW v AS SELECT [option_name] FROM wp_options WHERE [option_name] IS NOT NULL', 2),
        'CREATE VIEW v AS SELECT [option_key] FROM wp_options WHERE [option_key] IS NOT NULL',
    ],
    'backtick old column in trigger' => [
        $record('trigger', 'trg', 'wp_options', 0, 'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new.`option_name`; END', 2),
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new.`option_key`; END',
    ],
    'case-insensitive current source' => [
        $record('index', 'idx_case', 'wp_options', 3, 'CREATE INDEX idx_case ON wp_options(OPTION_NAME) WHERE Option_Name IS NOT NULL', 2),
        'CREATE INDEX idx_case ON wp_options(option_key) WHERE option_key IS NOT NULL',
    ],
];

foreach ($quotedCases as $name => [$candidate, $expectedSql]) {
    $tests['schema rename column trigger view current source next110 ' . $name] = static function (TestRunner $t) use ($record, $candidate, $expectedSql): void {
        $records = [
            $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_name TEXT, autoload TEXT)', 1),
            $candidate,
        ];
        $plan = SQLiteSchemaDdlReparsePlan::apply($records, ['ALTER TABLE wp_options RENAME COLUMN option_name TO option_key'], 110);
        $t->same($expectedSql, $plan['records'][1]->sql);
    };
}

$currentSourceCases = [
    'view cte body and output column list' => [
        $record('view', 'cte_options', 'cte_options', 0, 'CREATE VIEW cte_options AS WITH named(option_name) AS (SELECT option_name FROM wp_options) SELECT option_name FROM named', 2),
        'CREATE VIEW cte_options AS WITH named(option_key) AS (SELECT option_key FROM wp_options) SELECT option_key FROM named',
    ],
    'view recursive cte body' => [
        $record('view', 'recursive_options', 'recursive_options', 0, 'CREATE VIEW recursive_options AS WITH RECURSIVE r(x) AS (SELECT option_name FROM wp_options UNION ALL SELECT option_name FROM wp_options) SELECT x FROM r', 2),
        'CREATE VIEW recursive_options AS WITH RECURSIVE r(x) AS (SELECT option_key FROM wp_options UNION ALL SELECT option_key FROM wp_options) SELECT x FROM r',
    ],
    'view compound select body' => [
        $record('view', 'compound_options', 'compound_options', 0, 'CREATE VIEW compound_options AS SELECT option_name FROM wp_options UNION SELECT option_name FROM wp_options', 2),
        'CREATE VIEW compound_options AS SELECT option_key FROM wp_options UNION SELECT option_key FROM wp_options',
    ],
    'view filter and window body' => [
        $record('view', 'window_options', 'window_options', 0, 'CREATE VIEW window_options AS SELECT count(*) FILTER (WHERE option_name IS NOT NULL), row_number() OVER (PARTITION BY option_name ORDER BY option_id) FROM wp_options', 2),
        'CREATE VIEW window_options AS SELECT count(*) FILTER (WHERE option_key IS NOT NULL), row_number() OVER (PARTITION BY option_key ORDER BY option_id) FROM wp_options',
    ],
    'view scalar subquery body' => [
        $record('view', 'scalar_options', 'scalar_options', 0, 'CREATE VIEW scalar_options AS SELECT (SELECT option_name FROM wp_options LIMIT 1) AS first_name FROM wp_options', 2),
        'CREATE VIEW scalar_options AS SELECT (SELECT option_key FROM wp_options LIMIT 1) AS first_name FROM wp_options',
    ],
    'view implicit alias remains current source output' => [
        $record('view', 'implicit_alias_options', 'implicit_alias_options', 0, 'CREATE VIEW implicit_alias_options AS SELECT option_name option_name FROM wp_options', 2),
        'CREATE VIEW implicit_alias_options AS SELECT option_key option_name FROM wp_options',
    ],
    'view string literal and comments stay stale text' => [
        $record('view', 'literal_options', 'literal_options', 0, "CREATE VIEW literal_options AS SELECT 'option_name' AS label, option_name /* option_name */ FROM wp_options -- option_name\nWHERE option_name IS NOT NULL", 2),
        "CREATE VIEW literal_options AS SELECT 'option_name' AS label, option_key /* option_name */ FROM wp_options -- option_name\nWHERE option_key IS NOT NULL",
    ],
    'trigger cte body current source' => [
        $record('trigger', 'cte_trigger', 'wp_options', 0, 'CREATE TRIGGER cte_trigger AFTER INSERT ON wp_options BEGIN WITH named AS (SELECT new.option_name AS value) INSERT INTO audit(name) SELECT value FROM named; END', 2),
        'CREATE TRIGGER cte_trigger AFTER INSERT ON wp_options BEGIN WITH named AS (SELECT new.option_key AS value) INSERT INTO audit(name) SELECT value FROM named; END',
    ],
    'trigger raise message stays literal' => [
        $record('trigger', 'raise_trigger', 'wp_options', 0, "CREATE TRIGGER raise_trigger BEFORE INSERT ON wp_options WHEN new.option_name IS NULL BEGIN SELECT raise(abort, 'option_name required'); END", 2),
        "CREATE TRIGGER raise_trigger BEFORE INSERT ON wp_options WHEN new.option_key IS NULL BEGIN SELECT raise(abort, 'option_name required'); END",
    ],
    'trigger select implicit alias remains stale output name' => [
        $record('trigger', 'alias_trigger', 'wp_options', 0, 'CREATE TRIGGER alias_trigger AFTER INSERT ON wp_options BEGIN SELECT new.option_name option_name; END', 2),
        'CREATE TRIGGER alias_trigger AFTER INSERT ON wp_options BEGIN SELECT new.option_key option_name; END',
    ],
    'trigger body source table named old column remains source' => [
        $record('trigger', 'delete_trigger', 'wp_options', 0, 'CREATE TRIGGER delete_trigger AFTER DELETE ON wp_options BEGIN DELETE FROM option_name WHERE option_name.id = old.option_name; END', 2),
        'CREATE TRIGGER delete_trigger AFTER DELETE ON wp_options BEGIN DELETE FROM option_name WHERE option_name.id = old.option_key; END',
    ],
    'index expression body current source' => [
        $record('index', 'idx_expr', 'wp_options', 3, 'CREATE INDEX idx_expr ON wp_options((option_name || autoload)) WHERE length(option_name) > 3', 2),
        'CREATE INDEX idx_expr ON wp_options((option_key || autoload)) WHERE length(option_key) > 3',
    ],
    'table generated stored expression current source' => [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_name TEXT, option_hash TEXT GENERATED ALWAYS AS (hex(option_name)) STORED)', 1),
        'CREATE TABLE wp_options(option_key TEXT, option_hash TEXT GENERATED ALWAYS AS (hex(option_key)) STORED)',
    ],
    'table foreign key reference current source' => [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_name TEXT REFERENCES wp_optionmeta(option_name), autoload TEXT)', 1),
        'CREATE TABLE wp_options(option_key TEXT REFERENCES wp_optionmeta(option_key), autoload TEXT)',
    ],
    'view source table name matching column stays source' => [
        $record('view', 'source_name_options', 'source_name_options', 0, 'CREATE VIEW source_name_options AS SELECT wp_options.option_name FROM wp_options JOIN option_name ON option_name.id = wp_options.option_id', 2),
        'CREATE VIEW source_name_options AS SELECT wp_options.option_key FROM wp_options JOIN option_name ON option_name.id = wp_options.option_id',
    ],
];

foreach ($currentSourceCases as $name => [$candidate, $expectedSql]) {
    $tests['schema rename column trigger view current source next110 ' . $name] = static function (TestRunner $t) use ($record, $candidate, $expectedSql): void {
        $records = $candidate->type === 'table'
            ? [$candidate]
            : [
                $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_name TEXT, autoload TEXT, option_id INTEGER)', 1),
                $candidate,
            ];
        $plan = SQLiteSchemaDdlReparsePlan::apply($records, ['ALTER TABLE wp_options RENAME COLUMN option_name TO option_key'], 110);
        $t->same($expectedSql, $candidate->type === 'table' ? $plan['records'][0]->sql : $plan['records'][1]->sql);
    };
}

return $tests;
