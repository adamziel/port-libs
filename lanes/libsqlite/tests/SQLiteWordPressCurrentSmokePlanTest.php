<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWordPressCurrentSmokePlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
];

return [
    'wordpress current smoke commits staged option updates and inserts' => static function (TestRunner $t) use ($currentRows): void {
        $smoke = SQLiteWordPressCurrentSmokePlan::optionImport($currentRows, [
            ['option_name' => 'siteurl', 'option_value' => 'https://current.example', 'autoload' => 'yes'],
            ['option_name' => 'blog_public', 'option_value' => '1', 'autoload' => 'yes'],
            ['option_id' => 9, 'option_name' => 'finished_upgrades', 'option_value' => '[]', 'autoload' => 'no'],
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
        $t->same([], $smoke['wp_error_codes']);
        $t->same(['blog_public', 'finished_upgrades', 'home', 'rewrite_rules', 'siteurl'], $smoke['final_option_names']);
        $t->same('yes', $smoke['autoload_by_name']['blog_public']);
        $t->same('no', $smoke['autoload_by_name']['finished_upgrades']);
        $t->same([2], $smoke['dirty_pages']);
        $t->same(false, $smoke['rollback']['transaction_rolled_back']);
        $t->same(true, in_array('sqlite-wordpress-current-smoke-option-import', $smoke['dependencies'], true));
    },
    'wordpress current smoke can keep valid rows while yielding wp error shape' => static function (TestRunner $t) use ($currentRows): void {
        $smoke = SQLiteWordPressCurrentSmokePlan::optionImport($currentRows, [
            ['option_name' => 'template', 'option_value' => 'twentytwentyfive', 'autoload' => 'yes'],
            ['option_name' => 'home', 'option_value' => 'https://duplicate.example', 'autoload' => 'yes', 'option_id' => 8],
            ['option_name' => 'stylesheet', 'option_value' => 'twentytwentyfive', 'autoload' => 'yes'],
        ], ['fail_on_error' => false, 'statement_prefix' => 'wp_current_smoke']);

        $t->same('partial_errors', $smoke['status']);
        $t->same(2, $smoke['applied_count']);
        $t->same(1, $smoke['error_count']);
        $t->same(['applied', 'error', 'applied'], $smoke['yielded_statuses']);
        $t->same(['insert', 'rollback_statement', 'insert'], $smoke['yielded_events']);
        $t->same(['sqlite_constraint'], $smoke['wp_error_codes']);
        $t->same(['home', 'rewrite_rules', 'siteurl', 'stylesheet', 'template'], $smoke['final_option_names']);
        $t->same(true, $smoke['rollback']['statement_rollback_only']);
        $t->same(false, $smoke['rollback']['transaction_rolled_back']);
    },
    'wordpress current smoke rolls back all applied rows on fail-on-error' => static function (TestRunner $t) use ($currentRows): void {
        $smoke = SQLiteWordPressCurrentSmokePlan::optionImport($currentRows, [
            ['option_name' => 'template', 'option_value' => 'twentytwentyfive', 'autoload' => 'yes'],
            ['option_name' => '', 'option_value' => 'bad', 'autoload' => 'yes'],
            ['option_name' => 'stylesheet', 'option_value' => 'twentytwentyfive', 'autoload' => 'yes'],
        ], ['fail_on_error' => true]);

        $t->same('rolled_back', $smoke['status']);
        $t->same(0, $smoke['applied_count']);
        $t->same(1, $smoke['error_count']);
        $t->same(['applied', 'error'], $smoke['yielded_statuses']);
        $t->same(['insert', 'rollback_statement'], $smoke['yielded_events']);
        $t->same(['sqlite_import_error'], $smoke['wp_error_codes']);
        $t->same(['home', 'rewrite_rules', 'siteurl'], $smoke['final_option_names']);
        $t->same([], $smoke['dirty_pages']);
        $t->same(true, $smoke['rollback']['transaction_rolled_back']);
        $t->same(3, $smoke['rollback']['restored_current_rows']);
        $t->same(1, $smoke['rollback']['discarded_applied_rows']);
    },
];
