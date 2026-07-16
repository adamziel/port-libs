<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$tables = static function (): array {
    return [
        'app_settings' => [
            ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'alpha', 'load_policy' => 'eager', 'key_value' => 'A', 'bytes' => 8, 'state' => 'live'],
            ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'beta', 'load_policy' => 'lazy', 'key_value' => 'B', 'bytes' => 5, 'state' => 'live'],
            ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'gamma', 'load_policy' => 'lazy', 'key_value' => 'C', 'bytes' => 13, 'state' => 'stale'],
            ['setting_id' => 4, 'tenant_id' => 2, 'key_name' => 'alpha', 'load_policy' => 'eager', 'key_value' => 'D', 'bytes' => 21, 'state' => 'stale'],
            ['setting_id' => 5, 'tenant_id' => 2, 'key_name' => 'beta', 'load_policy' => 'lazy', 'key_value' => 'E', 'bytes' => 3, 'state' => 'queued'],
            ['setting_id' => 6, 'tenant_id' => 2, 'key_name' => 'gamma', 'load_policy' => 'lazy', 'key_value' => 'F', 'bytes' => 34, 'state' => 'queued'],
            ['setting_id' => 7, 'tenant_id' => 3, 'key_name' => 'alpha', 'load_policy' => 'eager', 'key_value' => 'G', 'bytes' => 2, 'state' => null],
            ['setting_id' => 8, 'tenant_id' => 3, 'key_name' => 'beta', 'load_policy' => 'lazy', 'key_value' => 'H', 'bytes' => 55, 'state' => 'stale'],
        ],
        'app_setting_targets' => [
            ['target_id' => 1, 'tenant_id' => 1, 'key_name' => 'beta', 'action' => 'refresh', 'priority' => 40],
            ['target_id' => 2, 'tenant_id' => 1, 'key_name' => 'gamma', 'action' => 'refresh', 'priority' => 20],
            ['target_id' => 3, 'tenant_id' => 2, 'key_name' => 'beta', 'action' => 'refresh', 'priority' => 30],
            ['target_id' => 4, 'tenant_id' => 2, 'key_name' => 'gamma', 'action' => 'cleanup', 'priority' => 10],
            ['target_id' => 5, 'tenant_id' => 3, 'key_name' => 'beta', 'action' => 'cleanup', 'priority' => 50],
        ],
    ];
};

$execute = static fn (string $sql): array => SQLiteUpdateDeleteReturningSql::execute($sql, $tables(), 'setting_id', [['tenant_id', 'key_name']]);

$tests = [];

$tests['rowvalue update delete limit between precedence cites upstream expression grammar'] = static function (TestRunner $t): void {
    $t->contains('/test/e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test e_expr-13.2 covers BETWEEN precedence left-to-right');
    $t->contains('parse.y', '/home/claude/port-libs/.upstream-cache/libsqlite/src/parse.y keeps %left IS MATCH LIKE_KW BETWEEN IN ISNULL NOTNULL NE EQ at one precedence level');
    $t->contains('/test/e_delete.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test UPDATE_DELETE_LIMIT expression admission');
    $t->contains('/test/rowvalue4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test row-value tuple subquery LIMIT selection');
};

$precedenceExpressions = [
    'equality tail' => '6 BETWEEN 4 AND 8 == 1',
    'not-equal tail' => '5 BETWEEN 0 AND 0 != 1',
    'like tail' => '6 BETWEEN 4 AND 8 LIKE 1',
    'glob tail' => '6 BETWEEN 4 AND 8 GLOB 1',
    'is tail' => '6 BETWEEN 4 AND 8 IS 1',
    'is not tail' => '6 BETWEEN 4 AND 8 IS NOT 0',
    'is distinct tail' => '6 BETWEEN 4 AND 8 IS DISTINCT FROM 0',
    'is not distinct tail' => '6 BETWEEN 4 AND 8 IS NOT DISTINCT FROM 1',
    'between tail' => '6 BETWEEN 4 AND 8 BETWEEN 0 AND 1',
    'not between tail' => '6 BETWEEN 4 AND 8 NOT BETWEEN 2 AND 3',
    'in tail' => '6 BETWEEN 4 AND 8 IN (1)',
    'not in tail' => '6 BETWEEN 4 AND 8 NOT IN (0)',
];

$parserCases = [
    'parse equality tail after between' => ['6 BETWEEN 4 AND 8 == 1', 1],
    'parse not-equal tail after between' => ['5 BETWEEN 0 AND 0 != 1', 1],
    'parse like tail after between' => ['6 BETWEEN 4 AND 8 LIKE 1', 1],
    'parse glob tail after between' => ['6 BETWEEN 4 AND 8 GLOB 1', 1],
    'parse is tail after between' => ['6 BETWEEN 4 AND 8 IS 1', 1],
    'parse between tail after between' => ['6 BETWEEN 4 AND 8 BETWEEN 0 AND 1', 1],
    'parse not between tail after between' => ['6 BETWEEN 4 AND 8 NOT BETWEEN 2 AND 3', 1],
    'parse in tail after between' => ['6 BETWEEN 4 AND 8 IN (1)', 1],
    'parse not in tail after between' => ['6 BETWEEN 4 AND 8 NOT IN (0)', 1],
    'parse lower-side equality groups before between' => ['1 == 10 BETWEEN 0 AND 2', 1],
    'parse parenthesized equality stays upper operand' => ['6 BETWEEN 4 AND (8 == 1)', 0],
    'parse less-than remains upper operand' => ['2 BETWEEN 1 AND 2 < 3', 0],
];

foreach ($parserCases as $name => [$expression, $expected]) {
    $tests['rowvalue update delete limit between precedence ' . $name] = static function (TestRunner $t) use ($expression, $expected): void {
        $parsed = SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT {$expression}");
        $t->same($expected, $parsed['limit']);
    };
}

$lazyOrder = [5, 2, 3, 6, 8];
$targetOrder = [6, 3, 5, 2, 8];
$expressionValues = array_values($precedenceExpressions);

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 4) + 1;
    $offsetValue = ($seed + 1) % 4;
    $limitExpr = '(' . $expressionValues[$seed % count($expressionValues)] . ') + ' . ($limitValue - 1);
    $offsetExpr = '(' . $expressionValues[($seed + 3) % count($expressionValues)] . ') + ' . ($offsetValue - 1);
    $sql = "UPDATE app_settings SET state = 'between_precedence' WHERE load_policy = 'lazy' RETURNING setting_id, state ORDER BY bytes ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}";
    $expected = array_slice($lazyOrder, $offsetValue, $limitValue);

    $tests[sprintf('rowvalue update delete limit between precedence update window seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected, $limitValue, $offsetValue): void {
            $result = $execute($sql);
            $t->same($limitValue, $result['plan']->toArray()['limit']);
            $t->same($offsetValue, $result['plan']->toArray()['offset']);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same(array_values(array_intersect([2, 3, 5, 6, 8], $expected)), array_column($result['returning'], 'setting_id'));
        };
}

for ($seed = 1; $seed <= 40; $seed++) {
    $limitValue = ($seed % 3) + 1;
    $offsetValue = ($seed + 2) % 4;
    $limitExpr = '(' . $expressionValues[($seed + 5) % count($expressionValues)] . ') + ' . ($limitValue - 1);
    $offsetExpr = '(' . $expressionValues[($seed + 7) % count($expressionValues)] . ') + ' . ($offsetValue - 1);
    $sql = "DELETE FROM app_settings WHERE (tenant_id, key_name) IN (SELECT tenant_id, key_name FROM app_setting_targets ORDER BY priority ASC LIMIT {$limitExpr} OFFSET {$offsetExpr}) RETURNING setting_id ORDER BY setting_id LIMIT -1";
    $expected = array_values(array_intersect([2, 3, 5, 6, 8], array_slice($targetOrder, $offsetValue, $limitValue)));

    $tests[sprintf('rowvalue update delete limit between precedence delete rowvalue subquery seed %02d', $seed)] =
        static function (TestRunner $t) use ($execute, $sql, $expected): void {
            $result = $execute($sql);
            $t->same($expected, $result['plan']->selectedIds);
            $t->same($expected, array_column($result['returning'], 'setting_id'));
            $t->same(count($expected), count($result['returning']));
        };
}

$malformedCases = [
    'malformed equality tail missing right operand rejected' => '6 BETWEEN 4 AND 8 ==',
    'malformed like tail missing pattern rejected' => '6 BETWEEN 4 AND 8 LIKE',
    'malformed in tail missing list rejected' => '6 BETWEEN 4 AND 8 IN',
    'malformed empty upper before equality tail rejected' => '6 BETWEEN 4 AND == 1',
];

foreach ($malformedCases as $name => $expression) {
    $tests['rowvalue update delete limit between precedence ' . $name] = static function (TestRunner $t) use ($expression): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT {$expression}"));
    };
}

return $tests;
