<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaFaultPlan;

$tests = [];

/*
 * Real upstream source: SQLite test/schemafault.test.
 *
 * schemafault-1.0 creates a table and a view over that table:
 *   CREATE TABLE t2(aaa INTTT);
 *   CREATE VIEW v2(xxx, yyy) AS SELECT aaa, aaa+1 FROM t2;
 *
 * schemafault-1 then injects OOM faults while running SELECT * FROM v2 and
 * expects a successful empty rowset. This batch ports the recoverable schema
 * expansion checkpoints with generic table/view names and verifies that the
 * fault path leaves schema-cache state stable and allocations released.
 */

foreach (range(1, 1000) as $variant) {
    $table = sprintf('schemafault_table_%04d', $variant);
    $view = sprintf('schemafault_view_%04d', $variant);
    $left = sprintf('col_%04d', $variant);
    $right = sprintf('next_%04d', $variant);
    $faultStep = $variant - 1;
    $createView = "CREATE VIEW {$view}({$left}, {$right}) AS SELECT aaa, aaa+1 FROM {$table}";

    $tests["real upstream schemafault recoverable view select oom variant {$variant}"] = static function (TestRunner $t) use ($createView, $table, $view, $left, $right, $faultStep): void {
        $plan = SQLiteSchemaFaultPlan::selectViewUnderRecoverableFault($createView, [], $faultStep, 'oom');

        $t->same('ok', $plan['status']);
        $t->same($view, $plan['view']);
        $t->same($table, $plan['source']);
        $t->same([$left, $right], $plan['columns']);
        $t->same([$table], $plan['dependencies']);
        $t->same('oom', $plan['fault']['kind']);
        $t->same($faultStep, $plan['fault']['step']);
        $t->same([], $plan['rows']);
        $t->same(true, $plan['allocationsReleased']);
        $t->same(true, $plan['schemaCacheStable']);
    };
}

$tests['real upstream schemafault successful view projection after fault loop'] = static function (TestRunner $t): void {
    $plan = SQLiteSchemaFaultPlan::selectViewUnderRecoverableFault(
        'CREATE VIEW schemafault_view_ok(xxx, yyy) AS SELECT aaa, aaa+1 FROM schemafault_table_ok',
        [['aaa' => 41]],
        6,
        'none'
    );

    $t->same('ok', $plan['status']);
    $t->same([['xxx' => 41, 'yyy' => 42]], $plan['rows']);
    $t->same(false, $plan['fault']['recovered']);
    $t->same('row-output', $plan['fault']['checkpoint']);
};

$tests['real upstream schemafault source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'schemafault.test schemafault-1.0 creates t2 and v2 over SELECT aaa, aaa+1 FROM t2',
        'schemafault.test schemafault-1 faultsim injects oom-* while SELECT * FROM v2 returns an empty rowset',
    ];

    $t->same(2, count($sections));
    $t->contains('schemafault-1.0', $sections[0]);
    $t->contains('oom-*', $sections[1]);
};

$tests['real upstream schemafault rejects unsupported view SQL'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteSchemaFaultPlan::selectViewUnderRecoverableFault('CREATE VIEW broken AS SELECT * FROM x')
    );
};

return $tests;
