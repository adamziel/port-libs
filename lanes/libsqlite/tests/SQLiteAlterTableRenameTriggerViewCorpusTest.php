<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAlterTableRenamePlan;

$tests = [];

$renameCases = [
    'table create name' => ['CREATE TABLE wp_options(option_id integer primary key, option_name text)', 'CREATE TABLE wp_options_new(option_id integer primary key, option_name text)'],
    'table schema qualified name' => ['CREATE TABLE main.wp_options(option_name text)', 'CREATE TABLE main.wp_options_new(option_name text)'],
    'table quoted name' => ['CREATE TABLE "wp_options"(option_name text)', 'CREATE TABLE "wp_options_new"(option_name text)'],
    'table bracket name' => ['CREATE TABLE [wp_options](option_name text)', 'CREATE TABLE [wp_options_new](option_name text)'],
    'table backtick name' => ['CREATE TABLE `wp_options`(option_name text)', 'CREATE TABLE `wp_options_new`(option_name text)'],
    'index keeps index name' => ['CREATE INDEX wp_options ON wp_options(option_name)', 'CREATE INDEX wp_options ON wp_options_new(option_name)'],
    'index schema table' => ['CREATE INDEX idx ON main.wp_options(option_name)', 'CREATE INDEX idx ON main.wp_options_new(option_name)'],
    'index quoted table' => ['CREATE INDEX idx ON "wp_options"(option_name)', 'CREATE INDEX idx ON "wp_options_new"(option_name)'],
    'index partial where untouched string' => ["CREATE INDEX idx ON wp_options(option_name) WHERE option_value <> 'wp_options'", "CREATE INDEX idx ON wp_options_new(option_name) WHERE option_value <> 'wp_options'"],
    'index expression column string kept' => ["CREATE INDEX idx ON wp_options(lower('wp_options'))", "CREATE INDEX idx ON wp_options_new(lower('wp_options'))"],
    'view keeps view name' => ['CREATE VIEW wp_options AS SELECT option_name FROM wp_options', 'CREATE VIEW wp_options AS SELECT option_name FROM wp_options_new'],
    'view star from table' => ['CREATE VIEW active_options AS SELECT * FROM wp_options', 'CREATE VIEW active_options AS SELECT * FROM wp_options_new'],
    'view schema from table' => ['CREATE VIEW active_options AS SELECT * FROM main.wp_options', 'CREATE VIEW active_options AS SELECT * FROM main.wp_options_new'],
    'view quoted from table' => ['CREATE VIEW active_options AS SELECT * FROM "wp_options"', 'CREATE VIEW active_options AS SELECT * FROM "wp_options_new"'],
    'view bracket from table' => ['CREATE VIEW active_options AS SELECT * FROM [wp_options]', 'CREATE VIEW active_options AS SELECT * FROM [wp_options_new]'],
    'view join both references' => ['CREATE VIEW option_pairs AS SELECT a.option_name FROM wp_options a JOIN wp_options b ON a.option_id=b.option_id', 'CREATE VIEW option_pairs AS SELECT a.option_name FROM wp_options_new a JOIN wp_options_new b ON a.option_id=b.option_id'],
    'view left join reference' => ['CREATE VIEW option_meta AS SELECT * FROM wp_options LEFT JOIN wp_postmeta ON wp_postmeta.meta_key=wp_options.option_name', 'CREATE VIEW option_meta AS SELECT * FROM wp_options_new LEFT JOIN wp_postmeta ON wp_postmeta.meta_key=wp_options_new.option_name'],
    'view subquery reference' => ['CREATE VIEW option_names AS SELECT option_name FROM (SELECT option_name FROM wp_options)', 'CREATE VIEW option_names AS SELECT option_name FROM (SELECT option_name FROM wp_options_new)'],
    'view cte reference' => ['CREATE VIEW hot_options AS WITH hot AS (SELECT * FROM wp_options) SELECT * FROM hot', 'CREATE VIEW hot_options AS WITH hot AS (SELECT * FROM wp_options_new) SELECT * FROM hot'],
    'view trigger word literal kept' => ["CREATE VIEW audit AS SELECT 'wp_options' AS label FROM wp_options", "CREATE VIEW audit AS SELECT 'wp_options' AS label FROM wp_options_new"],
    'view line comment kept' => ["CREATE VIEW audit AS SELECT * FROM wp_options -- wp_options\nWHERE autoload='yes'", "CREATE VIEW audit AS SELECT * FROM wp_options_new -- wp_options\nWHERE autoload='yes'"],
    'view block comment kept' => ['CREATE VIEW audit AS SELECT * FROM wp_options /* wp_options */ WHERE autoload=\'yes\'', 'CREATE VIEW audit AS SELECT * FROM wp_options_new /* wp_options */ WHERE autoload=\'yes\''],
    'view double quoted identifier body' => ['CREATE VIEW audit AS SELECT * FROM "wp_options" WHERE "wp_options".autoload=\'yes\'', 'CREATE VIEW audit AS SELECT * FROM "wp_options_new" WHERE "wp_options_new".autoload=\'yes\''],
    'trigger keeps trigger name' => ['CREATE TRIGGER wp_options AFTER INSERT ON wp_options BEGIN SELECT 1; END', 'CREATE TRIGGER wp_options AFTER INSERT ON wp_options_new BEGIN SELECT 1; END'],
    'trigger on quoted table' => ['CREATE TRIGGER trg AFTER INSERT ON "wp_options" BEGIN SELECT 1; END', 'CREATE TRIGGER trg AFTER INSERT ON "wp_options_new" BEGIN SELECT 1; END'],
    'trigger on bracket table' => ['CREATE TRIGGER trg AFTER INSERT ON [wp_options] BEGIN SELECT 1; END', 'CREATE TRIGGER trg AFTER INSERT ON [wp_options_new] BEGIN SELECT 1; END'],
    'trigger before update on table' => ['CREATE TRIGGER trg BEFORE UPDATE ON wp_options BEGIN SELECT new.option_name; END', 'CREATE TRIGGER trg BEFORE UPDATE ON wp_options_new BEGIN SELECT new.option_name; END'],
    'trigger delete body from table' => ['CREATE TRIGGER trg AFTER DELETE ON wp_options BEGIN DELETE FROM wp_options WHERE option_id=old.option_id; END', 'CREATE TRIGGER trg AFTER DELETE ON wp_options_new BEGIN DELETE FROM wp_options_new WHERE option_id=old.option_id; END'],
    'trigger update body table' => ['CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN UPDATE wp_options SET autoload=new.autoload WHERE option_id=new.option_id; END', 'CREATE TRIGGER trg AFTER INSERT ON wp_options_new BEGIN UPDATE wp_options_new SET autoload=new.autoload WHERE option_id=new.option_id; END'],
    'trigger insert body table' => ['CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN INSERT INTO wp_options(option_name) VALUES(new.option_name); END', 'CREATE TRIGGER trg AFTER INSERT ON wp_options_new BEGIN INSERT INTO wp_options_new(option_name) VALUES(new.option_name); END'],
    'trigger select body table' => ['CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT count(*) FROM wp_options; END', 'CREATE TRIGGER trg AFTER INSERT ON wp_options_new BEGIN SELECT count(*) FROM wp_options_new; END'],
    'trigger qualified body table' => ['CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN UPDATE main.wp_options SET autoload=\'no\'; END', 'CREATE TRIGGER trg AFTER INSERT ON wp_options_new BEGIN UPDATE main.wp_options_new SET autoload=\'no\'; END'],
    'trigger string literal kept' => ["CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN INSERT INTO audit(label) VALUES('wp_options'); END", "CREATE TRIGGER trg AFTER INSERT ON wp_options_new BEGIN INSERT INTO audit(label) VALUES('wp_options'); END"],
    'trigger line comment kept' => ["CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN -- wp_options\nSELECT * FROM wp_options; END", "CREATE TRIGGER trg AFTER INSERT ON wp_options_new BEGIN -- wp_options\nSELECT * FROM wp_options_new; END"],
    'trigger block comment kept' => ['CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN /* wp_options */ SELECT * FROM wp_options; END', 'CREATE TRIGGER trg AFTER INSERT ON wp_options_new BEGIN /* wp_options */ SELECT * FROM wp_options_new; END'],
    'trigger when table reference' => ['CREATE TRIGGER trg AFTER INSERT ON wp_options WHEN EXISTS(SELECT 1 FROM wp_options) BEGIN SELECT 1; END', 'CREATE TRIGGER trg AFTER INSERT ON wp_options_new WHEN EXISTS(SELECT 1 FROM wp_options_new) BEGIN SELECT 1; END'],
    'trigger instead of view target kept as old view object' => ['CREATE TRIGGER trg INSTEAD OF INSERT ON wp_options BEGIN SELECT * FROM wp_options; END', 'CREATE TRIGGER trg INSTEAD OF INSERT ON wp_options_new BEGIN SELECT * FROM wp_options_new; END'],
    'trigger mixed case table' => ['CREATE TRIGGER trg AFTER INSERT ON WP_OPTIONS BEGIN SELECT * FROM wp_options; END', 'CREATE TRIGGER trg AFTER INSERT ON wp_options_new BEGIN SELECT * FROM wp_options_new; END'],
    'trigger multiple statements' => ['CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN UPDATE wp_options SET autoload=\'yes\'; DELETE FROM wp_options WHERE option_name IS NULL; END', 'CREATE TRIGGER trg AFTER INSERT ON wp_options_new BEGIN UPDATE wp_options_new SET autoload=\'yes\'; DELETE FROM wp_options_new WHERE option_name IS NULL; END'],
    'foreign key reference table' => ['CREATE TABLE wp_optionmeta(id integer, option_id integer REFERENCES wp_options(option_id))', 'CREATE TABLE wp_optionmeta(id integer, option_id integer REFERENCES wp_options_new(option_id))'],
    'foreign key quoted reference table' => ['CREATE TABLE wp_optionmeta(id integer REFERENCES "wp_options"(option_id))', 'CREATE TABLE wp_optionmeta(id integer REFERENCES "wp_options_new"(option_id))'],
    'table check string kept' => ["CREATE TABLE wp_optionmeta(label text CHECK(label <> 'wp_options'))", "CREATE TABLE wp_optionmeta(label text CHECK(label <> 'wp_options'))"],
    'view quoted literal escaped kept' => ["CREATE VIEW quoted AS SELECT 'wp_options''wp_options' FROM wp_options", "CREATE VIEW quoted AS SELECT 'wp_options''wp_options' FROM wp_options_new"],
    'trigger double quoted alias kept when not old' => ['CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT "label" FROM wp_options; END', 'CREATE TRIGGER trg AFTER INSERT ON wp_options_new BEGIN SELECT "label" FROM wp_options_new; END'],
    'trigger backtick body table' => ['CREATE TRIGGER trg AFTER INSERT ON `wp_options` BEGIN SELECT * FROM `wp_options`; END', 'CREATE TRIGGER trg AFTER INSERT ON `wp_options_new` BEGIN SELECT * FROM `wp_options_new`; END'],
    'view repeated qualified refs' => ['CREATE VIEW v AS SELECT wp_options.option_id FROM wp_options WHERE wp_options.autoload=\'yes\'', 'CREATE VIEW v AS SELECT wp_options_new.option_id FROM wp_options_new WHERE wp_options_new.autoload=\'yes\''],
    'index object name old with quoted table' => ['CREATE INDEX "wp_options" ON "wp_options"(option_name)', 'CREATE INDEX "wp_options" ON "wp_options_new"(option_name)'],
];

foreach ($renameCases as $name => [$sql, $expected]) {
    $tests['alter table rename trigger view corpus ' . $name] = static function (TestRunner $t) use ($sql, $expected): void {
        $t->same($expected, SQLiteAlterTableRenamePlan::renameTableSql($sql, 'wp_options', 'wp_options_new'));
    };
}

$renameColumnCases = [
    'table column declaration' => ['CREATE TABLE wp_options(option_id integer primary key, option_name text, autoload text)', 'CREATE TABLE wp_options(option_id integer primary key, option_key text, autoload text)'],
    'table quoted column declaration' => ['CREATE TABLE wp_options("option_name" text, autoload text)', 'CREATE TABLE wp_options("option_key" text, autoload text)'],
    'table bracket column declaration' => ['CREATE TABLE wp_options([option_name] text, autoload text)', 'CREATE TABLE wp_options([option_key] text, autoload text)'],
    'table backtick column declaration' => ['CREATE TABLE wp_options(`option_name` text, autoload text)', 'CREATE TABLE wp_options(`option_key` text, autoload text)'],
    'table primary key column constraint' => ['CREATE TABLE wp_options(option_id integer, option_name text, PRIMARY KEY(option_name, option_id))', 'CREATE TABLE wp_options(option_id integer, option_key text, PRIMARY KEY(option_key, option_id))'],
    'table unique column constraint' => ['CREATE TABLE wp_options(option_id integer, option_name text, UNIQUE(option_name))', 'CREATE TABLE wp_options(option_id integer, option_key text, UNIQUE(option_key))'],
    'table check bare column' => ['CREATE TABLE wp_options(option_name text CHECK(length(option_name) > 0))', 'CREATE TABLE wp_options(option_key text CHECK(length(option_key) > 0))'],
    'table check qualified column' => ['CREATE TABLE wp_options(option_name text, CHECK(wp_options.option_name <> \'\'))', 'CREATE TABLE wp_options(option_key text, CHECK(wp_options.option_key <> \'\'))'],
    'table generated column expression' => ['CREATE TABLE wp_options(option_name text, option_slug text GENERATED ALWAYS AS (lower(option_name)))', 'CREATE TABLE wp_options(option_key text, option_slug text GENERATED ALWAYS AS (lower(option_key)))'],
    'table foreign key column list' => ['CREATE TABLE wp_optionmeta(option_name text, FOREIGN KEY(option_name) REFERENCES wp_options(option_name))', 'CREATE TABLE wp_optionmeta(option_key text, FOREIGN KEY(option_key) REFERENCES wp_options(option_key))'],
    'index column list' => ['CREATE INDEX idx_option_name ON wp_options(option_name)', 'CREATE INDEX idx_option_name ON wp_options(option_key)'],
    'index keeps object name matching old column' => ['CREATE INDEX option_name ON wp_options(option_name)', 'CREATE INDEX option_name ON wp_options(option_key)'],
    'index quoted object name matching old column' => ['CREATE INDEX "option_name" ON wp_options("option_name")', 'CREATE INDEX "option_name" ON wp_options("option_key")'],
    'index descending collation column' => ['CREATE INDEX idx ON wp_options(option_name COLLATE nocase DESC)', 'CREATE INDEX idx ON wp_options(option_key COLLATE nocase DESC)'],
    'index expression column' => ['CREATE INDEX idx ON wp_options(lower(option_name))', 'CREATE INDEX idx ON wp_options(lower(option_key))'],
    'index expression qualified column' => ['CREATE INDEX idx ON wp_options(substr(wp_options.option_name, 1, 3))', 'CREATE INDEX idx ON wp_options(substr(wp_options.option_key, 1, 3))'],
    'index partial where column' => ['CREATE INDEX idx ON wp_options(option_id) WHERE option_name IS NOT NULL', 'CREATE INDEX idx ON wp_options(option_id) WHERE option_key IS NOT NULL'],
    'index partial where string kept' => ["CREATE INDEX idx ON wp_options(option_name) WHERE option_value <> 'option_name'", "CREATE INDEX idx ON wp_options(option_key) WHERE option_value <> 'option_name'"],
    'index partial where comment kept' => ["CREATE INDEX idx ON wp_options(option_name) WHERE option_name <> '' -- option_name\n", "CREATE INDEX idx ON wp_options(option_key) WHERE option_key <> '' -- option_name\n"],
    'view projection column' => ['CREATE VIEW v AS SELECT option_name FROM wp_options', 'CREATE VIEW v AS SELECT option_key FROM wp_options'],
    'view qualified projection column' => ['CREATE VIEW v AS SELECT wp_options.option_name FROM wp_options', 'CREATE VIEW v AS SELECT wp_options.option_key FROM wp_options'],
    'view alias keeps alias' => ['CREATE VIEW v AS SELECT option_name AS option_name_label FROM wp_options', 'CREATE VIEW v AS SELECT option_key AS option_name_label FROM wp_options'],
    'view predicate column' => ["CREATE VIEW v AS SELECT option_id FROM wp_options WHERE option_name = 'siteurl'", "CREATE VIEW v AS SELECT option_id FROM wp_options WHERE option_key = 'siteurl'"],
    'view order by column' => ['CREATE VIEW v AS SELECT option_id FROM wp_options ORDER BY option_name COLLATE nocase', 'CREATE VIEW v AS SELECT option_id FROM wp_options ORDER BY option_key COLLATE nocase'],
    'view group by column' => ['CREATE VIEW v AS SELECT option_name, count(*) FROM wp_options GROUP BY option_name', 'CREATE VIEW v AS SELECT option_key, count(*) FROM wp_options GROUP BY option_key'],
    'view join using column' => ['CREATE VIEW v AS SELECT * FROM wp_options JOIN wp_optionmeta USING(option_name)', 'CREATE VIEW v AS SELECT * FROM wp_options JOIN wp_optionmeta USING(option_key)'],
    'view subquery column' => ['CREATE VIEW v AS SELECT * FROM wp_options WHERE option_id IN (SELECT option_id FROM wp_optionmeta WHERE option_name IS NOT NULL)', 'CREATE VIEW v AS SELECT * FROM wp_options WHERE option_id IN (SELECT option_id FROM wp_optionmeta WHERE option_key IS NOT NULL)'],
    'view cte column' => ['CREATE VIEW v AS WITH named AS (SELECT option_name FROM wp_options) SELECT option_name FROM named', 'CREATE VIEW v AS WITH named AS (SELECT option_key FROM wp_options) SELECT option_key FROM named'],
    'view string literal kept' => ["CREATE VIEW v AS SELECT 'option_name' AS label, option_name FROM wp_options", "CREATE VIEW v AS SELECT 'option_name' AS label, option_key FROM wp_options"],
    'view block comment kept' => ["CREATE VIEW v AS SELECT option_name /* option_name */ FROM wp_options", "CREATE VIEW v AS SELECT option_key /* option_name */ FROM wp_options"],
    'trigger update of column' => ['CREATE TRIGGER trg AFTER UPDATE OF option_name ON wp_options BEGIN SELECT 1; END', 'CREATE TRIGGER trg AFTER UPDATE OF option_key ON wp_options BEGIN SELECT 1; END'],
    'trigger update of column list' => ['CREATE TRIGGER trg AFTER UPDATE OF option_id, option_name, autoload ON wp_options BEGIN SELECT 1; END', 'CREATE TRIGGER trg AFTER UPDATE OF option_id, option_key, autoload ON wp_options BEGIN SELECT 1; END'],
    'trigger when new column' => ['CREATE TRIGGER trg AFTER INSERT ON wp_options WHEN new.option_name IS NOT NULL BEGIN SELECT 1; END', 'CREATE TRIGGER trg AFTER INSERT ON wp_options WHEN new.option_key IS NOT NULL BEGIN SELECT 1; END'],
    'trigger when old column' => ['CREATE TRIGGER trg AFTER UPDATE ON wp_options WHEN old.option_name <> new.option_name BEGIN SELECT 1; END', 'CREATE TRIGGER trg AFTER UPDATE ON wp_options WHEN old.option_key <> new.option_key BEGIN SELECT 1; END'],
    'trigger insert values new column' => ['CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN INSERT INTO audit(audit_option_name) VALUES(new.option_name); END', 'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN INSERT INTO audit(audit_option_name) VALUES(new.option_key); END'],
    'trigger update set column' => ['CREATE TRIGGER trg AFTER UPDATE ON wp_options BEGIN UPDATE wp_options SET option_name = new.option_name WHERE option_id = old.option_id; END', 'CREATE TRIGGER trg AFTER UPDATE ON wp_options BEGIN UPDATE wp_options SET option_key = new.option_key WHERE option_id = old.option_id; END'],
    'trigger delete predicate column' => ['CREATE TRIGGER trg AFTER DELETE ON wp_options BEGIN DELETE FROM audit WHERE audit_option_name = old.option_name; END', 'CREATE TRIGGER trg AFTER DELETE ON wp_options BEGIN DELETE FROM audit WHERE audit_option_name = old.option_key; END'],
    'trigger select body column' => ['CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT option_name FROM wp_options WHERE option_name = new.option_name; END', 'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT option_key FROM wp_options WHERE option_key = new.option_key; END'],
    'trigger quoted new column' => ['CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new."option_name"; END', 'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new."option_key"; END'],
    'trigger string and comment kept' => ["CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN -- option_name\nSELECT 'option_name', new.option_name; END", "CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN -- option_name\nSELECT 'option_name', new.option_key; END"],
    'trigger object name matching old column kept' => ['CREATE TRIGGER option_name AFTER INSERT ON wp_options BEGIN SELECT new.option_name; END', 'CREATE TRIGGER option_name AFTER INSERT ON wp_options BEGIN SELECT new.option_key; END'],
    'function name matching old column kept' => ['CREATE VIEW v AS SELECT option_name(option_id), option_name FROM wp_options', 'CREATE VIEW v AS SELECT option_name(option_id), option_key FROM wp_options'],
    'quoted function-like column still rewritten after qualifier' => ['CREATE VIEW v AS SELECT wp_options.option_name(option_id), wp_options.option_name FROM wp_options', 'CREATE VIEW v AS SELECT wp_options.option_key(option_id), wp_options.option_key FROM wp_options'],
    'case-insensitive column match' => ['CREATE INDEX idx ON wp_options(OPTION_NAME) WHERE Option_Name IS NOT NULL', 'CREATE INDEX idx ON wp_options(option_key) WHERE option_key IS NOT NULL'],
    'unrelated column prefix not rewritten' => ['CREATE VIEW v AS SELECT option_name_extra, option_name FROM wp_options', 'CREATE VIEW v AS SELECT option_name_extra, option_key FROM wp_options'],
];

foreach ($renameColumnCases as $name => [$sql, $expected]) {
    $tests['alter table rename column index trigger corpus ' . $name] = static function (TestRunner $t) use ($sql, $expected): void {
        $t->same($expected, SQLiteAlterTableRenamePlan::renameColumnSql($sql, 'wp_options', 'option_name', 'option_key'));
    };
}

$tests['alter table rename trigger view corpus rejects malformed old name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameTableSql('CREATE VIEW v AS SELECT 1', 'bad-name', 'wp_options_new'));
};

$tests['alter table rename trigger view corpus rejects malformed new name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameTableSql('CREATE VIEW v AS SELECT 1', 'wp_options', 'bad-name'));
};

$tests['alter table rename trigger view corpus rejects unterminated literal'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameTableSql("CREATE VIEW v AS SELECT 'wp_options FROM wp_options", 'wp_options', 'wp_options_new'));
};

$tests['alter table rename column index trigger corpus rejects malformed table name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameColumnSql('CREATE VIEW v AS SELECT 1', 'bad-name', 'option_name', 'option_key'));
};

$tests['alter table rename column index trigger corpus rejects malformed old column name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameColumnSql('CREATE VIEW v AS SELECT 1', 'wp_options', 'bad-name', 'option_key'));
};

$tests['alter table rename column index trigger corpus rejects malformed new column name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameColumnSql('CREATE VIEW v AS SELECT 1', 'wp_options', 'option_name', 'bad-name'));
};

return $tests;
