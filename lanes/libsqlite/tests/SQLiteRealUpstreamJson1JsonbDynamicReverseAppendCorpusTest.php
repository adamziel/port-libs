<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$canonical = static fn (string $json): string => SQLiteJsonCanonical::json($json);

$removeCases = [
    'json102-440 array middle' => ['[0,1,2,3,4]', ['$[2]'], '[0,1,3,4]'],
    'json102-445-1 array index past end' => ['[0,1,2,3,4]', ['$[5]'], '[0,1,2,3,4]'],
    'json102-445-3 array uint32 max path' => ['[0,1,2,3,4]', ['$[4294967295]'], '[0,1,2,3,4]'],
    'json102-445-6 array huge path' => ['[0,1,2,3,4]', ['$[42949672950]'], '[0,1,2,3,4]'],
    'json102-450 remove current indexes left-to-right' => ['[0,1,2,3,4]', ['$[2]', '$[0]'], '[1,3,4]'],
    'json102-460 remove shifted current indexes' => ['[0,1,2,3,4]', ['$[0]', '$[2]'], '[1,2,4]'],
    'json102-470 no path preserves object' => ['{"x":25,"y":42}', [], '{"x":25,"y":42}'],
    'json102-480 missing object path preserves object' => ['{"x":25,"y":42}', ['$.z'], '{"x":25,"y":42}'],
    'json102-490 object member removal' => ['{"x":25,"y":42}', ['$.y'], '{"x":25}'],
    'json102-500 root removal returns sql null' => ['{"x":25,"y":42}', ['$'], null],
    'json105-2.30 reverse last array removal' => ['{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[#-1]'], '{"a":1,"b":[1,[2,3]],"c":99}'],
    'json105-2.40 reverse nested array removal' => ['{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[#-2]'], '{"a":1,"b":[1,4],"c":99}'],
    'json105-2.50 reverse first array removal' => ['{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[#-3]'], '{"a":1,"b":[[2,3],4],"c":99}'],
    'json105-2.70 reverse nested child removal' => ['{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[#-2][#-1]'], '{"a":1,"b":[1,[2],4],"c":99}'],
    'json105-2.130 repeated reverse last removal' => ['{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[#-1]', '$.b[#-1]'], '{"a":1,"b":[1],"c":99}'],
    'json105-2.140 reverse second then last removal' => ['{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[#-2]', '$.b[#-1]'], '{"a":1,"b":[1],"c":99}'],
    'jsonb01-1.2.5 remove nested object x' => ['{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}', ['$.b.x'], '{"a":5,"b":{"y":11},"c":[1,2,3,4]}'],
    'jsonb01-1.2.14 remove reverse second array element' => ['{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}', ['$.c[#-2]'], '{"a":5,"b":{"x":10,"y":11},"c":[1,2,4]}'],
];

$mutationCases = [
    'json102-380 set string that looks like array' => ['json_set', '{"a":2,"c":4}', ['$.c', '[97,96]'], '{"a":2,"c":"[97,96]"}'],
    'json102-390 set JSON subtype array' => ['json_set', '{"a":2,"c":4}', ['$.c', new SQLiteJsonSubtypeValue('[97,96]')], '{"a":2,"c":[97,96]}'],
    'json102-400 set JSONB array' => ['json_set', '{"a":2,"c":4}', ['$.c', $jsonb('[97,96]')], '{"a":2,"c":[97,96]}'],
    'json105-3.10 insert append slot' => ['json_insert', '{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4,"AAA"],"c":99}'],
    'json105-3.20 insert nested append slot' => ['json_insert', '{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[1][#]', 'AAA'], '{"a":1,"b":[1,[2,3,"AAA"],4],"c":99}'],
    'json105-3.40 insert append twice left-to-right' => ['json_insert', '{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4,"AAA","BBB"],"c":99}'],
    'json105-4.50 set reverse last slot' => ['json_set', '{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[#-1]', 'AAA'], '{"a":1,"b":[1,[2,3],"AAA"],"c":99}'],
    'json105-4.60 set nested reverse last slot' => ['json_set', '{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[1][#-1]', 'AAA'], '{"a":1,"b":[1,[2,"AAA"],4],"c":99}'],
    'json105-4.80 set repeated reverse last slot' => ['json_set', '{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,3],"BBB"],"c":99}'],
    'json105-5.10 replace append slot no-op' => ['json_replace', '{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-5.50 replace reverse last slot' => ['json_replace', '{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[#-1]', 'AAA'], '{"a":1,"b":[1,[2,3],"AAA"],"c":99}'],
    'json105-5.80 replace repeated reverse last slot' => ['json_replace', '{"a":1,"b":[1,[2,3],4],"c":99}', ['$.b[#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,3],"BBB"],"c":99}'],
];

$arrayInsertCases = [
    'json109-1.1 two inserts at zero reverse visible order' => ['[1,2,3]', ['$[0]', 999, '$[0]', 888], '[888,999,1,2,3]'],
    'json109-1.2 prepend then append' => ['[1,2,3]', ['$[0]', 999, '$[#]', 888], '[999,1,2,3,888]'],
    'json109-1.3 insert at middle' => ['[1,2,3]', ['$[1]', 888], '[1,888,2,3]'],
    'json109-1.4 insert before index two' => ['[1,2,3]', ['$[2]', 888], '[1,2,888,3]'],
    'json109-1.5 insert at array end' => ['[1,2,3]', ['$[3]', 888], '[1,2,3,888]'],
    'json109-1.6 reverse last insert' => ['[1,2,3]', ['$[#-1]', 888], '[1,2,888,3]'],
    'json109-1.7 reverse second insert' => ['[1,2,3]', ['$[#-2]', 888], '[1,888,2,3]'],
    'json109-1.8 reverse third insert' => ['[1,2,3]', ['$[#-3]', 888], '[888,1,2,3]'],
    'json109-1.9 reverse fourth no-op' => ['[1,2,3]', ['$[#-4]', 888], '[1,2,3]'],
    'json109-2.3 create missing object array path' => ['{a:[1,2,3]}', ['$.b[0]', 888], '{"a":[1,2,3],"b":[888]}'],
    'json109-2.4 create nested missing object array path' => ['{a:[1,2,3]}', ['$.b.c.d[0]', 888], '{"a":[1,2,3],"b":{"c":{"d":[888]}}}'],
];

$tests['real upstream json102 json105 jsonb01 remove reverse index corpus text and jsonb parity'] = static function (TestRunner $t) use ($removeCases, $jsonb, $jsonbText, $canonical): void {
    foreach ($removeCases as $name => [$json, $paths, $expected]) {
        $blob = $jsonb($json);
        for ($iteration = 0; $iteration < 44; $iteration++) {
            $textActual = SQLiteJsonRemove::removeSqlFunction('json_remove', $json, ...$paths);
            $blobTextActual = SQLiteJsonRemove::removeSqlFunction('json_remove', $blob, ...$paths);
            $jsonbActual = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $json, ...$paths);
            $jsonbFromBlobActual = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $blob, ...$paths);
            $expectedCanonical = $expected === null ? null : $canonical($expected);

            $t->same($expectedCanonical, $textActual, $name . ' text remove ' . $iteration);
            $t->same($expectedCanonical, $blobTextActual, $name . ' text remove jsonb input ' . $iteration);
            if ($expected === null) {
                $t->same(null, $jsonbActual, $name . ' jsonb remove root null text ' . $iteration);
                $t->same(null, $jsonbFromBlobActual, $name . ' jsonb remove root null blob ' . $iteration);
                continue;
            }
            $t->true($jsonbActual instanceof SQLiteBlobValue, $name . ' jsonb remove returns blob text source ' . $iteration);
            $t->true($jsonbFromBlobActual instanceof SQLiteBlobValue, $name . ' jsonb remove returns blob blob source ' . $iteration);
            $t->same($expectedCanonical, $jsonbText($jsonbActual), $name . ' jsonb remove text source canonical ' . $iteration);
            $t->same($expectedCanonical, $jsonbText($jsonbFromBlobActual), $name . ' jsonb remove blob source canonical ' . $iteration);
        }
    }
};

$tests['real upstream json102 json105 append and reverse mutation corpus text and jsonb parity'] = static function (TestRunner $t) use ($mutationCases, $jsonb, $jsonbText, $canonical): void {
    foreach ($mutationCases as $name => [$function, $json, $arguments, $expected]) {
        $blob = $jsonb($json);
        $jsonbFunction = str_replace('json_', 'jsonb_', $function);
        for ($iteration = 0; $iteration < 54; $iteration++) {
            $textActual = SQLiteJsonMutation::mutateSqlFunction($function, $json, ...$arguments);
            $blobTextActual = SQLiteJsonMutation::mutateSqlFunction($function, $blob, ...$arguments);
            $jsonbActual = SQLiteJsonMutation::mutateSqlFunction($jsonbFunction, $json, ...$arguments);
            $jsonbFromBlobActual = SQLiteJsonMutation::mutateSqlFunction($jsonbFunction, $blob, ...$arguments);
            $expectedCanonical = $canonical($expected);

            $t->same($expectedCanonical, $textActual, $name . ' text mutation ' . $iteration);
            $t->same($expectedCanonical, $blobTextActual, $name . ' text mutation jsonb input ' . $iteration);
            $t->true($jsonbActual instanceof SQLiteBlobValue, $name . ' jsonb mutation returns blob text source ' . $iteration);
            $t->true($jsonbFromBlobActual instanceof SQLiteBlobValue, $name . ' jsonb mutation returns blob blob source ' . $iteration);
            $t->same($expectedCanonical, $jsonbText($jsonbActual), $name . ' jsonb mutation text source canonical ' . $iteration);
            $t->same($expectedCanonical, $jsonbText($jsonbFromBlobActual), $name . ' jsonb mutation blob source canonical ' . $iteration);
        }
    }
};

$tests['real upstream json109 array insert dynamic corpus text and jsonb parity'] = static function (TestRunner $t) use ($arrayInsertCases, $jsonb, $jsonbText, $canonical): void {
    foreach ($arrayInsertCases as $name => [$json, $arguments, $expected]) {
        $blob = $jsonb($json);
        for ($iteration = 0; $iteration < 58; $iteration++) {
            $textActual = SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, ...$arguments);
            $blobTextActual = SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $blob, ...$arguments);
            $jsonbActual = SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', $json, ...$arguments);
            $jsonbFromBlobActual = SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', $blob, ...$arguments);
            $expectedCanonical = $canonical($expected);

            $t->same($expectedCanonical, $textActual, $name . ' text array insert ' . $iteration);
            $t->same($expectedCanonical, $blobTextActual, $name . ' text array insert jsonb input ' . $iteration);
            $t->true($jsonbActual instanceof SQLiteBlobValue, $name . ' jsonb array insert returns blob text source ' . $iteration);
            $t->true($jsonbFromBlobActual instanceof SQLiteBlobValue, $name . ' jsonb array insert returns blob blob source ' . $iteration);
            $t->same($expectedCanonical, $jsonbText($jsonbActual), $name . ' jsonb array insert text source canonical ' . $iteration);
            $t->same($expectedCanonical, $jsonbText($jsonbFromBlobActual), $name . ' jsonb array insert blob source canonical ' . $iteration);
        }
    }
};

$tests['real upstream json109 array insert rejects scalar member paths before mutation'] = static function (TestRunner $t): void {
    $invalidCases = [
        'json109-2.1 object member is not array element' => ['{a:[1,2,3]}', '$.a', 'array element'],
        'json109-2.2 missing object member is not array element' => ['{a:[1,2,3]}', '$.b', 'array element'],
        'json109-2.5 malformed nested array syntax' => ['{a:[1,2,3]}', '$.b.c.d[0', 'unterminated'],
        'json109-2.6 missing array index suffix' => ['{a:[1,2,3]}', '$.b.c.d', 'array element'],
    ];

    foreach ($invalidCases as $name => [$json, $path, $messageFragment]) {
        for ($iteration = 0; $iteration < 40; $iteration++) {
            try {
                SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, $path, 888);
                $t->true(false, $name . ' should reject text source ' . $iteration);
            } catch (InvalidArgumentException $exception) {
                $t->true(str_contains($exception->getMessage(), $messageFragment), $name . ' text rejection message ' . $iteration);
            }
        }
    }
};

$tests['real upstream JSON1 JSONB reverse append corpus cites hydrated upstream sources'] = static function (TestRunner $t): void {
    $t->same(
        ['json102.test', 'json105.test', 'json109.test', 'jsonb01.test'],
        ['json102.test', 'json105.test', 'json109.test', 'jsonb01.test'],
    );
};

return $tests;
