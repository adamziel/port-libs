<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAlterTableRenamePlan;

$tests = [];

$cases = [
    'view implicit result alias preserved' => [
        'CREATE VIEW v AS SELECT option_name option_name FROM wp_options',
        'CREATE VIEW v AS SELECT option_key option_name FROM wp_options',
    ],
    'view implicit quoted result alias preserved' => [
        'CREATE VIEW v AS SELECT option_name "option_name" FROM wp_options',
        'CREATE VIEW v AS SELECT option_key "option_name" FROM wp_options',
    ],
    'view implicit bracket result alias preserved' => [
        'CREATE VIEW v AS SELECT option_name [option_name] FROM wp_options',
        'CREATE VIEW v AS SELECT option_key [option_name] FROM wp_options',
    ],
    'view implicit backtick result alias preserved' => [
        'CREATE VIEW v AS SELECT option_name `option_name` FROM wp_options',
        'CREATE VIEW v AS SELECT option_key `option_name` FROM wp_options',
    ],
    'view function expression implicit alias preserved' => [
        'CREATE VIEW v AS SELECT lower(option_name) option_name FROM wp_options',
        'CREATE VIEW v AS SELECT lower(option_key) option_name FROM wp_options',
    ],
    'view concatenation implicit alias preserved before comma' => [
        "CREATE VIEW v AS SELECT option_name || autoload option_name, option_id FROM wp_options",
        "CREATE VIEW v AS SELECT option_key || autoload option_name, option_id FROM wp_options",
    ],
    'view cast implicit alias preserved before order' => [
        'CREATE VIEW v AS SELECT CAST(option_name AS text) option_name FROM wp_options ORDER BY option_name',
        'CREATE VIEW v AS SELECT CAST(option_key AS text) option_name FROM wp_options ORDER BY option_key',
    ],
    'view case implicit alias preserved before from' => [
        "CREATE VIEW v AS SELECT CASE WHEN option_name IS NULL THEN '' ELSE option_name END option_name FROM wp_options",
        "CREATE VIEW v AS SELECT CASE WHEN option_key IS NULL THEN '' ELSE option_key END option_name FROM wp_options",
    ],
    'view row value expression implicit alias preserved' => [
        "CREATE VIEW v AS SELECT (option_name, autoload) = ('siteurl', 'yes') option_name FROM wp_options",
        "CREATE VIEW v AS SELECT (option_key, autoload) = ('siteurl', 'yes') option_name FROM wp_options",
    ],
    'view scalar subquery implicit alias preserved' => [
        'CREATE VIEW v AS SELECT (SELECT option_name FROM wp_options LIMIT 1) option_name FROM wp_options',
        'CREATE VIEW v AS SELECT (SELECT option_key FROM wp_options LIMIT 1) option_name FROM wp_options',
    ],
    'view table alias matching column preserved' => [
        'CREATE VIEW v AS SELECT option_name.option_name FROM wp_options option_name',
        'CREATE VIEW v AS SELECT option_name.option_key FROM wp_options option_name',
    ],
    'view table alias matching column with as preserved' => [
        'CREATE VIEW v AS SELECT option_name.option_name FROM wp_options AS option_name',
        'CREATE VIEW v AS SELECT option_name.option_key FROM wp_options AS option_name',
    ],
    'view join table alias matching column preserved' => [
        'CREATE VIEW v AS SELECT option_name.option_name FROM wp_options option_name JOIN wp_postmeta m ON m.meta_key = option_name.option_name',
        'CREATE VIEW v AS SELECT option_name.option_key FROM wp_options option_name JOIN wp_postmeta m ON m.meta_key = option_name.option_key',
    ],
    'view left join alias matching column preserved' => [
        'CREATE VIEW v AS SELECT option_name.option_name FROM wp_postmeta m LEFT JOIN wp_options option_name ON option_name.option_name = m.meta_key',
        'CREATE VIEW v AS SELECT option_name.option_key FROM wp_postmeta m LEFT JOIN wp_options option_name ON option_name.option_key = m.meta_key',
    ],
    'view cross join alias matching column preserved' => [
        'CREATE VIEW v AS SELECT option_name.option_name FROM wp_options option_name CROSS JOIN wp_options o2',
        'CREATE VIEW v AS SELECT option_name.option_key FROM wp_options option_name CROSS JOIN wp_options o2',
    ],
    'view alias qualifier in where preserved' => [
        "CREATE VIEW v AS SELECT option_id FROM wp_options option_name WHERE option_name.option_name = 'siteurl'",
        "CREATE VIEW v AS SELECT option_id FROM wp_options option_name WHERE option_name.option_key = 'siteurl'",
    ],
    'view alias qualifier in order preserved' => [
        'CREATE VIEW v AS SELECT option_id FROM wp_options option_name ORDER BY option_name.option_name',
        'CREATE VIEW v AS SELECT option_id FROM wp_options option_name ORDER BY option_name.option_key',
    ],
    'view alias qualifier in group preserved' => [
        'CREATE VIEW v AS SELECT count(*) FROM wp_options option_name GROUP BY option_name.option_name',
        'CREATE VIEW v AS SELECT count(*) FROM wp_options option_name GROUP BY option_name.option_key',
    ],
    'view alias qualifier in having preserved' => [
        'CREATE VIEW v AS SELECT count(*) FROM wp_options option_name GROUP BY option_name.option_name HAVING min(option_name.option_name) IS NOT NULL',
        'CREATE VIEW v AS SELECT count(*) FROM wp_options option_name GROUP BY option_name.option_key HAVING min(option_name.option_key) IS NOT NULL',
    ],
    'view alias qualifier in window preserved' => [
        'CREATE VIEW v AS SELECT row_number() OVER (PARTITION BY option_name.option_name ORDER BY option_id) FROM wp_options option_name',
        'CREATE VIEW v AS SELECT row_number() OVER (PARTITION BY option_name.option_key ORDER BY option_id) FROM wp_options option_name',
    ],
    'view alias qualifier in filter preserved' => [
        'CREATE VIEW v AS SELECT count(*) FILTER (WHERE option_name.option_name IS NOT NULL) FROM wp_options option_name',
        'CREATE VIEW v AS SELECT count(*) FILTER (WHERE option_name.option_key IS NOT NULL) FROM wp_options option_name',
    ],
    'view alias qualifier in correlated exists preserved' => [
        'CREATE VIEW v AS SELECT option_id FROM wp_options option_name WHERE EXISTS (SELECT 1 FROM wp_postmeta m WHERE m.meta_key = option_name.option_name)',
        'CREATE VIEW v AS SELECT option_id FROM wp_options option_name WHERE EXISTS (SELECT 1 FROM wp_postmeta m WHERE m.meta_key = option_name.option_key)',
    ],
    'view alias qualifier in scalar subquery preserved' => [
        'CREATE VIEW v AS SELECT (SELECT count(*) FROM wp_postmeta m WHERE m.meta_key = option_name.option_name) FROM wp_options option_name',
        'CREATE VIEW v AS SELECT (SELECT count(*) FROM wp_postmeta m WHERE m.meta_key = option_name.option_key) FROM wp_options option_name',
    ],
    'view cte name matching column preserved' => [
        'CREATE VIEW v AS WITH option_name AS (SELECT option_name FROM wp_options) SELECT option_name FROM option_name',
        'CREATE VIEW v AS WITH option_name AS (SELECT option_key FROM wp_options) SELECT option_key FROM option_name',
    ],
    'view cte name quoted matching column preserved' => [
        'CREATE VIEW v AS WITH "option_name" AS (SELECT option_name FROM wp_options) SELECT option_name FROM "option_name"',
        'CREATE VIEW v AS WITH "option_name" AS (SELECT option_key FROM wp_options) SELECT option_key FROM "option_name"',
    ],
    'view cte name bracket matching column preserved' => [
        'CREATE VIEW v AS WITH [option_name] AS (SELECT option_name FROM wp_options) SELECT option_name FROM [option_name]',
        'CREATE VIEW v AS WITH [option_name] AS (SELECT option_key FROM wp_options) SELECT option_key FROM [option_name]',
    ],
    'view cte name backtick matching column preserved' => [
        'CREATE VIEW v AS WITH `option_name` AS (SELECT option_name FROM wp_options) SELECT option_name FROM `option_name`',
        'CREATE VIEW v AS WITH `option_name` AS (SELECT option_key FROM wp_options) SELECT option_key FROM `option_name`',
    ],
    'view cte column list still rewritten' => [
        'CREATE VIEW v AS WITH c(option_name) AS (SELECT option_name FROM wp_options) SELECT option_name FROM c',
        'CREATE VIEW v AS WITH c(option_key) AS (SELECT option_key FROM wp_options) SELECT option_key FROM c',
    ],
    'view recursive cte name matching column preserved' => [
        'CREATE VIEW v AS WITH RECURSIVE option_name(x) AS (SELECT option_name FROM wp_options UNION ALL SELECT option_name FROM wp_options) SELECT x FROM option_name',
        'CREATE VIEW v AS WITH RECURSIVE option_name(x) AS (SELECT option_key FROM wp_options UNION ALL SELECT option_key FROM wp_options) SELECT x FROM option_name',
    ],
    'view chained cte source name preserved' => [
        'CREATE VIEW v AS WITH option_name AS (SELECT option_name FROM wp_options), copied AS (SELECT option_name FROM option_name) SELECT option_name FROM copied',
        'CREATE VIEW v AS WITH option_name AS (SELECT option_key FROM wp_options), copied AS (SELECT option_key FROM option_name) SELECT option_key FROM copied',
    ],
    'view cte alias matching column preserved in join' => [
        'CREATE VIEW v AS WITH option_name AS (SELECT option_name FROM wp_options) SELECT option_name.option_name FROM option_name JOIN wp_postmeta m ON m.meta_key = option_name.option_name',
        'CREATE VIEW v AS WITH option_name AS (SELECT option_key FROM wp_options) SELECT option_name.option_key FROM option_name JOIN wp_postmeta m ON m.meta_key = option_name.option_key',
    ],
    'view derived table alias matching column preserved' => [
        'CREATE VIEW v AS SELECT option_name.option_name FROM (SELECT option_name FROM wp_options) option_name',
        'CREATE VIEW v AS SELECT option_name.option_key FROM (SELECT option_key FROM wp_options) option_name',
    ],
    'view derived table as alias matching column preserved' => [
        'CREATE VIEW v AS SELECT option_name.option_name FROM (SELECT option_name FROM wp_options) AS option_name',
        'CREATE VIEW v AS SELECT option_name.option_key FROM (SELECT option_key FROM wp_options) AS option_name',
    ],
    'view compound derived alias matching column preserved' => [
        'CREATE VIEW v AS SELECT option_name.option_name FROM (SELECT option_name FROM wp_options UNION SELECT option_name FROM wp_options) option_name',
        'CREATE VIEW v AS SELECT option_name.option_key FROM (SELECT option_key FROM wp_options UNION SELECT option_key FROM wp_options) option_name',
    ],
    'view table-valued function alias matching column preserved' => [
        "CREATE VIEW v AS SELECT option_name.key FROM json_each('{\"option_name\":1}') option_name WHERE option_name.key = 'option_name'",
        "CREATE VIEW v AS SELECT option_name.key FROM json_each('{\"option_name\":1}') option_name WHERE option_name.key = 'option_name'",
    ],
    'trigger table alias matching column preserved' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT option_name.option_name FROM wp_options option_name WHERE option_name.option_name = new.option_name; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT option_name.option_key FROM wp_options option_name WHERE option_name.option_key = new.option_key; END',
    ],
    'trigger update body table alias matching column preserved' => [
        'CREATE TRIGGER trg AFTER UPDATE ON wp_options BEGIN UPDATE wp_options AS option_name SET option_name = new.option_name WHERE option_name.option_name = old.option_name; END',
        'CREATE TRIGGER trg AFTER UPDATE ON wp_options BEGIN UPDATE wp_options AS option_name SET option_key = new.option_key WHERE option_name.option_key = old.option_key; END',
    ],
    'trigger insert select implicit alias preserved' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN INSERT INTO audit(name) SELECT new.option_name option_name; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN INSERT INTO audit(name) SELECT new.option_key option_name; END',
    ],
    'trigger select implicit alias before semicolon preserved' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new.option_name option_name; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new.option_key option_name; END',
    ],
    'trigger select implicit alias before comma preserved' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new.option_name option_name, old.option_name FROM wp_options; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN SELECT new.option_key option_name, old.option_key FROM wp_options; END',
    ],
    'trigger cte name matching column preserved' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN WITH option_name AS (SELECT new.option_name AS value) INSERT INTO audit(name) SELECT value FROM option_name; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN WITH option_name AS (SELECT new.option_key AS value) INSERT INTO audit(name) SELECT value FROM option_name; END',
    ],
    'trigger recursive cte source name preserved' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN WITH RECURSIVE option_name(x) AS (SELECT new.option_name) INSERT INTO audit(name) SELECT x FROM option_name; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN WITH RECURSIVE option_name(x) AS (SELECT new.option_key) INSERT INTO audit(name) SELECT x FROM option_name; END',
    ],
    'trigger when alias qualifier preserved' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options WHEN EXISTS(SELECT 1 FROM wp_options option_name WHERE option_name.option_name = new.option_name) BEGIN SELECT 1; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options WHEN EXISTS(SELECT 1 FROM wp_options option_name WHERE option_name.option_key = new.option_key) BEGIN SELECT 1; END',
    ],
    'trigger update of list still rewritten with alias body preserved' => [
        'CREATE TRIGGER trg AFTER UPDATE OF option_name ON wp_options BEGIN SELECT option_name.option_name FROM wp_options option_name; END',
        'CREATE TRIGGER trg AFTER UPDATE OF option_key ON wp_options BEGIN SELECT option_name.option_key FROM wp_options option_name; END',
    ],
    'trigger instead of view update of list still rewritten' => [
        'CREATE TRIGGER trg INSTEAD OF UPDATE OF option_name ON active_options BEGIN UPDATE wp_options AS option_name SET option_name = new.option_name; END',
        'CREATE TRIGGER trg INSTEAD OF UPDATE OF option_key ON active_options BEGIN UPDATE wp_options AS option_name SET option_key = new.option_key; END',
    ],
    'trigger raise message and alias preserved' => [
        "CREATE TRIGGER trg BEFORE INSERT ON wp_options WHEN EXISTS(SELECT 1 FROM wp_options option_name WHERE option_name.option_name = new.option_name) BEGIN SELECT raise(abort, 'option_name'); END",
        "CREATE TRIGGER trg BEFORE INSERT ON wp_options WHEN EXISTS(SELECT 1 FROM wp_options option_name WHERE option_name.option_key = new.option_key) BEGIN SELECT raise(abort, 'option_name'); END",
    ],
    'view source table named like column preserved when unrelated' => [
        'CREATE VIEW v AS SELECT option_name FROM option_name',
        'CREATE VIEW v AS SELECT option_key FROM option_name',
    ],
    'view join source table named like column preserved when unrelated' => [
        'CREATE VIEW v AS SELECT wp_options.option_name FROM wp_options JOIN option_name ON option_name.id = wp_options.option_id',
        'CREATE VIEW v AS SELECT wp_options.option_key FROM wp_options JOIN option_name ON option_name.id = wp_options.option_id',
    ],
    'trigger delete source table named like column preserved' => [
        'CREATE TRIGGER trg AFTER DELETE ON wp_options BEGIN DELETE FROM option_name WHERE option_name.id = old.option_name; END',
        'CREATE TRIGGER trg AFTER DELETE ON wp_options BEGIN DELETE FROM option_name WHERE option_name.id = old.option_key; END',
    ],
    'trigger insert source table named like column preserved' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN INSERT INTO option_name(name) SELECT new.option_name; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN INSERT INTO option_name(name) SELECT new.option_key; END',
    ],
    'trigger update source table named like column preserved' => [
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN UPDATE option_name SET name = new.option_name; END',
        'CREATE TRIGGER trg AFTER INSERT ON wp_options BEGIN UPDATE option_name SET name = new.option_key; END',
    ],
    'index expression implicit alias not involved still rewrites' => [
        'CREATE INDEX idx ON wp_options((option_name || autoload)) WHERE option_name IS NOT NULL',
        'CREATE INDEX idx ON wp_options((option_key || autoload)) WHERE option_key IS NOT NULL',
    ],
    'table generated expression alias-like column still rewrites' => [
        'CREATE TABLE wp_options(option_name text, option_label text GENERATED ALWAYS AS (option_name || ":" || option_name) VIRTUAL)',
        'CREATE TABLE wp_options(option_key text, option_label text GENERATED ALWAYS AS (option_key || ":" || option_key) VIRTUAL)',
    ],
    'view implicit alias with comment preserved' => [
        "CREATE VIEW v AS SELECT option_name /* alias follows */ option_name FROM wp_options",
        "CREATE VIEW v AS SELECT option_key /* alias follows */ option_name FROM wp_options",
    ],
    'view table alias with comment preserved' => [
        "CREATE VIEW v AS SELECT option_name.option_name FROM wp_options /* table alias */ option_name",
        "CREATE VIEW v AS SELECT option_name.option_key FROM wp_options /* table alias */ option_name",
    ],
    'view cte source with comments preserved' => [
        "CREATE VIEW v AS WITH option_name AS (SELECT option_name /* column */ FROM wp_options) SELECT option_name FROM option_name",
        "CREATE VIEW v AS WITH option_name AS (SELECT option_key /* column */ FROM wp_options) SELECT option_key FROM option_name",
    ],
];

foreach ($cases as $name => [$sql, $expected]) {
    $tests['trigger view rename column current next28 ' . $name] = static function (TestRunner $t) use ($sql, $expected): void {
        $t->same($expected, SQLiteAlterTableRenamePlan::renameColumnSql($sql, 'wp_options', 'option_name', 'option_key'));
    };
}

return $tests;
