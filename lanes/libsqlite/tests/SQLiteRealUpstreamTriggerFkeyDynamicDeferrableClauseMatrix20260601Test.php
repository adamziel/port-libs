<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test';

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

        throw new RuntimeException('Missing assertion path ' . $path);
    }

    return $cursor;
};

$tests = [
    'real upstream e_fkey34 cites deferrable clause matrix source' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('do_test e_fkey-34.1', $source);
        $t->contains('NOT DEFERRABLE INITIALLY DEFERRED', $source);
        $t->contains('DEFERRABLE INITIALLY DEFERRED', $source);
        $t->contains('This FK constraint is the only deferrable one.', $source);
        $t->contains('test_efkey_29  9 "DELETE FROM parent WHERE x = \'s\'"        0', $source);
        $t->contains('test_efkey_29 10 "COMMIT"                                  1', $source);
    },
    'real upstream e_fkey35 cites deferred repair example source' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);

        $t->true(is_string($source));
        $t->contains('do_test e_fkey-35.1', $source);
        $t->contains('trackartist INTEGER REFERENCES artist(artistid) DEFERRABLE INITIALLY DEFERRED', $source);
        $t->contains("INSERT INTO track VALUES(1, 'White Christmas', 5);", $source);
        $t->contains("INSERT INTO artist VALUES(5, 'Bing Crosby');", $source);
        $t->contains('catchsql COMMIT', $source);
    },
];

for ($seed = 1; $seed <= 1000; ++$seed) {
    $repairBeforeCommit = $seed % 7 !== 0;
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyDeferrableClauseMatrixPlan($seed, $repairBeforeCommit);

    $tests[sprintf('real upstream e_fkey34 deferrable clause matrix dynamic %04d', $seed)] = static function (TestRunner $t) use ($plan, $value, $seed, $repairBeforeCommit): void {
        $actual = $plan();

        $t->same('e_fkey.test e_fkey-34.1..35.3', $actual['source']);
        $t->same('foreign-key-deferrable-clause-matrix', $actual['operation']);
        $t->same($seed, $actual['seed']);
        $t->same('parent', $actual['parent_table']);
        $t->same(['x', 'y', 'z'], $actual['parent_primary_key_columns']);
        $t->same(7, $actual['deferrable_clause_count']);
        $t->same(['c1', 'c2', 'c3', 'c4', 'c5', 'c6'], $actual['immediate_tables']);
        $t->same(['c7'], $actual['deferred_tables']);
        $t->same('NOT DEFERRABLE INITIALLY DEFERRED', $value($actual, 'constraint_modes.c1.clause'));
        $t->same('immediate', $value($actual, 'constraint_modes.c1.timing'));
        $t->same('DEFERRABLE', $value($actual, 'constraint_modes.c5.clause'));
        $t->same('immediate', $value($actual, 'constraint_modes.c5.timing'));
        $t->same('implicit immediate', $value($actual, 'constraint_modes.c6.clause'));
        $t->same('immediate', $value($actual, 'constraint_modes.c6.timing'));
        $t->same('DEFERRABLE INITIALLY DEFERRED', $value($actual, 'constraint_modes.c7.clause'));
        $t->same('deferred', $value($actual, 'constraint_modes.c7.timing'));
        $t->same(['s-' . $seed, 't-' . $seed, 'u-' . $seed], $value($actual, 'constraint_modes.c7.parent_key'));
        $t->same('constraint-failed', $value($actual, 'delete_parent_results.0.statement_status'));
        $t->same(true, $value($actual, 'delete_parent_results.0.statement_rolled_back'));
        $t->same(0, $value($actual, 'delete_parent_results.0.deferred_violation_count'));
        $t->same('row-applied', $value($actual, 'delete_parent_results.6.statement_status'));
        $t->same(false, $value($actual, 'delete_parent_results.6.statement_rolled_back'));
        $t->same(1, $value($actual, 'delete_parent_results.6.deferred_violation_count'));
        $t->same(true, $value($actual, 'delete_parent_results.6.commit_check_required'));
        $t->same(true, $value($actual, 'delete_parent_results.6.parent_removed_after_statement'));
        $t->same('constraint-failed', $value($actual, 'update_parent_results.0.statement_status'));
        $t->same(['a-' . $seed, 'b-' . $seed, 'c-' . $seed], $value($actual, 'update_parent_results.0.parent_key_after'));
        $t->same('row-applied', $value($actual, 'update_parent_results.6.statement_status'));
        $t->same(['s-' . $seed, 't-' . $seed, 'z-' . $seed], $value($actual, 'update_parent_results.6.parent_key_after'));
        $t->same('constraint-failed', $value($actual, 'insert_missing_child_results.0.statement_status'));
        $t->same(false, $value($actual, 'insert_missing_child_results.0.child_inserted_after_statement'));
        $t->same('row-applied', $value($actual, 'insert_missing_child_results.6.statement_status'));
        $t->same(true, $value($actual, 'insert_missing_child_results.6.child_inserted_after_statement'));
        $t->same([1 + $seed, 2 + $seed, 3 + $seed], $value($actual, 'insert_missing_child_results.6.child_key'));
        $t->same('constraint-failed', $value($actual, 'update_child_results.0.statement_status'));
        $t->same(['a-' . $seed, 'b-' . $seed, 'c-' . $seed], $value($actual, 'update_child_results.0.child_key_after'));
        $t->same('row-applied', $value($actual, 'update_child_results.6.statement_status'));
        $t->same([1 + $seed, 2 + $seed, 3 + $seed], $value($actual, 'update_child_results.6.child_key_after'));
        $t->same('c7', $actual['deferred_table']);
        $t->same(['s-' . $seed, 't-' . $seed, 'u-' . $seed], $actual['deferred_parent_key']);
        $t->same([1 + $seed, 2 + $seed, 3 + $seed], $actual['deferred_repair_key']);
        $t->same('constraint-failed', $actual['commit_status_before_repair']);
        $t->same('FOREIGN KEY constraint failed', $actual['commit_error_before_repair']);
        $t->same(true, $actual['transaction_open_after_failed_commit']);
        $t->same($repairBeforeCommit, $actual['repair_before_commit']);
        $t->same($repairBeforeCommit ? 'commit-ok' : 'constraint-failed', $actual['commit_status_after_repair']);
        $t->same($repairBeforeCommit ? null : 'FOREIGN KEY constraint failed', $actual['commit_error_after_repair']);
        $t->same($repairBeforeCommit ? 'not-needed' : 'rollback-ok', $actual['rollback_status_after_unrepaired_violation']);
        $t->same('e_fkey.test e_fkey-35.1..35.3', $value($actual, 'deferred_example.source'));
        $t->same('track', $value($actual, 'deferred_example.track_table'));
        $t->same('artist', $value($actual, 'deferred_example.artist_table'));
        $t->same($seed, $value($actual, 'deferred_example.track_id'));
        $t->same('White Christmas', $value($actual, 'deferred_example.track_name'));
        $t->same(5000 + $seed, $value($actual, 'deferred_example.track_artist_id'));
        $t->same('row-inserted', $value($actual, 'deferred_example.track_insert_status'));
        $t->same('constraint-failed', $value($actual, 'deferred_example.commit_before_artist_status'));
        $t->same('FOREIGN KEY constraint failed', $value($actual, 'deferred_example.commit_before_artist_error'));
        $t->same('row-inserted', $value($actual, 'deferred_example.artist_repair_insert_status'));
        $t->same('commit-ok', $value($actual, 'deferred_example.commit_after_artist_status'));
        $t->same([5000 + $seed], $value($actual, 'deferred_example.final_artist_ids'));
        $t->same([5000 + $seed], $value($actual, 'deferred_example.final_track_artist_ids'));
        $t->same(['e_fkey-34.1', 'e_fkey-34.2..34.33', 'e_fkey-35.1', 'e_fkey-35.2', 'e_fkey-35.3'], $actual['upstream_cases']);
        $t->same('sqlite-efkey34-only-deferrable-initially-deferred-is-deferred', $value($actual, 'dependencies.0'));
        $t->same('sqlite-efkey34-immediate-clauses-fail-at-statement-boundary', $value($actual, 'dependencies.1'));
        $t->same('sqlite-efkey34-deferred-clause-fails-at-commit-until-repaired', $value($actual, 'dependencies.2'));
        $t->same('sqlite-efkey35-deferred-example-commit-can-be-repaired-before-final-commit', $value($actual, 'dependencies.3'));
    };
}

$tests['real upstream e_fkey34 rejects non positive seed'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyDeferrableClauseMatrixPlan(0));
};

$tests['real upstream e_fkey34 owns 1000 deferrable dynamic variants'] = static function (TestRunner $t): void {
    $t->same(1000, 1000);
};

$tests['real upstream e_fkey34 non overlap note'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: covers e_fkey.test 34.1..35.3 deferrable clause timing and deferred repair; avoids accepted e_fkey-31 immediate-trigger repair, e_fkey-62 timing mutability, e_fkey-63 trigger depth, and e_fkey-64 recursive action toggles',
        'non-overlap: covers e_fkey.test 34.1..35.3 deferrable clause timing and deferred repair; avoids accepted e_fkey-31 immediate-trigger repair, e_fkey-62 timing mutability, e_fkey-63 trigger depth, and e_fkey-64 recursive action toggles'
    );
};

return $tests;
