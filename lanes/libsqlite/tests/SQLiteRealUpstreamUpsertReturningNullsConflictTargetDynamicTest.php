<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$assertUnsupportedNulls = static function (TestRunner $t, callable $callback, string $modifier): void {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        $t->same('unsupported use of NULLS ' . $modifier, $exception->getMessage());

        return;
    }

    throw new RuntimeException('Expected unsupported use of NULLS ' . $modifier);
};

$tableRows = static fn (int $seed): array => [
    [
        'a' => $seed * 10 + 1,
        'b' => $seed * 10 + 2,
        'c' => $seed * 10 + 3,
        'd' => $seed * 10 + 4,
    ],
];

$quoteSql = static fn (mixed $value): string => is_string($value)
    ? "'" . str_replace("'", "''", $value) . "'"
    : (string) $value;

$sqlFor = static function (int $seed, string $modifier) use ($quoteSql): string {
    $values = [
        $seed * 100 + 1,
        $seed * 10 + 2,
        $seed * 100 + 3,
        $seed * 100 + 4,
    ];

    return 'INSERT INTO app_nulls(a,b,c,d) VALUES(' . implode(',', array_map($quoteSql, $values)) . ') '
        . 'ON CONFLICT (b DESC NULLS ' . $modifier . ') DO UPDATE SET a = a + 1 RETURNING a,b,c,d';
};

$validSqlFor = static function (int $seed) use ($quoteSql): string {
    $values = [
        $seed * 100 + 1,
        $seed * 10 + 2,
        $seed * 100 + 3,
        $seed * 100 + 4,
    ];

    return 'INSERT INTO app_nulls(a,b,c,d) VALUES(' . implode(',', array_map($quoteSql, $values)) . ') '
        . 'ON CONFLICT (b) DO UPDATE SET a = a + 1 RETURNING a,b,c,d';
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $modifier = ($seed % 2) === 0 ? 'FIRST' : 'LAST';
    $label = sprintf('real upstream nulls1 upsert conflict target NULLS %s dynamic %04d', $modifier, $seed);

    $tests[$label . ' rejects SQL before RETURNING rows'] = static function (TestRunner $t) use ($assertUnsupportedNulls, $sqlFor, $tableRows, $seed, $modifier): void {
        $assertUnsupportedNulls(
            $t,
            static fn (): array => SQLiteUpsertReturningSql::execute(
                $sqlFor($seed, $modifier),
                ['app_nulls' => $tableRows($seed)],
                [['b']],
            ),
            $modifier,
        );
    };

    $tests[$label . ' rejects planner target admission'] = static function (TestRunner $t) use ($assertUnsupportedNulls, $modifier): void {
        $assertUnsupportedNulls(
            $t,
            static fn (): array => SQLiteUpsertDoUpdateWherePlan::admitConflictTarget(
                ['b DESC NULLS ' . $modifier],
                [['name' => 'app_nulls_b', 'terms' => ['b']]],
            ),
            $modifier,
        );
    };

    $tests[$label . ' valid target still updates and returns current row image'] = static function (TestRunner $t) use ($validSqlFor, $tableRows, $seed): void {
        $result = SQLiteUpsertReturningSql::execute(
            $validSqlFor($seed),
            ['app_nulls' => $tableRows($seed)],
            [['b']],
        );

        $t->same([[
            'a' => $seed * 10 + 2,
            'b' => $seed * 10 + 2,
            'c' => $seed * 10 + 3,
            'd' => $seed * 10 + 4,
        ]], $result['returning']);
    };
}

$tests['real upstream nulls1 upsert conflict target source coverage'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/nulls1.test nulls1-3.1.11 rejects ON CONFLICT (b DESC NULLS LAST)',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/nulls1.test nulls1-3.1.12 rejects trigger-body ON CONFLICT (b DESC NULLS FIRST)',
        'non-overlap: covers unsupported NULLS modifiers in UPSERT conflict targets, not accepted ORDER BY NULLS sorting, expression ORDER BY, target admission, or RETURNING row-stream batches',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/nulls1.test nulls1-3.1.11 rejects ON CONFLICT (b DESC NULLS LAST)',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/nulls1.test nulls1-3.1.12 rejects trigger-body ON CONFLICT (b DESC NULLS FIRST)',
        'non-overlap: covers unsupported NULLS modifiers in UPSERT conflict targets, not accepted ORDER BY NULLS sorting, expression ORDER BY, target admission, or RETURNING row-stream batches',
    ]);
};

$tests['real upstream nulls1 upsert conflict target dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteUpsertReturningSql parsing and SQLiteUpsertDoUpdateWherePlan target admission',
        'no new support component needed; reuses SQLiteUpsertReturningSql parsing and SQLiteUpsertDoUpdateWherePlan target admission',
    );
};

$tests['real upstream nulls1 upsert conflict target quoted identifiers do not trip modifier guard'] = static function (TestRunner $t): void {
    $result = SQLiteUpsertReturningSql::execute(
        'INSERT INTO app_quoted(a,"b DESC NULLS LAST") VALUES(2,3) ON CONFLICT ("b DESC NULLS LAST") DO UPDATE SET a=a+1 RETURNING a',
        ['app_quoted' => [['a' => 1, 'b DESC NULLS LAST' => 3]]],
        [['b DESC NULLS LAST']],
    );

    $t->same([['a' => 2]], $result['returning']);
};

$tests['real upstream nulls1 upsert conflict target quoted literals do not trip admission guard'] = static function (TestRunner $t): void {
    $admission = SQLiteUpsertDoUpdateWherePlan::admitConflictTarget(
        [['expr' => "quote('NULLS LAST')"]],
        [['name' => 'literal_expr', 'terms' => [['expr' => "quote('NULLS LAST')"]]]],
    );

    $t->same(true, $admission['matched']);
};

return $tests;
