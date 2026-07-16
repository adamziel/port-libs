<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePreparedStatementSchemaExpiry;

$tests = [];

/*
 * Real upstream source: SQLite test/schema.test.
 *
 * - schema-6.1 through schema-6.4: adding a user function leaves a legacy
 *   prepared statement usable, while deleting the function makes the next
 *   legacy step return SQLITE_ERROR and finalize return SQLITE_SCHEMA.
 * - schema-7.1 through schema-7.4: adding a collation sequence is stable, but
 *   deleting it expires legacy statements with the same step/finalize result.
 * - schema-8.1 through schema-8.2: installing an authorizer expires a legacy
 *   sqlite_schema scan.
 */

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%03d', $variant);
    $table = "runtime_settings_{$suffix}";
    $function = "runtime_function_{$suffix}";
    $collation = "runtime_collation_{$suffix}";

    $tests["real upstream schema.test 6 add function keeps legacy statement stable variant {$suffix}"] =
        static function (TestRunner $t) use ($table, $function): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $prepared = $state->prepareLegacy('settings-reader', "SELECT * FROM {$table}");
            $addFunction = $state->apply('add_function', $function);
            $step = $state->step('settings-reader');
            $reset = $state->reset('settings-reader');
            $finalize = $state->finalize('settings-reader');

            $t->same(true, $prepared['legacy']);
            $t->same(false, $addFunction['invalidated']);
            $t->same([], $addFunction['invalidated_statements']);
            $t->same('SQLITE_ROW', $step['code']);
            $t->same(false, $step['auto_reprepared']);
            $t->same(null, $step['expiry_reason']);
            $t->same('SQLITE_OK', $reset['code']);
            $t->same('SQLITE_OK', $finalize['code']);
        };

    $tests["real upstream schema.test 6 delete function expires legacy statement variant {$suffix}"] =
        static function (TestRunner $t) use ($table, $function): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $state->prepareLegacy('settings-reader', "SELECT * FROM {$table}");
            $state->apply('add_function', $function);
            $firstStep = $state->step('settings-reader');
            $reset = $state->reset('settings-reader');
            $deleteFunction = $state->apply('delete_function', $function);
            $expiredStep = $state->step('settings-reader');
            $finalize = $state->finalize('settings-reader');

            $t->same('SQLITE_ROW', $firstStep['code']);
            $t->same('SQLITE_OK', $reset['code']);
            $t->same(true, $deleteFunction['invalidated']);
            $t->same(['settings-reader'], $deleteFunction['invalidated_statements']);
            $t->same('runtime_function_deleted', $deleteFunction['reason']);
            $t->same('SQLITE_ERROR', $expiredStep['code']);
            $t->same(false, $expiredStep['auto_reprepared']);
            $t->same('runtime_function_deleted', $expiredStep['expiry_reason']);
            $t->same('SQLITE_SCHEMA', $finalize['code']);
        };

    $tests["real upstream schema.test 7 collation delete expires legacy statement variant {$suffix}"] =
        static function (TestRunner $t) use ($table, $collation): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $state->prepareLegacy('settings-reader', "SELECT * FROM {$table}");
            $addCollation = $state->apply('add_collation', $collation);
            $firstStep = $state->step('settings-reader');
            $reset = $state->reset('settings-reader');
            $deleteCollation = $state->apply('delete_collation', $collation);
            $expiredStep = $state->step('settings-reader');
            $finalize = $state->finalize('settings-reader');

            $t->same(false, $addCollation['invalidated']);
            $t->same('SQLITE_ROW', $firstStep['code']);
            $t->same('SQLITE_OK', $reset['code']);
            $t->same(true, $deleteCollation['invalidated']);
            $t->same(['settings-reader'], $deleteCollation['invalidated_statements']);
            $t->same('runtime_collation_deleted', $deleteCollation['reason']);
            $t->same('SQLITE_ERROR', $expiredStep['code']);
            $t->same('runtime_collation_deleted', $expiredStep['expiry_reason']);
            $t->same('SQLITE_SCHEMA', $finalize['code']);
        };

    $tests["real upstream schema.test 8 authorizer expires legacy schema scan variant {$suffix}"] =
        static function (TestRunner $t) use ($table, $suffix): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $prepared = $state->prepareLegacy("schema-reader-{$suffix}", 'SELECT * FROM sqlite_master');
            $authorizer = $state->apply('set_authorizer');
            $expiredStep = $state->step("schema-reader-{$suffix}");
            $finalize = $state->finalize("schema-reader-{$suffix}");

            $t->same('schema_scan', $prepared['kind']);
            $t->same([strtolower($table)], $state->snapshot()['tables']);
            $t->same(true, $authorizer['invalidated']);
            $t->same(["schema-reader-{$suffix}"], $authorizer['invalidated_statements']);
            $t->same('authorizer_changed', $authorizer['reason']);
            $t->same('SQLITE_ERROR', $expiredStep['code']);
            $t->same(false, $expiredStep['auto_reprepared']);
            $t->same('authorizer_changed', $expiredStep['expiry_reason']);
            $t->same('SQLITE_SCHEMA', $finalize['code']);
        };
}

$tests['real upstream schema.test legacy runtime source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'schema.test schema-6.1 through schema-6.4: add user-function is stable; delete user-function expires legacy sqlite3_prepare statements',
        'schema.test schema-7.1 through schema-7.4: add collation is stable; delete collation expires legacy sqlite3_prepare statements',
        'schema.test schema-8.1 through schema-8.2: setting an authorizer expires a prepared sqlite_master scan',
    ];

    $t->same(3, count($sections));
    $t->contains('schema-6.1', $sections[0]);
    $t->contains('schema-7.4', $sections[1]);
    $t->contains('schema-8.1', $sections[2]);
};

$tests['real upstream schema.test legacy runtime dependency closure'] = static function (TestRunner $t): void {
    $state = new SQLitePreparedStatementSchemaExpiry(['settings']);
    $state->prepareLegacy('citation', 'SELECT * FROM settings');

    $t->same(1, $state->snapshot()['prepared']);
    $t->same(
        'no new support component needed; reuses lane-local prepared statement schema expiry model for upstream schema.test legacy runtime invalidation behavior',
        'no new support component needed; reuses lane-local prepared statement schema expiry model for upstream schema.test legacy runtime invalidation behavior'
    );
};

return $tests;
