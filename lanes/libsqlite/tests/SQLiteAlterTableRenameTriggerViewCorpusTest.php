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

$tests['alter table rename trigger view corpus rejects malformed old name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameTableSql('CREATE VIEW v AS SELECT 1', 'bad-name', 'wp_options_new'));
};

$tests['alter table rename trigger view corpus rejects malformed new name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameTableSql('CREATE VIEW v AS SELECT 1', 'wp_options', 'bad-name'));
};

$tests['alter table rename trigger view corpus rejects unterminated literal'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameTableSql("CREATE VIEW v AS SELECT 'wp_options FROM wp_options", 'wp_options', 'wp_options_new'));
};

return $tests;
