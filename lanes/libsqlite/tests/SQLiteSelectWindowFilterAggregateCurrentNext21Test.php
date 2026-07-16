<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_size' => 10, 'include_flag' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'option_size' => 20, 'include_flag' => 0],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'no', 'option_size' => 30, 'include_flag' => 1],
    ['option_id' => 4, 'option_name' => 'cron', 'autoload' => 'no', 'option_size' => 40, 'include_flag' => 1],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'option_size' => 50, 'include_flag' => null],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'no', 'option_size' => null, 'include_flag' => 1],
];
$tables = ['wp_options' => $rows];

$column = static fn (string $sql, string $field): array => array_column(SQLiteSelectSql::execute($sql, $tables), $field);

$cases = [
    'autoload count current next row1' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 0, 2],
    'autoload count current next row2' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 1, 1],
    'autoload count current next row3' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 2, 0],
    'autoload count current next row4' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 3, 1],
    'autoload count current next row5' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 4, 1],
    'autoload count current next row6' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 5, 0],
    'autoload sum current next row1' => ["SELECT option_id, sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 0, 30],
    'autoload sum current next row2' => ["SELECT option_id, sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 1, 20],
    'autoload sum current next row3' => ["SELECT option_id, sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 2, null],
    'autoload sum current next row4' => ["SELECT option_id, sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 3, 50],
    'autoload sum current next row5' => ["SELECT option_id, sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 4, 50],
    'autoload sum current next row6' => ["SELECT option_id, sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 5, null],
    'autoload concat current next row1' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 0, 'siteurl,home'],
    'autoload concat current next row2' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 1, 'home'],
    'autoload concat current next row3' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 2, null],
    'autoload concat current next row4' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 3, 'rewrite_rules'],
    'autoload concat current next row5' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 4, 'rewrite_rules'],
    'autoload concat current next row6' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 5, null],
    'flag count star current next row1' => ['SELECT option_id, count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 0, 1],
    'flag count star current next row2' => ['SELECT option_id, count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 1, 1],
    'flag count star current next row3' => ['SELECT option_id, count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 2, 2],
    'flag count star current next row4' => ['SELECT option_id, count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 3, 1],
    'flag count star current next row5' => ['SELECT option_id, count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 4, 1],
    'flag count star current next row6' => ['SELECT option_id, count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 5, 1],
    'flag sum current next row1' => ['SELECT option_id, sum(option_size) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 0, 10],
    'flag sum current next row2' => ['SELECT option_id, sum(option_size) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 1, 30],
    'flag sum current next row3' => ['SELECT option_id, sum(option_size) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 2, 70],
    'flag sum current next row4' => ['SELECT option_id, sum(option_size) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 3, 40],
    'flag sum current next row5' => ['SELECT option_id, sum(option_size) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 4, null],
    'flag sum current next row6' => ['SELECT option_id, sum(option_size) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 5, null],
    'exclude current autoload count row1' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 0, 1],
    'exclude current autoload count row2' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 1, 0],
    'exclude current autoload count row3' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 2, 0],
    'exclude current autoload count row4' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 3, 1],
    'exclude current autoload count row5' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 4, 0],
    'exclude current autoload count row6' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 5, 0],
    'partitioned filter sum yes row1' => ["SELECT option_id, sum(option_size) FILTER (WHERE include_flag = 1) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 0, 10],
    'partitioned filter sum yes row2' => ["SELECT option_id, sum(option_size) FILTER (WHERE include_flag = 1) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 1, null],
    'partitioned filter sum no row3' => ["SELECT option_id, sum(option_size) FILTER (WHERE include_flag = 1) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 2, 70],
    'partitioned filter sum no row4' => ["SELECT option_id, sum(option_size) FILTER (WHERE include_flag = 1) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 3, 40],
    'partitioned filter sum yes row5' => ["SELECT option_id, sum(option_size) FILTER (WHERE include_flag = 1) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 4, null],
    'partitioned filter sum no row6' => ["SELECT option_id, sum(option_size) FILTER (WHERE include_flag = 1) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 5, null],
    'range filter count row1' => ["SELECT option_id, count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id RANGE BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 0, 2],
    'range filter count row2' => ["SELECT option_id, count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id RANGE BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 1, 1],
    'range filter count row4' => ["SELECT option_id, count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id RANGE BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 3, 1],
    'groups filter concat row1' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY autoload GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 0, 'siteurl,home,rewrite_rules'],
    'groups filter concat row3' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY autoload GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 2, 'siteurl,home,rewrite_rules'],
    'groups exclude ties filter concat row1' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY autoload GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE TIES) AS v FROM wp_options ORDER BY option_id", 'v', 0, 'siteurl'],
    'groups exclude group filter concat row3' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY autoload GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE GROUP) AS v FROM wp_options ORDER BY option_id", 'v', 2, 'siteurl,home,rewrite_rules'],
    'case filter sum row1' => ["SELECT option_id, sum(option_size) FILTER (WHERE CASE autoload WHEN 'yes' THEN 1 ELSE 0 END = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 0, 30],
    'case filter sum row3' => ["SELECT option_id, sum(option_size) FILTER (WHERE CASE autoload WHEN 'yes' THEN 1 ELSE 0 END = 1) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 2, null],
    'not null filter count row5' => ['SELECT option_id, count(*) FILTER (WHERE include_flag IS NOT NULL) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 4, 1],
    'null filter count row5' => ['SELECT option_id, count(*) FILTER (WHERE include_flag IS NULL) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id', 'v', 4, 1],
];

foreach ($cases as $name => [$sql, $field, $index, $expected]) {
    $tests['select window filter aggregate current next21 ' . $name] = static function (TestRunner $t) use ($column, $sql, $field, $index, $expected): void {
        $t->same($expected, $column($sql, $field)[$index]);
    };
}

$tests['select window filter aggregate current next21 plan records filter predicate'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan("SELECT sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS filtered_sum FROM wp_options", $tables);
    $t->same('=', $plan['select'][0]['filter']['operator']);
};

$tests['select window filter aggregate current next21 rejects filter without where'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT sum(option_size) FILTER (autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM wp_options", $tables));
};

$tests['select window filter aggregate current next21 rejects filter on ranking function'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT row_number() FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id) AS v FROM wp_options", $tables));
};

return $tests;
