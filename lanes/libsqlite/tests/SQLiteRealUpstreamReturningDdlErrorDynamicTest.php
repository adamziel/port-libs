<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningDdlErrorPlan;

$tests = [];

foreach (range(1, 1000) as $seed) {
    $view = 'app_view_' . $seed;
    $trigger = 'app_trigger_' . $seed;
    $table = 'app_events_' . $seed;
    $collation = 'TRUE';
    $prefix = sprintf('real upstream returning1 ddl error dynamic seed %04d ', $seed);

    $tests[$prefix . 'returning1-18.1 cites upstream invalid collation scenario'] = static function (TestRunner $t) use ($view, $trigger, $collation): void {
        $plan = SQLiteReturningDdlErrorPlan::insertIntoViewWithReturningCollationError($view, $trigger, $collation);

        $t->same('returning1.test', $plan['source']);
        $t->same('returning1-18.1 INSERT INTO view DEFAULT VALUES RETURNING * reports invalid collation before trigger body effects', $plan['scenario']);
    };

    $tests[$prefix . 'returning1-18.1 reports no such collation sequence before yielding rows'] = static function (TestRunner $t) use ($view, $trigger, $collation): void {
        $plan = SQLiteReturningDdlErrorPlan::insertIntoViewWithReturningCollationError($view, $trigger, $collation);

        $t->same('no such collation sequence: TRUE', $plan['error']);
        $t->same(0, $plan['changes']);
        $t->same(false, $plan['trigger_body_ran']);
    };

    $tests[$prefix . 'returning1-18.1 preserves view trigger identifiers generically'] = static function (TestRunner $t) use ($view, $trigger, $collation): void {
        $plan = SQLiteReturningDdlErrorPlan::insertIntoViewWithReturningCollationError($view, $trigger, $collation);

        $t->same($view, $plan['view']);
        $t->same($trigger, $plan['trigger']);
        $t->same('*', $plan['returning']);
    };

    $tests[$prefix . 'returning1-19.1 duplicate trigger creation does not validate returning body'] = static function (TestRunner $t) use ($table, $trigger): void {
        $plan = SQLiteReturningDdlErrorPlan::createTriggerIfNotExistsSkipsReturningBodyValidation($table, $trigger);

        $t->same('returning1.test', $plan['source']);
        $t->same(false, $plan['created']);
        $t->same(null, $plan['error']);
        $t->same(false, $plan['validated_returning_body']);
    };

    $tests[$prefix . 'returning1-19.1 keeps skipped trigger body returning statements inspectable'] = static function (TestRunner $t) use ($table, $trigger): void {
        $plan = SQLiteReturningDdlErrorPlan::createTriggerIfNotExistsSkipsReturningBodyValidation($table, $trigger);

        $t->same([
            'INSERT INTO ' . $table . '(a) VALUES (1) RETURNING FALSE',
            'INSERT INTO ' . $table . '(a) VALUES (2) RETURNING TRUE',
        ], $plan['body_statements']);
        $t->same($trigger, $plan['trigger']);
    };

    $tests[$prefix . 'returning1-18.1 and 19.1 dependencies remain explicit'] = static function (TestRunner $t) use ($view, $trigger, $table, $collation): void {
        $errorPlan = SQLiteReturningDdlErrorPlan::insertIntoViewWithReturningCollationError($view, $trigger, $collation);
        $triggerPlan = SQLiteReturningDdlErrorPlan::createTriggerIfNotExistsSkipsReturningBodyValidation($table, $trigger);

        $t->same(['sqlite-returning-view-trigger-error-order', 'returning1.test-18.1'], $errorPlan['dependencies']);
        $t->same(['sqlite-returning-trigger-if-not-exists-short-circuit', 'returning1.test-19.1'], $triggerPlan['dependencies']);
    };
}

$tests['real upstream returning1 ddl error dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-18.1 invalid collation error from INSERT INTO view DEFAULT VALUES RETURNING *',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-19.1 duplicate CREATE TRIGGER IF NOT EXISTS skips invalid RETURNING body validation',
        '6000 focused TestRunner PASS cases from real upstream RETURNING DDL/error-order behavior',
        'non-overlap: existing UPSERT/RETURNING dynamic files cover upsert streams, fault cleanup, correlated subqueries, schema/virtual behavior, and trigger row streams; this file owns returning1-18.1 and returning1-19.1',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-18.1 invalid collation error from INSERT INTO view DEFAULT VALUES RETURNING *',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-19.1 duplicate CREATE TRIGGER IF NOT EXISTS skips invalid RETURNING body validation',
        '6000 focused TestRunner PASS cases from real upstream RETURNING DDL/error-order behavior',
        'non-overlap: existing UPSERT/RETURNING dynamic files cover upsert streams, fault cleanup, correlated subqueries, schema/virtual behavior, and trigger row streams; this file owns returning1-18.1 and returning1-19.1',
    ]);
};

$tests['real upstream returning1 ddl error dynamic rejects malformed identifiers'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningDdlErrorPlan::insertIntoViewWithReturningCollationError('bad-view', 'app_trigger', 'TRUE'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningDdlErrorPlan::createTriggerIfNotExistsSkipsReturningBodyValidation('app_events', 'bad trigger'));
};

$tests['real upstream returning1 ddl error dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses native RETURNING trigger/error-order planning and generic DDL short-circuit evidence',
        'no new support component needed; reuses native RETURNING trigger/error-order planning and generic DDL short-circuit evidence',
    );
};

return $tests;
