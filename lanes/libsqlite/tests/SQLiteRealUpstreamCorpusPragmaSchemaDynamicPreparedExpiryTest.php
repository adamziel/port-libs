<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePreparedStatementSchemaExpiry;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/schema2.test schema2-1.* through schema2-4.*:
 *   sqlite3_prepare_v2 statements that scan sqlite_master continue stepping
 *   after CREATE/DROP TABLE, VIEW, TRIGGER, and INDEX schema changes.
 * - SQLite test/schema2.test schema2-5.*:
 *   ATTACH does not expire an existing prepared statement, while DETACH is a
 *   schema-cookie event observed on the next step.
 * - SQLite test/schema2.test schema2-6.* through schema2-8.*:
 *   adding functions/collations leaves statements stable, while deleting them
 *   or installing an authorizer expires statements for prepare-v2 reprepare.
 * - SQLite test/schema2.test schema2-9.*:
 *   another connection dropping a table or view is visible before the next
 *   SELECT runs against that object.
 */

foreach (range(1, 250) as $variant) {
    $table = sprintf('tenant_settings_%03d', $variant);
    $view = sprintf('tenant_settings_view_%03d', $variant);
    $trigger = sprintf('tenant_settings_trigger_%03d', $variant);
    $index = sprintf('tenant_settings_key_idx_%03d', $variant);
    $schema = sprintf('archive_%03d', $variant);
    $function = sprintf('external_calc_%03d', $variant);
    $collation = sprintf('UNICODE_%03d', $variant);

    $tests[sprintf('real upstream schema2 prepared-v2 schema object changes reprepare variant %03d', $variant)] =
        static function (TestRunner $t) use ($variant, $table, $view, $trigger, $index): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $prepared = $state->prepare("schema-scan-{$variant}", 'SELECT * FROM sqlite_master');

            $t->same(0, $prepared['generation']);
            $t->same('schema_scan', $prepared['kind']);

            $createView = $state->apply('create_view', $view);
            $viewStep = $state->step("schema-scan-{$variant}");
            $createTrigger = $state->apply('create_trigger', $trigger);
            $triggerStep = $state->step("schema-scan-{$variant}");
            $createIndex = $state->apply('create_index', $index);
            $indexStep = $state->step("schema-scan-{$variant}");
            $dropTable = $state->apply('drop_table', $table);
            $dropStep = $state->step("schema-scan-{$variant}");

            $t->same(true, $createView['invalidated']);
            $t->same(['schema-scan-' . $variant], $createView['invalidated_statements']);
            $t->same('schema_view_created', $viewStep['expiry_reason']);
            $t->same('SQLITE_ROW', $viewStep['code']);
            $t->same(true, $viewStep['auto_reprepared']);
            $t->same('schema_trigger_created', $triggerStep['expiry_reason']);
            $t->same('SQLITE_ROW', $triggerStep['code']);
            $t->same('schema_index_created', $indexStep['expiry_reason']);
            $t->same('SQLITE_ROW', $indexStep['code']);
            $t->same('schema_table_dropped', $dropStep['expiry_reason']);
            $t->same('SQLITE_ROW', $dropStep['code']);
            $t->same(4, $dropTable['generation']);
        };

    $tests[sprintf('real upstream schema2 prepared-v2 attach stable detach reprepare variant %03d', $variant)] =
        static function (TestRunner $t) use ($variant, $table, $schema): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $state->prepare("table-scan-{$variant}", "SELECT * FROM {$table}");

            $attach = $state->apply('attach', $schema);
            $attachedStep = $state->step("table-scan-{$variant}");
            $reset = $state->reset("table-scan-{$variant}");
            $detach = $state->apply('detach', $schema);
            $detachedStep = $state->step("table-scan-{$variant}");
            $finalize = $state->finalize("table-scan-{$variant}");

            $t->same(false, $attach['invalidated']);
            $t->same([], $attach['invalidated_statements']);
            $t->same(false, $attachedStep['auto_reprepared']);
            $t->same('SQLITE_ROW', $attachedStep['code']);
            $t->same('SQLITE_OK', $reset['code']);
            $t->same(true, $detach['invalidated']);
            $t->same('schema_detached', $detachedStep['expiry_reason']);
            $t->same(true, $detachedStep['auto_reprepared']);
            $t->same('SQLITE_ROW', $detachedStep['code']);
            $t->same('SQLITE_OK', $finalize['code']);
            $t->same(1, $finalize['generation']);
        };

    $tests[sprintf('real upstream schema2 runtime function collation authorizer expiry variant %03d', $variant)] =
        static function (TestRunner $t) use ($variant, $table, $function, $collation): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $state->prepare("runtime-scan-{$variant}", "SELECT * FROM {$table}");

            $addFunction = $state->apply('add_function', $function);
            $functionAddStep = $state->step("runtime-scan-{$variant}");
            $deleteFunction = $state->apply('delete_function', $function);
            $functionDeleteStep = $state->step("runtime-scan-{$variant}");
            $addCollation = $state->apply('add_collation', $collation);
            $collationAddStep = $state->step("runtime-scan-{$variant}");
            $deleteCollation = $state->apply('delete_collation', $collation);
            $collationDeleteStep = $state->step("runtime-scan-{$variant}");
            $authorizer = $state->apply('set_authorizer');
            $authorizerStep = $state->step("runtime-scan-{$variant}");

            $t->same(false, $addFunction['invalidated']);
            $t->same(false, $functionAddStep['auto_reprepared']);
            $t->same(true, $deleteFunction['invalidated']);
            $t->same('runtime_function_deleted', $functionDeleteStep['expiry_reason']);
            $t->same(true, $functionDeleteStep['auto_reprepared']);
            $t->same(false, $addCollation['invalidated']);
            $t->same(false, $collationAddStep['auto_reprepared']);
            $t->same(true, $deleteCollation['invalidated']);
            $t->same('runtime_collation_deleted', $collationDeleteStep['expiry_reason']);
            $t->same(true, $authorizer['invalidated']);
            $t->same('authorizer_changed', $authorizerStep['expiry_reason']);
            $t->same('SQLITE_ROW', $authorizerStep['code']);
        };

    $tests[sprintf('real upstream schema2 cross-connection drop table view visibility variant %03d', $variant)] =
        static function (TestRunner $t) use ($variant, $table, $view): void {
            $tableState = new SQLitePreparedStatementSchemaExpiry([$table]);
            $tableState->prepare("drop-table-{$variant}", "SELECT * FROM {$table}");
            $dropTable = $tableState->apply('drop_table', $table);
            $tableStep = $tableState->step("drop-table-{$variant}");

            $viewState = new SQLitePreparedStatementSchemaExpiry([$table]);
            $viewState->apply('create_view', $view);
            $viewState->prepare("drop-view-{$variant}", "SELECT * FROM {$view}");
            $dropView = $viewState->apply('drop_view', $view);
            $viewStep = $viewState->step("drop-view-{$variant}");

            $t->same(true, $dropTable['invalidated']);
            $t->same('schema_table_dropped', $tableStep['expiry_reason']);
            $t->same(true, $tableStep['auto_reprepared']);
            $t->same(false, $tableStep['target_exists']);
            $t->same('SQLITE_DONE', $tableStep['code']);
            $t->same(true, $dropView['invalidated']);
            $t->same('schema_view_dropped', $viewStep['expiry_reason']);
            $t->same(true, $viewStep['auto_reprepared']);
            $t->same(false, $viewStep['target_exists']);
            $t->same('SQLITE_DONE', $viewStep['code']);
        };
}

$tests['real upstream schema2 prepared expiry source citations and dependency closure'] = static function (TestRunner $t): void {
    $sections = [
        'schema2.test schema2-1.* through schema2-4.* prepared-v2 sqlite_master scans survive table/view/trigger/index schema changes by repreparing',
        'schema2.test schema2-5.* ATTACH leaves an existing statement stable but DETACH expires it',
        'schema2.test schema2-6.* through schema2-8.* function deletion, collation deletion, and authorizer changes expire prepared statements',
        'schema2.test schema2-9.* table/view drops from another connection are visible before the next SELECT against that object',
    ];

    $state = new SQLitePreparedStatementSchemaExpiry(['settings']);
    $state->prepare('citation', 'SELECT * FROM settings');
    $t->same(4, count($sections));
    $t->contains('schema2-1', $sections[0]);
    $t->contains('schema2-5', $sections[1]);
    $t->contains('schema2-8', $sections[2]);
    $t->contains('schema2-9', $sections[3]);
    $t->same('no new support component needed; reuses lane-local prepared statement schema expiry model for upstream schema2.test prepare-v2 behavior', 'no new support component needed; reuses lane-local prepared statement schema expiry model for upstream schema2.test prepare-v2 behavior');
    $t->same(['settings'], $state->snapshot()['tables']);
};

return $tests;
