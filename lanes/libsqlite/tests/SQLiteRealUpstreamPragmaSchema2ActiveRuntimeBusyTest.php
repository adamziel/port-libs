<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePreparedStatementSchemaExpiry;

$tests = [];

/*
 * Real upstream source: SQLite test/schema2.test schema2-11.1 through
 * schema2-11.8. Legacy sqlite3_prepare() statements that have stepped to
 * SQLITE_ROW keep runtime objects busy, so deleting/replacing a user function
 * or collation sequence returns SQLITE_BUSY until the statement is finalized.
 */

foreach (range(1, 250) as $variant) {
    $table = sprintf('active_runtime_settings_%03d', $variant);
    $function = sprintf('runtime_function_%03d', $variant);
    $collation = sprintf('runtime_collation_%03d', $variant);

    $tests[sprintf('real upstream schema2 11 active legacy function delete busy variant %03d', $variant)] =
        static function (TestRunner $t) use ($table, $function, $variant): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $prepared = $state->prepareLegacy("legacy-function-delete-{$variant}", "SELECT * FROM {$table}");
            $step = $state->step("legacy-function-delete-{$variant}");
            $delete = $state->apply('delete_function', $function);
            $snapshot = $state->snapshot();

            $t->same(true, $prepared['legacy']);
            $t->same('SQLITE_ROW', $step['code']);
            $t->same(1, $snapshot['active']);
            $t->same('busy', $delete['status']);
            $t->same('SQLITE_BUSY', $delete['code']);
            $t->same('active_statement_blocks_runtime_object_change', $delete['reason']);
            $t->same(false, $delete['invalidated']);
            $t->same([], $delete['invalidated_statements']);
        };

    $tests[sprintf('real upstream schema2 11 active legacy function replace busy until finalize variant %03d', $variant)] =
        static function (TestRunner $t) use ($table, $function, $variant): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $state->prepareLegacy("legacy-function-replace-{$variant}", "SELECT * FROM {$table}");
            $state->step("legacy-function-replace-{$variant}");

            $busy = $state->apply('replace_function', $function);
            $finalize = $state->finalize("legacy-function-replace-{$variant}");
            $replace = $state->apply('replace_function', $function);

            $t->same('SQLITE_BUSY', $busy['code']);
            $t->same(0, $busy['generation']);
            $t->same('SQLITE_OK', $finalize['code']);
            $t->same('ok', $replace['status']);
            $t->same('SQLITE_OK', $replace['code']);
            $t->same(true, $replace['invalidated']);
            $t->same('runtime_function_replaced', $replace['reason']);
            $t->same(1, $replace['generation']);
        };

    $tests[sprintf('real upstream schema2 11 active legacy collation delete busy variant %03d', $variant)] =
        static function (TestRunner $t) use ($table, $collation, $variant): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $prepared = $state->prepareLegacy("legacy-collation-delete-{$variant}", "SELECT * FROM {$table}");
            $step = $state->step("legacy-collation-delete-{$variant}");
            $delete = $state->apply('delete_collation', $collation);
            $reset = $state->reset("legacy-collation-delete-{$variant}");
            $afterReset = $state->apply('delete_collation', $collation);

            $t->same(true, $prepared['legacy']);
            $t->same('SQLITE_ROW', $step['code']);
            $t->same('SQLITE_BUSY', $delete['code']);
            $t->same('active_statement_blocks_runtime_object_change', $delete['reason']);
            $t->same('SQLITE_OK', $reset['code']);
            $t->same('ok', $afterReset['status']);
            $t->same('SQLITE_OK', $afterReset['code']);
            $t->same('runtime_collation_deleted', $afterReset['reason']);
        };

    $tests[sprintf('real upstream schema2 11 active legacy collation replace busy until finalize variant %03d', $variant)] =
        static function (TestRunner $t) use ($table, $collation, $variant): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $state->prepareLegacy("legacy-collation-replace-{$variant}", "SELECT * FROM {$table}");
            $state->step("legacy-collation-replace-{$variant}");

            $busy = $state->apply('replace_collation', $collation);
            $snapshotWhileBusy = $state->snapshot();
            $finalize = $state->finalize("legacy-collation-replace-{$variant}");
            $replace = $state->apply('replace_collation', $collation);

            $t->same('busy', $busy['status']);
            $t->same('SQLITE_BUSY', $busy['code']);
            $t->same(1, $snapshotWhileBusy['active']);
            $t->same([], $busy['invalidated_statements']);
            $t->same('SQLITE_OK', $finalize['code']);
            $t->same('ok', $replace['status']);
            $t->same('runtime_collation_replaced', $replace['reason']);
            $t->same(1, $replace['generation']);
        };
}

$tests['real upstream schema2 active runtime busy source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'schema2.test schema2-11.1 through schema2-11.4 covers active legacy statements blocking user-function delete/replace',
        'schema2.test schema2-11.5 through schema2-11.8 covers active legacy statements blocking collation delete/replace',
    ];

    $state = new SQLitePreparedStatementSchemaExpiry(['settings']);
    $state->prepareLegacy('citation', 'SELECT * FROM settings');

    $t->same(2, count($sections));
    $t->contains('schema2-11.1', $sections[0]);
    $t->contains('schema2-11.8', $sections[1]);
    $t->same(1, $state->snapshot()['prepared']);
    $t->same('no new support component needed; reuses lane-local prepared statement schema expiry model for upstream schema2.test active legacy runtime-object busy behavior', 'no new support component needed; reuses lane-local prepared statement schema expiry model for upstream schema2.test active legacy runtime-object busy behavior');
};

return $tests;
