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

$tests['real upstream trigger fkey dynamic trigger1 schema lifecycle cites hydrated source'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test');
    $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.1.1'));
    $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.2.3'));
    $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.3'));
    $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.5'));
    $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.8'));
    $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.12'));
    $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.14'));
    $t->true(is_string($source) && str_contains($source, 'do_test trigger1-2.2'));
};

$plan = SQLiteUpstreamTriggerFkeyDynamicPlan::trigger1SchemaLifecycleCorpus();

$tests['real upstream trigger fkey dynamic trigger1 schema lifecycle corpus metadata'] = static function (TestRunner $t) use ($plan): void {
    $t->same('trigger1.test', $plan['source']);
    $t->same(20, count($plan['scenarios']));
    $t->same(130, count($plan['cases']));
    $t->same('trigger1-1.1.1', $plan['scenarios'][0]);
    $t->same('trigger1-2.2', $plan['scenarios'][19]);
    $t->same('sqlite-upstream-trigger1-create-trigger-name-resolution-errors', $plan['dependencies'][0]);
    $t->same('sqlite-upstream-trigger1-trigger-parser-error-does-not-install-partial-trigger', $plan['dependencies'][5]);
};

foreach ($plan['cases'] as $case) {
    $expectations = [
        'source' => 'trigger1.test',
        'missing_main_table.ok' => false,
        'missing_main_table.error' => 'no such table: main.missing_table',
        'missing_main_table.trigger_installed' => false,
        'missing_temp_table.ok' => false,
        'missing_temp_table.error' => 'no such table: missing_table',
        'statement_trigger_rejected.ok' => false,
        'statement_trigger_rejected.error' => 'near "STATEMENT": syntax error',
        'statement_trigger_rejected.for_each_statement_not_supported' => true,
        'duplicate_trigger.create_if_not_exists_ok' => true,
        'duplicate_trigger.create_duplicate_ok' => false,
        'duplicate_trigger.plain_duplicate_error' => 'trigger tr_lifecycle_' . $case['variant'] . ' already exists',
        'transaction_create_rollback.begin_create_rollback' => true,
        'transaction_create_rollback.create_after_rollback_ok' => true,
        'transaction_create_rollback.rollback_removed_created_trigger' => true,
        'drop_restore_rollback.drop_in_transaction' => true,
        'drop_restore_rollback.rollback_restores_trigger' => true,
        'drop_restore_rollback.drop_after_rollback_ok' => true,
        'drop_table_cleanup.drop_table_ok' => true,
        'drop_table_cleanup.table_drop_removes_trigger' => true,
        'drop_table_cleanup.drop_trigger_after_table_error' => 'no such trigger: tr_lifecycle_' . $case['variant'],
        'temp_trigger_catalog.sqlite_master_count' => 0,
        'temp_trigger_catalog.temp_schema_only' => true,
        'system_table_trigger_rejected.ok' => false,
        'system_table_trigger_rejected.error' => 'cannot create trigger on system table',
        'view_table_timing_rules.instead_of_on_table_error' => 'cannot create INSTEAD OF trigger on table: settings_' . $case['variant'],
        'view_table_timing_rules.before_on_view_error' => 'cannot create BEFORE trigger on view: settings_view_' . $case['variant'],
        'view_table_timing_rules.after_on_view_error' => 'cannot create AFTER trigger on view: settings_view_' . $case['variant'],
        'parser_error_rollback.single_bad_select_error' => 'near ";": syntax error',
        'parser_error_rollback.second_bad_select_error' => 'near ";": syntax error',
        'parser_error_rollback.trigger_installed_after_syntax_error' => false,
        'parser_error_rollback.partial_body_not_installed' => true,
    ];

    foreach ($expectations as $path => $expected) {
        $tests['real upstream trigger fkey dynamic trigger1 schema lifecycle ' . $case['variant'] . ' ' . $path] = static function (TestRunner $t) use ($case, $path, $expected, $valueAt): void {
            $t->same($expected, $valueAt($case, (string) $path));
        };
    }

    $tests['real upstream trigger fkey dynamic trigger1 schema lifecycle ' . $case['variant'] . ' scenario membership'] = static function (TestRunner $t) use ($case): void {
        $t->true(in_array('trigger1-1.1.1', $case['scenarios'], true));
        $t->true(in_array('trigger1-1.8', $case['scenarios'], true));
        $t->true(in_array('trigger1-1.14', $case['scenarios'], true));
        $t->true(in_array('trigger1-2.2', $case['scenarios'], true));
    };
}

return $tests;
