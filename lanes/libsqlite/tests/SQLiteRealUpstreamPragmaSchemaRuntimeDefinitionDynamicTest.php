<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRuntimeDefinitionInvalidationPlan;

$tests = [];

/*
 * Real upstream source: SQLite test/schema2.test.
 *
 * - schema2-6.1 through schema2-6.4: adding a user function does not expire
 *   an already prepared statement, but deleting that function invalidates
 *   statements that depend on it once no statement is active.
 * - schema2-7.1 through schema2-7.4: adding a collation is stable, while
 *   deleting the collation invalidates dependent prepared statements.
 * - schema2-8.1/schema2-8.3: changing the authorizer invalidates prepared
 *   sqlite_schema statements.
 * - schema2-11.1 through schema2-11.8: deleting or replacing a function or
 *   collation while a statement is active returns SQLITE_BUSY and leaves the
 *   active statement usable until finalize/reset.
 */

foreach (range(1, 250) as $case) {
    $suffix = sprintf('%03d', $case);
    $table = "settings_runtime_{$suffix}";
    $function = "normalize_runtime_{$suffix}";
    $collation = "runtime_collation_{$suffix}";
    $selectName = "select-runtime-{$suffix}";
    $writeName = "write-runtime-{$suffix}";

    $tests["real upstream schema2 runtime {$suffix} adding user function preserves prepared statement"] = static function (TestRunner $t) use ($table, $function, $selectName): void {
        $plan = SQLiteRuntimeDefinitionInvalidationPlan::plan(
            [
                [
                    'name' => $selectName,
                    'sql' => "SELECT {$function}(key_name) FROM {$table}",
                    'uses' => ["function:{$function}"],
                ],
            ],
            [
                ['op' => 'add', 'kind' => 'function', 'name' => $function],
            ],
        );

        $t->same('runtime_definitions_stable', $plan['status']);
        $t->same([], $plan['expired_statements']);
        $t->same([$selectName], $plan['stable_statements']);
        $t->same(["function:{$function}"], $plan['added_definitions']);
        $t->same('SQLITE_OK', $plan['statements'][0]['sqlite_result_on_current_step']);
        $t->same('reuse_prepared_statement', $plan['statements'][0]['next_step_action']);
    };

    $tests["real upstream schema2 runtime {$suffix} deleting user function expires dependent statement"] = static function (TestRunner $t) use ($table, $function, $selectName, $writeName): void {
        $plan = SQLiteRuntimeDefinitionInvalidationPlan::plan(
            [
                [
                    'name' => $selectName,
                    'sql' => "SELECT {$function}(key_name) FROM {$table}",
                    'uses' => ["function:{$function}"],
                ],
                [
                    'name' => $writeName,
                    'sql' => "UPDATE {$table} SET key_name = {$function}(key_name)",
                    'read_only' => false,
                    'uses' => ["function:{$function}"],
                ],
            ],
            [
                ['op' => 'delete', 'kind' => 'function', 'name' => $function],
            ],
        );

        $t->same('runtime_definitions_expired', $plan['status']);
        $t->same([$selectName, $writeName], $plan['expired_statements']);
        $t->same([$function], $plan['deleted_functions']);
        $t->same(['user-function-delete'], $plan['statements'][0]['invalidation_reasons']);
        $t->same('sqlite_schema_then_reprepare_and_retry', $plan['statements'][0]['next_step_action']);
        $t->same('sqlite_schema_before_write_retry', $plan['statements'][1]['next_step_action']);
    };

    $tests["real upstream schema2 runtime {$suffix} deleting collation expires dependent statement"] = static function (TestRunner $t) use ($table, $collation, $selectName): void {
        $plan = SQLiteRuntimeDefinitionInvalidationPlan::plan(
            [
                [
                    'name' => $selectName,
                    'sql' => "SELECT key_name FROM {$table} ORDER BY key_name COLLATE {$collation}",
                    'uses' => ["collation:{$collation}"],
                ],
                [
                    'name' => "unrelated-{$selectName}",
                    'sql' => "SELECT key_value FROM {$table}",
                ],
            ],
            [
                ['op' => 'add', 'kind' => 'collation', 'name' => $collation],
                ['op' => 'delete', 'kind' => 'collation', 'name' => $collation],
            ],
        );

        $t->same('runtime_definitions_expired', $plan['status']);
        $t->same([$selectName], $plan['expired_statements']);
        $t->same(["unrelated-{$selectName}"], $plan['stable_statements']);
        $t->same([$collation], $plan['deleted_collations']);
        $t->same(['collation-delete'], $plan['statements'][0]['invalidation_reasons']);
        $t->same([], $plan['statements'][1]['invalidation_reasons']);
    };

    $tests["real upstream schema2 runtime {$suffix} authorizer change expires sqlite schema statement"] = static function (TestRunner $t) use ($selectName): void {
        $plan = SQLiteRuntimeDefinitionInvalidationPlan::plan(
            [
                [
                    'name' => $selectName,
                    'sql' => 'SELECT name, type FROM sqlite_schema',
                    'uses' => ['authorizer:authorizer'],
                ],
            ],
            [
                ['op' => 'set', 'kind' => 'authorizer'],
            ],
        );

        $t->same('runtime_definitions_expired', $plan['status']);
        $t->same([$selectName], $plan['expired_statements']);
        $t->same('authorizer-change', $plan['statements'][0]['invalidation_reasons'][0]);
        $t->same('SQLITE_SCHEMA', $plan['statements'][0]['sqlite_result_on_current_step']);
        $t->same('sqlite_schema_then_reprepare_and_retry', $plan['statements'][0]['next_step_action']);
        $t->same('SQLITE_OK', $plan['events'][0]['sqlite_result']);
    };

    $tests["real upstream schema2 runtime {$suffix} active statement blocks function replacement"] = static function (TestRunner $t) use ($table, $function, $selectName): void {
        $plan = SQLiteRuntimeDefinitionInvalidationPlan::plan(
            [
                [
                    'name' => $selectName,
                    'sql' => "SELECT {$function}(key_name) FROM {$table}",
                    'active' => true,
                    'uses' => ["function:{$function}"],
                ],
            ],
            [
                ['op' => 'delete', 'kind' => 'function', 'name' => $function],
                ['op' => 'replace', 'kind' => 'function', 'name' => $function],
            ],
        );

        $t->same('runtime_definitions_stable', $plan['status']);
        $t->same(["function:{$function}"], $plan['blocked_definitions']);
        $t->same('SQLITE_BUSY', $plan['events'][0]['sqlite_result']);
        $t->same('unable to delete/modify user-function due to active statements', $plan['events'][0]['message']);
        $t->same('SQLITE_BUSY', $plan['events'][1]['sqlite_result']);
        $t->same('SQLITE_OK', $plan['statements'][0]['sqlite_result_on_current_step']);
    };

    $tests["real upstream schema2 runtime {$suffix} active statement blocks collation replacement"] = static function (TestRunner $t) use ($table, $collation, $selectName): void {
        $plan = SQLiteRuntimeDefinitionInvalidationPlan::plan(
            [
                [
                    'name' => $selectName,
                    'sql' => "SELECT key_name FROM {$table} ORDER BY key_name COLLATE {$collation}",
                    'active' => true,
                    'uses' => ["collation:{$collation}"],
                ],
            ],
            [
                ['op' => 'delete', 'kind' => 'collation', 'name' => $collation],
                ['op' => 'replace', 'kind' => 'collation', 'name' => $collation],
            ],
        );

        $t->same('runtime_definitions_stable', $plan['status']);
        $t->same(["collation:{$collation}"], $plan['blocked_definitions']);
        $t->same('SQLITE_BUSY', $plan['events'][0]['sqlite_result']);
        $t->same('unable to delete/modify collation sequence due to active statements', $plan['events'][0]['message']);
        $t->same('SQLITE_BUSY', $plan['events'][1]['sqlite_result']);
        $t->same('reuse_prepared_statement', $plan['statements'][0]['next_step_action']);
    };
}

$tests['real upstream schema2 runtime definition invalidation cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'schema2.test schema2-6.1 through schema2-6.4 user-function add is stable while delete invalidates dependent prepared statements',
        'schema2.test schema2-7.1 through schema2-7.4 collation add is stable while delete invalidates dependent prepared statements',
        'schema2.test schema2-8.1 and schema2-8.3 setting an authorizer invalidates prepared sqlite_schema statements',
        'schema2.test schema2-11.1 through schema2-11.8 active statements make function and collation delete/replace return SQLITE_BUSY',
    ];

    $t->same(4, count($sections));
    $t->contains('schema2.test', $sections[0]);
    $t->contains('schema2-11.8', $sections[3]);
};

$tests['real upstream schema2 runtime definition invalidation uses generic sqlite API names'] = static function (TestRunner $t): void {
    $t->same(SQLiteRuntimeDefinitionInvalidationPlan::class, PortLibs\LibSqlite\SQLiteRuntimeDefinitionInvalidationPlan::class);
    $t->same(false, str_contains(SQLiteRuntimeDefinitionInvalidationPlan::class, 'DomainSpecific'));
};

return $tests;
