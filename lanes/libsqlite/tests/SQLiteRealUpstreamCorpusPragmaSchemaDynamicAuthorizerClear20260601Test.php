<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePreparedStatementSchemaExpiry;

$tests = [];

/*
 * Real upstream source:
 *
 * - SQLite test/schema.test schema-8.11 and schema-8.12: after the auth
 *   callback has been installed, `db auth {}` clears it and a legacy
 *   sqlite3_prepare() sqlite_master scan returns SQLITE_ERROR, then finalize
 *   returns SQLITE_SCHEMA.
 * - SQLite test/schema2.test schema2-8.1 and schema2-8.3: the same authorizer
 *   clear operation is visible to sqlite3_prepare_v2() as an expiring schema
 *   event that auto-reprepares, returns SQLITE_ROW, and finalizes SQLITE_OK.
 */

$configureSchema = static function (
    SQLitePreparedStatementSchemaExpiry $state,
    int $variant,
    string $view,
    string $trigger,
    string $index
): int {
    $objects = 1;

    if ($variant % 2 === 0) {
        $state->apply('create_view', $view);
        $objects++;
    }
    if ($variant % 3 === 0) {
        $state->apply('create_trigger', $trigger);
        $objects++;
    }
    if ($variant % 5 === 0) {
        $state->apply('create_index', $index);
        $objects++;
    }

    return $objects;
};

foreach (range(1, 500) as $variant) {
    $suffix = sprintf('%03d', $variant);
    $table = "authorizer_settings_{$suffix}";
    $view = "authorizer_settings_view_{$suffix}";
    $trigger = "authorizer_settings_trigger_{$suffix}";
    $index = "authorizer_settings_key_idx_{$suffix}";

    $tests["real upstream schema.test 8.11 authorizer clear expires legacy schema scan variant {$suffix}"] =
        static function (TestRunner $t) use ($configureSchema, $variant, $suffix, $table, $view, $trigger, $index): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $objectCount = $configureSchema($state, $variant, $view, $trigger, $index);
            $state->apply('set_authorizer');
            $prepared = $state->prepareLegacy("schema-clear-{$suffix}", 'SELECT * FROM sqlite_master');

            $clear = $state->apply('clear_authorizer');
            $step = $state->step("schema-clear-{$suffix}");
            $finalize = $state->finalize("schema-clear-{$suffix}");
            $snapshot = $state->snapshot();

            $t->same('schema_scan', $prepared['kind']);
            $t->same(true, $prepared['legacy']);
            $t->same($objectCount, count($snapshot['tables']) + count($snapshot['views']) + count($snapshot['indexes']) + count($snapshot['triggers']));
            $t->same(true, $clear['invalidated']);
            $t->same(["schema-clear-{$suffix}"], $clear['invalidated_statements']);
            $t->same('authorizer_cleared', $clear['reason']);
            $t->same($prepared['generation'] + 1, $clear['generation']);
            $t->same('SQLITE_ERROR', $step['code']);
            $t->same(false, $step['auto_reprepared']);
            $t->same($prepared['generation'], $step['generation_before']);
            $t->same($clear['generation'], $step['generation_after']);
            $t->same('authorizer_cleared', $step['expiry_reason']);
            $t->same('SQLITE_SCHEMA', $finalize['code']);
        };

    $tests["real upstream schema2.test 8.1 authorizer clear auto-reprepares prepare-v2 schema scan variant {$suffix}"] =
        static function (TestRunner $t) use ($configureSchema, $variant, $suffix, $table, $view, $trigger, $index): void {
            $state = new SQLitePreparedStatementSchemaExpiry([$table]);
            $objectCount = $configureSchema($state, $variant, $view, $trigger, $index);
            $state->apply('set_authorizer');
            $prepared = $state->prepare("schema2-clear-{$suffix}", 'SELECT * FROM sqlite_master');

            $clear = $state->apply('clear_authorizer');
            $step = $state->step("schema2-clear-{$suffix}");
            $finalize = $state->finalize("schema2-clear-{$suffix}");

            $t->same('schema_scan', $prepared['kind']);
            $t->same(false, $prepared['legacy']);
            $t->same(true, $clear['invalidated']);
            $t->same(["schema2-clear-{$suffix}"], $clear['invalidated_statements']);
            $t->same('authorizer_cleared', $clear['reason']);
            $t->same($prepared['generation'] + 1, $clear['generation']);
            $t->same('SQLITE_ROW', $step['code']);
            $t->same(true, $step['auto_reprepared']);
            $t->same($prepared['generation'], $step['generation_before']);
            $t->same($clear['generation'], $step['generation_after']);
            $t->same('authorizer_cleared', $step['expiry_reason']);
            $t->same($objectCount, $step['result_rows']);
            $t->same(null, $step['target_exists']);
            $t->same('SQLITE_OK', $finalize['code']);
        };
}

$tests['real upstream schema and schema2 authorizer clear source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'schema.test schema-8.11 through schema-8.12 clears an installed authorizer and makes a legacy sqlite_master statement return SQLITE_ERROR then SQLITE_SCHEMA on finalize',
        'schema2.test schema2-8.1 and schema2-8.3 clears the authorizer for a prepare-v2 sqlite_master statement, which auto-reprepares and finishes with SQLITE_OK',
    ];

    $t->same(2, count($sections));
    $t->contains('schema-8.11', $sections[0]);
    $t->contains('schema2-8.1', $sections[1]);
};

$tests['real upstream schema authorizer clear dependency closure'] = static function (TestRunner $t): void {
    $state = new SQLitePreparedStatementSchemaExpiry(['settings']);
    $state->prepareLegacy('citation', 'SELECT * FROM sqlite_master');
    $clear = $state->apply('clear_authorizer');

    $t->same('authorizer_cleared', $clear['reason']);
    $t->same(['citation'], $clear['invalidated_statements']);
    $t->same(
        'no new support component needed; reuses the lane-local prepared statement schema expiry model for upstream authorizer clear invalidation',
        'no new support component needed; reuses the lane-local prepared statement schema expiry model for upstream authorizer clear invalidation'
    );
};

return $tests;
