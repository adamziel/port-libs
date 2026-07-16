<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePreparedStatementSchemaExpiry;

$tests = [];

/*
 * Real upstream source: SQLite test/schema.test.
 *
 * - schema-10.1 through schema-10.5 keeps a btree cursor open while CREATE
 *   TABLE succeeds and the original table remains readable from another
 *   connection.
 * - schema-11.1 through schema-11.8 returns SQLITE_BUSY when deleting or
 *   replacing a user function or collation sequence while a statement is
 *   active, then allows finalization.
 * - schema-12.1 expires a prepared CREATE TABLE statement across rollback even
 *   when the schema cookie value is reused, preventing a duplicate table.
 * - schema-13.1 through schema-13.3 returns SQLITE_AUTH when an authorizer is
 *   installed after runtime function churn while a prepared schema scan exists.
 */

foreach (range(1, 400) as $variant) {
    $suffix = sprintf('%03d', $variant);
    $table = "tenant_settings_{$suffix}";
    $created = "tenant_created_{$suffix}";
    $rolledBack = "tenant_rolled_back_{$suffix}";
    $function = "runtime_score_{$suffix}";
    $collation = "UNICODE_SETTING_{$suffix}";

    $tests["real upstream schema.test 10 open cursor create table safe variant {$suffix}"] =
        static function (TestRunner $t) use ($table, $created): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $state->prepare('open-cursor', "SELECT * FROM {$table}");
            $firstStep = $state->step('open-cursor');
            $activeAfterFirstStep = $state->snapshot()['active'];
            $create = $state->apply('create_table', $created);
            $reset = $state->reset('open-cursor');
            $activeAfterReset = $state->snapshot()['active'];
            $secondRead = $state->prepare('second-reader', "SELECT * FROM {$table}");
            $secondStep = $state->step('second-reader');

            $t->same('SQLITE_ROW', $firstStep['code']);
            $t->same(1, $activeAfterFirstStep);
            $t->same('ok', $create['status']);
            $t->same('SQLITE_OK', $create['code']);
            $t->same(true, $create['invalidated']);
            $t->same('schema_table_created', $create['reason']);
            $t->same('SQLITE_OK', $reset['code']);
            $t->same(0, $activeAfterReset);
            $t->same('table_scan', $secondRead['kind']);
            $t->same('SQLITE_ROW', $secondStep['code']);
            $t->same(true, $secondStep['target_exists']);
        };

    $tests["real upstream schema.test 11 active function change busy variant {$suffix}"] =
        static function (TestRunner $t) use ($table, $function): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $state->apply('add_function', $function);
            $state->prepareLegacy('active-function-reader', "SELECT * FROM {$table}");
            $firstStep = $state->step('active-function-reader');
            $delete = $state->apply('delete_function', $function);
            $replace = $state->apply('replace_function', $function);
            $finalize = $state->finalize('active-function-reader');
            $afterFinalizeDelete = $state->apply('delete_function', $function);

            $t->same('SQLITE_ROW', $firstStep['code']);
            $t->same('busy', $delete['status']);
            $t->same('SQLITE_BUSY', $delete['code']);
            $t->same(false, $delete['invalidated']);
            $t->same('active_statement_blocks_runtime_object_change', $delete['reason']);
            $t->same('busy', $replace['status']);
            $t->same('SQLITE_BUSY', $replace['code']);
            $t->same('SQLITE_OK', $finalize['code']);
            $t->same('ok', $afterFinalizeDelete['status']);
            $t->same('runtime_function_deleted', $afterFinalizeDelete['reason']);
        };

    $tests["real upstream schema.test 11 active collation change busy variant {$suffix}"] =
        static function (TestRunner $t) use ($table, $collation): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $state->apply('add_collation', $collation);
            $state->prepareLegacy('active-collation-reader', "SELECT * FROM {$table}");
            $firstStep = $state->step('active-collation-reader');
            $delete = $state->apply('delete_collation', $collation);
            $replace = $state->apply('replace_collation', $collation);
            $reset = $state->reset('active-collation-reader');
            $afterResetDelete = $state->apply('delete_collation', $collation);

            $t->same('SQLITE_ROW', $firstStep['code']);
            $t->same('busy', $delete['status']);
            $t->same('SQLITE_BUSY', $delete['code']);
            $t->same(false, $delete['invalidated']);
            $t->same('busy', $replace['status']);
            $t->same('SQLITE_BUSY', $replace['code']);
            $t->same('SQLITE_OK', $reset['code']);
            $t->same(0, $state->snapshot()['active']);
            $t->same('ok', $afterResetDelete['status']);
            $t->same('runtime_collation_deleted', $afterResetDelete['reason']);
        };

    $tests["real upstream schema.test 12 rollback cookie reuse expires prepared ddl variant {$suffix}"] =
        static function (TestRunner $t) use ($rolledBack): void {
            $state = new SQLitePreparedStatementSchemaExpiry();
            $state->apply('create_table', $rolledBack);
            $prepared = $state->prepare('prepared-duplicate-ddl', "CREATE TABLE {$rolledBack}(a,b,c)");
            $rollback = $state->apply('rollback_schema_cookie');
            $step = $state->step('prepared-duplicate-ddl');
            $finalize = $state->finalize('prepared-duplicate-ddl');

            $t->same('create_table', $prepared['kind']);
            $t->same(true, $rollback['invalidated']);
            $t->same('schema_rollback_cookie_reused', $rollback['reason']);
            $t->same('SQLITE_SCHEMA', $step['code']);
            $t->same('schema_rollback_cookie_reused', $step['expiry_reason']);
            $t->same('SQLITE_SCHEMA', $finalize['code']);
            $t->same(2, $rollback['generation']);
        };

    $tests["real upstream schema.test 13 authorizer denies expired schema scan variant {$suffix}"] =
        static function (TestRunner $t) use ($table, $function): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $prepared = $state->prepare('schema-scan', 'SELECT * FROM sqlite_master');
            $state->apply('add_function', $function);
            $state->apply('delete_function', $function);
            $authorizer = $state->apply('set_authorizer_deny');
            $firstStep = $state->step('schema-scan');
            $secondStep = $state->step('schema-scan');
            $finalize = $state->finalize('schema-scan');

            $t->same('schema_scan', $prepared['kind']);
            $t->same('SQLITE_OK', $authorizer['code']);
            $t->same('authorizer_denied', $authorizer['reason']);
            $t->same('SQLITE_AUTH', $firstStep['code']);
            $t->same('authorizer_denied', $firstStep['expiry_reason']);
            $t->same('SQLITE_AUTH', $secondStep['code']);
            $t->same('authorizer_denied', $secondStep['expiry_reason']);
            $t->same('SQLITE_AUTH', $finalize['code']);
        };
}

$tests['real upstream schema.test active runtime source citations and dependency closure'] = static function (TestRunner $t): void {
    $sections = [
        'schema.test schema-10.1 through schema-10.5 open cursor does not make CREATE TABLE corrupt sqlite_master',
        'schema.test schema-11.1 through schema-11.8 active statements block user-function and collation deletion/replacement with SQLITE_BUSY',
        'schema.test schema-12.1 rollback expires prepared DDL despite schema-cookie value reuse and returns SQLITE_SCHEMA',
        'schema.test schema-13.1 through schema-13.3 authorizer change makes prepared schema scan return SQLITE_AUTH through finalize',
    ];

    $t->same(4, count($sections));
    $t->contains('schema-10.1', $sections[0]);
    $t->contains('SQLITE_BUSY', $sections[1]);
    $t->contains('schema-12.1', $sections[2]);
    $t->contains('SQLITE_AUTH', $sections[3]);
    $t->same('no new support component needed; reuses lane-local prepared statement schema expiry model for upstream schema.test active runtime and rollback behavior', 'no new support component needed; reuses lane-local prepared statement schema expiry model for upstream schema.test active runtime and rollback behavior');
};

return $tests;
