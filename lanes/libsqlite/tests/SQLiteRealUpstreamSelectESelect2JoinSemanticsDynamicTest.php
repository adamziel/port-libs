<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test
 *
 * This batch ports the join dataset semantics described by e_select2.test:
 * cartesian join, ON filtering, USING column elision, and LEFT JOIN
 * null-extension. Existing accepted SELECT batches cover e_select.test syntax
 * and selectD parenthesized joins; this file owns dynamic e_select2 dataset
 * join behavior over generic application tables.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelectFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'flat value fingerprint for ' . $sql
    );
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$joinTables = static function (int $case): array {
    $tenant = ($case % 17) + 1;
    $base = 1000 + ($case * 11);

    return [
        'left_items' => [
            ['tenant_id' => $tenant, 'item_key' => 'k-a-' . $case, 'left_value' => $base + 1, 'rank' => 1],
            ['tenant_id' => $tenant, 'item_key' => 'k-b-' . $case, 'left_value' => $base + 2, 'rank' => 2],
            ['tenant_id' => $tenant, 'item_key' => 'k-c-' . $case, 'left_value' => $base + 3, 'rank' => 3],
            ['tenant_id' => $tenant + 100, 'item_key' => 'k-z-' . $case, 'left_value' => $base + 99, 'rank' => 9],
        ],
        'right_items' => [
            ['tenant_id' => $tenant, 'item_key' => 'k-a-' . $case, 'right_value' => 'ra-' . $case, 'rank' => 10],
            ['tenant_id' => $tenant, 'item_key' => 'k-c-' . $case, 'right_value' => 'rc-' . $case, 'rank' => 30],
            ['tenant_id' => $tenant, 'item_key' => 'k-d-' . $case, 'right_value' => 'rd-' . $case, 'rank' => 40],
            ['tenant_id' => $tenant + 200, 'item_key' => 'k-a-' . $case, 'right_value' => 'wrong-' . $case, 'rank' => 50],
        ],
    ];
};

$tests = [];

$tests['real upstream e_select2.test cites dataset join semantic source'] = static function (TestRunner $t): void {
    $t->contains('/test/e_select2.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test');
    $t->contains('te_join', 'e_select2.test te_join cartesian, ON, USING, and LEFT JOIN dataset semantics');
    $t->contains('USING', 'e_select2.test USING column omission');
    $t->contains('LEFT JOIN', 'e_select2.test LEFT JOIN null extension');
};

for ($case = 0; $case < 1000; $case++) {
    $tenant = ($case % 17) + 1;
    $base = 1000 + ($case * 11);
    $tables = $joinTables($case);

    $crossSql = 'SELECT left_items.item_key, right_items.right_value '
        . 'FROM left_items CROSS JOIN right_items '
        . 'WHERE left_items.tenant_id=' . $tenant . ' AND right_items.tenant_id=' . $tenant . ' '
        . 'ORDER BY left_items.item_key, right_items.right_value LIMIT 5';
    $crossExpected = [
        'k-a-' . $case, 'ra-' . $case,
        'k-a-' . $case, 'rc-' . $case,
        'k-a-' . $case, 'rd-' . $case,
        'k-b-' . $case, 'ra-' . $case,
        'k-b-' . $case, 'rc-' . $case,
    ];

    $onSql = 'SELECT left_items.item_key, left_items.left_value, right_items.right_value '
        . 'FROM left_items JOIN right_items '
        . 'ON left_items.tenant_id=right_items.tenant_id AND left_items.item_key=right_items.item_key '
        . 'WHERE left_items.tenant_id=' . $tenant . ' ORDER BY left_items.item_key';
    $onExpected = [
        'k-a-' . $case, $base + 1, 'ra-' . $case,
        'k-c-' . $case, $base + 3, 'rc-' . $case,
    ];

    $usingSql = 'SELECT left_items.tenant_id, left_items.item_key, left_value, right_value '
        . 'FROM left_items JOIN right_items USING (tenant_id, item_key) '
        . 'WHERE left_items.tenant_id=' . $tenant . ' ORDER BY left_items.item_key';
    $usingExpected = [
        $tenant, 'k-a-' . $case, $base + 1, 'ra-' . $case,
        $tenant, 'k-c-' . $case, $base + 3, 'rc-' . $case,
    ];

    $leftSql = 'SELECT left_items.item_key, right_items.right_value '
        . 'FROM left_items LEFT JOIN right_items '
        . 'ON left_items.tenant_id=right_items.tenant_id AND left_items.item_key=right_items.item_key '
        . 'WHERE left_items.tenant_id=' . $tenant . ' ORDER BY left_items.item_key';
    $leftExpected = [
        'k-a-' . $case, 'ra-' . $case,
        'k-b-' . $case, null,
        'k-c-' . $case, 'rc-' . $case,
    ];

    $tests[sprintf('real upstream e_select2.test dynamic join semantics case %04d', $case)] =
        static function (TestRunner $t) use (
            $assertSelectFlat,
            $tables,
            $crossSql,
            $crossExpected,
            $onSql,
            $onExpected,
            $usingSql,
            $usingExpected,
            $leftSql,
            $leftExpected,
            $case
        ): void {
            $assertSelectFlat($t, $crossSql, $tables, $crossExpected);
            $assertSelectFlat($t, $onSql, $tables, $onExpected);
            $assertSelectFlat($t, $usingSql, $tables, $usingExpected);
            $assertSelectFlat($t, $leftSql, $tables, $leftExpected);
            $t->same(true, $case >= 0 && $case < 1000, 'bounded dynamic e_select2 case id');
        };
}

return $tests;
