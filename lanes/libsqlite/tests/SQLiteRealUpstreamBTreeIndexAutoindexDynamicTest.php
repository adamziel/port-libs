<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeAutoindexDynamicCorpusPlan;

$tests = [];

$rowCounts = range(1, 160);

foreach ($rowCounts as $rowCount) {
    $left = SQLiteBTreeAutoindexDynamicCorpusPlan::t1Rows($rowCount);
    $right = SQLiteBTreeAutoindexDynamicCorpusPlan::t2Rows($left);
    $scan = SQLiteBTreeAutoindexDynamicCorpusPlan::join($left, $right, false);
    $indexed = SQLiteBTreeAutoindexDynamicCorpusPlan::join($left, $right, true);
    $scalarScan = SQLiteBTreeAutoindexDynamicCorpusPlan::scalarSubquery($left, $right, false, false);
    $scalarIndexed = SQLiteBTreeAutoindexDynamicCorpusPlan::scalarSubquery($left, $right, true, true);
    $mutating = SQLiteBTreeAutoindexDynamicCorpusPlan::joinWithMutatingRightTable($left, $right);
    $chain = SQLiteBTreeAutoindexDynamicCorpusPlan::chainJoinCount($rowCount + 9, 10);

    $tests["upstream autoindex1 dynamic join rowset {$rowCount}"] = static function (TestRunner $t) use ($scan, $indexed): void {
        $t->same($scan['rows'], $indexed['rows']);
    };
    $tests["upstream autoindex1 dynamic join autoindex inserts {$rowCount}"] = static function (TestRunner $t) use ($indexed, $rowCount): void {
        $t->same($rowCount, $indexed['autoindex_inserts']);
    };
    $tests["upstream autoindex1 dynamic join scan step count {$rowCount}"] = static function (TestRunner $t) use ($scan, $rowCount): void {
        $t->same($rowCount * $rowCount, $scan['step_count']);
    };
    $tests["upstream autoindex1 dynamic join indexed step count {$rowCount}"] = static function (TestRunner $t) use ($indexed, $rowCount): void {
        $t->same($rowCount, $indexed['step_count']);
    };
    $tests["upstream autoindex1 dynamic warning {$rowCount}"] = static function (TestRunner $t) use ($indexed): void {
        $t->contains('automatic index on t2(c)', (string) $indexed['warning']);
    };
    $tests["upstream autoindex1 dynamic scalar rowset {$rowCount}"] = static function (TestRunner $t) use ($scalarScan, $scalarIndexed): void {
        $t->same($scalarScan['rows'], $scalarIndexed['rows']);
    };
    $tests["upstream autoindex1 dynamic scalar correlated inserts {$rowCount}"] = static function (TestRunner $t) use ($scalarIndexed, $rowCount): void {
        $t->same($rowCount, $scalarIndexed['autoindex_inserts']);
    };
    $tests["upstream autoindex1 dynamic mutating rhs snapshot {$rowCount}"] = static function (TestRunner $t) use ($indexed, $mutating): void {
        $t->same($indexed['rows'], $mutating['rows']);
    };
    $tests["upstream autoindex1 dynamic mutating rhs after {$rowCount}"] = static function (TestRunner $t) use ($mutating, $rowCount): void {
        $t->same(900 + 11 + $rowCount, $mutating['right_after'][0]['d']);
    };
    $tests["upstream autoindex1 dynamic chain count {$rowCount}"] = static function (TestRunner $t) use ($chain, $rowCount): void {
        $t->same($rowCount, $chain['path_count']);
    };
}

$planCases = [
    'noncorrelated in subquery scans rhs' => [false, false, 'LIST SUBQUERY', 'SCAN t502', false, false, 'autoindex1-500.1'],
    'correlated in subquery builds automatic covering index' => [true, false, 'CORRELATED LIST SUBQUERY', 'SEARCH t502 USING AUTOMATIC COVERING INDEX (y=?)', true, true, 'autoindex1-501'],
    'outer point lookup suppresses correlated automatic index' => [true, true, 'CORRELATED LIST SUBQUERY', 'SCAN t502', false, false, 'autoindex1-502'],
];

foreach ($planCases as $name => [$correlated, $outerPoint, $kind, $access, $autoindex, $bloom, $source]) {
    $tests["upstream autoindex1 {$name}"] = static function (TestRunner $t) use ($correlated, $outerPoint, $kind, $access, $autoindex, $bloom, $source): void {
        $plan = SQLiteBTreeAutoindexDynamicCorpusPlan::inSubqueryPlan($correlated, $outerPoint);
        $t->same($source, $plan['source']);
        $t->same($kind, $plan['subquery_kind']);
        $t->same($access, $plan['subquery_access']);
        $t->same($autoindex, $plan['autoindex']);
        $t->same($bloom, $plan['bloom_filter']);
    };
}

$tests['upstream autoindex1 rejects nonpositive row count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeAutoindexDynamicCorpusPlan::t1Rows(0));
};

$tests['upstream autoindex1 rejects chain depth below two'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeAutoindexDynamicCorpusPlan::chainJoinCount(8, 1));
};

return $tests;
