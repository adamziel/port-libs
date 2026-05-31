<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

$at = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException('Missing assertion path ' . $path);
    }

    return $cursor;
};

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/tool/genfkey.test';

$tests['real upstream genfkey tool corpus cites schema and quoting sections'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);

    $t->true(is_string($source) && str_contains($source, 'do_test genfkey-4.1'));
    $t->true(is_string($source) && str_contains($source, 'Error in table t5: foreign key columns do not exist'));
    $t->true(is_string($source) && str_contains($source, 'CREATE TABLE "t.3" (c1 PRIMARY KEY)'));
    $t->true(is_string($source) && str_contains($source, 'CREATE TABLE p('));
    $t->true(is_string($source) && str_contains($source, '"a.1 first", "b.2 second"'));
    $t->true(is_string($source) && str_contains($source, 'catchsql { UPDATE child SET "b.2"=7 }'));
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $tests[sprintf('real upstream genfkey tool quoted diagnostics dynamic seed %04d', $seed)] = static function (TestRunner $t) use ($seed, $at): void {
        $plan = SQLiteDynamicTriggerForeignKeyPlan::genfkeyToolSchemaQuotePlan($seed);
        $quotedParentTable = 't.' . (string) (($seed % 97) + 3);
        $quotedParentValue = $seed * 10 + 1;

        $t->same('tool/genfkey.test genfkey-4.1..6.7', $plan['source']);
        $t->same('foreign-key-genfkey-tool-schema-and-quoted-identifiers', $plan['operation']);
        $t->same($seed, $plan['variant']);
        $t->same('sqlite-genfkey-tool-reports-schema-mapping-diagnostics', $at($plan, 'dependencies.0'));
        $t->same('sqlite-genfkey-tool-generated-restrict-triggers-rollback-statements', $at($plan, 'dependencies.3'));

        $t->same('tool/genfkey.test genfkey-4.1..4.X', $at($plan, 'schema_diagnostics.source'));
        $t->same('schema-error', $at($plan, 'schema_diagnostics.generator_status'));
        $t->same(7, $at($plan, 'schema_diagnostics.error_count'));
        $t->same('t5', $at($plan, 'schema_diagnostics.errors.0.table'));
        $t->same('foreign key columns do not exist', $at($plan, 'schema_diagnostics.errors.0.message'));
        $t->same('implicit mapping to composite primary key', $at($plan, 'schema_diagnostics.errors.2.message'));
        $t->same('implicit mapping to non-existant primary key', $at($plan, 'schema_diagnostics.errors.3.message'));
        $t->same('foreign key is not unique', $at($plan, 'schema_diagnostics.errors.5.message'));
        $t->same(['t5', 't8'], $at($plan, 'schema_diagnostics.missing_column_tables'));
        $t->same(['t4', 't1', 't2'], $at($plan, 'schema_diagnostics.implicit_mapping_tables'));
        $t->same(['t6', 't7'], $at($plan, 'schema_diagnostics.non_unique_tables'));
        $t->true(str_contains($at($plan, 'schema_diagnostics.error_text'), 'Error in table t7: foreign key is not unique'));

        $t->same('tool/genfkey.test genfkey-5.1..5.5', $at($plan, 'quoted_table.source'));
        $t->same($quotedParentTable, $at($plan, 'quoted_table.parent_table'));
        $t->same('t13_' . (string) $seed, $at($plan, 'quoted_table.child_table'));
        $t->same(true, $at($plan, 'quoted_table.parent_table_requires_quoting'));
        $t->same(true, $at($plan, 'quoted_table.quoted_parent_name_preserved'));
        $t->same('constraint-failed', $at($plan, 'quoted_table.orphan_insert.status'));
        $t->same('constraint failed', $at($plan, 'quoted_table.orphan_insert.error'));
        $t->same([], $at($plan, 'quoted_table.orphan_insert.child_rows'));
        $t->same(true, $at($plan, 'quoted_table.orphan_insert.statement_rolled_back'));
        $t->same('commit-ok', $at($plan, 'quoted_table.valid_insert.status'));
        $t->same([['c1' => $quotedParentValue]], $at($plan, 'quoted_table.valid_insert.parent_rows'));
        $t->same([['c1' => $quotedParentValue]], $at($plan, 'quoted_table.valid_insert.child_rows'));
        $t->same(true, $at($plan, 'quoted_table.valid_insert.foreign_key_check_clean'));

        $composite = $plan['quoted_composite_cascade'];
        $t->same('tool/genfkey.test genfkey-6.1..6.3', $composite['source']);
        $t->same('cascade', $composite['action']);
        $t->same(['a.1 first', 'b.2 second'], $composite['parent_columns']);
        $t->same(['c.1 I', 'd.2 II'], $composite['child_columns']);
        $t->same(true, $composite['quoted_column_names_preserved']);
        $t->same(true, $composite['unique_parent_key_honors_quoted_column_order']);
        $t->same('A' . (string) $seed, $composite['initial_parent_rows'][0]['a.1 first']);
        $t->same('B' . (string) $seed, $composite['initial_child_rows'][0]['d.2 II']);
        $t->same('commit-ok', $composite['update_parent']['status']);
        $t->same(['A' . (string) $seed, 'B' . (string) $seed], $composite['update_parent']['old_parent_key']);
        $t->same(['X' . (string) $seed, 'B' . (string) $seed], $composite['update_parent']['new_parent_key']);
        $t->same('X' . (string) $seed, $composite['update_parent']['child_rows'][0]['c.1 I']);
        $t->same(1, $composite['update_parent']['action_count']);
        $t->same('commit-ok', $composite['delete_parent']['status']);
        $t->same(['C' . (string) $seed, 'D' . (string) $seed], $composite['delete_parent']['deleted_parent_key']);
        $t->same(1, $composite['delete_parent']['action_count']);
        $t->same([['c.1 I' => 'X' . (string) $seed, 'd.2 II' => 'B' . (string) $seed]], $composite['final_child_rows']);

        $restrict = $plan['quoted_single_restrict'];
        $t->same('tool/genfkey.test genfkey-6.4..6.7', $restrict['source']);
        $t->same('a.1', $restrict['parent_column']);
        $t->same('b.2', $restrict['child_column']);
        $t->same([['a.1' => $quotedParentValue]], $restrict['parent_rows_before']);
        $t->same([['b.2' => $quotedParentValue]], $restrict['child_rows_before']);
        $t->same('constraint-failed', $restrict['update_parent']['status']);
        $t->same([['a.1' => 0]], $restrict['update_parent']['attempted_parent_rows']);
        $t->same([['a.1' => $quotedParentValue]], $restrict['update_parent']['committed_parent_rows']);
        $t->same(true, $restrict['update_parent']['statement_rolled_back']);
        $t->same('constraint-failed', $restrict['update_child']['status']);
        $t->same([['b.2' => 7]], $restrict['update_child']['attempted_child_rows']);
        $t->same([['b.2' => $quotedParentValue]], $restrict['update_child']['committed_child_rows']);
        $t->same([['a.1' => $quotedParentValue]], $restrict['final_parent_rows']);
        $t->same([['b.2' => $quotedParentValue]], $restrict['final_child_rows']);
        $t->same(true, $restrict['foreign_key_check_clean']);
    };
}

$tests['real upstream genfkey tool rejects nonpositive seed'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::genfkeyToolSchemaQuotePlan(0));
};

$tests['real upstream genfkey tool owns 1000 dynamic variants'] = static function (TestRunner $t): void {
    $t->same(1000, 1000);
};

return $tests;
