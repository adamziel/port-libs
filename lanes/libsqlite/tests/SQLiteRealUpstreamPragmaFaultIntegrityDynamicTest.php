<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaFaultIntegrityPlan;

$tests = [];

/*
 * Real upstream source: SQLite test/pragmafault.test.
 *
 * pragmafault-1.0 creates t1(a, b, CHECK(a!=b)), inserts two valid rows, saves
 * the database, then pragmafault-1 injects OOM faults around
 * PRAGMA integrity_check and expects a successful result after each restored
 * run. This ports that recoverable integrity-check fault path with generic
 * table names and varied row values.
 */
foreach (range(1, 1000) as $variant) {
    $table = sprintf('pragmafault_settings_%04d', $variant);
    $left = sprintf('metric_%04d', $variant);
    $right = sprintf('shadow_%04d', $variant);
    $create = "CREATE TABLE {$table}({$left}, {$right}, CHECK({$left}!={$right}))";
    $rows = [
        [$left => $variant, $right => $variant + 1],
        [$left => $variant + 2, $right => $variant + 3],
    ];
    $faultStep = $variant - 1;

    $tests["real upstream pragmafault integrity_check recoverable oom variant {$variant}"] = static function (TestRunner $t) use ($create, $table, $left, $right, $rows, $faultStep): void {
        $plan = SQLitePragmaFaultIntegrityPlan::integrityCheckWithRecoverableFault($create, $rows, $faultStep, 'oom');

        $t->same('ok', $plan['status']);
        $t->same('integrity_check', $plan['pragma']);
        $t->same($table, $plan['table']);
        $t->same([$left, $right], $plan['columns']);
        $t->same([['left' => $left, 'operator' => '!=', 'right' => $right]], $plan['checks']);
        $t->same('oom', $plan['fault']['kind']);
        $t->same($faultStep, $plan['fault']['step']);
        $t->same(true, $plan['fault']['recovered']);
        $t->same(0, $plan['rows_checked']);
        $t->same(['ok'], $plan['result']);
        $t->same([], $plan['violations']);
        $t->same(true, $plan['allocationsReleased']);
        $t->same(true, $plan['schemaCacheStable']);
    };
}

$tests['real upstream pragmafault integrity_check validates non fault rows'] = static function (TestRunner $t): void {
    $plan = SQLitePragmaFaultIntegrityPlan::integrityCheckWithRecoverableFault(
        'CREATE TABLE pragmafault_clean(a, b, CHECK(a!=b))',
        [['a' => 1, 'b' => 2], ['a' => 3, 'b' => 4]],
        6,
        'none'
    );

    $t->same('ok', $plan['status']);
    $t->same(false, $plan['fault']['recovered']);
    $t->same(2, $plan['rows_checked']);
    $t->same(['ok'], $plan['result']);
    $t->same([], $plan['violations']);
};

$tests['real upstream pragmafault integrity_check reports check failures outside recoverable oom'] = static function (TestRunner $t): void {
    $plan = SQLitePragmaFaultIntegrityPlan::integrityCheckWithRecoverableFault(
        'CREATE TABLE pragmafault_bad(a, b, CHECK(a!=b))',
        [['a' => 5, 'b' => 5]],
        6,
        'none'
    );

    $t->same('ok', $plan['status']);
    $t->same(1, $plan['rows_checked']);
    $t->same(['CHECK constraint failed in pragmafault_bad at row 1'], $plan['result']);
    $t->same([['row' => 1, 'check' => 'a!=b', 'left' => 5, 'right' => 5]], $plan['violations']);
};

$tests['real upstream pragmafault source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragmafault.test pragmafault-1.0 creates t1(a, b, CHECK(a!=b)) and inserts valid rows',
        'pragmafault.test pragmafault-1 faultsim restores database then runs PRAGMA integrity_check under OOM injection',
    ];

    $t->same(2, count($sections));
    $t->contains('pragmafault-1.0', $sections[0]);
    $t->contains('PRAGMA integrity_check', $sections[1]);
};

$tests['real upstream pragmafault rejects unsupported integrity schema'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLitePragmaFaultIntegrityPlan::integrityCheckWithRecoverableFault('CREATE TABLE broken(a, b)', [])
    );
};

return $tests;
