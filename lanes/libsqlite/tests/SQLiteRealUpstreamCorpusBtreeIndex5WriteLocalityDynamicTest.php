<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexBuildWriteOrderPlan;

$tests = [];

$tests['real upstream corpus index5.test records create-index write locality source'] = static function (TestRunner $t): void {
    $plan = SQLiteIndexBuildWriteOrderPlan::createIndexWriteOrder();

    $t->same('index5.test', $plan['upstream']);
    $t->same('index5-1.1 through index5-1.3 create-index write locality', $plan['scenario']);
    $t->same(100000, $plan['rowCount']);
    $t->same(1024, $plan['pageSize']);
    $t->same(1024, $plan['pageSizeAfterDrop']);
    $t->same('ok', $plan['integrityCheck']);
    $t->same(true, $plan['forwardDominant']);
    $t->contains('index5.test', $plan['nonOverlap']);
    $t->contains('no new support component needed', $plan['dependencyClosure']);
};

foreach (range(1, 1000) as $case) {
    $tests['real upstream corpus index5.test dynamic create-index write locality ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $rowCount = 1000 + ($case * 17);
        $pageSize = ($case % 4) === 0 ? 2048 : 1024;
        $fanout = 6 + ($case % 7);
        $plan = SQLiteIndexBuildWriteOrderPlan::createIndexWriteOrder($rowCount, $pageSize, $fanout, $case);

        $t->same('index5.test', $plan['upstream']);
        $t->same('index5-1.1 through index5-1.3 create-index write locality', $plan['scenario']);
        $t->same($rowCount, $plan['rowCount']);
        $t->same($pageSize, $plan['pageSize']);
        $t->same($pageSize, $plan['pageSizeAfterDrop']);
        $t->same('i1', $plan['indexName']);
        $t->same('ok', $plan['integrityCheck']);
        $t->same(true, $plan['forwardDominant']);
        $t->same(true, $plan['forwardWrites'] > 2 * ($plan['backwardWrites'] + $plan['nonContiguousWrites']));
        $t->same(true, count($plan['writePages']) >= (int) ceil($rowCount / $fanout));
        $t->same(true, $plan['forwardWrites'] > $plan['backwardWrites']);
        $t->same(true, $plan['forwardWrites'] > $plan['nonContiguousWrites']);
        $t->contains('index5.test', $plan['nonOverlap']);
    };
}

$tests['real upstream corpus index5.test write-order planner rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexBuildWriteOrderPlan::createIndexWriteOrder(0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexBuildWriteOrderPlan::createIndexWriteOrder(1, 128));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexBuildWriteOrderPlan::createIndexWriteOrder(1, 1024, 0));
};

return $tests;
