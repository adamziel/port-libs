<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAlterTableRenamePlan;

$tests = [];

$cases = [
    'view current table bare projection' => [
        'CREATE VIEW option_keys AS SELECT option_name FROM wp_options',
        'CREATE VIEW option_keys AS SELECT option_key FROM wp_options',
    ],
    'view current table qualified projection' => [
        'CREATE VIEW option_keys AS SELECT wp_options.option_name FROM wp_options',
        'CREATE VIEW option_keys AS SELECT wp_options.option_key FROM wp_options',
    ],
    'view current table alias projection' => [
        'CREATE VIEW option_keys AS SELECT o.option_name FROM wp_options AS o',
        'CREATE VIEW option_keys AS SELECT o.option_key FROM wp_options AS o',
    ],
    'view current table implicit alias projection' => [
        'CREATE VIEW option_keys AS SELECT o.option_name FROM wp_options o',
        'CREATE VIEW option_keys AS SELECT o.option_key FROM wp_options o',
    ],
    'view current table where predicate' => [
        "CREATE VIEW active_options AS SELECT option_id FROM wp_options WHERE option_name = 'active_plugins'",
        "CREATE VIEW active_options AS SELECT option_id FROM wp_options WHERE option_key = 'active_plugins'",
    ],
    'view current table qualified where predicate' => [
        "CREATE VIEW active_options AS SELECT option_id FROM wp_options WHERE wp_options.option_name = 'siteurl'",
        "CREATE VIEW active_options AS SELECT option_id FROM wp_options WHERE wp_options.option_key = 'siteurl'",
    ],
    'view current table alias where predicate' => [
        "CREATE VIEW active_options AS SELECT o.option_id FROM wp_options o WHERE o.option_name = 'siteurl'",
        "CREATE VIEW active_options AS SELECT o.option_id FROM wp_options o WHERE o.option_key = 'siteurl'",
    ],
    'view current table order expression' => [
        'CREATE VIEW ordered_options AS SELECT option_id FROM wp_options ORDER BY lower(option_name), option_id',
        'CREATE VIEW ordered_options AS SELECT option_id FROM wp_options ORDER BY lower(option_key), option_id',
    ],
    'view current table group expression' => [
        'CREATE VIEW grouped_options AS SELECT option_name, count(*) FROM wp_options GROUP BY option_name HAVING count(option_name) > 0',
        'CREATE VIEW grouped_options AS SELECT option_key, count(*) FROM wp_options GROUP BY option_key HAVING count(option_key) > 0',
    ],
    'view current table window partition' => [
        'CREATE VIEW ranked_options AS SELECT option_id, row_number() OVER (PARTITION BY option_name ORDER BY option_id) FROM wp_options',
        'CREATE VIEW ranked_options AS SELECT option_id, row_number() OVER (PARTITION BY option_key ORDER BY option_id) FROM wp_options',
    ],
    'view current table filter clause' => [
        'CREATE VIEW filtered_options AS SELECT count(*) FILTER (WHERE option_name IS NOT NULL) FROM wp_options',
        'CREATE VIEW filtered_options AS SELECT count(*) FILTER (WHERE option_key IS NOT NULL) FROM wp_options',
    ],
    'view current table case expression' => [
        "CREATE VIEW option_labels AS SELECT CASE option_name WHEN 'siteurl' THEN 'core' ELSE option_name END FROM wp_options",
        "CREATE VIEW option_labels AS SELECT CASE option_key WHEN 'siteurl' THEN 'core' ELSE option_key END FROM wp_options",
    ],
    'view current table collate expression' => [
        'CREATE VIEW option_labels AS SELECT option_name COLLATE nocase FROM wp_options',
        'CREATE VIEW option_labels AS SELECT option_key COLLATE nocase FROM wp_options',
    ],
    'view current table cast expression' => [
        'CREATE VIEW option_labels AS SELECT CAST(option_name AS text) FROM wp_options',
        'CREATE VIEW option_labels AS SELECT CAST(option_key AS text) FROM wp_options',
    ],
    'view current table coalesce expression' => [
        "CREATE VIEW option_labels AS SELECT coalesce(option_name, '') FROM wp_options",
        "CREATE VIEW option_labels AS SELECT coalesce(option_key, '') FROM wp_options",
    ],
    'view current table distinct expression' => [
        'CREATE VIEW option_labels AS SELECT DISTINCT option_name FROM wp_options',
        'CREATE VIEW option_labels AS SELECT DISTINCT option_key FROM wp_options',
    ],
    'view current table join on qualified columns' => [
        'CREATE VIEW option_pairs AS SELECT a.option_name FROM wp_options a JOIN wp_options b ON a.option_name = b.option_name',
        'CREATE VIEW option_pairs AS SELECT a.option_key FROM wp_options a JOIN wp_options b ON a.option_key = b.option_key',
    ],
    'view current table join using column' => [
        'CREATE VIEW option_pairs AS SELECT * FROM wp_options a JOIN wp_options b USING(option_name)',
        'CREATE VIEW option_pairs AS SELECT * FROM wp_options a JOIN wp_options b USING(option_key)',
    ],
    'view current table left join predicate' => [
        'CREATE VIEW option_meta AS SELECT o.option_name FROM wp_options o LEFT JOIN wp_postmeta m ON m.meta_key = o.option_name',
        'CREATE VIEW option_meta AS SELECT o.option_key FROM wp_options o LEFT JOIN wp_postmeta m ON m.meta_key = o.option_key',
    ],
    'view current table correlated exists' => [
        'CREATE VIEW option_meta AS SELECT option_id FROM wp_options o WHERE EXISTS (SELECT 1 FROM wp_postmeta m WHERE m.meta_key = o.option_name)',
        'CREATE VIEW option_meta AS SELECT option_id FROM wp_options o WHERE EXISTS (SELECT 1 FROM wp_postmeta m WHERE m.meta_key = o.option_key)',
    ],
    'view current table scalar subquery' => [
        'CREATE VIEW option_counts AS SELECT (SELECT count(*) FROM wp_options i WHERE i.option_name = o.option_name) AS matches FROM wp_options o',
        'CREATE VIEW option_counts AS SELECT (SELECT count(*) FROM wp_options i WHERE i.option_key = o.option_key) AS matches FROM wp_options o',
    ],
    'view current table cte column list' => [
        'CREATE VIEW option_cte AS WITH named(option_name) AS (SELECT option_name FROM wp_options) SELECT option_name FROM named',
        'CREATE VIEW option_cte AS WITH named(option_key) AS (SELECT option_key FROM wp_options) SELECT option_key FROM named',
    ],
    'view current table recursive cte body' => [
        'CREATE VIEW option_cte AS WITH RECURSIVE named(x) AS (SELECT option_name FROM wp_options UNION ALL SELECT option_name FROM wp_options) SELECT x FROM named',
        'CREATE VIEW option_cte AS WITH RECURSIVE named(x) AS (SELECT option_key FROM wp_options UNION ALL SELECT option_key FROM wp_options) SELECT x FROM named',
    ],
    'view current table compound select' => [
        'CREATE VIEW option_union AS SELECT option_name FROM wp_options UNION SELECT option_name FROM wp_options',
        'CREATE VIEW option_union AS SELECT option_key FROM wp_options UNION SELECT option_key FROM wp_options',
    ],
    'view current table values comparison' => [
        "CREATE VIEW option_values AS SELECT option_name IN (VALUES('siteurl'),('home')) FROM wp_options",
        "CREATE VIEW option_values AS SELECT option_key IN (VALUES('siteurl'),('home')) FROM wp_options",
    ],
    'view current table row value comparison' => [
        "CREATE VIEW option_rows AS SELECT (option_name, autoload) = ('siteurl', 'yes') FROM wp_options",
        "CREATE VIEW option_rows AS SELECT (option_key, autoload) = ('siteurl', 'yes') FROM wp_options",
    ],
    'view current table limit expression' => [
        'CREATE VIEW option_limited AS SELECT option_id FROM wp_options ORDER BY option_id LIMIT length(option_name)',
        'CREATE VIEW option_limited AS SELECT option_id FROM wp_options ORDER BY option_id LIMIT length(option_key)',
    ],
    'view current table alias name preserved' => [
        'CREATE VIEW option_aliases AS SELECT option_name AS option_name FROM wp_options',
        'CREATE VIEW option_aliases AS SELECT option_key AS option_name FROM wp_options',
    ],
    'view current object name preserved' => [
        'CREATE VIEW option_name AS SELECT option_name FROM wp_options',
        'CREATE VIEW option_name AS SELECT option_key FROM wp_options',
    ],
    'view current literal preserved' => [
        "CREATE VIEW option_literals AS SELECT 'option_name', option_name FROM wp_options",
        "CREATE VIEW option_literals AS SELECT 'option_name', option_key FROM wp_options",
    ],
    'view current quoted literal escaped preserved' => [
        "CREATE VIEW option_literals AS SELECT 'option_name''option_name', option_name FROM wp_options",
        "CREATE VIEW option_literals AS SELECT 'option_name''option_name', option_key FROM wp_options",
    ],
    'view current line comment preserved' => [
        "CREATE VIEW option_comments AS SELECT option_name -- option_name\nFROM wp_options",
        "CREATE VIEW option_comments AS SELECT option_key -- option_name\nFROM wp_options",
    ],
    'view current block comment preserved' => [
        'CREATE VIEW option_comments AS SELECT option_name /* option_name */ FROM wp_options',
        'CREATE VIEW option_comments AS SELECT option_key /* option_name */ FROM wp_options',
    ],
    'view current function name preserved' => [
        'CREATE VIEW option_functions AS SELECT option_name(option_id), option_name FROM wp_options',
        'CREATE VIEW option_functions AS SELECT option_name(option_id), option_key FROM wp_options',
    ],
    'view current quoted column' => [
        'CREATE VIEW option_quoted AS SELECT "option_name" FROM wp_options',
        'CREATE VIEW option_quoted AS SELECT "option_key" FROM wp_options',
    ],
    'view current bracket column' => [
        'CREATE VIEW option_quoted AS SELECT [option_name] FROM wp_options',
        'CREATE VIEW option_quoted AS SELECT [option_key] FROM wp_options',
    ],
    'view current backtick column' => [
        'CREATE VIEW option_quoted AS SELECT `option_name` FROM wp_options',
        'CREATE VIEW option_quoted AS SELECT `option_key` FROM wp_options',
    ],
    'trigger current update of single column' => [
        'CREATE TRIGGER trg AFTER UPDATE OF option_name ON wp_options BEGIN SELECT 1; END',
        'CREATE TRIGGER trg AFTER UPDATE OF option_key ON wp_options BEGIN SELECT 1; END',
    ],
    'trigger current update of mixed list' => [
        'CREATE TRIGGER trg AFTER UPDATE OF option_id, option_name, autoload ON wp_options BEGIN SELECT 1; END',
        'CREATE TRIGGER trg AFTER UPDATE OF option_id, option_key, autoload ON wp_options BEGIN SELECT 1; END',
    ],
    'trigger current when new reference' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options WHEN new.option_name IS NOT NULL BEGIN SELECT 1; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options WHEN new.option_key IS NOT NULL BEGIN SELECT 1; END',
    ],
    'trigger current when old and new references' => [
        'CREATE TRIGGER trg AFTER UPDATE ON wp_options WHEN old.option_name <> new.option_name BEGIN SELECT 1; END',
        'CREATE TRIGGER trg AFTER UPDATE ON wp_options WHEN old.option_key <> new.option_key BEGIN SELECT 1; END',
    ],
    'trigger current insert new reference' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN INSERT INTO audit(name) VALUES(new.option_name); END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN INSERT INTO audit(name) VALUES(new.option_key); END',
    ],
    'trigger current update set old new references' => [
        'CREATE TRIGGER trg AFTER UPDATE ON wp_options BEGIN UPDATE wp_options SET option_name = new.option_name WHERE option_name = old.option_name; END',
        'CREATE TRIGGER trg AFTER UPDATE ON wp_options BEGIN UPDATE wp_options SET option_key = new.option_key WHERE option_key = old.option_key; END',
    ],
    'trigger current delete old reference' => [
        'CREATE TRIGGER trg AFTER DELETE ON wp_options BEGIN DELETE FROM audit WHERE name = old.option_name; END',
        'CREATE TRIGGER trg AFTER DELETE ON wp_options BEGIN DELETE FROM audit WHERE name = old.option_key; END',
    ],
    'trigger current select new reference' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new.option_name; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new.option_key; END',
    ],
    'trigger current select quoted new reference' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new."option_name"; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new."option_key"; END',
    ],
    'trigger current select bracket new reference' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new.[option_name]; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new.[option_key]; END',
    ],
    'trigger current body table reference' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT option_name FROM wp_options WHERE option_name = new.option_name; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT option_key FROM wp_options WHERE option_key = new.option_key; END',
    ],
    'trigger current body table alias reference' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT o.option_name FROM wp_options o WHERE o.option_name = new.option_name; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT o.option_key FROM wp_options o WHERE o.option_key = new.option_key; END',
    ],
    'trigger current body cte reference' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN WITH named AS (SELECT option_name FROM wp_options) INSERT INTO audit(name) SELECT option_name FROM named; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN WITH named AS (SELECT option_key FROM wp_options) INSERT INTO audit(name) SELECT option_key FROM named; END',
    ],
    'trigger current object name preserved' => [
        'CREATE TRIGGER option_name AFTER INSERT ON wp_options BEGIN SELECT new.option_name; END',
        'CREATE TRIGGER option_name AFTER INSERT ON wp_options BEGIN SELECT new.option_key; END',
    ],
    'trigger current literal preserved' => [
        "CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT 'option_name', new.option_name; END",
        "CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT 'option_name', new.option_key; END",
    ],
    'trigger current line comment preserved' => [
        "CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN -- option_name\nSELECT new.option_name; END",
        "CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN -- option_name\nSELECT new.option_key; END",
    ],
    'trigger current block comment preserved' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN /* option_name */ SELECT new.option_name; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN /* option_name */ SELECT new.option_key; END',
    ],
    'trigger current instead of view reference' => [
        'CREATE TRIGGER trg INSTEAD OF UPDATE OF option_name ON active_options BEGIN UPDATE wp_options SET option_name = new.option_name; END',
        'CREATE TRIGGER trg INSTEAD OF UPDATE OF option_key ON active_options BEGIN UPDATE wp_options SET option_key = new.option_key; END',
    ],
    'trigger current recursive update reference' => [
        'CREATE TRIGGER trg AFTER UPDATE ON wp_options BEGIN UPDATE wp_options SET option_name = lower(new.option_name) WHERE option_id = new.option_id; END',
        'CREATE TRIGGER trg AFTER UPDATE ON wp_options BEGIN UPDATE wp_options SET option_key = lower(new.option_key) WHERE option_id = new.option_id; END',
    ],
    'trigger current raise message preserved' => [
        "CREATE TRIGGER trg BEFORE INSERT ON wp_options WHEN new.option_name IS NULL BEGIN SELECT raise(abort, 'option_name required'); END",
        "CREATE TRIGGER trg BEFORE INSERT ON wp_options WHEN new.option_key IS NULL BEGIN SELECT raise(abort, 'option_name required'); END",
    ],
    'index current expression body' => [
        'CREATE INDEX option_current_expr ON wp_options(lower(option_name), option_id) WHERE option_name IS NOT NULL',
        'CREATE INDEX option_current_expr ON wp_options(lower(option_key), option_id) WHERE option_key IS NOT NULL',
    ],
    'index current quoted expression body' => [
        'CREATE INDEX option_current_expr ON wp_options(lower("option_name")) WHERE "option_name" IS NOT NULL',
        'CREATE INDEX option_current_expr ON wp_options(lower("option_key")) WHERE "option_key" IS NOT NULL',
    ],
    'table current generated expression' => [
        'CREATE TABLE wp_options(option_name text, option_hash text GENERATED ALWAYS AS (hex(option_name)) STORED)',
        'CREATE TABLE wp_options(option_key text, option_hash text GENERATED ALWAYS AS (hex(option_key)) STORED)',
    ],
    'table current check expression' => [
        "CREATE TABLE wp_options(option_name text, CHECK(option_name <> ''))",
        "CREATE TABLE wp_options(option_key text, CHECK(option_key <> ''))",
    ],
    'table current foreign key reference' => [
        'CREATE TABLE wp_optionmeta(option_name text REFERENCES wp_options(option_name))',
        'CREATE TABLE wp_optionmeta(option_key text REFERENCES wp_options(option_key))',
    ],
];

foreach ($cases as $name => [$sql, $expected]) {
    $tests['alter table rename column current next15 ' . $name] = static function (TestRunner $t) use ($sql, $expected): void {
        $t->same($expected, SQLiteAlterTableRenamePlan::renameColumnSql($sql, 'wp_options', 'option_name', 'option_key'));
    };
}

$tests['alter table rename column current next15 rejects unterminated bracket identifier'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameColumnSql('CREATE VIEW broken AS SELECT [option_name FROM wp_options', 'wp_options', 'option_name', 'option_key'));
};

$tests['alter table rename column current next15 rejects unterminated block comment'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAlterTableRenamePlan::renameColumnSql('CREATE VIEW broken AS SELECT option_name /* option_name FROM wp_options', 'wp_options', 'option_name', 'option_key'));
};

return $tests;
