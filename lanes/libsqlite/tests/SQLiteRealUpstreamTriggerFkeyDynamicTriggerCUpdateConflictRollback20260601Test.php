<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

/**
 * @param array<string,mixed> $array
 */
$value = static function (array $array, string $path): mixed {
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

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test';

$tests = [
    'real upstream triggerC update conflict rollback cites source block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('do_test triggerC-1.11', $source);
        $t->contains('CREATE TRIGGER au_tbl AFTER UPDATE ON t5', $source);
        $t->contains('UPDATE OR IGNORE t5 SET a = new.a, c = 10', $source);
        $t->contains('do_test triggerC-1.12', $source);
        $t->contains('UPDATE OR REPLACE t5 SET a = 4 WHERE a = 1', $source);
        $t->contains('do_test triggerC-1.13', $source);
        $t->contains('UPDATE t6 SET a=a', $source);
        $t->contains('do_test triggerC-1.14', $source);
        $t->contains('CREATE TRIGGER t1r1 AFTER UPDATE ON t1 BEGIN UPDATE cnt SET n=n+1; END', $source);
        $t->contains('do_test triggerC-1.15', $source);
        $t->contains('UPDATE OR ROLLBACK t1 SET a=100', $source);
        $t->contains('UNIQUE constraint failed: t1.a', $source);
    },
];

for ($seed = 1; $seed <= 100; ++$seed) {
    $baseKey = $seed * 100;
    $replacementKey = $baseKey + 40;
    $recursionDepthLimit = 16 + ($seed % 31);
    $uniqueConflictKey = $baseKey + 90;
    $sameRowidKey = $baseKey + 30;
    $case = sprintf('real upstream triggerC update conflict rollback dynamic %03d', $seed);
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCUpdateConflictRollbackPlan(
        $baseKey,
        $replacementKey,
        $recursionDepthLimit,
        $uniqueConflictKey
    );

    $paths = [
        'source' => 'triggerC.test triggerC-1.11..1.15',
        'scenarios.0' => 'triggerC-1.11',
        'scenarios.1' => 'triggerC-1.12',
        'scenarios.2' => 'triggerC-1.13',
        'scenarios.3' => 'triggerC-1.14',
        'scenarios.4' => 'triggerC-1.15',
        'operation' => 'after-update-conflict-rollback-order',
        'status' => 'constraint-failed',
        'recursive_update.scenario' => 'triggerC-1.12',
        'recursive_update.table_name' => 't5',
        'recursive_update.trigger_name' => 'au_tbl',
        'recursive_update.statement' => 'UPDATE OR REPLACE t5 SET a = ' . $replacementKey . ' WHERE a = ' . $baseKey,
        'recursive_update.trigger_statement' => 'UPDATE OR IGNORE t5 SET a = new.a, c = 10',
        'recursive_update.status' => 'constraint-failed',
        'recursive_update.error' => 'too many levels of trigger recursion',
        'recursive_update.outer_conflict_policy' => 'replace',
        'recursive_update.inner_conflict_policy' => 'ignore',
        'recursive_update.recursive_triggers' => true,
        'recursive_update.initial_row.a' => $baseKey,
        'recursive_update.initial_row.b' => $baseKey + 1,
        'recursive_update.initial_row.c' => $baseKey + 2,
        'recursive_update.outer_new_row.a' => $replacementKey,
        'recursive_update.outer_new_row.b' => $baseKey + 1,
        'recursive_update.outer_new_row.c' => $baseKey + 2,
        'recursive_update.recursive_frame_count' => $recursionDepthLimit,
        'recursive_update.first_recursive_frame.depth' => 1,
        'recursive_update.first_recursive_frame.old_a' => $replacementKey,
        'recursive_update.first_recursive_frame.old_c' => $baseKey + 2,
        'recursive_update.first_recursive_frame.new_a' => $replacementKey,
        'recursive_update.first_recursive_frame.new_c' => 10,
        'recursive_update.first_recursive_frame.inner_conflict_policy' => 'ignore',
        'recursive_update.first_recursive_frame.fires_after_update_again' => true,
        'recursive_update.last_recursive_frame.depth' => $recursionDepthLimit,
        'recursive_update.last_recursive_frame.old_a' => $replacementKey,
        'recursive_update.last_recursive_frame.old_c' => 10,
        'recursive_update.last_recursive_frame.new_a' => $replacementKey,
        'recursive_update.last_recursive_frame.new_c' => 10,
        'recursive_update.last_recursive_frame.fires_after_update_again' => true,
        'recursive_update.attempted_final_row.a' => $replacementKey,
        'recursive_update.attempted_final_row.b' => $baseKey + 1,
        'recursive_update.attempted_final_row.c' => 10,
        'recursive_update.rows_after_statement.0.a' => $baseKey,
        'recursive_update.rows_after_statement.0.b' => $baseKey + 1,
        'recursive_update.rows_after_statement.0.c' => $baseKey + 2,
        'recursive_update.statement_rolled_back' => true,
        'same_rowid_update.scenario' => 'triggerC-1.13',
        'same_rowid_update.table_name' => 't6',
        'same_rowid_update.trigger_name' => 'r1',
        'same_rowid_update.statement' => 'UPDATE t6 SET a=a',
        'same_rowid_update.status' => 'commit-ok',
        'same_rowid_update.integer_primary_key_self_assignment' => true,
        'same_rowid_update.after_trigger_fire_count' => 1,
        'same_rowid_update.initial_row.a' => $sameRowidKey,
        'same_rowid_update.initial_row.b' => $sameRowidKey + 1,
        'same_rowid_update.rows_after_statement.0.a' => $sameRowidKey,
        'same_rowid_update.rows_after_statement.0.b' => $sameRowidKey + 1,
        'same_rowid_update.statement_rolled_back' => false,
        'rollback_conflict.scenario' => 'triggerC-1.15',
        'rollback_conflict.table_name' => 't1',
        'rollback_conflict.counter_table_name' => 'cnt',
        'rollback_conflict.trigger_name' => 't1r1',
        'rollback_conflict.statement' => 'UPDATE OR ROLLBACK t1 SET a=' . $uniqueConflictKey,
        'rollback_conflict.status' => 'constraint-failed',
        'rollback_conflict.error' => 'UNIQUE constraint failed: t1.a',
        'rollback_conflict.conflict_policy' => 'rollback',
        'rollback_conflict.unique_columns.0' => 'a',
        'rollback_conflict.secondary_unique_columns.0' => 'b',
        'rollback_conflict.initial_rows.0.a' => $baseKey,
        'rollback_conflict.initial_rows.1.a' => $baseKey + 5,
        'rollback_conflict.initial_rows.2.a' => $baseKey + 10,
        'rollback_conflict.attempted_rows_before_conflict.0.a' => $uniqueConflictKey,
        'rollback_conflict.attempted_rows_before_conflict.1.a' => $baseKey + 5,
        'rollback_conflict.attempted_rows_before_conflict.2.a' => $baseKey + 10,
        'rollback_conflict.updated_keys_before_conflict.0' => $uniqueConflictKey,
        'rollback_conflict.updated_keys_before_conflict.1' => $baseKey + 5,
        'rollback_conflict.updated_keys_before_conflict.2' => $baseKey + 10,
        'rollback_conflict.conflict_row.a' => $baseKey + 5,
        'rollback_conflict.conflict_row.b' => $baseKey + 6,
        'rollback_conflict.conflict_row_ordinal' => 1,
        'rollback_conflict.not_visited_row.a' => $baseKey + 10,
        'rollback_conflict.counter_before' => 0,
        'rollback_conflict.counter_attempted_before_rollback' => 1,
        'rollback_conflict.counter_after_rollback' => 0,
        'rollback_conflict.after_trigger_fire_count_before_rollback' => 1,
        'rollback_conflict.after_trigger_fire_count_after_rollback' => 0,
        'rollback_conflict.constraint_checked_before_after_trigger_on_conflict_row' => true,
        'rollback_conflict.rows_after_statement.0.a' => $baseKey,
        'rollback_conflict.rows_after_statement.1.a' => $baseKey + 5,
        'rollback_conflict.rows_after_statement.2.a' => $baseKey + 10,
        'rollback_conflict.statement_rolled_back' => true,
        'dependencies.0' => 'sqlite-triggerC-update-or-replace-recursive-after-trigger-rolls-back',
        'dependencies.1' => 'sqlite-triggerC-rowid-self-update-fires-trigger-without-conflict',
        'dependencies.2' => 'sqlite-triggerC-update-or-rollback-unique-conflict-unwinds-trigger-side-effects',
        'non_overlap' => 'covers triggerC-1.11..1.15 conflict and rollback ordering, not triggerC-1.2..1.10 OLD/NEW lifecycle or triggerC-13 depth-limit counters',
    ];

    foreach ($paths as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }
}

$tests['real upstream triggerC update conflict rollback rejects nonpositive base key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCUpdateConflictRollbackPlan(0, 4, 10, 100));
};

$tests['real upstream triggerC update conflict rollback rejects unchanged replacement key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCUpdateConflictRollbackPlan(1, 1, 10, 100));
};

$tests['real upstream triggerC update conflict rollback rejects nonpositive recursion limit'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCUpdateConflictRollbackPlan(1, 4, 0, 100));
};

$tests['real upstream triggerC update conflict rollback rejects existing conflict key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerCUpdateConflictRollbackPlan(1, 4, 10, 6));
};

return $tests;
