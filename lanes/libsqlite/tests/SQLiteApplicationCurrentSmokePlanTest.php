<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCurrentSmokePlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'primary_url', 'key_value' => 'https://old.example', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'dashboard_url', 'key_value' => 'https://old.example/dashboard', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'route_map', 'key_value' => '{"routes":[]}', 'load_policy' => 'no'],
];

return [
    'application current smoke commits staged setting updates and inserts' => static function (TestRunner $t) use ($currentRows): void {
        $smoke = SQLiteCurrentSmokePlan::keyValueImport($currentRows, [
            ['key_name' => 'primary_url', 'key_value' => 'https://current.example', 'load_policy' => 'yes'],
            ['key_name' => 'tenant_public', 'key_value' => '1', 'load_policy' => 'yes'],
            ['setting_id' => 9, 'key_name' => 'completed_migrations', 'key_value' => '[]', 'load_policy' => 'no'],
        ]);

        $t->same('committed', $smoke['status']);
        $t->same('immediate', $smoke['begin_mode']);
        $t->same(true, $smoke['write_lock_acquired']);
        $t->same(3, $smoke['current_count']);
        $t->same(3, $smoke['staged_count']);
        $t->same(3, $smoke['applied_count']);
        $t->same(0, $smoke['error_count']);
        $t->same(['applied', 'applied', 'applied'], $smoke['yielded_statuses']);
        $t->same(['update', 'insert', 'insert'], $smoke['yielded_events']);
        $t->same([], $smoke['error_codes']);
        $t->same(['completed_migrations', 'dashboard_url', 'primary_url', 'route_map', 'tenant_public'], $smoke['final_key_names']);
        $t->same('yes', $smoke['load_policy_by_name']['tenant_public']);
        $t->same('no', $smoke['load_policy_by_name']['completed_migrations']);
        $t->same([2], $smoke['dirty_pages']);
        $t->same(false, $smoke['rollback']['transaction_rolled_back']);
        $t->same(true, in_array('sqlite-application-current-smoke-key-value-import', $smoke['dependencies'], true));
    },
    'application current smoke can keep valid rows while yielding error shape' => static function (TestRunner $t) use ($currentRows): void {
        $smoke = SQLiteCurrentSmokePlan::keyValueImport($currentRows, [
            ['key_name' => 'layout_template', 'key_value' => 'standard', 'load_policy' => 'yes'],
            ['key_name' => 'dashboard_url', 'key_value' => 'https://duplicate.example/dashboard', 'load_policy' => 'yes', 'setting_id' => 8],
            ['key_name' => 'visual_skin', 'key_value' => 'default', 'load_policy' => 'yes'],
        ], ['fail_on_error' => false, 'statement_prefix' => 'app_current_smoke']);

        $t->same('partial_errors', $smoke['status']);
        $t->same(2, $smoke['applied_count']);
        $t->same(1, $smoke['error_count']);
        $t->same(['applied', 'error', 'applied'], $smoke['yielded_statuses']);
        $t->same(['insert', 'rollback_statement', 'insert'], $smoke['yielded_events']);
        $t->same(['sqlite_constraint'], $smoke['error_codes']);
        $t->same(['dashboard_url', 'layout_template', 'primary_url', 'route_map', 'visual_skin'], $smoke['final_key_names']);
        $t->same(true, $smoke['rollback']['statement_rollback_only']);
        $t->same(false, $smoke['rollback']['transaction_rolled_back']);
    },
    'application current smoke rolls back all applied rows on fail-on-error' => static function (TestRunner $t) use ($currentRows): void {
        $smoke = SQLiteCurrentSmokePlan::keyValueImport($currentRows, [
            ['key_name' => 'layout_template', 'key_value' => 'standard', 'load_policy' => 'yes'],
            ['key_name' => '', 'key_value' => 'bad', 'load_policy' => 'yes'],
            ['key_name' => 'visual_skin', 'key_value' => 'default', 'load_policy' => 'yes'],
        ], ['fail_on_error' => true]);

        $t->same('rolled_back', $smoke['status']);
        $t->same(0, $smoke['applied_count']);
        $t->same(1, $smoke['error_count']);
        $t->same(['applied', 'error'], $smoke['yielded_statuses']);
        $t->same(['insert', 'rollback_statement'], $smoke['yielded_events']);
        $t->same(['sqlite_import_error'], $smoke['error_codes']);
        $t->same(['dashboard_url', 'primary_url', 'route_map'], $smoke['final_key_names']);
        $t->same([], $smoke['dirty_pages']);
        $t->same(true, $smoke['rollback']['transaction_rolled_back']);
        $t->same(3, $smoke['rollback']['restored_current_rows']);
        $t->same(1, $smoke['rollback']['discarded_applied_rows']);
    },
];
