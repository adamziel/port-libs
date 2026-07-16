<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteImportTransactionErrorYieldPlan;

$currentRows = static fn (): array => [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://old.example', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://old.example', 'load_policy' => 'yes'],
    ['setting_id' => 64, 'key_name' => 'blogname', 'key_value' => 'Old Site', 'load_policy' => 'yes'],
    ['setting_id' => 65, 'key_name' => 'active_plugins', 'key_value' => 'a:0:{}', 'load_policy' => 'no'],
];

$stagedRows = static fn (): array => [
    ['key_name' => 'blogdescription', 'key_value' => 'Imported Site', 'load_policy' => 'yes'],
    ['setting_id' => 65, 'key_name' => 'siteurl', 'key_value' => 'duplicate-name', 'load_policy' => 'no'],
    ['key_name' => 'rewrite_rules', 'key_value' => 'rules', 'load_policy' => 'no'],
];

$abortPlan = static fn (): array => SQLiteImportTransactionErrorYieldPlan::plan(
    $currentRows(),
    $stagedRows(),
    ['database_path' => '/tmp/app-import-error-current-next29.sqlite', 'page_size' => 1024]
);

$partialPlan = static fn (): array => SQLiteImportTransactionErrorYieldPlan::plan(
    $currentRows(),
    $stagedRows(),
    ['database_path' => '/tmp/app-import-error-current-next29.sqlite', 'page_size' => 1024, 'fail_on_error' => false]
);

$cleanPlan = static fn (): array => SQLiteImportTransactionErrorYieldPlan::plan(
    $currentRows(),
    [
        ['key_name' => 'blogdescription', 'key_value' => 'Imported Site', 'load_policy' => 'yes'],
        ['setting_id' => 65, 'key_name' => 'active_plugins', 'key_value' => 'a:1:{i:0;s:19:"plugin/plugin.php";}', 'load_policy' => 'no'],
        ['key_name' => 'rewrite_rules', 'key_value' => 'rules', 'load_policy' => 'no'],
    ],
    ['database_path' => '/tmp/app-import-clean-current-next29.sqlite', 'page_size' => 1024]
);

$invalidPlan = static fn (): array => SQLiteImportTransactionErrorYieldPlan::plan(
    $currentRows(),
    [
        ['key_name' => 'blogdescription', 'key_value' => 'Imported Site', 'load_policy' => 'yes'],
        ['key_name' => '', 'key_value' => 'bad', 'load_policy' => 'no'],
        ['key_name' => 'rewrite_rules', 'key_value' => 'rules', 'load_policy' => 'no'],
    ],
    ['database_path' => '/tmp/app-import-invalid-current-next29.sqlite', 'page_size' => 1024]
);

$cases = [
    'abort status rolls back transaction' => [static fn (): mixed => $abortPlan()['status'], 'rolled_back'],
    'abort records one applied yield before error' => [static fn (): mixed => $abortPlan()['yielded'][0]['status'], 'applied'],
    'abort records second yield as error' => [static fn (): mixed => $abortPlan()['yielded'][1]['status'], 'error'],
    'abort stops before third staged row' => [static fn (): mixed => count($abortPlan()['yielded']), 2],
    'abort reports one error' => [static fn (): mixed => $abortPlan()['error_count'], 1],
    'abort discards applied count' => [static fn (): mixed => $abortPlan()['applied_count'], 0],
    'abort final rows restore original count' => [static fn (): mixed => count($abortPlan()['final_rows']), 4],
    'abort final row ids restore original order' => [static fn (): mixed => array_column($abortPlan()['final_rows'], 'setting_id'), [1, 2, 64, 65]],
    'abort final row names restore original order' => [static fn (): mixed => array_column($abortPlan()['final_rows'], 'key_name'), ['siteurl', 'home', 'blogname', 'active_plugins']],
    'abort clears dirty pages' => [static fn (): mixed => $abortPlan()['dirty_pages'], []],
    'abort rollback transaction flag' => [static fn (): mixed => $abortPlan()['rollback']['transaction_rolled_back'], true],
    'abort rollback is not statement only' => [static fn (): mixed => $abortPlan()['rollback']['statement_rollback_only'], false],
    'abort restored current row count' => [static fn (): mixed => $abortPlan()['rollback']['restored_current_rows'], 4],
    'abort discarded applied row count' => [static fn (): mixed => $abortPlan()['rollback']['discarded_applied_rows'], 1],
    'first yield statement name' => [static fn (): mixed => $abortPlan()['yielded'][0]['statement'], 'app_import_row_1'],
    'first yield event insert' => [static fn (): mixed => $abortPlan()['yielded'][0]['event'], 'insert'],
    'first yield current option id absent' => [static fn (): mixed => $abortPlan()['yielded'][0]['current_setting_id'], null],
    'first yield next option id after insert' => [static fn (): mixed => $abortPlan()['yielded'][0]['next_setting_id'], 67],
    'first yield inserted row id' => [static fn (): mixed => $abortPlan()['yielded'][0]['row']['setting_id'], 66],
    'first yield inserted name' => [static fn (): mixed => $abortPlan()['yielded'][0]['row']['key_name'], 'blogdescription'],
    'first yield inserted load_policy' => [static fn (): mixed => $abortPlan()['yielded'][0]['row']['load_policy'], 'yes'],
    'error yield statement name' => [static fn (): mixed => $abortPlan()['yielded'][1]['statement'], 'app_import_row_2'],
    'error yield event rollback statement' => [static fn (): mixed => $abortPlan()['yielded'][1]['event'], 'rollback_statement'],
    'error yield current option id from supplied rowid' => [static fn (): mixed => $abortPlan()['yielded'][1]['current_setting_id'], 65],
    'error yield next option id remains after prior insert' => [static fn (): mixed => $abortPlan()['yielded'][1]['next_setting_id'], 67],
    'error yield has null row' => [static fn (): mixed => $abortPlan()['yielded'][1]['row'], null],
    'error code is sqlite constraint' => [static fn (): mixed => $abortPlan()['errors'][0]['code'], 'sqlite_constraint'],
    'error message names unique key' => [static fn (): mixed => str_contains($abortPlan()['errors'][0]['message'], 'app_settings.key_name'), true],
    'error data ordinal is second' => [static fn (): mixed => $abortPlan()['errors'][0]['data']['ordinal'], 2],
    'error data statement is second' => [static fn (): mixed => $abortPlan()['errors'][0]['data']['statement'], 'app_import_row_2'],
    'error data current option id' => [static fn (): mixed => $abortPlan()['errors'][0]['data']['current_setting_id'], 65],
    'error data next option id' => [static fn (): mixed => $abortPlan()['errors'][0]['data']['next_setting_id'], 67],
    'error data exception class' => [static fn (): mixed => $abortPlan()['errors'][0]['data']['exception'], LogicException::class],
    'error data sqlite abort scope' => [static fn (): mixed => $abortPlan()['errors'][0]['data']['sqlite_abort'], 'statement'],
    'begin mode is immediate' => [static fn (): mixed => $abortPlan()['begin']['mode'], 'immediate'],
    'begin write lock acquired' => [static fn (): mixed => $abortPlan()['begin']['write_lock_acquired'], true],
    'database path preserved' => [static fn (): mixed => $abortPlan()['database_path'], '/tmp/app-import-error-current-next29.sqlite'],
    'page size preserved' => [static fn (): mixed => $abortPlan()['page_size'], 1024],
    'current row count tracked' => [static fn (): mixed => $abortPlan()['current_count'], 4],
    'staged row count tracked' => [static fn (): mixed => $abortPlan()['staged_count'], 3],
    'dependency names current next29' => [static fn (): mixed => in_array('sqlite-application-import-transaction-error-yield-current-next29', $abortPlan()['dependencies'], true), true],
    'partial status records statement errors' => [static fn (): mixed => $partialPlan()['status'], 'partial_errors'],
    'partial keeps applied count after continuing' => [static fn (): mixed => $partialPlan()['applied_count'], 2],
    'partial yields all staged rows' => [static fn (): mixed => count($partialPlan()['yielded']), 3],
    'partial third yield applies after error' => [static fn (): mixed => $partialPlan()['yielded'][2]['status'], 'applied'],
    'partial third yield receives next rowid after failed statement' => [static fn (): mixed => $partialPlan()['yielded'][2]['row']['setting_id'], 67],
    'partial final rows include two successful inserts' => [static fn (): mixed => array_column($partialPlan()['final_rows'], 'key_name'), ['siteurl', 'home', 'blogname', 'active_plugins', 'blogdescription', 'rewrite_rules']],
    'partial dirty pages include inserted leaf' => [static fn (): mixed => $partialPlan()['dirty_pages'], [3]],
    'partial rollback is statement only' => [static fn (): mixed => $partialPlan()['rollback']['statement_rollback_only'], true],
    'partial transaction not rolled back' => [static fn (): mixed => $partialPlan()['rollback']['transaction_rolled_back'], false],
    'clean status commits' => [static fn (): mixed => $cleanPlan()['status'], 'committed'],
    'clean has no errors' => [static fn (): mixed => $cleanPlan()['error_count'], 0],
    'clean applies three rows' => [static fn (): mixed => $cleanPlan()['applied_count'], 3],
    'clean update yield current option id' => [static fn (): mixed => $cleanPlan()['yielded'][1]['current_setting_id'], 65],
    'clean update yield event' => [static fn (): mixed => $cleanPlan()['yielded'][1]['event'], 'update'],
    'clean update keeps option id' => [static fn (): mixed => $cleanPlan()['yielded'][1]['row']['setting_id'], 65],
    'clean third insert receives rowid after first insert' => [static fn (): mixed => $cleanPlan()['yielded'][2]['row']['setting_id'], 67],
    'clean dirty pages include second leaf' => [static fn (): mixed => $cleanPlan()['dirty_pages'], [3]],
    'invalid row maps to import error code' => [static fn (): mixed => $invalidPlan()['errors'][0]['code'], 'sqlite_import_error'],
    'invalid row error data exception class' => [static fn (): mixed => $invalidPlan()['errors'][0]['data']['exception'], InvalidArgumentException::class],
    'invalid row rolls back transaction' => [static fn (): mixed => $invalidPlan()['rollback']['transaction_rolled_back'], true],
    'custom statement prefix is used' => [static fn (): mixed => SQLiteImportTransactionErrorYieldPlan::plan($currentRows(), [$stagedRows()[0]], ['statement_prefix' => 'copy_options'])['yielded'][0]['statement'], 'copy_options_1'],
    'exclusive begin is accepted' => [static fn (): mixed => SQLiteImportTransactionErrorYieldPlan::plan($currentRows(), [$stagedRows()[0]], ['begin' => 'BEGIN EXCLUSIVE'])['begin']['mode'], 'exclusive'],
    'continue option is reported' => [static fn (): mixed => $partialPlan()['fail_on_error'], false],
    'abort option is reported' => [static fn (): mixed => $abortPlan()['fail_on_error'], true],
    'relative path is rejected' => [static function () use ($currentRows, $stagedRows): mixed {
        try {
            SQLiteImportTransactionErrorYieldPlan::plan($currentRows(), $stagedRows(), ['database_path' => 'wp.sqlite']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'bad page size is rejected' => [static function () use ($currentRows, $stagedRows): mixed {
        try {
            SQLiteImportTransactionErrorYieldPlan::plan($currentRows(), $stagedRows(), ['page_size' => 1000]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'deferred begin is rejected' => [static function () use ($currentRows, $stagedRows): mixed {
        try {
            SQLiteImportTransactionErrorYieldPlan::plan($currentRows(), $stagedRows(), ['begin' => 'BEGIN DEFERRED']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'duplicate current names are rejected' => [static function (): mixed {
        try {
            SQLiteImportTransactionErrorYieldPlan::plan([
                ['setting_id' => 1, 'key_name' => 'home', 'key_value' => 'a'],
                ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'b'],
            ], []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'duplicate current rowids are rejected' => [static function (): mixed {
        try {
            SQLiteImportTransactionErrorYieldPlan::plan([
                ['setting_id' => 1, 'key_name' => 'home', 'key_value' => 'a'],
                ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'b'],
            ], []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['sqlite application import transaction error current next29 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
