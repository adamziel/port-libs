<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteImportTransactionErrorYieldPlan;

$currentRows = static fn (): array => [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 64, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'autoload' => 'yes'],
    ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
];

$stagedRows = static fn (): array => [
    ['option_name' => 'blogdescription', 'option_value' => 'Imported Site', 'autoload' => 'yes'],
    ['option_id' => 65, 'option_name' => 'siteurl', 'option_value' => 'duplicate-name', 'autoload' => 'no'],
    ['option_name' => 'rewrite_rules', 'option_value' => 'rules', 'autoload' => 'no'],
];

$abortPlan = static fn (): array => SQLiteImportTransactionErrorYieldPlan::plan(
    $currentRows(),
    $stagedRows(),
    ['database_path' => '/tmp/wp-import-error-current-next29.sqlite', 'page_size' => 1024]
);

$partialPlan = static fn (): array => SQLiteImportTransactionErrorYieldPlan::plan(
    $currentRows(),
    $stagedRows(),
    ['database_path' => '/tmp/wp-import-error-current-next29.sqlite', 'page_size' => 1024, 'fail_on_error' => false]
);

$cleanPlan = static fn (): array => SQLiteImportTransactionErrorYieldPlan::plan(
    $currentRows(),
    [
        ['option_name' => 'blogdescription', 'option_value' => 'Imported Site', 'autoload' => 'yes'],
        ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:19:"plugin/plugin.php";}', 'autoload' => 'no'],
        ['option_name' => 'rewrite_rules', 'option_value' => 'rules', 'autoload' => 'no'],
    ],
    ['database_path' => '/tmp/wp-import-clean-current-next29.sqlite', 'page_size' => 1024]
);

$invalidPlan = static fn (): array => SQLiteImportTransactionErrorYieldPlan::plan(
    $currentRows(),
    [
        ['option_name' => 'blogdescription', 'option_value' => 'Imported Site', 'autoload' => 'yes'],
        ['option_name' => '', 'option_value' => 'bad', 'autoload' => 'no'],
        ['option_name' => 'rewrite_rules', 'option_value' => 'rules', 'autoload' => 'no'],
    ],
    ['database_path' => '/tmp/wp-import-invalid-current-next29.sqlite', 'page_size' => 1024]
);

$cases = [
    'abort status rolls back transaction' => [static fn (): mixed => $abortPlan()['status'], 'rolled_back'],
    'abort records one applied yield before error' => [static fn (): mixed => $abortPlan()['yielded'][0]['status'], 'applied'],
    'abort records second yield as error' => [static fn (): mixed => $abortPlan()['yielded'][1]['status'], 'error'],
    'abort stops before third staged row' => [static fn (): mixed => count($abortPlan()['yielded']), 2],
    'abort reports one error' => [static fn (): mixed => $abortPlan()['error_count'], 1],
    'abort discards applied count' => [static fn (): mixed => $abortPlan()['applied_count'], 0],
    'abort final rows restore original count' => [static fn (): mixed => count($abortPlan()['final_rows']), 4],
    'abort final row ids restore original order' => [static fn (): mixed => array_column($abortPlan()['final_rows'], 'option_id'), [1, 2, 64, 65]],
    'abort final row names restore original order' => [static fn (): mixed => array_column($abortPlan()['final_rows'], 'option_name'), ['siteurl', 'home', 'blogname', 'active_plugins']],
    'abort clears dirty pages' => [static fn (): mixed => $abortPlan()['dirty_pages'], []],
    'abort rollback transaction flag' => [static fn (): mixed => $abortPlan()['rollback']['transaction_rolled_back'], true],
    'abort rollback is not statement only' => [static fn (): mixed => $abortPlan()['rollback']['statement_rollback_only'], false],
    'abort restored current row count' => [static fn (): mixed => $abortPlan()['rollback']['restored_current_rows'], 4],
    'abort discarded applied row count' => [static fn (): mixed => $abortPlan()['rollback']['discarded_applied_rows'], 1],
    'first yield statement name' => [static fn (): mixed => $abortPlan()['yielded'][0]['statement'], 'wp_import_row_1'],
    'first yield event insert' => [static fn (): mixed => $abortPlan()['yielded'][0]['event'], 'insert'],
    'first yield current option id absent' => [static fn (): mixed => $abortPlan()['yielded'][0]['current_option_id'], null],
    'first yield next option id after insert' => [static fn (): mixed => $abortPlan()['yielded'][0]['next_option_id'], 67],
    'first yield inserted row id' => [static fn (): mixed => $abortPlan()['yielded'][0]['row']['option_id'], 66],
    'first yield inserted name' => [static fn (): mixed => $abortPlan()['yielded'][0]['row']['option_name'], 'blogdescription'],
    'first yield inserted autoload' => [static fn (): mixed => $abortPlan()['yielded'][0]['row']['autoload'], 'yes'],
    'error yield statement name' => [static fn (): mixed => $abortPlan()['yielded'][1]['statement'], 'wp_import_row_2'],
    'error yield event rollback statement' => [static fn (): mixed => $abortPlan()['yielded'][1]['event'], 'rollback_statement'],
    'error yield current option id from supplied rowid' => [static fn (): mixed => $abortPlan()['yielded'][1]['current_option_id'], 65],
    'error yield next option id remains after prior insert' => [static fn (): mixed => $abortPlan()['yielded'][1]['next_option_id'], 67],
    'error yield has null row' => [static fn (): mixed => $abortPlan()['yielded'][1]['row'], null],
    'error code is sqlite constraint' => [static fn (): mixed => $abortPlan()['errors'][0]['code'], 'sqlite_constraint'],
    'error message names unique option' => [static fn (): mixed => str_contains($abortPlan()['errors'][0]['message'], 'wp_options.option_name'), true],
    'error data ordinal is second' => [static fn (): mixed => $abortPlan()['errors'][0]['data']['ordinal'], 2],
    'error data statement is second' => [static fn (): mixed => $abortPlan()['errors'][0]['data']['statement'], 'wp_import_row_2'],
    'error data current option id' => [static fn (): mixed => $abortPlan()['errors'][0]['data']['current_option_id'], 65],
    'error data next option id' => [static fn (): mixed => $abortPlan()['errors'][0]['data']['next_option_id'], 67],
    'error data exception class' => [static fn (): mixed => $abortPlan()['errors'][0]['data']['exception'], LogicException::class],
    'error data sqlite abort scope' => [static fn (): mixed => $abortPlan()['errors'][0]['data']['sqlite_abort'], 'statement'],
    'begin mode is immediate' => [static fn (): mixed => $abortPlan()['begin']['mode'], 'immediate'],
    'begin write lock acquired' => [static fn (): mixed => $abortPlan()['begin']['write_lock_acquired'], true],
    'database path preserved' => [static fn (): mixed => $abortPlan()['database_path'], '/tmp/wp-import-error-current-next29.sqlite'],
    'page size preserved' => [static fn (): mixed => $abortPlan()['page_size'], 1024],
    'current row count tracked' => [static fn (): mixed => $abortPlan()['current_count'], 4],
    'staged row count tracked' => [static fn (): mixed => $abortPlan()['staged_count'], 3],
    'dependency names current next29' => [static fn (): mixed => in_array('sqlite-application-import-transaction-error-yield-current-next29', $abortPlan()['dependencies'], true), true],
    'partial status records statement errors' => [static fn (): mixed => $partialPlan()['status'], 'partial_errors'],
    'partial keeps applied count after continuing' => [static fn (): mixed => $partialPlan()['applied_count'], 2],
    'partial yields all staged rows' => [static fn (): mixed => count($partialPlan()['yielded']), 3],
    'partial third yield applies after error' => [static fn (): mixed => $partialPlan()['yielded'][2]['status'], 'applied'],
    'partial third yield receives next rowid after failed statement' => [static fn (): mixed => $partialPlan()['yielded'][2]['row']['option_id'], 67],
    'partial final rows include two successful inserts' => [static fn (): mixed => array_column($partialPlan()['final_rows'], 'option_name'), ['siteurl', 'home', 'blogname', 'active_plugins', 'blogdescription', 'rewrite_rules']],
    'partial dirty pages include inserted leaf' => [static fn (): mixed => $partialPlan()['dirty_pages'], [3]],
    'partial rollback is statement only' => [static fn (): mixed => $partialPlan()['rollback']['statement_rollback_only'], true],
    'partial transaction not rolled back' => [static fn (): mixed => $partialPlan()['rollback']['transaction_rolled_back'], false],
    'clean status commits' => [static fn (): mixed => $cleanPlan()['status'], 'committed'],
    'clean has no errors' => [static fn (): mixed => $cleanPlan()['error_count'], 0],
    'clean applies three rows' => [static fn (): mixed => $cleanPlan()['applied_count'], 3],
    'clean update yield current option id' => [static fn (): mixed => $cleanPlan()['yielded'][1]['current_option_id'], 65],
    'clean update yield event' => [static fn (): mixed => $cleanPlan()['yielded'][1]['event'], 'update'],
    'clean update keeps option id' => [static fn (): mixed => $cleanPlan()['yielded'][1]['row']['option_id'], 65],
    'clean third insert receives rowid after first insert' => [static fn (): mixed => $cleanPlan()['yielded'][2]['row']['option_id'], 67],
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
                ['option_id' => 1, 'option_name' => 'home', 'option_value' => 'a'],
                ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'b'],
            ], []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    }, 'rejected'],
    'duplicate current rowids are rejected' => [static function (): mixed {
        try {
            SQLiteImportTransactionErrorYieldPlan::plan([
                ['option_id' => 1, 'option_name' => 'home', 'option_value' => 'a'],
                ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'b'],
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
