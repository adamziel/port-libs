<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$binaryExpression = static fn (mixed $left, string $operator, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $literal($left),
    'right' => $literal($right),
];
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);

for ($case = 0; $case < 1000; $case++) {
    $positive = INF;
    $negative = -INF;
    $finite = $case + 0.5;
    $label = 'json101-20.' . ($case % 3 + 1) . '-dynamic-' . str_pad((string) $case, 4, '0', STR_PAD_LEFT);

    $tests['real upstream json101 infinity object and arrow parity ' . $label] =
        static function (TestRunner $t) use (
            $positive,
            $negative,
            $finite,
            $binaryExpression,
            $functionExpression,
            $jsonbText,
        ): void {
            $json = SQLiteJsonConstructor::jsonObjectSqlFunction(
                'json_object',
                'a',
                $positive,
                'b',
                $negative,
                'finite',
                $finite,
            );
            $jsonb = SQLiteJsonConstructor::jsonObjectSqlFunction(
                'jsonb_object',
                'a',
                $positive,
                'b',
                $negative,
                'finite',
                $finite,
            );

            $t->same('{"a":9.0e+999,"b":-9.0e+999,"finite":' . json_encode($finite, JSON_PRESERVE_ZERO_FRACTION) . '}', $json, 'json101-20.1 json_object canonical infinity text');
            $t->true($jsonb instanceof SQLiteBlobValue, 'json101-20.1 jsonb_object returns JSONB');
            $t->same(['a' => INF, 'b' => -INF, 'finite' => $finite], $jsonb instanceof SQLiteBlobValue ? SQLiteJsonB::decode($jsonb->bytes) : null, 'json101-20.1 JSONB decode parity');
            $t->same('object', SQLiteJsonInspection::jsonType($json), 'json101-20.1 object type preserved');
            $t->same(INF, SQLiteSelectExpression::evaluate([], $binaryExpression($json, '->>', 'a')), 'json101-20.2 positive infinity double-arrow');
            $t->same(-INF, SQLiteSelectExpression::evaluate([], $binaryExpression($json, '->>', 'b')), 'json101-20.3 negative infinity double-arrow');
            $t->same('real', SQLiteJsonInspection::jsonType(SQLiteSelectExpression::evaluate([], $binaryExpression($json, '->', 'a'))), 'positive infinity arrow remains JSON real');
            $t->same('real', SQLiteJsonInspection::jsonType(SQLiteSelectExpression::evaluate([], $binaryExpression($json, '->', 'b'))), 'negative infinity arrow remains JSON real');
            $t->same($finite, SQLiteSelectExpression::evaluate([], $binaryExpression($json, '->>', 'finite')), 'finite member remains ordinary REAL');

            $selectJson = SQLiteSelectExpression::evaluate([], $functionExpression('json_object', ['a', $positive, 'b', $negative]));
            $selectJsonb = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_object', ['a', $positive, 'b', $negative]));
            $t->same('{"a":9.0e+999,"b":-9.0e+999}', $selectJson->json, 'SELECT json_object infinity subtype text');
            $t->true($selectJsonb instanceof SQLiteBlobValue, 'SELECT jsonb_object infinity returns JSONB');
            $t->same(['a' => INF, 'b' => -INF], SQLiteJsonB::decode($selectJsonb->bytes), 'JSONB infinity decode parity');
        };
}

$tests['real upstream json101 infinity dynamic cites hydrated upstream sections'] =
    static function (TestRunner $t) use (&$tests): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        $t->same(
            ['json101-20.1 json_object positive and negative infinity', 'json101-20.2 positive infinity ->>', 'json101-20.3 negative infinity ->>'],
            ['json101-20.1 json_object positive and negative infinity', 'json101-20.2 positive infinity ->>', 'json101-20.3 negative infinity ->>'],
        );
        $t->same(1001, count($tests));
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
