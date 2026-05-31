<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningSchemaNamePlan;

$tests = [];

foreach (SQLiteReturningSchemaNamePlan::dynamicReturningSchemaCases(150) as $case) {
    $prefix = sprintf('returning1 schema name dynamic variant %03d ', $case['variant']);

    $tests[$prefix . 'returning1-21.0 main sqlite_schema default row returns nullable name'] = static function (TestRunner $t) use ($case): void {
        $t->same('ok', $case['main']['status']);
        $t->same([['name' => null]], $case['main']['returning_rows']);
    };

    $tests[$prefix . 'returning1-21.0 records main writable-schema dependency'] = static function (TestRunner $t) use ($case): void {
        $t->same('main', $case['main']['schema']);
        $t->same(['returning1.test-21.0'], $case['main']['dependencies']);
    };

    $tests[$prefix . 'returning1-21.1 temp sqlite_schema default row returns nullable name'] = static function (TestRunner $t) use ($case): void {
        $t->same('ok', $case['temp']['status']);
        $t->same([['name' => null]], $case['temp']['returning_rows']);
    };

    $tests[$prefix . 'returning1-21.1 records temp writable-schema dependency'] = static function (TestRunner $t) use ($case): void {
        $t->same('temp', $case['temp']['schema']);
        $t->same(['returning1.test-21.1'], $case['temp']['dependencies']);
    };

    $tests[$prefix . 'returning1-22.1 reports schema alias missing column before returning rows'] = static function (TestRunner $t) use ($case): void {
        $t->same('error-before-returning', $case['subquery']['status']);
        $t->same('no such column: sqlite_master.name', $case['subquery']['error']);
        $t->same([], $case['subquery']['returning_rows']);
    };

    $tests[$prefix . 'returning1-22.1 keeps subquery binding on user alias not target schema'] = static function (TestRunner $t) use ($case): void {
        $t->same([
            'table' => 'xyz',
            'alias' => 'sqlite_master',
            'where_left' => 'a',
            'where_right' => 'sqlite_master.name',
        ], $case['subquery']['subquery']);
        $t->same(['returning1.test-22.1'], $case['subquery']['dependencies']);
    };
}

return $tests;
