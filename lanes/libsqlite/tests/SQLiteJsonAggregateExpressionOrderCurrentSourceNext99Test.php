<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'alpha', 'priority' => 10, 'bonus' => 0, 'enabled' => 1, 'bucket' => 'core'],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'beta', 'priority' => 20, 'bonus' => 5, 'enabled' => 1, 'bucket' => 'core'],
        ['option_id' => 3, 'autoload' => 'yes', 'option_name' => 'alpha', 'priority' => 30, 'bonus' => 0, 'enabled' => 1, 'bucket' => 'core'],
        ['option_id' => 4, 'autoload' => 'yes', 'option_name' => 'gamma', 'priority' => 15, 'bonus' => 10, 'enabled' => 0, 'bucket' => 'core'],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'delta', 'priority' => 18, 'bonus' => 4, 'enabled' => 1, 'bucket' => 'plugin'],
        ['option_id' => 6, 'autoload' => 'no', 'option_name' => 'epsilon', 'priority' => 20, 'bonus' => -10, 'enabled' => 1, 'bucket' => 'plugin'],
        ['option_id' => 7, 'autoload' => 'no', 'option_name' => 'beta', 'priority' => 5, 'bonus' => 100, 'enabled' => 1, 'bucket' => 'plugin'],
        ['option_id' => 8, 'autoload' => 'no', 'option_name' => 'zeta', 'priority' => 0, 'bonus' => 0, 'enabled' => 1, 'bucket' => 'plugin'],
    ],
];

$tests['json aggregate expression order current source next99 implicit desc expression order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY priority + bonus DESC) FILTER (WHERE enabled) AS names FROM wp_options',
        $tables,
    );

    $t->same('["beta","alpha","delta","epsilon","zeta"]', $rows[0]['names']);
};

$tests['json aggregate expression order current source next99 implicit asc expression order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY priority + bonus ASC) FILTER (WHERE enabled) AS names FROM wp_options',
        $tables,
    );

    $t->same('["zeta","alpha","epsilon","delta","beta"]', $rows[0]['names']);
};

$tests['json aggregate expression order current source next99 grouped expression order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload, json_group_array(DISTINCT option_name ORDER BY priority + bonus DESC) FILTER (WHERE enabled) AS names FROM wp_options GROUP BY autoload ORDER BY autoload',
        $tables,
    );

    $t->same('["beta","delta","epsilon","zeta"]', $rows[0]['names']);
    $t->same('["alpha","beta"]', $rows[1]['names']);
};

$tests['json aggregate expression order current source next99 having reads expression ordered hidden summary'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(DISTINCT option_name ORDER BY priority + bonus DESC) FILTER (WHERE enabled) LIKE '[\"beta\"%' ORDER BY autoload",
        $tables,
    );

    $t->same(['no'], array_column($rows, 'autoload'));
};

$tests['json aggregate expression order current source next99 final order reads expression ordered hidden summary'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload FROM wp_options GROUP BY autoload ORDER BY json_group_array(DISTINCT option_name ORDER BY priority + bonus DESC) FILTER (WHERE enabled) DESC',
        $tables,
    );

    $t->same(['no', 'yes'], array_column($rows, 'autoload'));
};

$tests['json aggregate expression order current source next99 jsonb expression order decodes'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT jsonb_group_array(DISTINCT option_name ORDER BY priority + bonus DESC) FILTER (WHERE enabled) AS names FROM wp_options',
        $tables,
    );

    $t->true($rows[0]['names'] instanceof SQLiteBlobValue);
    $t->same(['beta', 'alpha', 'delta', 'epsilon', 'zeta'], SQLiteJsonB::decode($rows[0]['names']->bytes));
};

$orderCases = [
    'priority plus bonus desc' => ['priority + bonus DESC', '["beta","alpha","delta","epsilon","zeta"]'],
    'priority plus bonus asc' => ['priority + bonus ASC', '["zeta","alpha","epsilon","delta","beta"]'],
    'priority minus bonus desc' => ['priority - bonus DESC', '["alpha","epsilon","beta","delta","zeta"]'],
    'priority times option desc' => ['priority * option_id DESC', '["epsilon","alpha","delta","beta","zeta"]'],
    'option plus priority asc' => ['option_id + priority ASC', '["zeta","alpha","beta","delta","epsilon"]'],
    'option plus priority desc' => ['option_id + priority DESC', '["alpha","epsilon","delta","beta","zeta"]'],
    'case expression desc' => ["CASE bucket WHEN 'plugin' THEN priority + bonus ELSE priority END DESC", '["beta","alpha","delta","epsilon","zeta"]'],
    'case expression asc' => ["CASE bucket WHEN 'plugin' THEN priority + bonus ELSE priority END ASC", '["zeta","alpha","epsilon","beta","delta"]'],
];

foreach ($orderCases as $name => [$orderSql, $expected]) {
    $tests['json aggregate expression order current source next99 order case ' . $name] = static function (TestRunner $t) use ($tables, $orderSql, $expected): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT json_group_array(DISTINCT option_name ORDER BY {$orderSql}) FILTER (WHERE enabled) AS names FROM wp_options",
            $tables,
        );

        $t->same($expected, $rows[0]['names']);
    };
}

$filterCases = [
    "enabled" => '["beta","alpha","delta","epsilon","zeta"]',
    "autoload = 'no'" => '["beta","delta","epsilon","zeta"]',
    "autoload = 'yes'" => '["alpha","beta"]',
    "bucket = 'plugin'" => '["beta","delta","epsilon","zeta"]',
    "bucket = 'core'" => '["alpha","beta"]',
    "priority >= 18" => '["alpha","beta","delta","epsilon"]',
    "priority < 18" => '["beta","alpha","zeta"]',
    "bonus >= 0" => '["beta","alpha","delta","zeta"]',
    "bonus < 0" => '["epsilon"]',
    "option_id BETWEEN 2 AND 7" => '["beta","alpha","delta","epsilon"]',
    "option_id NOT BETWEEN 2 AND 7" => '["alpha","zeta"]',
    "option_name LIKE '%ta'" => '["beta","delta","zeta"]',
    "option_name GLOB '*a'" => '["beta","alpha","delta","zeta"]',
];

foreach ($filterCases as $filterSql => $expected) {
    $tests['json aggregate expression order current source next99 filter ' . $filterSql] = static function (TestRunner $t) use ($tables, $filterSql, $expected): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT json_group_array(DISTINCT option_name ORDER BY priority + bonus DESC) FILTER (WHERE enabled AND {$filterSql}) AS names FROM wp_options",
            $tables,
        );

        $t->same($expected, $rows[0]['names']);
    };
}

$groupCases = [
    'minimum 0' => [0, '["beta","delta","epsilon","zeta"]', '["alpha","beta"]'],
    'minimum 10' => [10, '["delta","epsilon"]', '["alpha","beta"]'],
    'minimum 20' => [20, '["epsilon"]', '["alpha","beta"]'],
    'minimum 30' => [30, '[]', '["alpha"]'],
    'minimum 100' => [100, '[]', '[]'],
];

foreach ($groupCases as $name => [$minimum, $expectedNo, $expectedYes]) {
    $tests['json aggregate expression order current source next99 grouped priority filter ' . $name] = static function (TestRunner $t) use ($tables, $minimum, $expectedNo, $expectedYes): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT autoload, json_group_array(DISTINCT option_name ORDER BY priority + bonus DESC) FILTER (WHERE enabled AND priority >= {$minimum}) AS names FROM wp_options GROUP BY autoload ORDER BY autoload",
            $tables,
        );

        $t->same($expectedNo, $rows[0]['names']);
        $t->same($expectedYes, $rows[1]['names']);
    };
}

foreach ([
    'beta first no' => ['%beta%', ['no', 'yes']],
    'delta no only' => ['%delta%', ['no']],
    'epsilon no only' => ['%epsilon%', ['no']],
    'alpha yes only' => ['%alpha%', ['yes']],
    'zeta no only' => ['%zeta%', ['no']],
    'gamma filtered out' => ['%gamma%', []],
    'beta before delta' => ['%beta%delta%', ['no']],
    'alpha before beta' => ['%alpha%beta%', ['yes']],
] as $name => [$pattern, $expectedAutoloads]) {
    $tests['json aggregate expression order current source next99 grouped having membership ' . $name] = static function (TestRunner $t) use ($tables, $pattern, $expectedAutoloads): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(DISTINCT option_name ORDER BY priority + bonus DESC) FILTER (WHERE enabled) LIKE '{$pattern}' ORDER BY autoload",
            $tables,
        );

        $t->same($expectedAutoloads, array_column($rows, 'autoload'));
    };
}

$tests['json aggregate expression order current source next99 expression and column order summaries do not collide'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT json_group_array(DISTINCT option_name ORDER BY priority + bonus DESC) FILTER (WHERE enabled) AS expr_names, json_group_array(DISTINCT option_name ORDER BY priority DESC) FILTER (WHERE enabled) AS column_names FROM wp_options',
        $tables,
    );

    $t->same('["beta","alpha","delta","epsilon","zeta"]', $rows[0]['expr_names']);
    $t->same('["alpha","beta","epsilon","delta","zeta"]', $rows[0]['column_names']);
};

$tests['json aggregate expression order current source next99 grouped expression aliases remain visible'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT autoload, json_group_array(DISTINCT option_name ORDER BY priority + bonus DESC) FILTER (WHERE enabled) AS option_summary FROM wp_options GROUP BY autoload ORDER BY autoload',
        $tables,
    );

    $t->same(['autoload' => 'no', 'option_summary' => '["beta","delta","epsilon","zeta"]'], $rows[0]);
    $t->same(['autoload' => 'yes', 'option_summary' => '["alpha","beta"]'], $rows[1]);
};

$tests['json aggregate expression order current source next99 no matching filter remains empty array'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT json_group_array(DISTINCT option_name ORDER BY priority + bonus DESC) FILTER (WHERE enabled AND option_name = 'missing') AS names FROM wp_options",
        $tables,
    );

    $t->same('[]', $rows[0]['names']);
};

return $tests;
