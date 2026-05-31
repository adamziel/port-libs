<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$baseRows = static fn (): array => [
    ['w' => 'a', 'x' => 1, 'a b' => 1, 'z' => 1],
    ['w' => 'b', 'x' => 2, 'a b' => 2, 'z' => 2],
];

$run = static function (string $sql, array $rows = null): array {
    return SQLiteUpsertReturningSql::execute(
        $sql,
        ['excluded' => $rows ?? [
            ['w' => 'a', 'x' => 1, 'a b' => 1, 'z' => 1],
            ['w' => 'b', 'x' => 2, 'a b' => 2, 'z' => 2],
        ]],
        [['x', 'a b'], ['z']],
    );
};

for ($i = 0; $i < 1000; ++$i) {
    $seed = $i + 1;
    $rows = [
        ['w' => 'base_' . $seed, 'x' => $seed, 'a b' => $seed, 'z' => $seed],
        ['w' => 'peer_' . $seed, 'x' => $seed + 1, 'a b' => $seed + 1, 'z' => $seed + 1],
    ];

    $unaliased = static fn (): array => $run(
        "INSERT INTO excluded(w,x,[a b],z) VALUES('incoming_{$seed}',{$seed},{$seed},NULL) "
        . 'ON CONFLICT(x, [a b]) DO UPDATE SET w=excluded.w '
        . 'RETURNING w,x,[a b],z',
        $rows,
    );
    $aliased = static fn (): array => $run(
        "INSERT INTO excluded AS x1(w,x,[a b],z) VALUES('incoming_{$seed}',{$seed},{$seed},NULL) "
        . 'ON CONFLICT(x, [a b]) DO UPDATE SET w=excluded.w '
        . 'RETURNING w,x,[a b],z',
        $rows,
    );
    $whereFalse = static fn (): array => $run(
        "INSERT INTO excluded AS x1(w,x,[a b],z) VALUES('incoming_{$seed}',{$seed},{$seed},NULL) "
        . "ON CONFLICT(x, [a b]) DO UPDATE SET w=w||w WHERE excluded.w!='incoming_{$seed}' "
        . 'RETURNING w,x,[a b],z',
        $rows,
    );
    $whereTrue = static fn (): array => $run(
        "INSERT INTO excluded AS x1(w,x,[a b],z) VALUES('incoming_{$seed}',{$seed},{$seed},NULL) "
        . 'ON CONFLICT(x, [a b]) DO UPDATE SET w=w||w WHERE excluded.x=' . $seed . ' '
        . 'RETURNING w,x,[a b],z',
        $rows,
    );

    $prefix = sprintf('upsert4.test 8 excluded alias dynamic variant %04d ', $i);
    $tests[$prefix . 'unaliased target table named excluded reads target row'] = static function (TestRunner $t) use ($unaliased, $seed): void {
        $t->same('base_' . $seed, $unaliased()['after'][0]['w']);
    };
    $tests[$prefix . 'aliased target table named excluded reads incoming pseudo row'] = static function (TestRunner $t) use ($aliased, $seed): void {
        $t->same('incoming_' . $seed, $aliased()['after'][0]['w']);
    };
    $tests[$prefix . 'aliased excluded predicate can skip update and yield no returning row'] = static function (TestRunner $t) use ($whereFalse): void {
        $t->same([0, []], [$whereFalse()['changes'], $whereFalse()['returning']]);
    };
    $tests[$prefix . 'aliased excluded predicate can admit current row update'] = static function (TestRunner $t) use ($whereTrue, $seed): void {
        $result = $whereTrue();
        $t->same(['base_' . $seed . 'base_' . $seed, 1], [$result['returning'][0]['w'], $result['changes']]);
    };
}

$tests['upsert4.test 8.5 unresolved conflict-target WHERE column is rejected'] = static function (TestRunner $t) use ($run, $baseRows): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => $run(
            "INSERT INTO excluded AS x1(w,x,[a b],z) VALUES('incoming',1,1,NULL) "
            . 'ON CONFLICT(x, [a b]) WHERE y=1 DO UPDATE SET w=w||w WHERE excluded.x=1 '
            . 'RETURNING w',
            $baseRows(),
        ),
    );
};

return $tests;
