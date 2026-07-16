<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/TestRunner.php';
require_once __DIR__ . '/../src/SQLiteReturningTransferPlan.php';

use PortLibs\LibSqlite\SQLiteReturningTransferPlan;

$tests = [];

$tests['real upstream insert4 returning xfer source covers integrity and returning boundary'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/insert4.test');

    $t->true($source !== false, 'hydrated upstream insert4.test is readable');
    $t->contains('Check that running an integrity-check does not disable the xfer', (string) $source);
    $t->contains('do_test 10.2', (string) $source);
    $t->contains('INSERT INTO x SELECT * FROM t8', (string) $source);
    $t->contains('do_test 10.3', (string) $source);
    $t->contains('execsql { PRAGMA integrity_check }', (string) $source);
    $t->contains('do_test 10.4', (string) $source);
    $t->contains('INSERT INTO x     SELECT * FROM t8  RETURNING *', (string) $source);
    $t->contains('set sqlite3_xferopt_count', (string) $source);
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $tests[sprintf('real upstream insert4 returning disables xfer optimization dynamic %04d', $seed)] = static function (TestRunner $t) use ($seed): void {
        $columns = ['rid', 'pid', 'mid', 'px'];
        $targetRows = [
            ['rid' => -$seed, 'pid' => $seed * 10, 'mid' => $seed * 100, 'px' => ($seed + 1) % 2],
        ];
        $sourceRows = $seed % 10 === 0 ? [] : [
            ['rid' => $seed, 'pid' => $seed + 1, 'mid' => $seed + 2, 'px' => $seed % 2],
            ['rid' => $seed + 1000, 'pid' => $seed + 1001, 'mid' => $seed + 1002, 'px' => ($seed + 1) % 2],
            ['rid' => $seed + 2000, 'pid' => $seed + 2001, 'mid' => $seed + 2002, 'px' => 0, 'ignored' => 'not transferred'],
        ];
        $expectedInserted = array_map(static function (array $row): array {
            return [
                'rid' => $row['rid'],
                'pid' => $row['pid'],
                'mid' => $row['mid'],
                'px' => $row['px'],
            ];
        }, $sourceRows);
        $expectedAfter = array_merge($targetRows, $expectedInserted);

        $beforeIntegrity = SQLiteReturningTransferPlan::insertSelectXferOptimizationDecision(
            $targetRows,
            $sourceRows,
            $columns,
            false,
            false,
            'app_source_' . $seed,
            'app_target_' . $seed
        );
        $afterIntegrity = SQLiteReturningTransferPlan::insertSelectXferOptimizationDecision(
            $targetRows,
            $sourceRows,
            $columns,
            false,
            true,
            'app_source_' . $seed,
            'app_target_' . $seed
        );
        $withReturning = SQLiteReturningTransferPlan::insertSelectXferOptimizationDecision(
            $targetRows,
            $sourceRows,
            $columns,
            true,
            true,
            'app_source_' . $seed,
            'app_target_' . $seed
        );

        $t->same('insert4.test', $beforeIntegrity['source']);
        $t->same('insert4-10.2/10.3/10.4 INSERT INTO target SELECT * FROM source transfer optimization and RETURNING', $beforeIntegrity['scenario']);
        $t->same($columns, $beforeIntegrity['columns']);
        $t->same('app_source_' . $seed, $beforeIntegrity['source_table']);
        $t->same('app_target_' . $seed, $beforeIntegrity['target_table']);
        $t->same($targetRows, $beforeIntegrity['before']);
        $t->same($expectedInserted, $beforeIntegrity['inserted_rows']);
        $t->same([], $beforeIntegrity['returning_rows']);
        $t->same($expectedAfter, $beforeIntegrity['after']);
        $t->same(count($expectedInserted), $beforeIntegrity['changes']);
        $t->same(false, $beforeIntegrity['returning']);
        $t->same(false, $beforeIntegrity['integrity_check_ran']);
        $t->true($beforeIntegrity['integrity_check_preserves_transfer_eligibility']);
        $t->true($beforeIntegrity['xfer_optimization_used']);
        $t->same(1, $beforeIntegrity['xferopt_count']);
        $t->same(null, $beforeIntegrity['optimization_blocker']);

        $t->true($afterIntegrity['integrity_check_ran']);
        $t->true($afterIntegrity['integrity_check_preserves_transfer_eligibility']);
        $t->true($afterIntegrity['xfer_optimization_used']);
        $t->same(1, $afterIntegrity['xferopt_count']);
        $t->same([], $afterIntegrity['returning_rows']);
        $t->same($expectedAfter, $afterIntegrity['after']);

        $t->true($withReturning['returning']);
        $t->true($withReturning['integrity_check_ran']);
        $t->true($withReturning['integrity_check_preserves_transfer_eligibility']);
        $t->same(false, $withReturning['xfer_optimization_used']);
        $t->same(0, $withReturning['xferopt_count']);
        $t->same('returning-clause-requires-row-image-emission', $withReturning['optimization_blocker']);
        $t->same($expectedInserted, $withReturning['returning_rows']);
        $t->same($expectedAfter, $withReturning['after']);
        $t->same([
            'insert4-10.2 transfer optimization without RETURNING',
            'insert4-10.3 integrity_check preserves transfer optimization',
            'insert4-10.4 RETURNING disables transfer optimization',
            'returning-emits-inserted-row-image',
            'target-append-preserves-existing-rows',
        ], $withReturning['dependencies']);
    };
}

$tests['real upstream insert4 returning xfer rejects malformed transfer inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static function (): void {
        SQLiteReturningTransferPlan::insertSelectXferOptimizationDecision([], [['rid' => 1]], []);
    });

    $t->throws(InvalidArgumentException::class, static function (): void {
        SQLiteReturningTransferPlan::insertSelectXferOptimizationDecision([], [['rid' => 1]], ['rid', 'rid']);
    });

    $t->throws(InvalidArgumentException::class, static function (): void {
        SQLiteReturningTransferPlan::insertSelectXferOptimizationDecision([], [['rid' => 1]], ['rid', 'missing']);
    });

    $t->throws(InvalidArgumentException::class, static function (): void {
        SQLiteReturningTransferPlan::insertSelectXferOptimizationDecision([], [['rid' => 1]], ['rid'], false, false, 'bad-source-name');
    });
};

$tests['real upstream insert4 returning xfer dependency closure'] = static function (TestRunner $t): void {
    $plan = SQLiteReturningTransferPlan::insertSelectXferOptimizationDecision(
        [],
        [['rid' => 1, 'pid' => 2, 'mid' => 3, 'px' => 1]],
        ['rid', 'pid', 'mid', 'px'],
        true,
        true
    );

    $t->same(1, $plan['changes']);
    $t->same(0, $plan['xferopt_count']);
    $t->same([
        ['rid' => 1, 'pid' => 2, 'mid' => 3, 'px' => 1],
    ], $plan['returning_rows']);
    $t->same([
        'insert4-10.2 transfer optimization without RETURNING',
        'insert4-10.3 integrity_check preserves transfer optimization',
        'insert4-10.4 RETURNING disables transfer optimization',
        'returning-emits-inserted-row-image',
        'target-append-preserves-existing-rows',
    ], $plan['dependencies']);
};

return $tests;
