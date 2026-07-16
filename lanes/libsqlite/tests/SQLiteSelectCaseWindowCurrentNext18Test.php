<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 9, 'slot' => 'core'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 9, 'slot' => 'core'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 5, 'slot' => 'site'],
    ['option_id' => 4, 'option_name' => 'cron', 'autoload' => 'no', 'weight' => 4, 'slot' => 'system'],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 4, 'slot' => 'system'],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'no', 'weight' => 1, 'slot' => 'theme'],
    ['option_id' => 7, 'option_name' => 'plugin_alpha', 'autoload' => null, 'weight' => 3, 'slot' => 'plugin'],
    ['option_id' => 8, 'option_name' => 'plugin_beta', 'autoload' => null, 'weight' => 8, 'slot' => 'plugin'],
];

$tables = ['wp_options' => $rows];
$column = static fn (string $sql, string $name): array => array_column(SQLiteSelectSql::execute($sql, $tables), $name);

$cases = [
    'simple case projects autoload buckets' => [
        "SELECT option_name, CASE autoload WHEN 'yes' THEN 'autoloaded' WHEN 'no' THEN 'manual' ELSE 'unknown' END AS bucket FROM wp_options ORDER BY option_id",
        'bucket',
        ['autoloaded', 'autoloaded', 'autoloaded', 'manual', 'manual', 'manual', 'unknown', 'unknown'],
    ],
    'searched case projects numeric truthiness buckets' => [
        "SELECT option_name, CASE WHEN weight - 9 THEN 'not-nine' ELSE 'nine' END AS bucket FROM wp_options ORDER BY option_id",
        'bucket',
        ['nine', 'nine', 'not-nine', 'not-nine', 'not-nine', 'not-nine', 'not-nine', 'not-nine'],
    ],
    'case expression can feed where predicate' => [
        "SELECT option_name FROM wp_options WHERE CASE autoload WHEN 'yes' THEN 1 ELSE 0 END = 1 ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname'],
    ],
    'case expression where keeps null simple case non-match' => [
        "SELECT option_name FROM wp_options WHERE CASE autoload WHEN NULL THEN 1 ELSE 0 END = 0 ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'cron', 'rewrite_rules', 'theme_mods', 'plugin_alpha', 'plugin_beta'],
    ],
    'case expression order by puts plugin rows last' => [
        "SELECT option_name FROM wp_options ORDER BY CASE slot WHEN 'plugin' THEN 1 ELSE 0 END, option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'cron', 'rewrite_rules', 'theme_mods', 'plugin_alpha', 'plugin_beta'],
    ],
        'case expression order by can reverse manual rows' => [
        "SELECT option_name FROM wp_options ORDER BY CASE autoload WHEN 'no' THEN weight ELSE option_id END DESC, option_id",
        'option_name',
        ['plugin_beta', 'plugin_alpha', 'cron', 'rewrite_rules', 'blogname', 'home', 'siteurl', 'theme_mods'],
    ],
    'window order by case row numbers autoloaded before manual before unknown' => [
        "SELECT option_name, row_number() OVER (ORDER BY CASE autoload WHEN 'yes' THEN 0 WHEN 'no' THEN 1 ELSE 2 END, option_id) AS rn FROM wp_options ORDER BY option_id",
        'rn',
        [1, 2, 3, 4, 5, 6, 7, 8],
    ],
    'window order by case names use unknown rows after manual' => [
        "SELECT option_name, row_number() OVER (ORDER BY CASE autoload WHEN 'yes' THEN 0 WHEN 'no' THEN 1 ELSE 2 END, option_id) AS rn FROM wp_options ORDER BY rn",
        'option_name',
        ['siteurl', 'home', 'blogname', 'cron', 'rewrite_rules', 'theme_mods', 'plugin_alpha', 'plugin_beta'],
    ],
    'window order by searched case ranks heavy rows first' => [
        "SELECT option_name, row_number() OVER (ORDER BY CASE WHEN weight - 9 THEN 1 ELSE 0 END, option_id) AS rn FROM wp_options ORDER BY option_id",
        'rn',
        [1, 2, 3, 4, 5, 6, 7, 8],
    ],
    'window order by searched case can group nonmatching branch' => [
        "SELECT option_name, dense_rank() OVER (ORDER BY CASE WHEN weight - 9 THEN 'other' ELSE 'heavy' END) AS bucket FROM wp_options ORDER BY option_id",
        'bucket',
        [1, 1, 2, 2, 2, 2, 2, 2],
    ],
    'window partition by simple case resets manual unknown buckets' => [
        "SELECT option_name, row_number() OVER (PARTITION BY CASE autoload WHEN 'yes' THEN 'a' WHEN 'no' THEN 'm' ELSE 'u' END ORDER BY option_id) AS rn FROM wp_options ORDER BY option_id",
        'rn',
        [1, 2, 3, 1, 2, 3, 1, 2],
    ],
    'window partition by searched case resets heavy rows' => [
        "SELECT option_name, row_number() OVER (PARTITION BY CASE WHEN weight - 9 THEN 'other' ELSE 'heavy' END ORDER BY option_id) AS rn FROM wp_options ORDER BY option_id",
        'rn',
        [1, 2, 1, 2, 3, 4, 5, 6],
    ],
    'lag argument can be simple case label' => [
        "SELECT option_name, lag(CASE autoload WHEN 'yes' THEN 'auto' WHEN 'no' THEN 'manual' ELSE 'unknown' END, 1, 'start') OVER (ORDER BY option_id) AS previous_bucket FROM wp_options ORDER BY option_id",
        'previous_bucket',
        ['start', 'auto', 'auto', 'auto', 'manual', 'manual', 'manual', 'unknown'],
    ],
    'lead argument can be searched case label' => [
        "SELECT option_name, lead(CASE WHEN weight - 9 THEN slot ELSE 'top' END, 1, 'end') OVER (ORDER BY option_id) AS next_slot FROM wp_options ORDER BY option_id",
        'next_slot',
        ['top', 'site', 'system', 'system', 'theme', 'plugin', 'plugin', 'end'],
    ],
    'first value can read case expression per partition' => [
        "SELECT option_name, first_value(CASE slot WHEN 'plugin' THEN option_name ELSE autoload END) OVER (PARTITION BY CASE autoload WHEN 'yes' THEN 'a' WHEN 'no' THEN 'm' ELSE 'u' END ORDER BY option_id) AS first_label FROM wp_options ORDER BY option_id",
        'first_label',
        ['yes', 'yes', 'yes', 'no', 'no', 'no', 'plugin_alpha', 'plugin_alpha'],
    ],
    'last value can read case expression per partition' => [
        "SELECT option_name, last_value(CASE slot WHEN 'plugin' THEN option_name ELSE autoload END) OVER (PARTITION BY CASE autoload WHEN 'yes' THEN 'a' WHEN 'no' THEN 'm' ELSE 'u' END ORDER BY option_id) AS last_label FROM wp_options ORDER BY option_id",
        'last_label',
        ['yes', 'yes', 'yes', 'no', 'no', 'no', 'plugin_beta', 'plugin_beta'],
    ],
    'nth value can read case expression per partition' => [
        "SELECT option_name, nth_value(CASE slot WHEN 'plugin' THEN option_name ELSE autoload END, 2) OVER (PARTITION BY CASE autoload WHEN 'yes' THEN 'a' WHEN 'no' THEN 'm' ELSE 'u' END ORDER BY option_id) AS second_label FROM wp_options ORDER BY option_id",
        'second_label',
        ['yes', 'yes', 'yes', 'no', 'no', 'no', 'plugin_beta', 'plugin_beta'],
    ],
    'ntile argument can be case expression' => [
        "SELECT option_name, ntile(CASE autoload WHEN 'yes' THEN 2 ELSE 3 END) OVER (ORDER BY option_id) AS tile FROM wp_options ORDER BY option_id",
        'tile',
        [1, 1, 1, 1, 2, 2, 2, 2],
    ],
    'lag offset can be case expression' => [
        "SELECT option_name, lag(option_name, CASE slot WHEN 'plugin' THEN 2 ELSE 1 END, 'start') OVER (ORDER BY option_id) AS previous_name FROM wp_options ORDER BY option_id",
        'previous_name',
        ['start', 'siteurl', 'home', 'blogname', 'cron', 'rewrite_rules', 'theme_mods', 'plugin_alpha'],
    ],
    'lead offset can be case expression' => [
        "SELECT option_name, lead(option_name, CASE slot WHEN 'plugin' THEN 2 ELSE 1 END, 'end') OVER (ORDER BY option_id) AS next_name FROM wp_options ORDER BY option_id",
        'next_name',
        ['home', 'blogname', 'cron', 'rewrite_rules', 'theme_mods', 'plugin_alpha', 'plugin_beta', 'end'],
    ],
    'case default expression can read concatenation' => [
        "SELECT option_name, CASE slot WHEN 'core' THEN option_name ELSE slot || ':' || option_name END AS label FROM wp_options ORDER BY option_id",
        'label',
        ['siteurl', 'home', 'site:blogname', 'system:cron', 'system:rewrite_rules', 'theme:theme_mods', 'plugin:plugin_alpha', 'plugin:plugin_beta'],
    ],
    'nested case expression evaluates inner branch' => [
        "SELECT option_name, CASE autoload WHEN 'yes' THEN CASE slot WHEN 'core' THEN 'autoload-core' ELSE 'autoload-site' END ELSE 'other' END AS label FROM wp_options ORDER BY option_id",
        'label',
        ['autoload-core', 'autoload-core', 'autoload-site', 'other', 'other', 'other', 'other', 'other'],
    ],
    'case without else returns null' => [
        "SELECT option_name, CASE autoload WHEN 'yes' THEN 'auto' END AS label FROM wp_options ORDER BY option_id",
        'label',
        ['auto', 'auto', 'auto', null, null, null, null, null],
    ],
    'case null result participates in partition key' => [
        "SELECT option_name, row_number() OVER (PARTITION BY CASE autoload WHEN 'yes' THEN NULL ELSE autoload END ORDER BY option_id) AS rn FROM wp_options ORDER BY option_id",
        'rn',
        [1, 2, 3, 1, 2, 3, 4, 5],
    ],
    'case null result participates in order key' => [
        "SELECT option_name, row_number() OVER (ORDER BY CASE autoload WHEN 'yes' THEN NULL ELSE autoload END, option_id) AS rn FROM wp_options ORDER BY option_id",
        'rn',
        [1, 2, 3, 6, 7, 8, 4, 5],
    ],
    'case collate operand can sort nocase labels' => [
        "SELECT option_name FROM wp_options ORDER BY CASE slot WHEN 'plugin' THEN 'Alpha' ELSE 'beta' END COLLATE NOCASE, option_id",
        'option_name',
        ['plugin_alpha', 'plugin_beta', 'siteurl', 'home', 'blogname', 'cron', 'rewrite_rules', 'theme_mods'],
    ],
    'case expression can feed arithmetic order' => [
        "SELECT option_name, row_number() OVER (ORDER BY CASE autoload WHEN 'yes' THEN weight ELSE weight + 10 END DESC, option_id) AS rn FROM wp_options ORDER BY option_id",
        'rn',
        [6, 7, 8, 2, 3, 5, 4, 1],
    ],
    'case expression can feed json extraction in projection' => [
        "SELECT option_name, json_extract(CASE slot WHEN 'plugin' THEN '{\"enabled\":true}' ELSE '{\"enabled\":false}' END, '$.enabled') AS enabled FROM wp_options ORDER BY option_id",
        'enabled',
        [0, 0, 0, 0, 0, 0, 1, 1],
    ],
    'case expression can feed length function in window order' => [
        "SELECT option_name, row_number() OVER (ORDER BY length(CASE slot WHEN 'plugin' THEN option_name ELSE slot END), option_id) AS rn FROM wp_options ORDER BY option_id",
        'rn',
        [1, 2, 3, 5, 6, 4, 8, 7],
    ],
    'case expression aliases survive distinct rows' => [
        "SELECT DISTINCT CASE autoload WHEN 'yes' THEN 'auto' WHEN 'no' THEN 'manual' ELSE 'unknown' END AS bucket FROM wp_options ORDER BY bucket",
        'bucket',
        ['auto', 'manual', 'unknown'],
    ],
    'case expression final order can use alias' => [
        "SELECT option_name, CASE slot WHEN 'plugin' THEN 0 ELSE 1 END AS plugin_first FROM wp_options ORDER BY plugin_first, option_id",
        'option_name',
        ['plugin_alpha', 'plugin_beta', 'siteurl', 'home', 'blogname', 'cron', 'rewrite_rules', 'theme_mods'],
    ],
    'case expression supports blob branch equality' => [
        "SELECT option_name, CASE x'6162' WHEN x'6162' THEN 'blob-match' ELSE 'miss' END AS label FROM wp_options ORDER BY option_id LIMIT 3",
        'label',
        ['blob-match', 'blob-match', 'blob-match'],
    ],
    'case expression supports searched blob truthiness' => [
        "SELECT option_name, CASE WHEN x'31' THEN 'truthy' ELSE 'falsey' END AS label FROM wp_options ORDER BY option_id LIMIT 3",
        'label',
        ['truthy', 'truthy', 'truthy'],
    ],
    'case expression in limit is rejected as non integer literal boundary' => [
        "SELECT option_name FROM wp_options ORDER BY option_id LIMIT CASE autoload WHEN 'yes' THEN 1 ELSE 2 END",
        'error',
        InvalidArgumentException::class,
    ],
];

foreach ($cases as $name => [$sql, $field, $expected]) {
    $tests['select case window current next18 ' . $name] = static function (TestRunner $t) use ($sql, $field, $expected, $column, $tables): void {
        if ($field === 'error') {
            $t->throws($expected, static fn () => SQLiteSelectSql::execute($sql, $tables));
            return;
        }

        $t->same($expected, $column($sql, $field));
    };
}

$tests['select case window current next18 plan keeps case metadata in window order'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan("SELECT option_name, row_number() OVER (ORDER BY CASE autoload WHEN 'yes' THEN 0 ELSE 1 END, option_id) AS rn FROM wp_options", $tables);
    $t->same('case', $plan['select'][1]['orderBy'][0]['expression']['type']);
};

$tests['select case window current next18 plan keeps case metadata in partition'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan("SELECT option_name, row_number() OVER (PARTITION BY CASE autoload WHEN 'yes' THEN 0 ELSE 1 END ORDER BY option_id) AS rn FROM wp_options", $tables);
    $t->same('case', $plan['select'][1]['partitionBy'][0]['type']);
};

$tests['select case window current next18 rejects case without when'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute('SELECT CASE autoload ELSE 1 END AS bad FROM wp_options', $tables));
};

$tests['select case window current next18 rejects case missing then'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT CASE autoload WHEN 'yes' ELSE 1 END AS bad FROM wp_options", $tables));
};

$tests['select case window current next18 rejects unterminated case'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT CASE autoload WHEN 'yes' THEN 1 AS bad FROM wp_options", $tables));
};

$tests['select case window current next18 rejects empty else'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT CASE autoload WHEN 'yes' THEN 1 ELSE END AS bad FROM wp_options", $tables));
};

return $tests;
