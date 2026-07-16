<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'kind' => 'core', 'bytes' => 20],
        ['option_id' => 2, 'option_name' => 'blogname', 'option_value' => 'Port Fixture', 'autoload' => 'yes', 'kind' => 'core', 'bytes' => 12],
        ['option_id' => 3, 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'autoload' => 'no', 'kind' => 'plugin', 'bytes' => 30],
        ['option_id' => 4, 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'autoload' => 'no', 'kind' => 'plugin', 'bytes' => 30],
        ['option_id' => 5, 'option_name' => 'plugin_queue', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['pending' => 2])), 'autoload' => 'no', 'kind' => 'plugin', 'bytes' => 25],
        ['option_id' => 6, 'option_name' => 'empty_option', 'option_value' => null, 'autoload' => 'no', 'kind' => 'empty', 'bytes' => 0],
    ],
];

$havingCases = [
    'having only ordered names keeps plugin group' => [
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(option_name ORDER BY option_id) LIKE '%plugin_queue%' ORDER BY autoload",
        ['no'],
    ],
    'having only distinct ordered names keeps core group' => [
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(DISTINCT option_name ORDER BY option_name) = '[\"blogname\",\"siteurl\"]'",
        ['yes'],
    ],
    'having only filtered names keeps no autoload' => [
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(option_name ORDER BY option_id) FILTER (WHERE kind = 'plugin') LIKE '%plugin_rules%'",
        ['no'],
    ],
    'having only filtered empty aggregate rejects core group' => [
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(option_name ORDER BY option_id) FILTER (WHERE kind = 'plugin') != '[]' ORDER BY autoload",
        ['no'],
    ],
    'having only jsonb distinct payload keeps plugin group' => [
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING jsonb_group_array(DISTINCT option_value ORDER BY option_id) FILTER (WHERE kind = 'plugin') IS NOT NULL ORDER BY autoload",
        ['no', 'yes'],
    ],
    'having compound json predicates keep no autoload' => [
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(option_name ORDER BY option_id) LIKE '%plugin%' AND json_group_array(DISTINCT option_name ORDER BY option_name) LIKE '%empty_option%'",
        ['no'],
    ],
    'having nested json predicate keeps core names' => [
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING (json_group_array(DISTINCT option_name ORDER BY option_name) = '[\"blogname\",\"siteurl\"]') OR autoload = 'missing'",
        ['yes'],
    ],
    'having not json predicate skips plugin rows' => [
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING NOT (json_group_array(option_name ORDER BY option_id) LIKE '%plugin%')",
        ['yes'],
    ],
    'having json with no selected aggregate still orders result' => [
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(option_name ORDER BY option_id) LIKE '%option%' ORDER BY autoload DESC",
        ['no'],
    ],
    'having implicit aggregate can use json summary' => [
        "SELECT count(*) AS rows FROM wp_options HAVING json_group_array(DISTINCT kind ORDER BY kind) = '[\"core\",\"empty\",\"plugin\"]'",
        [6],
    ],
    'having implicit aggregate can filter json summary' => [
        "SELECT count(*) AS rows FROM wp_options HAVING json_group_array(option_name ORDER BY option_id) FILTER (WHERE autoload = 'yes') LIKE '%blogname%'",
        [6],
    ],
    'having implicit aggregate can reject empty json filter' => [
        "SELECT count(*) AS rows FROM wp_options HAVING json_group_array(option_name ORDER BY option_id) FILTER (WHERE bytes < 0) != '[]'",
        [],
    ],
];

foreach ($havingCases as $name => [$sql, $expected]) {
    $tests['json aggregate grouped current next82 ' . $name] = static function (TestRunner $t) use ($tables, $sql, $expected): void {
        $rows = SQLiteSelectSql::execute($sql, $tables);
        $column = array_key_exists('rows', $rows[0] ?? []) ? 'rows' : 'autoload';

        $t->same($expected, array_map(static fn (array $row): mixed => $row[$column], $rows));
    };
}

$orderCases = [
    'order only json summary sorts plugin group first descending' => [
        "SELECT autoload FROM wp_options GROUP BY autoload ORDER BY json_group_array(option_name ORDER BY option_id) DESC",
        ['yes', 'no'],
    ],
    'order only json summary sorts core group first ascending' => [
        "SELECT autoload FROM wp_options GROUP BY autoload ORDER BY json_group_array(option_name ORDER BY option_id) ASC",
        ['no', 'yes'],
    ],
    'order only distinct json names can sort without projection' => [
        "SELECT autoload FROM wp_options GROUP BY autoload ORDER BY json_group_array(DISTINCT option_name ORDER BY option_name)",
        ['yes', 'no'],
    ],
    'order only filtered json names can sort without projection' => [
        "SELECT autoload FROM wp_options GROUP BY autoload ORDER BY json_group_array(option_name ORDER BY option_id) FILTER (WHERE kind = 'plugin') DESC",
        ['yes', 'no'],
    ],
    'order only jsonb aggregate can sort without projection' => [
        "SELECT autoload FROM wp_options GROUP BY autoload ORDER BY jsonb_group_array(DISTINCT option_name ORDER BY option_name) DESC",
        ['no', 'yes'],
    ],
    'order expression combines hidden json aggregate and scalar' => [
        "SELECT autoload FROM wp_options GROUP BY autoload ORDER BY json_group_array(DISTINCT option_name ORDER BY option_name) || autoload DESC",
        ['no', 'yes'],
    ],
    'order expression keeps selected json aggregate hidden width stripped' => [
        "SELECT autoload, count(*) AS rows FROM wp_options GROUP BY autoload ORDER BY json_group_array(option_name ORDER BY option_id) DESC",
        ['autoload', 'rows'],
    ],
    'implicit aggregate order json expression remains single row' => [
        "SELECT count(*) AS rows FROM wp_options ORDER BY json_group_array(option_name ORDER BY option_id)",
        [6],
    ],
];

foreach ($orderCases as $name => [$sql, $expected]) {
    $tests['json aggregate grouped current next82 ' . $name] = static function (TestRunner $t) use ($tables, $sql, $expected, $name): void {
        $rows = SQLiteSelectSql::execute($sql, $tables);
        if (str_contains($name, 'hidden width')) {
            $t->same($expected, array_keys($rows[0]));
            return;
        }
        $column = array_key_exists('rows', $rows[0] ?? []) ? 'rows' : 'autoload';
        $t->same($expected, array_map(static fn (array $row): mixed => $row[$column], $rows));
    };
}

foreach (['plugin_rules', 'plugin_queue', 'empty_option', 'siteurl', 'blogname'] as $optionName) {
    $tests['json aggregate grouped current next82 having distinct membership ' . $optionName] = static function (TestRunner $t) use ($tables, $optionName): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(DISTINCT option_name ORDER BY option_name) LIKE '%{$optionName}%' ORDER BY autoload",
            $tables,
        );

        $expected = in_array($optionName, ['siteurl', 'blogname'], true) ? ['yes'] : ['no'];
        $t->same($expected, array_map(static fn (array $row): mixed => $row['autoload'], $rows));
    };
}

foreach ([0, 12, 20, 25, 30] as $bytes) {
    $tests['json aggregate grouped current next82 having filter byte threshold ' . $bytes] = static function (TestRunner $t) use ($tables, $bytes): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(option_name ORDER BY option_id) FILTER (WHERE bytes >= {$bytes}) != '[]' ORDER BY autoload",
            $tables,
        );

        $expected = $bytes <= 20 ? ['no', 'yes'] : ['no'];
        $t->same($expected, array_map(static fn (array $row): mixed => $row['autoload'], $rows));
    };
}

$tests['json aggregate grouped current next82 having json aggregate no longer requires selected value aggregate'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(option_name ORDER BY option_id) LIKE '%siteurl%'",
        $tables,
    );

    $t->same([['autoload' => 'yes']], $rows);
};

$tests['json aggregate grouped current next82 order json aggregate no longer requires selected value aggregate'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload FROM wp_options GROUP BY autoload ORDER BY json_group_array(option_name ORDER BY option_id)',
        $tables,
    );

    $t->same([['autoload' => 'no'], ['autoload' => 'yes']], $rows);
};

$tests['json aggregate grouped current next82 having jsonb summary remains usable with selected scalar only'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING jsonb_group_array(DISTINCT option_name ORDER BY option_name) IS NOT NULL ORDER BY autoload",
        $tables,
    );

    $t->same(['no', 'yes'], array_map(static fn (array $row): mixed => $row['autoload'], $rows));
};

$tests['json aggregate grouped current next82 malformed order aggregate expression still rejects unsupported argument'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT autoload FROM wp_options GROUP BY autoload ORDER BY json_group_array(option_name || autoload)',
        $tables,
    ));
};

$tests['json aggregate grouped current next82 having aggregate accepts expression order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(option_name ORDER BY option_id + 1) IS NOT NULL ORDER BY autoload',
        $tables,
    );

    $t->same(['no', 'yes'], array_column($rows, 'autoload'));
};

$tests['json aggregate grouped current next82 malformed having distinct star still rejects'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(DISTINCT *) IS NOT NULL',
        $tables,
    ));
};

$tests['json aggregate grouped current next82 selected count with having json distinct does not require value column'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT autoload, count(*) AS total FROM wp_options GROUP BY autoload HAVING json_group_array(DISTINCT kind ORDER BY kind) LIKE '%plugin%' ORDER BY autoload",
        $tables,
    );

    $t->same([['autoload' => 'no', 'total' => 4]], $rows);
};

$tests['json aggregate grouped current next82 selected count with order json distinct strips hidden order summary'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload, count(*) AS total FROM wp_options GROUP BY autoload ORDER BY json_group_array(DISTINCT kind ORDER BY kind) DESC',
        $tables,
    );

    $t->same(['autoload', 'total'], array_keys($rows[0]));
};

$tests['json aggregate grouped current next82 having json aggregate can compare to selected alias value'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT autoload, count(*) AS total FROM wp_options GROUP BY autoload HAVING count(*) > 1 AND json_group_array(option_name ORDER BY option_id) LIKE '%blogname%'",
        $tables,
    );

    $t->same([['autoload' => 'yes', 'total' => 2]], $rows);
};

$tests['json aggregate grouped current next82 order json aggregate can follow having count alias'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload, count(*) AS total FROM wp_options GROUP BY autoload HAVING count(*) >= 2 ORDER BY json_group_array(option_name ORDER BY option_id)',
        $tables,
    );

    $t->same(['no', 'yes'], array_map(static fn (array $row): mixed => $row['autoload'], $rows));
};

return $tests;
