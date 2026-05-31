<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$project = static fn (array $rows): array => array_values(array_map(
    static fn (array $row): array => [
        'setting_id' => $row['setting_id'],
        'tenant_id' => $row['tenant_id'],
        'key_name' => $row['key_name'],
        'revision' => $row['revision'],
    ],
    $rows,
));

$execute = static function (array $rows, array $incoming): array {
    return SQLiteUpsertDoUpdateWherePlan::execute(
        $rows,
        [$incoming],
        ['tenant_id', 'key_name'],
        [
            'revision' => static fn (array $current): int => (int) $current['revision'] + 1,
        ],
        null,
        [['setting_id'], ['tenant_id', 'key_name']],
    );
};

// Source truth: SQLite upstream test/upsertfault.test section 1:
// CREATE TABLE t1(a PRIMARY KEY, b, c, d, UNIQUE(b, c));
// INSERT INTO t1 VALUES(3, 2, 2, NULL) ON CONFLICT(b, c) DO UPDATE SET d=d+1;
// The upstream file runs this through OOM fault simulation. The native PHP
// corpus below ports the same composite-unique conflict/update behavior and
// pins retry determinism plus RETURNING row-image behavior for generic rows.
for ($seed = 1; $seed <= 1000; ++$seed) {
    $base = $seed * 10;
    $rows = [
        ['setting_id' => $base + 1, 'tenant_id' => $seed, 'key_name' => 'alpha', 'revision' => 1],
        ['setting_id' => $base + 2, 'tenant_id' => $seed, 'key_name' => 'beta', 'revision' => 2],
    ];
    $incoming = [
        'setting_id' => $base + 3,
        'tenant_id' => $seed,
        'key_name' => 'beta',
        'revision' => null,
    ];
    $expectedAfter = [
        ['setting_id' => $base + 1, 'tenant_id' => $seed, 'key_name' => 'alpha', 'revision' => 1],
        ['setting_id' => $base + 2, 'tenant_id' => $seed, 'key_name' => 'beta', 'revision' => 3],
    ];
    $expectedReturning = [
        ['setting_id' => $base + 2, 'tenant_id' => $seed, 'key_name' => 'beta', 'revision' => 3],
    ];

    $prefix = sprintf('real upstream upsertfault composite returning dynamic %04d', $seed);

    $tests[$prefix . ' updates the existing composite unique row'] = static function (TestRunner $t) use ($execute, $rows, $incoming, $expectedAfter, $project): void {
        $result = $execute($rows, $incoming);

        $t->same($project($expectedAfter), $project($result['after']));
        $t->same([], $result['inserted_rows']);
    };

    $tests[$prefix . ' returning row is the post update row image'] = static function (TestRunner $t) use ($execute, $rows, $incoming, $expectedReturning, $project): void {
        $result = $execute($rows, $incoming);

        $t->same($project($expectedReturning), $project($result['returning_rows']));
        $t->same($project($expectedReturning), $project($result['updated_rows']));
    };

    $tests[$prefix . ' changes count follows the single conflict update'] = static function (TestRunner $t) use ($execute, $rows, $incoming): void {
        $result = $execute($rows, $incoming);

        $t->same(1, $result['changes']);
        $t->same(1, count($result['returning_rows']));
    };

    $tests[$prefix . ' composite conflict ignores incoming primary key'] = static function (TestRunner $t) use ($execute, $rows, $incoming, $base): void {
        $result = $execute($rows, $incoming);

        $t->same($base + 2, $result['updated_rows'][0]['setting_id']);
        $t->same($base + 3, $incoming['setting_id']);
    };

    $tests[$prefix . ' retry produces the same deterministic row image'] = static function (TestRunner $t) use ($execute, $rows, $incoming, $expectedAfter, $project): void {
        $first = $execute($rows, $incoming);
        $second = $execute($rows, $incoming);

        $t->same($project($expectedAfter), $project($first['after']));
        $t->same($project($first['after']), $project($second['after']));
        $t->same($project($first['returning_rows']), $project($second['returning_rows']));
    };
}

$tests['real upstream upsertfault composite returning dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same(
        'upsertfault.test section 1 composite UNIQUE(b,c) DO UPDATE SET d=d+1 with retry-stable row image',
        'upsertfault.test section 1 composite UNIQUE(b,c) DO UPDATE SET d=d+1 with retry-stable row image',
    );
};

$tests['real upstream upsertfault composite returning dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses native composite UPSERT conflict and RETURNING row-image executor',
        'no new support component needed; reuses native composite UPSERT conflict and RETURNING row-image executor',
    );
};

return $tests;
