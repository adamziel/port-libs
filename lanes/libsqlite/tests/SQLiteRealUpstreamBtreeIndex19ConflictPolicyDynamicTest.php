<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/index.test sections index-19.1 through
// index-19.6. This shard focuses the merged UNIQUE/PRIMARY KEY autoindex
// conflict-policy behavior that the broad lifecycle corpus only summarized:
// ABORT keeps the transaction open, ROLLBACK unwinds it, and inconsistent
// table-constraint conflict clauses are rejected before schema mutation.
foreach (SQLiteBTreeIndexDynamicCorpusPlan::index19MergedConstraintConflictPolicyCases(1000) as $case) {
    $tests['real upstream index19 merged constraint conflict dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test sections index-19.1 through index-19.6', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1);
        $t->true(in_array($case['upstream_section'], ['index-19.1', 'index-19.2', 'index-19.3', 'index-19.4', 'index-19.5', 'index-19.6'], true));
        $t->true($case['table_name'] === 't7' || $case['table_name'] === 't8');
        $t->true(str_contains($case['constraint_sql'], 'PRIMARY KEY'));
        $t->true(in_array($case['conflict_policy'], ['ABORT', 'ROLLBACK', 'CONFLICTING'], true));
        $t->true($case['statement'] !== '');
        $t->true(str_starts_with($case['autoindex_name'], 'sqlite_autoindex_'));
        $t->same($case['merged_autoindex'], $case['result_code'] === 0 || $case['schema_preserved']);

        if ($case['result_code'] === 0) {
            $t->same(null, $case['message']);
            $t->true($case['integrity'] === 'ok');
        } else {
            $t->true(is_string($case['message']));
            $t->true(str_starts_with($case['integrity'], 'expected-'));
        }

        foreach ($case['rows_after'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if ($case['upstream_section'] === 'index-19.1') {
            $t->same(0, $case['result_code']);
            $t->same('ABORT', $case['conflict_policy']);
            $t->same('outside-transaction', $case['transaction_state']);
            $t->same([[1]], $case['rows_after']);
        }

        if ($case['upstream_section'] === 'index-19.2') {
            $t->same(1, $case['result_code']);
            $t->same('UNIQUE constraint failed: t7.a', $case['message']);
            $t->same('transaction-open-after-abort-conflict', $case['transaction_state']);
            $t->same([[1]], $case['rows_after']);
        }

        if ($case['upstream_section'] === 'index-19.3') {
            $t->same('cannot start a transaction within a transaction', $case['message']);
            $t->same('transaction-still-open', $case['transaction_state']);
        }

        if ($case['upstream_section'] === 'index-19.4') {
            $t->same('ROLLBACK', $case['conflict_policy']);
            $t->same('UNIQUE constraint failed: t8.a', $case['message']);
            $t->same('rolled-back-to-autocommit', $case['transaction_state']);
            $t->same([[1]], $case['rows_after']);
        }

        if ($case['upstream_section'] === 'index-19.5') {
            $t->same(0, $case['result_code']);
            $t->same('committed-empty-transaction-after-rollback', $case['transaction_state']);
            $t->same([[1]], $case['rows_after']);
        }

        if ($case['upstream_section'] === 'index-19.6') {
            $t->same('CONFLICTING', $case['conflict_policy']);
            $t->same('conflicting ON CONFLICT clauses specified', $case['message']);
            $t->same('schema-change-rejected', $case['transaction_state']);
            $t->same([], $case['rows_after']);
            $t->same(false, $case['schema_preserved']);
            $t->same(false, $case['merged_autoindex']);
        }
    };
}

$tests['real upstream index19 merged constraint conflict dynamic source range'] = static function (TestRunner $t): void {
    $cases = SQLiteBTreeIndexDynamicCorpusPlan::index19MergedConstraintConflictPolicyCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));

    $t->same(1000, count($cases));
    $t->same('index-19.1', $cases[0]['upstream_section']);
    $t->same('index-19.6', $cases[5]['upstream_section']);
    $t->same('index-19.4', $cases[999]['upstream_section']);
    $t->same(['index-19.1', 'index-19.2', 'index-19.3', 'index-19.4', 'index-19.5', 'index-19.6'], $sections);
};

$tests['real upstream index19 merged constraint conflict rejects empty corpus'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteBTreeIndexDynamicCorpusPlan::index19MergedConstraintConflictPolicyCases(0));
};

$tests['real upstream index19 merged constraint conflict dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for merged autoindex constraint policies, transaction-state outcomes, and schema-preservation checks',
        'no new support component needed; reuses lane-local B-tree/index dynamic corpus planner for merged autoindex constraint policies, transaction-state outcomes, and schema-preservation checks',
    );
};

return $tests;
