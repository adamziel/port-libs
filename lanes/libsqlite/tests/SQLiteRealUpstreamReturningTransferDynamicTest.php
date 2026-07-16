<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/TestRunner.php';
require_once __DIR__ . '/../src/SQLiteReturningTransferPlan.php';

use PortLibs\LibSqlite\SQLiteReturningTransferPlan;

$tests = [];

$tests['real upstream returning1 transfer source covers insert select returning'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test');

    $t->true($source !== false, 'hydrated upstream returning1.test is readable');
    $t->contains('set testprefix returning1', (string) $source);
    $t->contains('do_execsql_test 16.0', (string) $source);
    $t->contains('CREATE TEMP TABLE t2(x,y,z);', (string) $source);
    $t->contains('INSERT INTO t2 SELECT * FROM t1 RETURNING *', (string) $source);
    $t->contains('do_execsql_test 16.1', (string) $source);
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $tests[sprintf('real upstream returning1 transfer dynamic insert select returning %04d', $seed)] = static function (TestRunner $t) use ($seed): void {
        $columns = ['a', 'b', 'c'];
        $targetRows = [
            ['a' => 'existing-' . $seed, 'b' => -$seed, 'c' => null],
        ];
        $sourceRows = [
            ['a' => $seed, 'b' => $seed + 1, 'c' => $seed + 2],
            ['a' => 'text-' . $seed, 'b' => 'payload-' . $seed, 'c' => 'tag-' . $seed],
            ['a' => $seed + 0.5, 'b' => null, 'c' => 'tail-' . $seed, 'ignored' => 'not selected'],
        ];

        $plan = SQLiteReturningTransferPlan::insertSelectReturning(
            $targetRows,
            $sourceRows,
            $columns,
            'app_source_' . $seed,
            'app_target_' . $seed
        );

        $expectedInserted = [
            ['a' => $seed, 'b' => $seed + 1, 'c' => $seed + 2],
            ['a' => 'text-' . $seed, 'b' => 'payload-' . $seed, 'c' => 'tag-' . $seed],
            ['a' => $seed + 0.5, 'b' => null, 'c' => 'tail-' . $seed],
        ];

        $t->same('returning1.test', $plan['source']);
        $t->same('returning1-16.0 INSERT INTO target SELECT * FROM source RETURNING *', $plan['scenario']);
        $t->same($columns, $plan['columns']);
        $t->same($targetRows, $plan['before']);
        $t->same($expectedInserted, $plan['inserted_rows']);
        $t->same($expectedInserted, $plan['returning_rows']);
        $t->same(array_merge($targetRows, $expectedInserted), $plan['after']);
        $t->same(3, $plan['changes']);
        $t->same('app_source_' . $seed, $plan['source_table']);
        $t->same('app_target_' . $seed, $plan['target_table']);
        $t->same([
            'source-select-row-order',
            'returning-emits-inserted-row-image',
            'target-append-preserves-existing-rows',
        ], $plan['dependencies']);
    };
}

$tests['real upstream returning1 transfer rejects malformed transfer inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static function (): void {
        SQLiteReturningTransferPlan::insertSelectReturning([], [['a' => 1]], []);
    });

    $t->throws(InvalidArgumentException::class, static function (): void {
        SQLiteReturningTransferPlan::insertSelectReturning([], [['a' => 1]], ['a', 'a']);
    });

    $t->throws(InvalidArgumentException::class, static function (): void {
        SQLiteReturningTransferPlan::insertSelectReturning([], [['a' => 1]], ['a', 'b']);
    });

    $t->throws(InvalidArgumentException::class, static function (): void {
        SQLiteReturningTransferPlan::insertSelectReturning([], [['a' => 1]], ['a'], 'bad-source-name');
    });
};

$tests['real upstream returning1 transfer dependency closure'] = static function (TestRunner $t): void {
    $plan = SQLiteReturningTransferPlan::insertSelectReturning(
        [],
        [['a' => 1, 'b' => 2, 'c' => 3]],
        ['a', 'b', 'c']
    );

    $t->same(1, $plan['changes']);
    $t->same([
        ['a' => 1, 'b' => 2, 'c' => 3],
    ], $plan['returning_rows']);
    $t->same([
        'source-select-row-order',
        'returning-emits-inserted-row-image',
        'target-append-preserves-existing-rows',
    ], $plan['dependencies']);
};

return $tests;
