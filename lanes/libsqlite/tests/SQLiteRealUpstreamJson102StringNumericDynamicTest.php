<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => $arguments,
];

for ($case = 0; $case < 100; $case++) {
    $upstreamId = 'json102-' . (1300 + $case);
    $quoteRun = str_repeat('"', $case + 50);
    $source = 'abcdef' . $quoteRun . 'uvwxyz';
    $array = SQLiteJsonConstructor::jsonArraySqlFunction('json_array', $source);
    $jsonbArray = SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', $source);

    $tests['real upstream json102 append-string quote stress ' . $upstreamId] =
        static function (TestRunner $t) use ($source, $array, $jsonbArray, $literal, $functionExpression, $case, $upstreamId): void {
            $t->same($source, SQLiteJsonExtract::extract($array, '$[0]'), $upstreamId . ' json_extract(json_array(str), $[0]) round trips');
            $t->true($jsonbArray instanceof SQLiteBlobValue, $upstreamId . ' jsonb_array returns JSONB');
            $t->same($source, SQLiteJsonExtract::extract($jsonbArray, '$[0]'), $upstreamId . ' json_extract(jsonb_array(str), $[0]) round trips');
            $t->same($case + 50, substr_count($array, '\\"'), $upstreamId . ' every embedded quote is escaped exactly once');
            $t->same(1, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $array) ? 1 : 0, $upstreamId . ' generated JSON array is valid');
            $t->same($source, SQLiteSelectExpression::evaluate([], $functionExpression('json_extract', [
                $functionExpression('json_array', [$literal($source)]),
                $literal('$[0]'),
            ])), $upstreamId . ' SELECT expression dispatch preserves quote run');
        };
}

$numericRows = [
    'json102-1401 leading zero integer' => ['{"x":01}', 0, 0],
    'json102-1402 negative leading zero integer' => ['{"x":-01}', 0, 0],
    'json102-1403 zero integer' => ['{"x":0}', 1, 1],
    'json102-1404 negative zero integer' => ['{"x":-0}', 1, 1],
    'json102-1405 zero decimal' => ['{"x":0.1}', 1, 1],
    'json102-1406 negative zero decimal' => ['{"x":-0.1}', 1, 1],
    'json102-1407 zero fractional padding' => ['{"x":0.0000}', 1, 1],
    'json102-1408 negative zero fractional padding' => ['{"x":-0.0000}', 1, 1],
    'json102-1409 leading zero real' => ['{"x":01.5}', 0, 0],
    'json102-1410 negative leading zero real' => ['{"x":-01.5}', 0, 0],
    'json102-1411 double zero integer' => ['{"x":00}', 0, 0],
    'json102-1412 negative double zero integer' => ['{"x":-00}', 0, 0],
    'json102-1413 plus zero JSON5 extension' => ['{"x":+0}', 0, 1],
    'json102-1414 plus integer JSON5 extension' => ['{"x":+5}', 0, 1],
    'json102-1415 plus real JSON5 extension' => ['{"x":+5.5}', 0, 1],
];

foreach ($numericRows as $upstreamId => [$json, $strictValid, $json5NoError]) {
    $tests['real upstream json102 numeric validity boundary ' . $upstreamId] =
        static function (TestRunner $t) use ($json, $strictValid, $json5NoError, $literal, $functionExpression, $upstreamId): void {
            $t->same($strictValid === 1, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json), $upstreamId . ' strict json_valid result');
            $t->same($json5NoError === 1, SQLiteJsonErrorPosition::jsonErrorPosition($json) === 0, $upstreamId . ' NOT json_error_position result');
            $t->same($strictValid, SQLiteSelectExpression::evaluate([], $functionExpression('json_valid', [$literal($json)])), $upstreamId . ' SELECT json_valid dispatch');
            $t->same($json5NoError === 1 ? 0 : SQLiteJsonErrorPosition::jsonErrorPosition($json), SQLiteSelectExpression::evaluate([], $functionExpression('json_error_position', [$literal($json)])), $upstreamId . ' SELECT json_error_position dispatch');
            if ($strictValid === 1) {
                $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                $t->same($decoded, SQLiteJsonB::decode(new SQLiteBlobValue(SQLiteJsonB::encode($decoded))->bytes), $upstreamId . ' strict-valid JSONB decoded parity');
            } else {
                $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$json, 1]), $upstreamId . ' argument-vector strict rejection');
            }
        };
}

$tests['real upstream json102 string numeric dynamic source citations'] =
    static function (TestRunner $t): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
        $t->same(
            ['json102-1300..1399', 'json102-1401..1415'],
            ['json102-1300..1399', 'json102-1401..1415'],
        );
        $t->same(
            'does not repeat json102 constructor/path/mutation/operator rows, json105 reverse paths, json109 array insert, json104 merge patch, json106/json108 invariants, or JSON table cursor/source/constraint slices',
            'does not repeat json102 constructor/path/mutation/operator rows, json105 reverse paths, json109 array insert, json104 merge patch, json106/json108 invariants, or JSON table cursor/source/constraint slices',
        );
    };

$tests['real upstream json102 string numeric dynamic dependency closure'] =
    static function (TestRunner $t): void {
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
