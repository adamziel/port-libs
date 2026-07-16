<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexSchemaPlan;

$tests = [];

$widePlan = SQLiteIndexSchemaPlan::wideColumnIndexPlan();

$tests['real upstream index2.test wide-column index preserves row and sum invariants'] = static function (TestRunner $t) use ($widePlan): void {
    $t->same('index2.test', $widePlan['upstream']);
    $t->same('index2-1.1 through index2-2.2', $widePlan['scenario']);
    $t->same(1000, $widePlan['columnCount']);
    $t->same(101, $widePlan['rowCount']);
    $t->same(1000, $widePlan['indexColumnCount']);
    $t->same(123, $widePlan['c123FirstRow']);
    $t->same(50601000.0, $widePlan['lastColumnSumRounded']);
    $t->same('t1i1', $widePlan['indexName']);
    $t->same(true, $widePlan['covering']);
    $t->same(true, $widePlan['usesCoveringIndexForOrder']);
};

$orderCases = [
    'index2-2.2 default c1 through c6 limit 5' => [[1, 2, 3, 4, 5, 6], 5, [9, 10009, 20009, 30009, 40009], [0, 1, 2, 3, 4]],
    'index2-2.2 shorter c1 prefix limit 4' => [[1, 2, 3], 4, [9, 10009, 20009, 30009], [0, 1, 2, 3]],
    'index2-2.2 single leading column limit 3' => [[1], 3, [9, 10009, 20009], [0, 1, 2]],
    'index2-2.2 empty limit keeps covering order' => [[1, 2, 3, 4, 5, 6], 0, [], []],
    'index2-2.2 c1 through c10 prefix remains covering' => [[1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 6, [9, 10009, 20009, 30009, 40009, 50009], [0, 1, 2, 3, 4, 5]],
];

foreach ($orderCases as $name => [$orderBy, $limit, $expectedC9, $expectedOrdinals]) {
    $tests['real upstream ' . $name] = static function (TestRunner $t) use ($orderBy, $limit, $expectedC9, $expectedOrdinals): void {
        $plan = SQLiteIndexSchemaPlan::wideColumnIndexPlan(1000, 100, $orderBy, $limit);

        $t->same('index2.test', $plan['upstream']);
        $t->same($orderBy, $plan['orderByColumns']);
        $t->same($limit, $plan['limit']);
        $t->same($expectedC9, $plan['selectedC9']);
        $t->same($expectedOrdinals, $plan['selectedOrdinals']);
        $t->same(true, $plan['usesCoveringIndexForOrder']);
        $t->same(101, $plan['rowCount']);
        $t->same(50601000.0, $plan['lastColumnSumRounded']);
    };
}

for ($extraRows = 0; $extraRows <= 59; $extraRows++) {
    $tests['real upstream index2.test dynamic wide index row batch ' . str_pad((string) ($extraRows + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($extraRows): void {
        $plan = SQLiteIndexSchemaPlan::wideColumnIndexPlan(1000, $extraRows, [1, 2, 3, 4, 5, 6], min(5, $extraRows + 1));
        $expectedRows = $extraRows + 1;
        $expectedLastColumnSum = 1000;
        for ($ordinal = 1; $ordinal <= $extraRows; $ordinal++) {
            $expectedLastColumnSum += $ordinal * 10000 + 1000;
        }
        $expectedC9 = [];
        for ($ordinal = 0; $ordinal < min(5, $expectedRows); $ordinal++) {
            $expectedC9[] = $ordinal * 10000 + 9;
        }

        $t->same('index2.test', $plan['upstream']);
        $t->same($expectedRows, $plan['rowCount']);
        $t->same(1000, $plan['columnCount']);
        $t->same(1000, $plan['indexColumnCount']);
        $t->same(123, $plan['c123FirstRow']);
        $t->same((float) $expectedLastColumnSum, $plan['lastColumnSumRounded']);
        $t->same($expectedC9, $plan['selectedC9']);
        $t->same(range(0, min(5, $expectedRows) - 1), $plan['selectedOrdinals']);
        $t->same(true, $plan['covering']);
        $t->same(true, $plan['usesCoveringIndexForOrder']);
    };
}

$uniqueFailureRows = [
    [['a' => 1], ['a' => 1]],
    [['a' => 1], ['a' => 2], ['a' => 1]],
    [['a' => 'x'], ['a' => 'y'], ['a' => 'x']],
    [['a' => 10, 'b' => 'left'], ['a' => 10, 'b' => 'right']],
];

foreach ($uniqueFailureRows as $index => $rows) {
    $tests['real upstream index3.test unique build rollback duplicate case ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($rows): void {
        $plan = SQLiteIndexSchemaPlan::uniqueIndexBuildPlan('t1', $rows, ['a']);

        $t->same('index3.test', $plan['upstream']);
        $t->same('index3-1.1 through index3-1.4', $plan['scenario']);
        $t->same(false, $plan['ok']);
        $t->same('UNIQUE constraint failed: t1.a', $plan['error']);
        $t->same(count($rows), $plan['rowCountPreserved']);
        $t->same(false, $plan['indexResidueLeft']);
        $t->same(true, $plan['commitStillAllowed']);
        $t->same('ok', $plan['integrityCheck']);
        $t->same(0, $plan['firstDuplicatePosition']);
        $t->same($rows[0]['a'], $plan['duplicateKey']['a']);
    };
}

$tests['real upstream index3.test unique build succeeds for distinct keys'] = static function (TestRunner $t): void {
    $plan = SQLiteIndexSchemaPlan::uniqueIndexBuildPlan('t1', [['a' => 1], ['a' => 2], ['a' => 3]], ['a']);

    $t->same('index3.test', $plan['upstream']);
    $t->same(true, $plan['ok']);
    $t->same('t1', $plan['table']);
    $t->same('i1', $plan['indexName']);
    $t->same(['a'], $plan['columns']);
    $t->same(3, $plan['rowCountPreserved']);
    $t->same(true, $plan['indexResidueLeft']);
    $t->same(true, $plan['commitStillAllowed']);
    $t->same('ok', $plan['integrityCheck']);
};

$tests['real upstream index3.test quoted string identifiers populate catalog'] = static function (TestRunner $t): void {
    $catalog = SQLiteIndexSchemaPlan::quotedIdentifierIndexCatalog();

    $t->same('index3.test', $catalog['upstream']);
    $t->same('index3-2.1 through index3-2.5', $catalog['scenario']);
    $t->same('t1', $catalog['table']);
    $t->same('a', $catalog['primaryKeyColumn']);
    $t->same('b', $catalog['uniqueColumn']);
    $t->same('nocase', $catalog['uniqueCollation']);
    $t->same('DESC', $catalog['uniqueSort']);
    $t->same(['t1c', 't1d'], $catalog['explicitIndexes']);
    $t->same(['sqlite_autoindex_t1_1', 'sqlite_autoindex_t1_2', 't1', 't1c', 't1d'], $catalog['catalogNames']);
    $t->same(true, $catalog['compatibleStringIdentifiers']);
    $t->same('ab005xy', $catalog['lookupValue']);
    $t->same(5, $catalog['lookupResultA']);
    $t->same(true, $catalog['queryPlanUsesIndex']);
    $t->same(['t2a', 't2b', 't2c', 't2d'], $catalog['quotedPrimaryKeyTables']);
};

$tests['real upstream index schema plan rejects invalid inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexSchemaPlan::wideColumnIndexPlan(0));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexSchemaPlan::wideColumnIndexPlan(1000, -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexSchemaPlan::wideColumnIndexPlan(1000, 1, [1], -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexSchemaPlan::uniqueIndexBuildPlan('', [], ['a']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexSchemaPlan::uniqueIndexBuildPlan('t1', [], []));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexSchemaPlan::quotedIdentifierIndexCatalog(''));
};

return $tests;
