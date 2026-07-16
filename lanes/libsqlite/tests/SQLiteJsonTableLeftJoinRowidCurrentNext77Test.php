<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha',
        'option_value' => '{"flags":["network","beta"],"rules":[{"name":"seo","priority":2},{"name":"cache","priority":7}]}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_empty',
        'option_value' => '{"flags":[],"rules":[]}',
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_null',
        'option_value' => null,
        'autoload' => 'no',
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_jsonb',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'flags' => ['jsonb', 'fast'],
            'rules' => [
                ['name' => 'media', 'priority' => 4],
            ],
        ])),
        'autoload' => 'yes',
    ],
];

$valueAt = static function (array $rows, string $path): mixed {
    if ($path === 'count') {
        return count($rows);
    }
    if ($path === 'rows') {
        return $rows;
    }
    if (str_starts_with($path, 'column.')) {
        return array_column($rows, substr($path, 7));
    }

    throw new InvalidArgumentException("Unknown result path {$path}");
};

$queries = [
    'matches rowid alias' => [
        "SELECT o.option_name AS option_name, f.rowid AS flag_rowid, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2 ORDER BY option_name",
        'rows',
        [
            ['option_name' => 'plugin_alpha', 'flag_rowid' => 2, 'flag' => 'beta'],
            ['option_name' => 'plugin_empty', 'flag_rowid' => null, 'flag' => null],
            ['option_name' => 'plugin_jsonb', 'flag_rowid' => 2, 'flag' => 'fast'],
            ['option_name' => 'plugin_null', 'flag_rowid' => null, 'flag' => null],
        ],
    ],
    'matches underscore rowid alias' => [
        "SELECT o.option_name AS option_name, f._rowid_ AS flag_rowid, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f._rowid_ = 1 ORDER BY option_name",
        'rows',
        [
            ['option_name' => 'plugin_alpha', 'flag_rowid' => 1, 'flag' => 'network'],
            ['option_name' => 'plugin_empty', 'flag_rowid' => null, 'flag' => null],
            ['option_name' => 'plugin_jsonb', 'flag_rowid' => 1, 'flag' => 'jsonb'],
            ['option_name' => 'plugin_null', 'flag_rowid' => null, 'flag' => null],
        ],
    ],
    'matches oid alias' => [
        "SELECT o.option_name AS option_name, f.oid AS flag_oid, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.oid = 2 ORDER BY option_name",
        'rows',
        [
            ['option_name' => 'plugin_alpha', 'flag_oid' => 2, 'flag' => 'beta'],
            ['option_name' => 'plugin_empty', 'flag_oid' => null, 'flag' => null],
            ['option_name' => 'plugin_jsonb', 'flag_oid' => 2, 'flag' => 'fast'],
            ['option_name' => 'plugin_null', 'flag_oid' => null, 'flag' => null],
        ],
    ],
    'preserves IN-list matches and null extension' => [
        "SELECT o.option_name AS option_name, f.rowid AS flag_rowid, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid IN (1, 2) ORDER BY option_name, flag_rowid",
        'column.flag',
        ['network', 'beta', null, 'jsonb', 'fast', null],
    ],
    'preserves range matches and null extension' => [
        "SELECT o.option_name AS option_name, f.rowid AS flag_rowid FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid >= 2 ORDER BY option_name, flag_rowid",
        'rows',
        [
            ['option_name' => 'plugin_alpha', 'flag_rowid' => 2],
            ['option_name' => 'plugin_empty', 'flag_rowid' => null],
            ['option_name' => 'plugin_jsonb', 'flag_rowid' => 2],
            ['option_name' => 'plugin_null', 'flag_rowid' => null],
        ],
    ],
    'null extends impossible rowid matches' => [
        "SELECT o.option_name AS option_name, f.rowid AS flag_rowid FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 99 ORDER BY option_name",
        'column.flag_rowid',
        [null, null, null, null],
    ],
    'combines rowid predicate with visible atom predicate' => [
        "SELECT o.option_name AS option_name, f.rowid AS flag_rowid, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2 AND f.atom GLOB 'b*' ORDER BY option_name",
        'rows',
        [
            ['option_name' => 'plugin_alpha', 'flag_rowid' => 2, 'flag' => 'beta'],
            ['option_name' => 'plugin_empty', 'flag_rowid' => null, 'flag' => null],
            ['option_name' => 'plugin_jsonb', 'flag_rowid' => null, 'flag' => null],
            ['option_name' => 'plugin_null', 'flag_rowid' => null, 'flag' => null],
        ],
    ],
    'matches json_tree nested rowids' => [
        "SELECT o.option_name AS option_name, j.rowid AS json_rowid, j.key AS json_key, j.atom AS json_atom FROM wp_options AS o LEFT JOIN json_tree(o.option_value, '$.rules') AS j ON j.rowid = 3 ORDER BY option_name",
        'rows',
        [
            ['option_name' => 'plugin_alpha', 'json_rowid' => 3, 'json_key' => 'priority', 'json_atom' => 2],
            ['option_name' => 'plugin_empty', 'json_rowid' => null, 'json_key' => null, 'json_atom' => null],
            ['option_name' => 'plugin_jsonb', 'json_rowid' => 3, 'json_key' => 'priority', 'json_atom' => 4],
            ['option_name' => 'plugin_null', 'json_rowid' => null, 'json_key' => null, 'json_atom' => null],
        ],
    ],
    'filters rowid misses after null extension' => [
        "SELECT o.option_name AS option_name FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 3 WHERE f.rowid IS NULL ORDER BY option_name",
        'column.option_name',
        ['plugin_alpha', 'plugin_empty', 'plugin_jsonb', 'plugin_null'],
    ],
    'filters rowid hits after ON pushdown' => [
        "SELECT o.option_name AS option_name FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2 WHERE f.rowid IS NOT NULL ORDER BY option_name",
        'column.option_name',
        ['plugin_alpha', 'plugin_jsonb'],
    ],
    'supports less-than-or-equal rowid alias predicate' => [
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid <= 1 ORDER BY option_name",
        'column.flag',
        ['network', null, 'jsonb', null],
    ],
    'supports greater-than rowid alias predicate' => [
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid > 1 ORDER BY option_name",
        'column.flag',
        ['beta', null, 'fast', null],
    ],
    'supports IS rowid alias predicate' => [
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid IS 1 ORDER BY option_name",
        'column.flag',
        ['network', null, 'jsonb', null],
    ],
    'supports IS NOT rowid alias predicate' => [
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid IS NOT 1 ORDER BY option_name",
        'column.flag',
        ['beta', null, 'fast', null],
    ],
    'supports IS NOT NULL rowid alias predicate' => [
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid IS NOT NULL ORDER BY option_name, f.rowid",
        'column.flag',
        ['network', 'beta', null, 'jsonb', 'fast', null],
    ],
    'supports IS NULL rowid alias predicate as all misses' => [
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid IS NULL ORDER BY option_name",
        'column.flag',
        [null, null, null, null],
    ],
    'supports IS DISTINCT FROM rowid alias predicate' => [
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid IS DISTINCT FROM 1 ORDER BY option_name",
        'column.flag',
        ['beta', null, 'fast', null],
    ],
    'supports IS NOT DISTINCT FROM rowid alias predicate' => [
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid IS NOT DISTINCT FROM 1 ORDER BY option_name",
        'column.flag',
        ['network', null, 'jsonb', null],
    ],
    'supports reversed equality rowid predicate' => [
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON 2 = f.rowid ORDER BY option_name",
        'column.flag',
        ['beta', null, 'fast', null],
    ],
    'supports underscore rowid range predicate' => [
        "SELECT o.option_name AS option_name, f._rowid_ AS flag_rowid FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f._rowid_ >= 2 ORDER BY option_name",
        'column.flag_rowid',
        [2, null, 2, null],
    ],
    'supports oid range predicate' => [
        "SELECT o.option_name AS option_name, f.oid AS flag_oid FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.oid <= 1 ORDER BY option_name",
        'column.flag_oid',
        [1, null, 1, null],
    ],
    'combines rowid with type predicate' => [
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2 AND f.type = 'text' ORDER BY option_name",
        'column.flag',
        ['beta', null, 'fast', null],
    ],
    'combines rowid with host predicate inside ON' => [
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2 AND o.autoload = 'yes' ORDER BY option_name",
        'column.flag',
        ['beta', null, 'fast', null],
    ],
    'preserves WHERE host filter after rowid join' => [
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2 WHERE o.autoload = 'yes' ORDER BY option_name",
        'rows',
        [
            ['option_name' => 'plugin_alpha', 'flag' => 'beta'],
            ['option_name' => 'plugin_jsonb', 'flag' => 'fast'],
        ],
    ],
    'groups rowid matches by host autoload' => [
        "SELECT o.autoload AS autoload, count(f.rowid) AS matched FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2 GROUP BY o.autoload ORDER BY autoload",
        'rows',
        [
            ['autoload' => 'no', 'matched' => 0],
            ['autoload' => 'yes', 'matched' => 2],
        ],
    ],
    'orders null extended rowids after matched rowids descending' => [
        "SELECT o.option_name AS option_name, f.rowid AS flag_rowid FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid IN (1, 2) ORDER BY flag_rowid DESC, option_name LIMIT 3",
        'rows',
        [
            ['option_name' => 'plugin_alpha', 'flag_rowid' => 2],
            ['option_name' => 'plugin_jsonb', 'flag_rowid' => 2],
            ['option_name' => 'plugin_alpha', 'flag_rowid' => 1],
        ],
    ],
    'supports comma limit after rowid alias join' => [
        "SELECT o.option_name AS option_name, f.rowid AS flag_rowid FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid IN (1, 2) ORDER BY option_name, flag_rowid LIMIT 2, 3",
        'rows',
        [
            ['option_name' => 'plugin_empty', 'flag_rowid' => null],
            ['option_name' => 'plugin_jsonb', 'flag_rowid' => 1],
            ['option_name' => 'plugin_jsonb', 'flag_rowid' => 2],
        ],
    ],
    'supports json_tree rowid IN predicate' => [
        "SELECT o.option_name AS option_name, j.rowid AS json_rowid FROM wp_options AS o LEFT JOIN json_tree(o.option_value, '$.rules') AS j ON j.rowid IN (3, 6) AND j.key = 'priority' ORDER BY option_name, json_rowid",
        'rows',
        [
            ['option_name' => 'plugin_alpha', 'json_rowid' => 3],
            ['option_name' => 'plugin_alpha', 'json_rowid' => 6],
            ['option_name' => 'plugin_empty', 'json_rowid' => null],
            ['option_name' => 'plugin_jsonb', 'json_rowid' => 3],
            ['option_name' => 'plugin_null', 'json_rowid' => null],
        ],
    ],
    'supports json_tree rowid miss over JSONB source' => [
        "SELECT o.option_name AS option_name, j.rowid AS json_rowid FROM wp_options AS o LEFT JOIN json_tree(o.option_value, '$.rules') AS j ON j.rowid = 6 AND j.key = 'priority' WHERE o.option_name = 'plugin_jsonb'",
        'rows',
        [['option_name' => 'plugin_jsonb', 'json_rowid' => null]],
    ],
    'supports rowid alias through DISTINCT projection' => [
        "SELECT DISTINCT f.rowid AS flag_rowid FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid IN (1, 2) ORDER BY flag_rowid",
        'column.flag_rowid',
        [null, 1, 2],
    ],
    'supports coalesce over null extended rowid alias' => [
        "SELECT o.option_name AS option_name, coalesce(f.rowid, 0) AS flag_rowid FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 9 ORDER BY option_name",
        'column.flag_rowid',
        [0, 0, 0, 0],
    ],
    'supports rowid alias from JSONB source only' => [
        "SELECT o.option_name AS option_name, f.rowid AS flag_rowid, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 1 WHERE o.option_name = 'plugin_jsonb'",
        'rows',
        [['option_name' => 'plugin_jsonb', 'flag_rowid' => 1, 'flag' => 'jsonb']],
    ],
    'supports rowid alias from SQL NULL source only' => [
        "SELECT o.option_name AS option_name, f.rowid AS flag_rowid FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 1 WHERE o.option_name = 'plugin_null'",
        'rows',
        [['option_name' => 'plugin_null', 'flag_rowid' => null]],
    ],
    'supports rowid alias from empty array source only' => [
        "SELECT o.option_name AS option_name, f.rowid AS flag_rowid FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 1 WHERE o.option_name = 'plugin_empty'",
        'rows',
        [['option_name' => 'plugin_empty', 'flag_rowid' => null]],
    ],
    'supports rowid alias with LIKE residual after join' => [
        "SELECT o.option_name AS option_name, f.atom AS flag FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2 WHERE o.option_name LIKE 'plugin_j%'",
        'rows',
        [['option_name' => 'plugin_jsonb', 'flag' => 'fast']],
    ],
    'supports rowid alias with NOT NULL atom residual' => [
        "SELECT o.option_name AS option_name FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2 WHERE f.atom IS NOT NULL ORDER BY option_name",
        'column.option_name',
        ['plugin_alpha', 'plugin_jsonb'],
    ],
    'supports rowid alias with NULL atom residual' => [
        "SELECT o.option_name AS option_name FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2 WHERE f.atom IS NULL ORDER BY option_name",
        'column.option_name',
        ['plugin_empty', 'plugin_null'],
    ],
];

$tests = [];
foreach ($queries as $name => [$sql, $path, $expected]) {
    $tests['sqlite json table left join rowid current next77 ' . $name] = static function (TestRunner $t) use ($sql, $path, $expected, $options, $valueAt): void {
        $t->same($expected, $valueAt(SQLiteSelectSql::execute($sql, ['wp_options' => $options]), $path));
    };
}

$tests['sqlite json table left join rowid current next77 plan normalizes rowid to id constraint'] = static function (TestRunner $t) use ($options): void {
    $plan = SQLiteSelectSql::plan(
        "SELECT o.option_name, f.atom FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2",
        ['wp_options' => $options],
    );
    $t->same('LEFT', $plan['joins'][0]['type']);
    $t->same('id', $plan['joins'][0]['jsonTableIndex']['constraints'][0]['column']);
    $t->same(2, $plan['joins'][0]['jsonTableIndex']['constraints'][0]['value']);
};

$tests['sqlite json table left join rowid current next77 plan normalizes underscore rowid to id constraint'] = static function (TestRunner $t) use ($options): void {
    $plan = SQLiteSelectSql::plan(
        "SELECT o.option_name, f.atom FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f._rowid_ = 1",
        ['wp_options' => $options],
    );
    $t->same('id', $plan['joins'][0]['jsonTableIndex']['constraints'][0]['column']);
    $t->same(1, $plan['joins'][0]['jsonTableIndex']['constraints'][0]['value']);
};

$tests['sqlite json table left join rowid current next77 plan normalizes oid to id constraint'] = static function (TestRunner $t) use ($options): void {
    $plan = SQLiteSelectSql::plan(
        "SELECT o.option_name, f.atom FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.oid = 1",
        ['wp_options' => $options],
    );
    $t->same('id', $plan['joins'][0]['jsonTableIndex']['constraints'][0]['column']);
    $t->same(1, $plan['joins'][0]['jsonTableIndex']['constraints'][0]['value']);
};

$tests['sqlite json table left join rowid current next77 indexed dynamic callback uses current row'] = static function (TestRunner $t) use ($options): void {
    $plan = SQLiteSelectSql::plan(
        "SELECT o.option_name, f.atom FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.flags') AS f ON f.rowid = 2",
        ['wp_options' => $options],
    );
    $rows = ($plan['joins'][0]['indexedDynamicRows'])($plan['from'][0]);
    $t->same(1, count($rows));
    $t->same('beta', $rows[0]['f.atom']);
    $t->same(2, $rows[0]['f.rowid']);
};

return $tests;
