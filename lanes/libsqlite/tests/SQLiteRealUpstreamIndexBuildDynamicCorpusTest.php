<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexBuildPlan;
use PortLibs\LibSqlite\SQLiteIndexBuildUniqueConstraintException;

$tests = [];

$tests['real upstream index build dynamic index2 creates thousand-column key records'] = static function (TestRunner $t): void {
    $columns = array_map(static fn (int $i): string => "c{$i}", range(1, 1000));
    $rows = SQLiteIndexBuildPlan::deterministicRows($columns, 101);
    $plan = SQLiteIndexBuildPlan::build('t1', 't1i1', $columns, $rows, $columns);

    $t->same('index2.test index2-2.1/index2-2.2; index4.test index4-1.2/index4-1.3', $plan['upstream']);
    $t->same(1000, $plan['column_count']);
    $t->same(101, $plan['row_count']);
    $t->same(true, $plan['created']);
    $t->same('ok', $plan['integrity']);
    $t->same(false, $plan['schema_residue']);
    $t->same(range(1, 101), $plan['ordered_rowids']);

    foreach (range(1, 1000) as $i) {
        $column = "c{$i}";
        $t->same($column, $plan['index_columns'][$i - 1], "index2 column {$i} is preserved");
        $t->same($i, $plan['key_records'][0]['key'][$i - 1], "index2 first row key {$i}");
        $t->same(1000000 + $i, $plan['key_records'][100]['key'][$i - 1], "index2 last row key {$i}");
    }
};

$tests['real upstream index build dynamic index2 order by prefix returns first five c9 values'] = static function (TestRunner $t): void {
    $columns = array_map(static fn (int $i): string => "c{$i}", range(1, 1000));
    $rows = SQLiteIndexBuildPlan::deterministicRows($columns, 101);
    $plan = SQLiteIndexBuildPlan::build('t1', 't1i1', $columns, $rows, array_slice($columns, 0, 1000));
    $firstFive = array_slice($plan['ordered_rowids'], 0, 5);
    $c9Values = array_map(static fn (int $rowid): int => $rows[$rowid - 1]['c9'], $firstFive);

    $t->same([9, 10009, 20009, 30009, 40009], $c9Values);
    foreach (range(1, 5) as $position) {
        $rowid = $firstFive[$position - 1];
        $t->same($position, $rowid, "index2 ordered rowid {$position}");
        $t->same(($position - 1) * 10000 + 1, $rows[$rowid - 1]['c1'], "index2 c1 prefix {$position}");
        $t->same(($position - 1) * 10000 + 6, $rows[$rowid - 1]['c6'], "index2 c6 prefix {$position}");
        $t->same(($position - 1) * 10000 + 9, $rows[$rowid - 1]['c9'], "index2 c9 output {$position}");
    }
};

$tests['real upstream index build dynamic index3 unique failure leaves no residue'] = static function (TestRunner $t): void {
    try {
        SQLiteIndexBuildPlan::build('t1', 'i1', ['a'], [['a' => 1], ['a' => 1]], ['a'], true);
        $t->fail('Expected UNIQUE index creation to fail');
    } catch (SQLiteIndexBuildUniqueConstraintException $exception) {
        $plan = $exception->plan();
        $t->same('UNIQUE constraint failed: t1.a', $exception->getMessage());
        $t->same('index3.test index3-1.2', $plan['upstream']);
        $t->same(false, $plan['created']);
        $t->same(false, $plan['schema_residue']);
        $t->same([1], $plan['duplicate_key']);
        $t->same([1, 2], $plan['duplicate_rowids']);
    }
};

$duplicateCases = [];
foreach (range(1, 600) as $i) {
    $duplicateCases["index3 duplicate unique key {$i}"] = [
        'table' => 't1',
        'index' => "i{$i}",
        'columns' => ['a', 'b'],
        'rows' => [
            ['a' => $i, 'b' => "v{$i}"],
            ['a' => $i + 1000, 'b' => "v{$i}"],
            ['a' => $i, 'b' => "other{$i}"],
        ],
        'indexColumns' => ['b'],
        'duplicateKey' => ["v{$i}"],
        'duplicateRowids' => [1, 2],
    ];
}

foreach ($duplicateCases as $name => $case) {
    $tests["real upstream index build dynamic {$name}"] = static function (TestRunner $t) use ($case): void {
        try {
            SQLiteIndexBuildPlan::build($case['table'], $case['index'], $case['columns'], $case['rows'], $case['indexColumns'], true);
            $t->fail('Expected UNIQUE index creation to fail');
        } catch (SQLiteIndexBuildUniqueConstraintException $exception) {
            $plan = $exception->plan();
            $t->same(false, $plan['created']);
            $t->same(false, $plan['schema_residue']);
            $t->same($case['duplicateKey'], $plan['duplicate_key']);
            $t->same($case['duplicateRowids'], $plan['duplicate_rowids']);
        }
    };
}

$sortCases = [];
foreach (range(1, 420) as $i) {
    $sortCases["index4 sorted duplicate-friendly key order {$i}"] = [
        ['x' => 35 + $i, 'payload' => 'b'],
        ['x' => 14 + $i, 'payload' => 'a'],
        ['x' => 35 + $i, 'payload' => 'c'],
        ['x' => null, 'payload' => 'n'],
        ['x' => 16 + $i, 'payload' => 'd'],
    ];
}

foreach ($sortCases as $name => $rows) {
    $tests["real upstream index build dynamic {$name}"] = static function (TestRunner $t) use ($rows): void {
        $plan = SQLiteIndexBuildPlan::build('t2', 'i3', ['x', 'payload'], $rows, ['x']);

        $t->same(true, $plan['created']);
        $t->same('ok', $plan['integrity']);
        $t->same([4, 2, 5, 1, 3], $plan['ordered_rowids']);
        $t->same([null], $plan['key_records'][0]['key']);
        $t->same([$rows[1]['x']], $plan['key_records'][1]['key']);
        $t->same([$rows[4]['x']], $plan['key_records'][2]['key']);
        $t->same([$rows[0]['x']], $plan['key_records'][3]['key']);
        $t->same([$rows[2]['x']], $plan['key_records'][4]['key']);
    };
}

$tests['real upstream index build dynamic rejects missing indexed column'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteIndexBuildPlan::build('test1', 'index1', ['f1', 'f2', 'f3'], [['f1' => 1]], ['f1', 'f4']),
    );
};

$tests['real upstream index build dynamic rejects empty index columns'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteIndexBuildPlan::build('test1', 'index1', ['f1'], [['f1' => 1]], []),
    );
};

return $tests;
