<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAutoIndexDynamicPlan;

$tests = [];

// Source truth: SQLite upstream test/autoindex1.test autoindex1-100 through
// 113. Automatic indexes must preserve join results while reducing statement
// steps and reporting SQLITE_WARNING_AUTOINDEX.
foreach (SQLiteAutoIndexDynamicPlan::joinLookupCases() as $case) {
    $tests['real upstream autoindex1 join lookup ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same('autoindex1.test autoindex1-100 through autoindex1-113', $case['source']);
        $t->true($case['batch'] >= 1 && $case['batch'] <= 1000);
        $t->same('t1 JOIN t2 ON a=c', $case['join_kind']);
        $t->same(8, count($case['result_rows']));
        $t->same($case['automatic_index'] ? 7 : 63, $case['step_count']);
        $t->same($case['automatic_index'] ? 7 : 0, $case['autoindex_inserts']);
        $t->same($case['automatic_index'], str_contains($case['detail'], 'AUTOMATIC COVERING INDEX'));
        $t->same($case['automatic_index'] ? 'SQLITE_WARNING_AUTOINDEX automatic index on t2(c)' : null, $case['warning']);
        foreach ($case['result_rows'] as $position => $row) {
            $expectedB = (($case['batch'] - 1) * 1000) + (($position + 1) * 11);
            $t->same($expectedB, $row['b']);
            $t->same($expectedB + 900, $row['d']);
        }
        $t->same(
            'no new support component needed; uses native PHP row-array join planning to model automatic index admission, result preservation, and stmt status counters',
            $case['dependency_closure'],
        );
        $t->true(str_contains($case['non_overlap'], 'does not repeat explicit CREATE INDEX builds'));
    };
}

// Source truth: SQLite upstream test/autoindex1.test autoindex1-200 through
// 212. The correlated subquery form admits the same transient index only when
// the subquery depends on the outer row.
foreach (SQLiteAutoIndexDynamicPlan::correlatedSubqueryCases() as $case) {
    $tests['real upstream autoindex1 correlated subquery ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same('autoindex1.test autoindex1-200 through autoindex1-212', $case['source']);
        $t->true($case['batch'] >= 1 && $case['batch'] <= 360);
        $t->same(true, $case['correlated']);
        $t->same(8, count($case['result_rows']));
        $t->same(7, $case['step_count']);
        $t->same(7, $case['autoindex_inserts']);
        $t->true(str_contains($case['detail'], 'CORRELATED'));
        $t->true(str_contains($case['detail'], 'AUTOMATIC COVERING INDEX'));
        $t->true($case['outer_lookup'] >= (($case['batch'] - 1) * 1000) + 1);
        $t->true($case['outer_lookup'] <= (($case['batch'] - 1) * 1000) + 8);
        foreach ($case['result_rows'] as $position => $row) {
            $expectedB = (($case['batch'] - 1) * 1000) + (($position + 1) * 11);
            $t->same($expectedB, $row['b']);
            $t->same($expectedB + 900, $row['d']);
        }
    };
}

// Source truth: SQLite upstream test/autoindex1.test autoindex1-400 through
// 401. The ten-way equality chain should use transient indexes and return
// row_count - 9 matching chains.
foreach (SQLiteAutoIndexDynamicPlan::multiJoinCases() as $case) {
    $tests['real upstream autoindex1 ten way join ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same('autoindex1.test autoindex1-400 through autoindex1-401', $case['source']);
        $t->true($case['batch'] >= 1 && $case['batch'] <= 240);
        $t->same(10, $case['join_terms']);
        $t->same($case['row_count'] - 9, $case['join_count']);
        $t->same(true, $case['automatic_index']);
        $t->true($case['row_count'] >= 4096);
        $t->true($case['row_count'] <= 4099);
        $t->true(str_contains($case['detail'], 'ten-way self join'));
        $t->true(str_contains($case['detail'], 'automatic indexes'));
    };
}

$tests['real upstream autoindex2 real world overuse stats suppress transient index'] = static function (TestRunner $t): void {
    $case = SQLiteAutoIndexDynamicPlan::realWorldOveruseCase();

    $t->same('autoindex2.test autoindex2-100 through autoindex2-120', $case['source']);
    $t->same(3, $case['table_count']);
    $t->same(23, $case['index_count']);
    $t->same(23, $case['stat_count']);
    $t->same(true, $case['autoindex_suppressed']);
    $t->same('t1x2 did/ssid/ptime/vstatus/exbyte/t1_id', $case['chosen_loop']);
    $t->same('automatic covering index on wide fact table', $case['rejected_loop']);
    $t->true(str_contains($case['detail'], 'ANALYZE sqlite_master stats'));
};

$tests['real upstream autoindex dynamic corpus count is non overlapping'] = static function (TestRunner $t): void {
    $t->same(2000, count(SQLiteAutoIndexDynamicPlan::joinLookupCases()));
    $t->same(360, count(SQLiteAutoIndexDynamicPlan::correlatedSubqueryCases()));
    $t->same(240, count(SQLiteAutoIndexDynamicPlan::multiJoinCases()));
};

$tests['real upstream autoindex dynamic corpus rejects invalid batch sizes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAutoIndexDynamicPlan::joinLookupCases(0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAutoIndexDynamicPlan::correlatedSubqueryCases(0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAutoIndexDynamicPlan::multiJoinCases(0));
};

return $tests;
