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
    ['option_id' => 7, 'option_name' => 'object_cache', 'autoload' => 'yes', 'option_size' => 70, 'include_flag' => 1],
];
$tables = ['wp_options' => $rows];

$column = static fn (string $sql, string $field): array => array_column(SQLiteSelectSql::execute($sql, $tables), $field);

$cases = [
    'preceding current autoload sum row1' => ["SELECT option_id, sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 0, 10],
    'preceding current autoload sum row2' => ["SELECT option_id, sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 1, 30],
    'preceding current autoload sum row3' => ["SELECT option_id, sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 2, 20],
    'preceding current autoload sum row4' => ["SELECT option_id, sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 3, null],
    'preceding current autoload sum row5' => ["SELECT option_id, sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 4, 50],
    'preceding current autoload sum row6' => ["SELECT option_id, sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 5, 50],
    'preceding current autoload sum row7' => ["SELECT option_id, sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 6, 70],
    'preceding current flag count row1' => ['SELECT option_id, count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id', 'v', 0, 1],
    'preceding current flag count row2' => ['SELECT option_id, count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id', 'v', 1, 1],
    'preceding current flag count row3' => ['SELECT option_id, count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id', 'v', 2, 1],
    'preceding current flag count row4' => ['SELECT option_id, count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id', 'v', 3, 2],
    'preceding current flag count row5' => ['SELECT option_id, count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id', 'v', 4, 1],
    'preceding current flag count row6' => ['SELECT option_id, count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id', 'v', 5, 1],
    'preceding current flag count row7' => ['SELECT option_id, count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id', 'v', 6, 2],
    'preceding following concat row1' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 0, 'siteurl,home'],
    'preceding following concat row2' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 1, 'siteurl,home'],
    'preceding following concat row3' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 2, 'siteurl,home'],
    'preceding following concat row4' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 3, 'home,rewrite_rules'],
    'preceding following concat row5' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 4, 'rewrite_rules'],
    'preceding following concat row6' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 5, 'rewrite_rules,object_cache'],
    'preceding following concat row7' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND 1 FOLLOWING) AS v FROM wp_options ORDER BY option_id", 'v', 6, 'rewrite_rules,object_cache'],
    'exclude current preceding current count row1' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 0, 0],
    'exclude current preceding current count row2' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 1, 1],
    'exclude current preceding current count row3' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 2, 1],
    'exclude current preceding current count row4' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 3, 0],
    'exclude current preceding current count row5' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 4, 0],
    'exclude current preceding current count row6' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 5, 1],
    'exclude current preceding current count row7' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 6, 0],
    'range preceding current count row1' => ["SELECT option_id, count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id RANGE BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 0, 1],
    'range preceding current count row2' => ["SELECT option_id, count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id RANGE BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 1, 2],
    'range preceding current count row3' => ["SELECT option_id, count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id RANGE BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 2, 1],
    'range preceding current count row4' => ["SELECT option_id, count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id RANGE BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 3, 0],
    'range preceding current count row5' => ["SELECT option_id, count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id RANGE BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 4, 1],
    'range preceding current count row6' => ["SELECT option_id, count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id RANGE BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 5, 1],
    'range preceding current count row7' => ["SELECT option_id, count(*) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id RANGE BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 6, 1],
    'groups preceding ties concat row1' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY autoload GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES) AS v FROM wp_options ORDER BY option_id", 'v', 0, 'siteurl'],
    'groups preceding ties concat row2' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY autoload GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES) AS v FROM wp_options ORDER BY option_id", 'v', 1, 'home'],
    'groups preceding ties concat row3' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY autoload GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES) AS v FROM wp_options ORDER BY option_id", 'v', 2, null],
    'groups preceding ties concat row4' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY autoload GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES) AS v FROM wp_options ORDER BY option_id", 'v', 3, null],
    'groups preceding ties concat row5' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY autoload GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES) AS v FROM wp_options ORDER BY option_id", 'v', 4, 'rewrite_rules'],
    'groups preceding ties concat row6' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY autoload GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES) AS v FROM wp_options ORDER BY option_id", 'v', 5, null],
    'groups preceding ties concat row7' => ["SELECT option_id, group_concat(option_name) FILTER (WHERE autoload = 'yes') OVER (ORDER BY autoload GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES) AS v FROM wp_options ORDER BY option_id", 'v', 6, 'object_cache'],
    'current current count row1' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 0, 1],
    'current current count row3' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 2, 0],
    'current current count row5' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS v FROM wp_options ORDER BY option_id", 'v', 4, 1],
    'current current exclude group row1' => ["SELECT option_id, count(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW EXCLUDE GROUP) AS v FROM wp_options ORDER BY option_id", 'v', 0, 0],
];

foreach ($cases as $name => [$sql, $field, $index, $expected]) {
    $tests['select window exclude filter current next27 ' . $name] = static function (TestRunner $t) use ($column, $sql, $field, $index, $expected): void {
        $t->same($expected, $column($sql, $field)[$index]);
    };
}

$tests['select window exclude filter current next27 plan records preceding frame'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan("SELECT sum(option_size) FILTER (WHERE autoload = 'yes') OVER (ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND CURRENT ROW EXCLUDE CURRENT ROW) AS v FROM wp_options", $tables);
    $t->same(['unit' => 'ROWS', 'preceding' => 2, 'following' => 0, 'exclude' => 'CURRENT ROW', 'startBoundary' => '2 PRECEDING', 'endBoundary' => 'CURRENT ROW'], $plan['select'][0]['frame']);
};

$tests['select window exclude filter current next27 plan records preceding following frame'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan("SELECT count(*) FILTER (WHERE include_flag = 1) OVER (ORDER BY option_id RANGE BETWEEN 1.5 PRECEDING AND 2 FOLLOWING) AS v FROM wp_options", $tables);
    $t->same(['unit' => 'RANGE', 'preceding' => 1.5, 'following' => 2, 'exclude' => 'NO OTHERS', 'startBoundary' => '1.5 PRECEDING', 'endBoundary' => '2 FOLLOWING'], $plan['select'][0]['frame']);
};

$tests['select window exclude filter current next27 accepts following following frame'] = static function (TestRunner $t) use ($column): void {
    $t->same(
        [2, 2, 2, 2, 2, 1, 0],
        $column("SELECT count(*) OVER (ORDER BY option_id ROWS BETWEEN 1 FOLLOWING AND 2 FOLLOWING) AS v FROM wp_options", 'v'),
    );
};

$tests['select window exclude filter current next27 accepts preceding preceding frame'] = static function (TestRunner $t) use ($column): void {
    $t->same(
        [0, 1, 2, 2, 2, 2, 2],
        $column("SELECT count(*) OVER (ORDER BY option_id ROWS BETWEEN 2 PRECEDING AND 1 PRECEDING) AS v FROM wp_options", 'v'),
    );
};

$tests['select window exclude filter current next27 rejects bare numeric bound'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT count(*) OVER (ORDER BY option_id ROWS BETWEEN 1 AND CURRENT ROW) AS v FROM wp_options", $tables));
};

return $tests;
