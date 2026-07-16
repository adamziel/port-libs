<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectCompound;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$normalize = null;
$normalize = static function (mixed $value) use (&$normalize): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return ['blob' => bin2hex($value->bytes)];
    }
    if (is_array($value)) {
        return array_map(static fn (mixed $item): mixed => $normalize($item), $value);
    }

    return $value;
};

$compoundCases = [
    'intersect keeps integer and text distinct' => [
        'INTERSECT',
        [['v' => 1]],
        [['v' => '1']],
        [],
    ],
    'except keeps integer when right arm has text' => [
        'EXCEPT',
        [['v' => 1]],
        [['v' => '1']],
        [['v' => 1]],
    ],
    'intersect matches integer and real numeric values' => [
        'INTERSECT',
        [['v' => 1]],
        [['v' => 1.0]],
        [['v' => 1]],
    ],
    'except removes integer when right arm has equivalent real' => [
        'EXCEPT',
        [['v' => 1]],
        [['v' => 1.0]],
        [],
    ],
    'intersect keeps text and blob distinct' => [
        'INTERSECT',
        [['v' => '1']],
        [['v' => new SQLiteBlobValue('1')]],
        [],
    ],
    'except keeps text when right arm has same blob bytes' => [
        'EXCEPT',
        [['v' => '1']],
        [['v' => new SQLiteBlobValue('1')]],
        [['v' => '1']],
    ],
    'intersect treats nulls as equal' => [
        'INTERSECT',
        [['v' => null], ['v' => '']],
        [['v' => null]],
        [['v' => null]],
    ],
    'except removes nulls as duplicates' => [
        'EXCEPT',
        [['v' => null], ['v' => '']],
        [['v' => null]],
        [['v' => '']],
    ],
    'intersect preserves first left duplicate representative' => [
        'INTERSECT',
        [['v' => 2.0], ['v' => 2]],
        [['v' => 2]],
        [['v' => 2.0]],
    ],
    'except emits one remaining left representative' => [
        'EXCEPT',
        [['v' => '2'], ['v' => '2'], ['v' => 2]],
        [['v' => 2]],
        [['v' => '2']],
    ],
    'intersect compares every result column without affinity' => [
        'INTERSECT',
        [['name' => 'siteurl', 'v' => 1], ['name' => 'home', 'v' => '1']],
        [['name' => 'siteurl', 'v' => '1'], ['name' => 'home', 'v' => '1']],
        [['name' => 'home', 'v' => '1']],
    ],
    'except compares every result column without affinity' => [
        'EXCEPT',
        [['name' => 'siteurl', 'v' => 1], ['name' => 'home', 'v' => '1']],
        [['name' => 'siteurl', 'v' => '1'], ['name' => 'home', 'v' => '1']],
        [['name' => 'siteurl', 'v' => 1]],
    ],
];

foreach ($compoundCases as $name => [$operator, $leftRows, $rightRows, $expected]) {
    $tests['compound except intersect affinity ' . $name] = static function (TestRunner $t) use ($operator, $leftRows, $rightRows, $expected, $normalize): void {
        $t->same($normalize($expected), $normalize(SQLiteSelectCompound::combine($leftRows, $rightRows, $operator)));
    };
}

$sqlCases = [
    'select integer intersect text is empty' => [
        "SELECT 1 AS v INTERSECT SELECT '1' AS v",
        [],
    ],
    'select integer except text keeps integer' => [
        "SELECT 1 AS v EXCEPT SELECT '1' AS v",
        [['v' => 1]],
    ],
    'select real intersect integer matches numeric' => [
        'SELECT 1.0 AS v INTERSECT SELECT 1 AS v',
        [['v' => 1.0]],
    ],
    'select integer except real removes numeric duplicate' => [
        'SELECT 1 AS v EXCEPT SELECT 1.0 AS v',
        [],
    ],
    'select cast text except integer keeps text' => [
        "SELECT CAST(1 AS TEXT) AS v EXCEPT SELECT 1 AS v",
        [['v' => '1']],
    ],
    'select cast integer intersect text remains empty' => [
        "SELECT CAST('1' AS INTEGER) AS v INTERSECT SELECT '1' AS v",
        [],
    ],
    'select blob except text keeps blob' => [
        "SELECT X'31' AS v EXCEPT SELECT '1' AS v",
        [['v' => new SQLiteBlobValue('1')]],
    ],
    'select blob intersect blob matches bytes' => [
        "SELECT X'31' AS v INTERSECT SELECT CAST('1' AS BLOB) AS v",
        [['v' => new SQLiteBlobValue('1')]],
    ],
    'select null intersect null matches' => [
        'SELECT NULL AS v INTERSECT SELECT NULL AS v',
        [['v' => null]],
    ],
    'select null except null removes duplicate' => [
        'SELECT NULL AS v EXCEPT SELECT NULL AS v',
        [],
    ],
    'select compound chained except preserves text one' => [
        "SELECT '1' AS v UNION SELECT 1 AS v EXCEPT SELECT 1.0 AS v ORDER BY v",
        [['v' => '1']],
    ],
    'select compound chained intersect keeps numeric representative' => [
        "SELECT 1.0 AS v UNION SELECT '1' AS v INTERSECT SELECT 1 AS v",
        [['v' => 1.0]],
    ],
    'select compound CTE text except numeric keeps CTE text' => [
        "WITH seed(v) AS (VALUES ('1')) SELECT v FROM seed EXCEPT SELECT 1 AS v",
        [['v' => '1']],
    ],
];

foreach ($sqlCases as $name => [$sql, $expected]) {
    $tests['select sql compound except intersect affinity ' . $name] = static function (TestRunner $t) use ($sql, $expected, $normalize): void {
        $t->same($normalize($expected), $normalize(SQLiteSelectSql::execute($sql, [])));
    };
}

return $tests;
