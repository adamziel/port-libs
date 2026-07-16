<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$baseSql = <<<'SQL'
WITH RECURSIVE import_window(slot) AS MATERIALIZED (
    VALUES (1)
    UNION ALL
    SELECT slot + 1
    FROM import_window
    WHERE slot < 6
    LIMIT 6
)
SQL;

$tables = [];
$rows = static fn (string $tail): array => SQLiteSelectSql::execute($baseSql . "\n" . $tail, $tables);
$column = static fn (string $tail, string $name): array => array_column($rows($tail), $name);

$cases = [
    'recursive materialized rows feed current following first value' => [
        "SELECT slot, first_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [1, 2, 3, 4, 5, 6],
    ],
    'recursive materialized rows feed current following last value' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 3, 4, 5, 6, 6],
    ],
    'recursive materialized rows feed current following nth value' => [
        "SELECT slot, nth_value(slot, 2) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 3, 4, 5, 6, null],
    ],
    'current row only first value stays current row' => [
        "SELECT slot, first_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS v FROM import_window ORDER BY slot",
        'v',
        [1, 2, 3, 4, 5, 6],
    ],
    'current row only last value stays current row' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS v FROM import_window ORDER BY slot",
        'v',
        [1, 2, 3, 4, 5, 6],
    ],
    'current row only nth value has no second row' => [
        "SELECT slot, nth_value(slot, 2) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS v FROM import_window ORDER BY slot",
        'v',
        [null, null, null, null, null, null],
    ],
    'one preceding current last value stays current row' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY slot ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM import_window ORDER BY slot",
        'v',
        [1, 2, 3, 4, 5, 6],
    ],
    'one preceding current first value sees prior row' => [
        "SELECT slot, first_value(slot) OVER (ORDER BY slot ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM import_window ORDER BY slot",
        'v',
        [1, 1, 2, 3, 4, 5],
    ],
    'two following nth third value clips at tail' => [
        "SELECT slot, nth_value(slot, 3) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [3, 4, 5, 6, null, null],
    ],
    'desc current following last value follows descending order' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY slot DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [1, 1, 2, 3, 4, 5],
    ],
    'partition current following last value clips per autoload' => [
        "SELECT slot, last_value(slot) OVER (PARTITION BY slot % 2 ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [3, 4, 5, 6, 5, 6],
    ],
    'partition nth value returns null at each partition tail' => [
        "SELECT slot, nth_value(slot, 2) OVER (PARTITION BY slot % 2 ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [3, 4, 5, 6, null, null],
    ],
    'exclude current row shifts first value to next row' => [
        "SELECT slot, first_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 3, 4, 5, 6, null],
    ],
    'exclude current row shifts last value to next row' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 3, 4, 5, 6, null],
    ],
    'exclude current row leaves nth second value empty' => [
        "SELECT slot, nth_value(slot, 2) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM import_window ORDER BY slot",
        'v',
        [null, null, null, null, null, null],
    ],
    'range current following weight last value includes numeric peers' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY CASE slot WHEN 1 THEN 9 WHEN 2 THEN 9 WHEN 3 THEN 5 WHEN 4 THEN 4 WHEN 5 THEN 4 ELSE 1 END RANGE BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 2, 3, 3, 3, 6],
    ],
    'groups current following last value includes next peer group' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY CASE slot WHEN 1 THEN 9 WHEN 2 THEN 9 WHEN 3 THEN 5 WHEN 4 THEN 4 WHEN 5 THEN 4 ELSE 1 END GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 2, 2, 3, 3, 5],
    ],
    'groups current following first value starts at peer group head' => [
        "SELECT slot, first_value(slot) OVER (ORDER BY CASE slot WHEN 1 THEN 9 WHEN 2 THEN 9 WHEN 3 THEN 5 WHEN 4 THEN 4 WHEN 5 THEN 4 ELSE 1 END GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [1, 1, 3, 4, 4, 6],
    ],
    'groups exclude ties keeps current and next group' => [
        "SELECT slot, nth_value(slot, 2) OVER (ORDER BY CASE slot WHEN 1 THEN 9 WHEN 2 THEN 9 WHEN 3 THEN 5 WHEN 4 THEN 4 WHEN 5 THEN 4 ELSE 1 END GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE TIES) AS v FROM import_window ORDER BY slot",
        'v',
        [null, null, 1, 3, 3, 4],
    ],
    'not materialized hint uses same recursive value frame' => [
        str_replace('AS MATERIALIZED', 'AS NOT MATERIALIZED', $baseSql) . "\nSELECT slot, last_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 3, 4, 5, 6, 6],
        true,
    ],
    'wide rows first value clips at partition head' => [
        "SELECT slot, first_value(slot) OVER (ORDER BY slot ROWS BETWEEN 2 PRECEDING AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [1, 1, 1, 2, 3, 4],
    ],
    'wide rows last value clips at partition tail' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY slot ROWS BETWEEN 2 PRECEDING AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 3, 4, 5, 6, 6],
    ],
    'wide rows nth value uses frame relative position' => [
        "SELECT slot, nth_value(slot, 2) OVER (ORDER BY slot ROWS BETWEEN 2 PRECEDING AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 2, 2, 3, 4, 5],
    ],
    'wide rows exclude current first value skips only current row' => [
        "SELECT slot, first_value(slot) OVER (ORDER BY slot ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 1, 2, 3, 4, 5],
    ],
    'wide rows exclude current nth value sees following edge' => [
        "SELECT slot, nth_value(slot, 2) OVER (ORDER BY slot ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS v FROM import_window ORDER BY slot",
        'v',
        [null, 3, 4, 5, 6, null],
    ],
    'range over slot current following behaves like row span' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY slot RANGE BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 3, 4, 5, 6, 6],
    ],
    'range over slot preceding current first value' => [
        "SELECT slot, first_value(slot) OVER (ORDER BY slot RANGE BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM import_window ORDER BY slot",
        'v',
        [1, 1, 2, 3, 4, 5],
    ],
    'range over slot nth value clips at tail' => [
        "SELECT slot, nth_value(slot, 2) OVER (ORDER BY slot RANGE BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 3, 4, 5, 6, null],
    ],
    'groups over unique slot current following last value' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY slot GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 3, 4, 5, 6, 6],
    ],
    'groups over unique slot preceding current first value' => [
        "SELECT slot, first_value(slot) OVER (ORDER BY slot GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW) AS v FROM import_window ORDER BY slot",
        'v',
        [1, 1, 2, 3, 4, 5],
    ],
    'groups over unique slot nth value tail null' => [
        "SELECT slot, nth_value(slot, 2) OVER (ORDER BY slot GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [2, 3, 4, 5, 6, null],
    ],
    'partition modulo three current following last value' => [
        "SELECT slot, last_value(slot) OVER (PARTITION BY slot % 3 ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [4, 5, 6, 4, 5, 6],
    ],
    'partition modulo three current following nth value' => [
        "SELECT slot, nth_value(slot, 2) OVER (PARTITION BY slot % 3 ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [4, 5, 6, null, null, null],
    ],
    'partition modulo three current following first value' => [
        "SELECT slot, first_value(slot) OVER (PARTITION BY slot % 3 ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot",
        'v',
        [1, 2, 3, 4, 5, 6],
    ],
    'derived recursive materialized source keeps value frame' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM (SELECT slot FROM import_window WHERE slot <= 4) AS picked ORDER BY slot",
        'v',
        [2, 3, 4, 4],
    ],
    'outer where filters before value frame' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window WHERE slot >= 3 ORDER BY slot",
        'v',
        [4, 5, 6, 6],
    ],
    'outer limit applies after value frame projection' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot LIMIT 3",
        'v',
        [2, 3, 4],
    ],
    'outer offset applies after value frame projection' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot LIMIT 2 OFFSET 3",
        'v',
        [5, 6],
    ],
    'comma limit applies after value frame projection' => [
        "SELECT slot, last_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY slot LIMIT 4, 2",
        'v',
        [6, 6],
    ],
    'distinct keeps framed value rows' => [
        "SELECT DISTINCT slot % 2 AS parity, last_value(slot % 2) OVER (PARTITION BY slot % 2 ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS v FROM import_window ORDER BY parity, v",
        'v',
        [0, 1],
    ],
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['select recursive materialized window current next38 ' . $name] = static function (TestRunner $t) use ($case, $column): void {
        [$sql, $field, $expected] = $case;
        $actual = ($case[3] ?? false) === true
            ? array_column(SQLiteSelectSql::execute($sql, []), $field)
            : $column($sql, $field);
        $t->same($expected, $actual);
    };
}

$tests['select recursive materialized window current next38 row values match sqlite oracle'] = static function (TestRunner $t) use ($rows): void {
    $actual = $rows("SELECT slot, first_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS first_slot, last_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS last_slot, nth_value(slot, 2) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS second_slot FROM import_window ORDER BY slot");
    $t->same([
        ['slot' => 1, 'first_slot' => 1, 'last_slot' => 2, 'second_slot' => 2],
        ['slot' => 2, 'first_slot' => 2, 'last_slot' => 3, 'second_slot' => 3],
        ['slot' => 3, 'first_slot' => 3, 'last_slot' => 4, 'second_slot' => 4],
        ['slot' => 4, 'first_slot' => 4, 'last_slot' => 5, 'second_slot' => 5],
        ['slot' => 5, 'first_slot' => 5, 'last_slot' => 6, 'second_slot' => 6],
        ['slot' => 6, 'first_slot' => 6, 'last_slot' => 6, 'second_slot' => null],
    ], $actual);
};

$tests['select recursive materialized window current next38 plan records value frame metadata'] = static function (TestRunner $t) use ($baseSql): void {
    $plan = SQLiteSelectSql::plan($baseSql . "\nSELECT last_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS next_slot FROM import_window", []);
    $t->same('window', $plan['select'][0]['type']);
    $t->same('last_value', $plan['select'][0]['function']);
    $t->same('ROWS', $plan['select'][0]['frame']['unit']);
    $t->same(0, $plan['select'][0]['frame']['preceding']);
    $t->same(1, $plan['select'][0]['frame']['following']);
};

$tests['select recursive materialized window current next38 aggregate and value frames share rows'] = static function (TestRunner $t) use ($rows): void {
    $actual = $rows("SELECT slot, last_value(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS last_slot, group_concat(slot) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS slots FROM import_window ORDER BY slot LIMIT 3");
    $t->same(['1,2', '2,3', '3,4'], array_column($actual, 'slots'));
    $t->same([2, 3, 4], array_column($actual, 'last_slot'));
};

$tests['select recursive materialized window current next38 rejects nth zero in framed value function'] = static function (TestRunner $t) use ($baseSql): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($baseSql . "\nSELECT nth_value(slot, 0) OVER (ORDER BY slot ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS bad FROM import_window", []));
};

$tests['select recursive materialized window current next38 accepts rows frame without order for value function'] = static function (TestRunner $t) use ($column): void {
    $t->same([2, 3, 4, 5, 6, 6], $column('SELECT last_value(slot) OVER (ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS bad FROM import_window', 'bad'));
};

return $tests;
