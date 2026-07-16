<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexBuildPlan;
use PortLibs\LibSqlite\SQLiteSchemaImportExecutor;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$recordNames = static fn (array $records): array => array_map(static fn (SQLiteSchemaRecord $record): string => $record->name, $records);
$indexNamesFor = static function (SQLiteSchemaImportExecutor $executor, string $table): array {
    return array_values(array_map(
        static fn (SQLiteSchemaRecord $record): string => $record->name,
        array_filter(
            $executor->schemaRecords('main'),
            static fn (SQLiteSchemaRecord $record): bool => $record->type === 'index' && $record->tableName === $table,
        ),
    ));
};

$tests = [];

$tests['real upstream index affinity autoindex index-15 numeric affinity order'] = static function (TestRunner $t): void {
    $rows = [
        ['a' => '1.234e5', 'b' => 1],
        ['a' => '12.33e04', 'b' => 2],
        ['a' => '12.35E4', 'b' => 3],
        ['a' => '12.34e', 'b' => 4],
        ['a' => '12.32e+4', 'b' => 5],
        ['a' => '12.36E+04', 'b' => 6],
        ['a' => '12.36E+', 'b' => 7],
        ['a' => '+123.10000E+0003', 'b' => 8],
        ['a' => '+', 'b' => 9],
        ['a' => '+12347.E+02', 'b' => 10],
        ['a' => '+12347E+02', 'b' => 11],
        ['a' => '+.125E+04', 'b' => 12],
        ['a' => '-.125E+04', 'b' => 13],
        ['a' => '.125E+0', 'b' => 14],
        ['a' => '.125', 'b' => 15],
    ];
    $plan = SQLiteIndexBuildPlan::build('t1', 't1a', ['a', 'b'], $rows, ['a', 'b'], false, ['a' => 'NUMERIC']);
    $orderedB = array_map(static fn (int $rowid): int => $rows[$rowid - 1]['b'], $plan['ordered_rowids']);

    $t->same([13, 14, 15, 12, 8, 5, 2, 1, 3, 6, 10, 11, 9, 4, 7], $orderedB);
    $t->same(-1250, $plan['key_records'][0]['key'][0]);
    $t->same(0.125, $plan['key_records'][1]['key'][0]);
    $t->same('+', $plan['key_records'][12]['key'][0]);
    $t->same('12.34e', $plan['key_records'][13]['key'][0]);
    $t->same('12.36E+', $plan['key_records'][14]['key'][0]);
    $t->same('index2.test index2-2.1/index2-2.2; index4.test index4-1.2/index4-1.3', $plan['upstream']);
};

$tests['real upstream index affinity autoindex index-16 duplicate constraints coalesce'] = static function (TestRunner $t) use ($indexNamesFor): void {
    $cases = [
        'index-16.1 column unique primary key' => ['CREATE TABLE t7(c UNIQUE PRIMARY KEY)', ['sqlite_autoindex_t7_1']],
        'index-16.3 table primary plus same unique' => ['CREATE TABLE t7(c PRIMARY KEY, UNIQUE(c))', ['sqlite_autoindex_t7_1']],
        'index-16.4 composite unique plus same primary' => ['CREATE TABLE t7(c, d, UNIQUE(c, d), PRIMARY KEY(c, d))', ['sqlite_autoindex_t7_1']],
        'index-16.5 unique c plus primary c d' => ['CREATE TABLE t7(c, d, UNIQUE(c), PRIMARY KEY(c, d))', ['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2']],
    ];

    foreach ($cases as $name => [$sql, $expected]) {
        $executor = new SQLiteSchemaImportExecutor();
        $result = $executor->execute($sql);

        $t->same($expected, $result['autoindexes'], $name . ' result autoindexes');
        $t->same($expected, $indexNamesFor($executor, 't7'), $name . ' schema autoindexes');
        $t->same(count($expected), count($indexNamesFor($executor, 't7')), $name . ' index count');
    }
};

$tests['real upstream index affinity autoindex index-17 autoindex names and reserved drop model'] = static function (TestRunner $t) use ($indexNamesFor, $recordNames): void {
    $executor = new SQLiteSchemaImportExecutor();
    $executor->execute('CREATE TABLE t7(c, d UNIQUE, UNIQUE(c), PRIMARY KEY(c, d))');

    $t->same(['t7', 'sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3'], $recordNames($executor->schemaRecords('main')));
    $t->same(['sqlite_autoindex_t7_1', 'sqlite_autoindex_t7_2', 'sqlite_autoindex_t7_3'], $indexNamesFor($executor, 't7'));
    foreach ($executor->schemaRecords('main') as $record) {
        if ($record->type === 'index') {
            $t->same(null, $record->sql, $record->name . ' is internal autoindex');
        }
    }
};

foreach (range(1, 620) as $i) {
    $tests["real upstream index affinity autoindex dynamic numeric range {$i}"] = static function (TestRunner $t) use ($i): void {
        $base = $i * 1000;
        $rows = [
            ['a' => (string) ($base + 10), 'b' => 1],
            ['a' => '+' . ($base + 2) . '.0', 'b' => 2],
            ['a' => ($base + 2) . '.00', 'b' => 3],
            ['a' => 'z' . $i, 'b' => 4],
            ['a' => '-' . $i . '.5', 'b' => 5],
            ['a' => '.125E+0', 'b' => 6],
        ];
        $plan = SQLiteIndexBuildPlan::build('t_numeric', "i_numeric_{$i}", ['a', 'b'], $rows, ['a', 'b'], false, ['a' => 'NUMERIC']);

        $t->same([5, 6, 2, 3, 1, 4], array_map(static fn (int $rowid): int => $rows[$rowid - 1]['b'], $plan['ordered_rowids']));
        $t->same(0.125, $plan['key_records'][1]['key'][0]);
        $t->same($base + 2, $plan['key_records'][2]['key'][0]);
        $t->same($base + 2, $plan['key_records'][3]['key'][0]);
        $t->same('z' . $i, $plan['key_records'][5]['key'][0]);
    };
}

foreach (range(1, 420) as $i) {
    $tests["real upstream index affinity autoindex dynamic coalesced constraints {$i}"] = static function (TestRunner $t) use ($i, $indexNamesFor): void {
        $executor = new SQLiteSchemaImportExecutor();
        $table = "t_auto_{$i}";
        $executor->execute("CREATE TABLE {$table}(c, d, UNIQUE(c, d), PRIMARY KEY(c, d), UNIQUE(c))");

        $t->same(["sqlite_autoindex_{$table}_1", "sqlite_autoindex_{$table}_2"], $indexNamesFor($executor, $table));
        $t->same(3, count($executor->schemaRecords('main')));
        $t->same($table, $executor->schemaRecords('main')[0]->name);
    };
}

return $tests;
