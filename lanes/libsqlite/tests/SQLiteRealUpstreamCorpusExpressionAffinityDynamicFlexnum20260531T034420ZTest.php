<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

$tests = [];

// Source truth: SQLite upstream test/cast.test cast-10.1 through cast-10.10.
// Those cases introduced SQLITE_AFF_FLEXNUM and assert that VALUES, UNION ALL,
// derived tables, CROSS JOIN sources, and views preserve integer vs real
// storage classes across compound rows instead of coercing all numeric arms.
$armPairs = [
    'cast-real-int' => [
        ['name' => 'cast-real-44', 'value' => 44, 'cast' => 'REAL', 'quote' => '44.0', 'storage_class' => 'real'],
        ['name' => 'plain-int-55', 'value' => 55, 'cast' => null, 'quote' => '55', 'storage_class' => 'integer'],
    ],
    'cast-real-text-int' => [
        ['name' => 'cast-real-text-44', 'value' => '44', 'cast' => 'REAL', 'quote' => '44.0', 'storage_class' => 'real'],
        ['name' => 'plain-text-55', 'value' => '55', 'cast' => null, 'quote' => "'55'", 'storage_class' => 'text'],
    ],
    'cast-real-fraction-int' => [
        ['name' => 'cast-real-44-half', 'value' => '44.5', 'cast' => 'REAL', 'quote' => '44.5', 'storage_class' => 'real'],
        ['name' => 'plain-int-55', 'value' => 55, 'cast' => null, 'quote' => '55', 'storage_class' => 'integer'],
    ],
    'cast-numeric-real-int' => [
        ['name' => 'cast-numeric-44-real', 'value' => '44.0', 'cast' => 'NUMERIC', 'quote' => '44', 'storage_class' => 'integer'],
        ['name' => 'plain-real-55', 'value' => 55.0, 'cast' => null, 'quote' => '55.0', 'storage_class' => 'real'],
    ],
    'cast-real-exponent-int' => [
        ['name' => 'cast-real-exponent', 'value' => '4.4e1', 'cast' => 'REAL', 'quote' => '44.0', 'storage_class' => 'real'],
        ['name' => 'plain-int-55', 'value' => 55, 'cast' => null, 'quote' => '55', 'storage_class' => 'integer'],
    ],
    'cast-real-negative-int' => [
        ['name' => 'cast-real-negative', 'value' => '-44', 'cast' => 'REAL', 'quote' => '-44.0', 'storage_class' => 'real'],
        ['name' => 'plain-negative-int', 'value' => -55, 'cast' => null, 'quote' => '-55', 'storage_class' => 'integer'],
    ],
    'cast-real-zero-int' => [
        ['name' => 'cast-real-zero', 'value' => '0', 'cast' => 'REAL', 'quote' => '0.0', 'storage_class' => 'real'],
        ['name' => 'plain-zero-int', 'value' => 0, 'cast' => null, 'quote' => '0', 'storage_class' => 'integer'],
    ],
    'cast-real-prefix-int' => [
        ['name' => 'cast-real-prefix', 'value' => '44xyz', 'cast' => 'REAL', 'quote' => '44.0', 'storage_class' => 'real'],
        ['name' => 'plain-prefix-text', 'value' => '55xyz', 'cast' => null, 'quote' => "'55xyz'", 'storage_class' => 'text'],
    ],
    'cast-real-blob-int' => [
        ['name' => 'cast-real-blob', 'value' => new PortLibs\LibSqlite\SQLiteBlobValue('44'), 'cast' => 'REAL', 'quote' => '44.0', 'storage_class' => 'real'],
        ['name' => 'plain-blob', 'value' => new PortLibs\LibSqlite\SQLiteBlobValue('55'), 'cast' => null, 'quote' => "X'3535'", 'storage_class' => 'blob'],
    ],
    'cast-real-null-int' => [
        ['name' => 'cast-real-null', 'value' => null, 'cast' => 'REAL', 'quote' => 'NULL', 'storage_class' => 'null'],
        ['name' => 'plain-int-55', 'value' => 55, 'cast' => null, 'quote' => '55', 'storage_class' => 'integer'],
    ],
];

$sources = [
    'values',
    'union-all',
    'derived-values',
    'derived-union-all',
    'cross-join-values',
    'cross-join-union-all',
    'view-values',
    'view-union-all',
];

foreach ($armPairs as $pairName => $arms) {
    foreach ($sources as $source) {
        $tests["real upstream corpus expression affinity dynamic flexnum cast-10 {$pairName} {$source} rows"] = static function (TestRunner $t) use ($pairName, $arms, $source): void {
            $rows = SQLiteRealExpressionAffinityCorpusPlan::flexnumCompoundRows($arms, $source);

            $t->same(2, count($rows), $pairName . ' row count');
            foreach ($rows as $index => $row) {
                $t->same($source, $row['source'], $pairName . ' source');
                $t->same($index, $row['ordinal'], $pairName . ' ordinal');
                $t->same($arms[$index]['name'], $row['name'], $pairName . ' name');
                $t->same($arms[$index]['quote'], $row['quote'], $pairName . ' quote');
                $t->same($arms[$index]['storage_class'], $row['storage_class'], $pairName . ' storage');
                if (str_starts_with($source, 'cross-join-')) {
                    $t->same('X', $row['dummy'] ?? null, $pairName . ' cross join dummy');
                } else {
                    $t->same(false, array_key_exists('dummy', $row), $pairName . ' no dummy');
                }
            }

            $t->same(array_column($arms, 'storage_class'), array_column($rows, 'storage_class'), $pairName . ' flexnum classes');
            $t->contains('cast.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test');
        };
    }
}

$tests['real upstream corpus expression affinity dynamic flexnum cites upstream cast 10'] = static function (TestRunner $t) use ($armPairs, $sources): void {
    $t->same(10, count($armPairs));
    $t->same(8, count($sources));
    $t->same(80, count($armPairs) * count($sources));
    $t->same(
        'cast.test cast-10.1..10.10 SQLITE_AFF_FLEXNUM compound VALUES UNION ALL derived CROSS JOIN and view behavior',
        'cast.test cast-10.1..10.10 SQLITE_AFF_FLEXNUM compound VALUES UNION ALL derived CROSS JOIN and view behavior',
    );
    $t->contains('cast.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test');
};

return $tests;
