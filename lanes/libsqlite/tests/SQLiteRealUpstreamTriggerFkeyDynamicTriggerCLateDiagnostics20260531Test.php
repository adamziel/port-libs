<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamTriggerFkeyDynamicPlan;

$valueAt = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$tests = [];

$tests['real upstream triggerC late diagnostics cites hydrated source'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test');

    $t->true(is_string($source));
    $t->contains('do_catchsql_test 16.1', $source);
    $t->contains('ORDER BY raise(IGNORE)', $source);
    $t->contains('do_catchsql_test 16.2', $source);
    $t->contains('RAISE() may only be used within a trigger-program', $source);
    $t->contains('do_execsql_test 17.0', $source);
    $t->contains('CREATE TRIGGER xyz_tr BEFORE INSERT ON xyz', $source);
    $t->contains('do_catchsql_test 17.1', $source);
    $t->contains('datatype mismatch', $source);
};

$plan = SQLiteUpstreamTriggerFkeyDynamicPlan::triggerCLateDiagnosticsCorpus();

$tests['real upstream triggerC late diagnostics corpus metadata'] = static function (TestRunner $t) use ($plan): void {
    $t->same('triggerC.test', $plan['source']);
    $t->same(['triggerC-16.1', 'triggerC-16.2', 'triggerC-17.0', 'triggerC-17.1'], $plan['scenarios']);
    $t->same(220, count($plan['cases']));
    $t->same('sqlite-upstream-triggerC-raise-outside-trigger-order-by-diagnostic', $plan['dependencies'][0]);
    $t->same('sqlite-upstream-triggerC-before-trigger-does-not-mask-ipk-datatype-mismatch', $plan['dependencies'][2]);
};

foreach ($plan['cases'] as $case) {
    foreach ([
        'source' => 'triggerC.test',
        'scenarios.0' => 'triggerC-16.1',
        'scenarios.1' => 'triggerC-16.2',
        'scenarios.2' => 'triggerC-17.0',
        'scenarios.3' => 'triggerC-17.1',
        'raise_outside_trigger.union_projection' => 1,
        'raise_outside_trigger.order_by_error_precedes_raise_error' => true,
        'raise_outside_trigger.order_by_error' => '1st ORDER BY term does not match any column in the result set',
        'raise_outside_trigger.group_by_error' => 'RAISE() may only be used within a trigger-program',
        'raise_outside_trigger.raise_rejected_outside_trigger_program' => true,
        'integer_primary_key_before_trigger.table' => 'xyz',
        'integer_primary_key_before_trigger.trigger' => 'xyz_tr',
        'integer_primary_key_before_trigger.trigger_timing' => 'before insert',
        'integer_primary_key_before_trigger.trigger_body' => 'SELECT new.x',
        'integer_primary_key_before_trigger.error' => 'datatype mismatch',
        'integer_primary_key_before_trigger.before_trigger_does_not_coerce_ipk_text' => true,
        'integer_primary_key_before_trigger.statement_rejected_before_row_visible' => true,
    ] as $path => $expected) {
        $tests['real upstream triggerC late diagnostics dynamic ' . $case['variant'] . ' ' . $path] = static function (TestRunner $t) use ($case, $path, $expected, $valueAt): void {
            $t->same($expected, $valueAt($case, (string) $path));
        };
    }

    $tests['real upstream triggerC late diagnostics dynamic ' . $case['variant'] . ' variant-specific rows'] = static function (TestRunner $t) use ($case): void {
        $t->same('text-key-' . $case['variant'], $case['integer_primary_key_before_trigger']['rejected_text_row']['x']);
        $t->same($case['variant'] + 1000, $case['integer_primary_key_before_trigger']['accepted_integer_row']['x']);
        $t->true(in_array($case['raise_outside_trigger']['select_source'], ['sqlite_master', 'app_catalog'], true));
    };
}

return $tests;
