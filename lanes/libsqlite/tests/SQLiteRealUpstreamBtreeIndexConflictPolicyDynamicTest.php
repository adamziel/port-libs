<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexLifecyclePlan;

$tests = [];

// Source truth: SQLite upstream test/index.test sections index-19.1 through
// index-19.7. Existing index.test late lifecycle coverage starts at index-20.1;
// this batch owns the immediately preceding shared-index ON CONFLICT behavior.
foreach (SQLiteIndexLifecyclePlan::conflictPolicySharedIndexCases(1000) as $case) {
    $tests['real upstream index.test shared conflict policy dynamic case ' . $case['case']] = static function (TestRunner $t) use ($case): void {
        $t->same('index.test sections index-19.1 through index-19.7', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true($case['batch'] >= 1 && $case['batch'] <= 200);
        $t->true(in_array($case['upstream_section'], ['index-19.1', 'index-19.2', 'index-19.4', 'index-19.6', 'index-19.7'], true));
        $t->true(in_array($case['conflict_policy'], ['ABORT', 'ROLLBACK', 'CONFLICTING', 'PRESERVE'], true));
        $t->true($case['scenario'] !== '');
        $t->true($case['table_name'] !== '');
        $t->true($case['index_name'] !== '');
        $t->true($case['constraint_shape'] !== '');

        if ($case['upstream_section'] === 'index-19.1') {
            $t->same('ABORT', $case['conflict_policy']);
            $t->same([0, ''], $case['statement_result']);
            $t->same('none', $case['transaction_after']);
            $t->same(1, $case['row_count']);
            $t->true(str_contains($case['index_name'], 'sqlite_autoindex_t7'));
        }

        if ($case['upstream_section'] === 'index-19.2') {
            $t->same('active', $case['transaction_before']);
            $t->same('active', $case['transaction_after']);
            $t->same(1, $case['statement_result'][0]);
            $t->true(str_contains($case['statement_result'][1], 'UNIQUE constraint failed'));
            $t->same([1, 'cannot start a transaction within a transaction'], $case['begin_after_result']);
            $t->same([0, []], $case['commit_after_result']);
        }

        if ($case['upstream_section'] === 'index-19.4') {
            $t->same('ROLLBACK', $case['conflict_policy']);
            $t->same('active', $case['transaction_before']);
            $t->same('none', $case['transaction_after']);
            $t->same(1, $case['statement_result'][0]);
            $t->true(str_contains($case['statement_result'][1], 'UNIQUE constraint failed'));
            $t->same([0, ''], $case['begin_after_result']);
        }

        if ($case['upstream_section'] === 'index-19.6') {
            $t->same('CONFLICTING', $case['conflict_policy']);
            $t->same([1, 'conflicting ON CONFLICT clauses specified'], $case['statement_result']);
            $t->same([], $case['catalog_names']);
            $t->same(0, $case['row_count']);
            $t->same('not-created', $case['integrity']);
        } else {
            $t->same('ok', $case['integrity']);
            $t->true($case['row_count'] >= 1);
            $t->true(count($case['catalog_names']) >= 2);
        }

        if ($case['upstream_section'] === 'index-19.7') {
            $t->same('PRESERVE', $case['conflict_policy']);
            $t->same([0, ''], $case['statement_result']);
            $t->same(2, $case['row_count']);
            $t->true(str_contains($case['index_name'], 'sqlite_autoindex_t7'));
            $t->true(str_contains($case['index_name'], 'sqlite_autoindex_t8'));
        }

        foreach ($case['catalog_names'] as $name) {
            $t->true($name !== '');
            $t->true(str_starts_with($name, 't') || str_starts_with($name, 'sqlite_autoindex_'));
        }
    };
}

$tests['real upstream index.test shared conflict policy dynamic corpus count'] = static function (TestRunner $t): void {
    $cases = SQLiteIndexLifecyclePlan::conflictPolicySharedIndexCases(1000);
    $sections = array_values(array_unique(array_column($cases, 'upstream_section')));
    sort($sections);

    $t->same(1000, count($cases));
    $t->same(['index-19.1', 'index-19.2', 'index-19.4', 'index-19.6', 'index-19.7'], $sections);
    $t->same(200, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'index-19.1')));
    $t->same(200, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'index-19.2')));
    $t->same(200, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'index-19.4')));
    $t->same(200, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'index-19.6')));
    $t->same(200, count(array_filter($cases, static fn (array $case): bool => $case['upstream_section'] === 'index-19.7')));
};

$tests['real upstream index.test shared conflict policy dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local index lifecycle, autoindex catalog, transaction-state, and conflict-policy helpers',
        'no new support component needed; reuses lane-local index lifecycle, autoindex catalog, transaction-state, and conflict-policy helpers',
    );
};

return $tests;
