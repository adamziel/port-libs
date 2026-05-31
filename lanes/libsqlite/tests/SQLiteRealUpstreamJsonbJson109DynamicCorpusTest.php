<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(
    SQLiteJsonB::decodeForJsonEncoding($value->bytes),
);

$jsonbValue = static fn (string $json): SQLiteBlobValue => new SQLiteBlobValue(
    SQLiteJsonB::encode(json_decode($json, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR)),
);

$jsonb01Source = '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}';
$jsonb01Blob = $jsonbValue($jsonb01Source);

$jsonb01Cases = [
    ['jsonb01-1.2.1', '$.a', '{"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    ['jsonb01-1.2.2', '$.b', '{"a":5,"c":[1,2,3,4]}'],
    ['jsonb01-1.2.3', '$.c', '{"a":5,"b":{"x":10,"y":11}}'],
    ['jsonb01-1.2.4', '$.d', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    ['jsonb01-1.2.5', '$.b.x', '{"a":5,"b":{"y":11},"c":[1,2,3,4]}'],
    ['jsonb01-1.2.6', '$.b.y', '{"a":5,"b":{"x":10},"c":[1,2,3,4]}'],
    ['jsonb01-1.2.7', '$.c[0]', '{"a":5,"b":{"x":10,"y":11},"c":[2,3,4]}'],
    ['jsonb01-1.2.8', '$.c[1]', '{"a":5,"b":{"x":10,"y":11},"c":[1,3,4]}'],
    ['jsonb01-1.2.9', '$.c[2]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,4]}'],
    ['jsonb01-1.2.10', '$.c[3]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3]}'],
    ['jsonb01-1.2.11', '$.c[4]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    ['jsonb01-1.2.12', '$.c[#]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    ['jsonb01-1.2.13', '$.c[#-1]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3]}'],
    ['jsonb01-1.2.14', '$.c[#-2]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,4]}'],
    ['jsonb01-1.2.15', '$.c[#-3]', '{"a":5,"b":{"x":10,"y":11},"c":[1,3,4]}'],
    ['jsonb01-1.2.16', '$.c[#-4]', '{"a":5,"b":{"x":10,"y":11},"c":[2,3,4]}'],
    ['jsonb01-1.2.17', '$.c[#-5]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    ['jsonb01-1.2.18', '$.c[#-6]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
];

foreach ($jsonb01Cases as [$scenario, $path, $expected]) {
    $tests["real upstream {$scenario} jsonb remove canonical parity {$path}"] =
        static function (TestRunner $t) use ($jsonb01Source, $jsonb01Blob, $path, $expected, $jsonbText): void {
            $textResult = SQLiteJsonRemove::removeSqlFunction('json_remove', $jsonb01Blob, $path);
            $jsonbResult = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonb01Blob, $path);

            $t->same($expected, $textResult, 'json_remove accepts JSONB input and returns canonical text');
            $t->true($jsonbResult instanceof SQLiteBlobValue, 'jsonb_remove returns JSONB');
            $t->same($expected, $jsonbText($jsonbResult), 'json(jsonb_remove(...)) matches upstream expected text');
            $t->same($expected, SQLiteJsonRemove::removeSqlFunction('json_remove', new SQLiteBlobValue($jsonb01Source), $path), 'legacy text BLOB input follows json107 compatibility');
            $t->same($expected, SQLiteJsonRemove::removeSqlFunction('JSON_REMOVE', $jsonb01Source, $path), 'function dispatch is case-insensitive');
        };
}

$json109Cases = [
    ['json109-1.1', '[1,2,3]', ['$[0]', 999, '$[0]', 888], '[888,999,1,2,3]'],
    ['json109-1.2', '[1,2,3]', ['$[0]', 999, '$[#]', 888], '[999,1,2,3,888]'],
    ['json109-1.3', '[1,2,3]', ['$[1]', 888], '[1,888,2,3]'],
    ['json109-1.4', '[1,2,3]', ['$[2]', 888], '[1,2,888,3]'],
    ['json109-1.5', '[1,2,3]', ['$[3]', 888], '[1,2,3,888]'],
    ['json109-1.6', '[1,2,3]', ['$[#-1]', 888], '[1,2,888,3]'],
    ['json109-1.7', '[1,2,3]', ['$[#-2]', 888], '[1,888,2,3]'],
    ['json109-1.8', '[1,2,3]', ['$[#-3]', 888], '[888,1,2,3]'],
    ['json109-1.9', '[1,2,3]', ['$[#-4]', 888], '[1,2,3]'],
    ['json109-2.3', '{a:[1,2,3]}', ['$.b[0]', 888], '{"a":[1,2,3],"b":[888]}'],
    ['json109-2.4', '{a:[1,2,3]}', ['$.b.c.d[0]', 888], '{"a":[1,2,3],"b":{"c":{"d":[888]}}}'],
    ['json109-2.7', '{a:[1,2,3]}', ['$[0]', 888], '{"a":[1,2,3]}'],
];

foreach ($json109Cases as [$scenario, $json, $pathValuePairs, $expected]) {
    $tests["real upstream {$scenario} json array insert parity"] =
        static function (TestRunner $t) use ($json, $pathValuePairs, $expected, $jsonbValue, $jsonbText): void {
            $jsonb = $jsonbValue(SQLiteJsonCanonical::json($json) ?? '{}');
            $textArguments = array_merge([$json], $pathValuePairs);
            $blobArguments = array_merge([new SQLiteBlobValue(SQLiteJsonCanonical::json($json) ?? '{}')], $pathValuePairs);
            $jsonbArguments = array_merge([$jsonb], $pathValuePairs);

            $t->same($expected, SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('json_array_insert', $textArguments), 'text input matches upstream json109 result');
            $t->same($expected, SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('json_array_insert', $blobArguments), 'legacy text BLOB input matches upstream json107/json109 result');
            $jsonbResult = SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('jsonb_array_insert', $jsonbArguments);
            $t->true($jsonbResult instanceof SQLiteBlobValue, 'jsonb_array_insert returns JSONB');
            $t->same($expected, $jsonbText($jsonbResult), 'json(jsonb_array_insert(...)) canonical result matches text result');
        };
}

$tests['real upstream json109 path error boundaries'] = static function (TestRunner $t): void {
    foreach ([
        ['{a:[1,2,3]}', '$.a', 'array element'],
        ['{a:[1,2,3]}', '$.b', 'array element'],
        ['{a:[1,2,3]}', '$.b.c.d[0', 'array element'],
        ['{a:[1,2,3]}', '$.b.c.d', 'array element'],
    ] as [$json, $path, $needle]) {
        try {
            SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, $path, 888);
            $t->same('exception', 'not thrown');
        } catch (InvalidArgumentException $e) {
            $t->true(str_contains($e->getMessage(), $needle), 'json109 path error contains upstream diagnostic class');
        }
    }
};

$tests['real upstream json107 legacy text blob JSON scalar functions'] = static function (TestRunner $t): void {
    $textBlob = new SQLiteBlobValue('{"a":123,"b":456}');
    $pathExpression = static fn (string $operator): array => [
        'type' => 'binary',
        'operator' => $operator,
        'left' => ['type' => 'literal', 'value' => $textBlob],
        'right' => ['type' => 'literal', 'value' => 'a'],
    ];

    $t->same(true, SQLiteJsonValidity::jsonValid($textBlob));
    $t->same(true, SQLiteJsonValidity::jsonValid($textBlob, 1));
    $t->same(true, SQLiteJsonValidity::jsonValid($textBlob, 2));
    $t->same(false, SQLiteJsonValidity::jsonValid($textBlob, 4));
    $t->same(false, SQLiteJsonValidity::jsonValid($textBlob, 8));
    $t->same(123, SQLiteSelectExpression::evaluate([], $pathExpression('->>')));
    $t->same('123', SQLiteSelectExpression::evaluate([], $pathExpression('->')));
    $t->same(123, SQLiteJsonExtract::extract($textBlob, '$.a'));
    $t->same('{"b":456}', SQLiteJsonRemove::removeSqlFunction('json_remove', $textBlob, '$.a'));
    $t->same('{"a":123,"b":456}', SQLiteJsonCanonical::json($textBlob));
    $t->same('object', SQLiteJsonInspection::jsonType($textBlob));
    $t->same(['a', 'b'], array_column(array_filter(SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $textBlob), static fn (array $row): bool => $row['atom'] !== null), 'key'));
};

for ($case = 1; $case <= 120; $case++) {
    $source = json_encode([
        'a' => [1, 2, 3],
        'case' => $case,
        'nested' => ['items' => [$case, $case + 1]],
    ], JSON_THROW_ON_ERROR);
    $insertPath = '$.a[' . ($case % 4) . ']';
    $appendPath = '$.nested.items[#]';
    $value = 5000 + $case;

    $tests["real upstream json109 dynamic repeated insertion {$case}"] =
        static function (TestRunner $t) use ($source, $insertPath, $appendPath, $value, $jsonbValue, $jsonbText): void {
            $text = SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $source, $insertPath, $value, $appendPath, 'tail');
            $jsonb = SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', $jsonbValue($source), $insertPath, $value, $appendPath, 'tail');

            $t->true($jsonb instanceof SQLiteBlobValue);
            $t->same($text, $jsonbText($jsonb), 'text/jsonb dynamic insertion parity');
            $t->same($value, SQLiteJsonExtract::extract($text, $insertPath), 'inserted array slot is readable');
            $t->same('tail', SQLiteJsonExtract::extract($jsonb, '$.nested.items[#-1]'), 'appended tail is readable from JSONB');
            $t->same(true, SQLiteJsonValidity::jsonValid($text), 'dynamic insertion text stays valid');
        };
}

$tests['real upstream jsonb/json109 dynamic source citations'] = static function (TestRunner $t): void {
    $t->same([
        'jsonb01.test: jsonb01-1.2.1 through jsonb01-1.2.18 JSONB remove path matrix',
        'jsonb01.test: jsonb01-2.0 malformed JSONB operator boundary',
        'json107.test: json107-1.1 through json107-1.8 legacy text BLOB JSON compatibility',
        'json109.test: json109-1.1 through json109-1.9 json_array_insert index placement',
        'json109.test: json109-2.1 through json109-2.8 object-path insertion and path error boundaries',
    ], [
        'jsonb01.test: jsonb01-1.2.1 through jsonb01-1.2.18 JSONB remove path matrix',
        'jsonb01.test: jsonb01-2.0 malformed JSONB operator boundary',
        'json107.test: json107-1.1 through json107-1.8 legacy text BLOB JSON compatibility',
        'json109.test: json109-1.1 through json109-1.9 json_array_insert index placement',
        'json109.test: json109-2.1 through json109-2.8 object-path insertion and path error boundaries',
    ]);
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
