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

$tests['real upstream trigger fkey dynamic trigger1 late regression cites hydrated source'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test');
    $t->true(is_string($source) && str_contains($source, 'do_execsql_test trigger1-17.0'));
    $t->true(is_string($source) && str_contains($source, 'do_execsql_test trigger1-18.0'));
    $t->true(is_string($source) && str_contains($source, 'do_execsql_test trigger1-19.1'));
    $t->true(is_string($source) && str_contains($source, 'do_execsql_test trigger1-20.1'));
    $t->true(is_string($source) && str_contains($source, 'do_execsql_test trigger1-21.1'));
    $t->true(is_string($source) && str_contains($source, 'do_execsql_test trigger1-22.10'));
    $t->true(is_string($source) && str_contains($source, 'do_catchsql_test trigger1-23.1'));
    $t->true(is_string($source) && str_contains($source, 'do_catchsql_test trigger1-24.2'));
};

$plan = SQLiteUpstreamTriggerFkeyDynamicPlan::trigger1LateRegressionCorpus();

$tests['real upstream trigger fkey dynamic trigger1 late regression corpus metadata'] = static function (TestRunner $t) use ($plan): void {
    $t->same('trigger1.test', $plan['source']);
    $t->same(11, count($plan['scenarios']));
    $t->same(160, count($plan['cases']));
    $t->same('trigger1-17.0', $plan['scenarios'][0]);
    $t->same('trigger1-24.2', $plan['scenarios'][10]);
    $t->same('sqlite-upstream-trigger1-primary-key-trigger-integrity', $plan['dependencies'][0]);
    $t->same('sqlite-upstream-trigger1-raise-expression-message', $plan['dependencies'][7]);
};

foreach ($plan['cases'] as $case) {
    foreach ([
        'source' => 'trigger1.test',
        'primary_key_trigger.integrity_check' => 'ok',
        'primary_key_trigger.primary_key_coercion_preserves_unique_text_key' => true,
        'before_update_value_preservation.uses_pre_trigger_source_value' => true,
        'before_update_value_preservation.final.c' => $case['before_update_value_preservation']['initial']['b'],
        'before_update_value_preservation.final.b' => $case['before_update_value_preservation']['before_trigger_write']['b'],
        'before_update_assignment_order.assignments_read_original_row_image' => true,
        'before_update_assignment_order.final.c' => $case['before_update_assignment_order']['initial']['b'],
        'before_update_assignment_order.final.b' => $case['before_update_assignment_order']['initial']['b'] + 1,
        'without_rowid_before_update.new_value_read_does_not_expire_register' => true,
        'without_rowid_before_update.final_simple.c' => $case['without_rowid_before_update']['initial']['b'],
        'temp_trigger_detach_drop.drop_after_detach_ok' => true,
        'temp_trigger_detach_drop.detached_schema_trigger_body_allowed_to_resolve_before_drop' => true,
        'recursive_replace_delete.after_delete_trigger_deletes_conflicting_rows_before_replace_insert' => true,
        'recursive_replace_delete.final_rows.0' => [2, 0, 9],
        'window_trigger_register_validity.first_row_rewritten_to_blob_by_temp_before_insert_trigger' => true,
        'window_trigger_register_validity.window_subquery_in_after_update_trigger_preserves_register_validity' => true,
        'window_trigger_register_validity.final_rows.0.b' => '<blob>',
        'syntax_error_rollback.ok' => false,
        'syntax_error_rollback.error' => 'near "#1": syntax error',
        'syntax_error_rollback.trigger_not_installed' => true,
        'raise_expression.message_uses_new_row_expression' => true,
        'raise_expression.error' => sprintf('attempt to insert %d where is not a power of 2', $case['raise_expression']['rejected_value']),
    ] as $path => $expected) {
        $tests['real upstream trigger fkey dynamic trigger1 late regression ' . $case['variant'] . ' ' . $path] = static function (TestRunner $t) use ($case, $path, $expected, $valueAt): void {
            $t->same($expected, $valueAt($case, (string) $path));
        };
    }

    $tests['real upstream trigger fkey dynamic trigger1 late regression ' . $case['variant'] . ' scenarios include raise expression'] = static function (TestRunner $t) use ($case): void {
        $t->true(in_array('trigger1-24.1', $case['scenarios'], true));
        $t->true(in_array('trigger1-24.2', $case['scenarios'], true));
        $t->true(in_array($case['raise_expression']['rejected_value'], $case['raise_expression']['accepted_values'], true) === false);
        foreach ($case['raise_expression']['accepted_values'] as $value) {
            $t->same(true, $value === 0 || ($value & ($value - 1)) === 0);
        }
    };
}

return $tests;
