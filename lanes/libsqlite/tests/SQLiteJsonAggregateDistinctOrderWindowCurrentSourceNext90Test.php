<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'alpha', 'rank' => 2, 'tie' => 'b', 'enabled' => 1, 'payload' => new SQLiteJsonSubtypeValue('{"name":"alpha"}')],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'beta', 'rank' => 2, 'tie' => 'a', 'enabled' => 1, 'payload' => new SQLiteJsonSubtypeValue('{"name":"beta"}')],
        ['option_id' => 3, 'autoload' => 'yes', 'option_name' => 'alpha', 'rank' => 1, 'tie' => 'z', 'enabled' => 1, 'payload' => new SQLiteJsonSubtypeValue('{"name":"alpha"}')],
        ['option_id' => 4, 'autoload' => 'yes', 'option_name' => 'gamma', 'rank' => 3, 'tie' => 'c', 'enabled' => 0, 'payload' => new SQLiteJsonSubtypeValue('{"name":"gamma"}')],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'delta', 'rank' => 1, 'tie' => 'a', 'enabled' => 1, 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['name' => 'delta']))],
        ['option_id' => 6, 'autoload' => 'no', 'option_name' => 'epsilon', 'rank' => 1, 'tie' => 'b', 'enabled' => 1, 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['name' => 'epsilon']))],
        ['option_id' => 7, 'autoload' => 'no', 'option_name' => 'delta', 'rank' => 2, 'tie' => 'a', 'enabled' => 1, 'payload' => new SQLiteBlobValue(SQLiteJsonB::encode(['name' => 'delta']))],
        ['option_id' => 8, 'autoload' => 'no', 'option_name' => null, 'rank' => 3, 'tie' => 'n', 'enabled' => 1, 'payload' => null],
    ],
];

$rowsByOptionId = static function (string $sql) use ($tables): array {
    $rows = SQLiteSelectSql::execute($sql, $tables);
    $byId = [];
    foreach ($rows as $row) {
        $byId[$row['option_id']] = $row;
    }

    return $byId;
};

$baseSql = "SELECT option_id, json_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id";
$baseRows = static fn (): array => $rowsByOptionId($baseSql);

foreach ([
    1 => '["gamma","beta","alpha"]',
    2 => '["gamma","beta","alpha"]',
    3 => '["gamma","alpha"]',
    4 => '["gamma"]',
    5 => '[null,"delta","epsilon"]',
    6 => '[null,"delta","epsilon"]',
    7 => '[null,"delta"]',
    8 => '[null]',
] as $optionId => $expected) {
    $tests['json aggregate distinct order window current source next90 rows frame multi term id ' . $optionId] = static function (TestRunner $t) use ($baseRows, $optionId, $expected): void {
        $rows = $baseRows();
        $t->same($expected, $rows[$optionId]['frame_json']);
    };
}

$tests['json aggregate distinct order window current source next90 secondary asc chooses beta before alpha'] = static function (TestRunner $t) use ($baseRows): void {
    $rows = $baseRows();
    $t->same('["gamma","beta","alpha"]', $rows[1]['frame_json']);
};

$tests['json aggregate distinct order window current source next90 null value sorts by aggregate rank before distinct'] = static function (TestRunner $t) use ($baseRows): void {
    $rows = $baseRows();
    $t->same('[null,"delta","epsilon"]', $rows[5]['frame_json']);
};

$filterRows = static fn (): array => $rowsByOptionId(
    "SELECT option_id, json_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id",
);

foreach ([
    1 => '["beta","alpha"]',
    2 => '["beta","alpha"]',
    3 => '["alpha"]',
    4 => '[]',
    5 => '[null,"delta","epsilon"]',
    6 => '[null,"delta","epsilon"]',
    7 => '[null,"delta"]',
    8 => '[null]',
] as $optionId => $expected) {
    $tests['json aggregate distinct order window current source next90 filter before distinct id ' . $optionId] = static function (TestRunner $t) use ($filterRows, $optionId, $expected): void {
        $rows = $filterRows();
        $t->same($expected, $rows[$optionId]['frame_json']);
    };
}

$secondaryDescRows = static fn (): array => $rowsByOptionId(
    "SELECT option_id, json_group_array(DISTINCT option_name ORDER BY rank DESC, tie DESC) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id",
);

foreach ([
    1 => '["gamma","alpha","beta"]',
    2 => '["gamma","beta","alpha"]',
    5 => '[null,"delta","epsilon"]',
    6 => '[null,"delta","epsilon"]',
] as $optionId => $expected) {
    $tests['json aggregate distinct order window current source next90 secondary desc id ' . $optionId] = static function (TestRunner $t) use ($secondaryDescRows, $optionId, $expected): void {
        $rows = $secondaryDescRows();
        $t->same($expected, $rows[$optionId]['frame_json']);
    };
}

$groupsRows = static fn (): array => $rowsByOptionId(
    "SELECT option_id, json_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC) OVER (PARTITION BY autoload ORDER BY rank GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id",
);

foreach ([
    1 => '["gamma","beta","alpha"]',
    2 => '["gamma","beta","alpha"]',
    3 => '["beta","alpha"]',
    4 => '["gamma"]',
    5 => '["delta","epsilon"]',
    6 => '["delta","epsilon"]',
    7 => '[null,"delta"]',
    8 => '[null]',
] as $optionId => $expected) {
    $tests['json aggregate distinct order window current source next90 groups frame id ' . $optionId] = static function (TestRunner $t) use ($groupsRows, $optionId, $expected): void {
        $rows = $groupsRows();
        $t->same($expected, $rows[$optionId]['frame_json']);
    };
}

$rangeRows = static fn (): array => $rowsByOptionId(
    "SELECT option_id, json_group_array(DISTINCT option_name ORDER BY tie ASC, option_id DESC) OVER (PARTITION BY autoload ORDER BY rank RANGE BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id",
);

foreach ([
    1 => '["beta","alpha","gamma"]',
    2 => '["beta","alpha","gamma"]',
    3 => '["beta","alpha"]',
    4 => '["gamma"]',
    5 => '["delta","epsilon"]',
    6 => '["delta","epsilon"]',
    7 => '["delta",null]',
    8 => '[null]',
] as $optionId => $expected) {
    $tests['json aggregate distinct order window current source next90 range frame id ' . $optionId] = static function (TestRunner $t) use ($rangeRows, $optionId, $expected): void {
        $rows = $rangeRows();
        $t->same($expected, $rows[$optionId]['frame_json']);
    };
}

$excludeRows = static fn (): array => $rowsByOptionId(
    "SELECT option_id, json_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC) OVER (PARTITION BY autoload ORDER BY rank GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS frame_json FROM wp_options ORDER BY option_id",
);

foreach ([
    1 => '["gamma","beta"]',
    2 => '["gamma","alpha"]',
    3 => '["beta","alpha"]',
    4 => '[]',
    5 => '["delta","epsilon"]',
    6 => '["delta"]',
    7 => '[null]',
    8 => '[]',
] as $optionId => $expected) {
    $tests['json aggregate distinct order window current source next90 exclude current id ' . $optionId] = static function (TestRunner $t) use ($excludeRows, $optionId, $expected): void {
        $rows = $excludeRows();
        $t->same($expected, $rows[$optionId]['frame_json']);
    };
}

$tests['json aggregate distinct order window current source next90 jsonb decodes multi term rows frame'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT option_id, jsonb_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS frame_jsonb FROM wp_options ORDER BY option_id",
        $tables,
    );

    $t->true($rows[0]['frame_jsonb'] instanceof SQLiteBlobValue);
    $t->same(['gamma', 'beta', 'alpha'], SQLiteJsonB::decode($rows[0]['frame_jsonb']->bytes));
};

$tests['json aggregate distinct order window current source next90 json subtype payload distinct survives multi term order'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT option_id, json_group_array(DISTINCT payload ORDER BY rank DESC, tie ASC) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id",
        $tables,
    );

    $t->same('[{"name":"gamma"},{"name":"beta"},{"name":"alpha"}]', $rows[0]['frame_json']);
};

$tests['json aggregate distinct order window current source next90 jsonb payload distinct keeps blob class separate'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT option_id, json_group_array(DISTINCT payload ORDER BY rank DESC, tie ASC) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id",
        $tables,
    );

    $t->same('[null,{"name":"delta"},{"name":"epsilon"}]', $rows[4]['frame_json']);
};

foreach ([
    'rank DESC, tie ASC, option_id DESC' => '["gamma","beta","alpha"]',
    'enabled ASC, rank DESC, tie ASC' => '["gamma","beta","alpha"]',
    'autoload ASC, rank DESC, tie ASC' => '["gamma","beta","alpha"]',
    'tie ASC, rank DESC' => '["beta","alpha","gamma"]',
    'tie DESC, rank ASC' => '["alpha","gamma","beta"]',
] as $orderSql => $expected) {
    $tests['json aggregate distinct order window current source next90 multi term variant ' . $orderSql] = static function (TestRunner $t) use ($tables, $orderSql, $expected): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT option_id, json_group_array(DISTINCT option_name ORDER BY {$orderSql}) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS frame_json FROM wp_options ORDER BY option_id",
            $tables,
        );

        $t->same($expected, $rows[0]['frame_json']);
    };
}

$tests['json aggregate distinct order window current source next90 final order can use multi term frame output'] = static function (TestRunner $t) use ($tables): void {
    $rows = SQLiteSelectSql::execute(
        "SELECT option_id, json_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS frame_json FROM wp_options ORDER BY frame_json DESC, option_id",
        $tables,
    );

    $t->same([8, 7, 5, 6, 4, 1, 2, 3], array_column($rows, 'option_id'));
};

$tests['json aggregate distinct order window current source next90 malformed second order direction is rejected'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT json_group_array(DISTINCT option_name ORDER BY rank DESC, tie SIDEWAYS) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options",
        $tables,
    ));
};

$tests['json aggregate distinct order window current source next90 missing second order expression is rejected'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        "SELECT json_group_array(DISTINCT option_name ORDER BY rank DESC,) OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_json FROM wp_options",
        $tables,
    ));
};

return $tests;
