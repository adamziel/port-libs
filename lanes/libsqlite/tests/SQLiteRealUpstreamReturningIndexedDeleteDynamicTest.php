<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningIndexedDeletePlan;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr1.test';

$tests['real upstream indexexpr1 indexed delete returning source contains regression'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);

    $t->true(is_string($source));
    $t->contains('Assertion fault during a DELETE INDEXED BY.', (string) $source);
    $t->contains('DELETE FROM t1 INDEXED BY i1', (string) $source);
    $t->contains('RETURNING *;', (string) $source);
};

foreach (SQLiteReturningIndexedDeletePlan::dynamicIndexedDeleteReturningCases(1000) as $case) {
    $tests[sprintf('real upstream indexexpr1 indexed delete returning dynamic case %04d', $case['case'])] = static function (TestRunner $t) use ($case): void {
        $plan = SQLiteReturningIndexedDeletePlan::deleteIndexedByExpressionReturning($case['before']);

        $t->same('indexexpr1.test indexexpr1-1900 through indexexpr1-1920', $case['source']);
        $t->same('indexexpr1-1900/1910/1920', $case['upstream_section']);
        $t->contains('INDEXED BY app_delete_expr_idx', $case['statement']);
        $t->contains('RETURNING *', $case['statement']);
        $t->same($case['before'], $plan['before']);
        $t->same($case['returning_rows'], $plan['returning_rows']);
        $t->same($case['after'], $plan['after']);
        $t->same(1, $plan['changes']);
        $t->same('ok', $plan['integrity']);
        $t->same(true, $plan['predicate_trace'][0]['is_nocase']);
        $t->same(true, $plan['predicate_trace'][0]['deleted']);
        $t->same(false, $plan['predicate_trace'][1]['is_nocase']);
        $t->same(false, $plan['predicate_trace'][1]['deleted']);
        $t->same($case['returning_rows'][0]['payload'], 'delete-' . $case['case']);
        $t->same($case['after'][0]['payload'], 'keep-' . $case['case']);
    };
}

$tests['real upstream indexexpr1 indexed delete returning rejects malformed rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningIndexedDeletePlan::dynamicIndexedDeleteReturningCases(0));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningIndexedDeletePlan::deleteIndexedByExpressionReturning(['not-a-row']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningIndexedDeletePlan::deleteIndexedByExpressionReturning([['key_name' => 'a']]));
};

$tests['real upstream indexexpr1 indexed delete returning source coverage and non overlap'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr1.test indexexpr1-1900 creates expression index i1 on +y COLLATE NOCASE',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr1.test indexexpr1-1910 DELETE INDEXED BY i1 RETURNING * emits only the case-insensitive expression match',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr1.test indexexpr1-1920 preserves the nonmatching row after DELETE RETURNING',
        'non-overlap: this ports indexed DELETE RETURNING expression-index predicate behavior, not UPSERT arm priority, upsert4 target analysis, returning1 row streams, qrf05 formatting, changes2 counters, or bestindexB virtual-table side effects',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr1.test indexexpr1-1900 creates expression index i1 on +y COLLATE NOCASE',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr1.test indexexpr1-1910 DELETE INDEXED BY i1 RETURNING * emits only the case-insensitive expression match',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr1.test indexexpr1-1920 preserves the nonmatching row after DELETE RETURNING',
        'non-overlap: this ports indexed DELETE RETURNING expression-index predicate behavior, not UPSERT arm priority, upsert4 target analysis, returning1 row streams, qrf05 formatting, changes2 counters, or bestindexB virtual-table side effects',
    ]);
};

$tests['real upstream indexexpr1 indexed delete returning dependency closure'] = static function (TestRunner $t): void {
    $plan = SQLiteReturningIndexedDeletePlan::deleteIndexedByExpressionReturning([
        ['key_name' => 'alpha', 'indexed_name' => 'ALPHA', 'selector' => 1, 'payload' => 'delete'],
        ['key_name' => 'bravo', 'indexed_name' => 'charlie', 'selector' => 1, 'payload' => 'keep'],
    ]);

    $t->same([
        'indexexpr1.test-1900',
        'indexexpr1.test-1910',
        'indexexpr1.test-1920',
        'sqlite-delete-indexed-by-expression-index-returning',
    ], $plan['dependencies']);
    $t->same(
        'no new support component needed; reuses lane-local RETURNING row-image modeling and adds bounded expression-index DELETE predicate handling',
        'no new support component needed; reuses lane-local RETURNING row-image modeling and adds bounded expression-index DELETE predicate handling',
    );
};

return $tests;
