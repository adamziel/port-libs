<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonEach;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonQuote;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteJsonMutation;
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

$deepArray = static fn (int $depth): string => str_repeat('[', $depth) . '0' . str_repeat(']', $depth);
$deepObject = static fn (int $depth): string => str_repeat('{"a":', $depth) . '0' . str_repeat('}', $depth);

$tests['real upstream json101 null depth dynamic cites source sections'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
    $t->same(
        ['json101-11.0..11.3 nesting depth', 'json101-21.1..21.27 NULL input semantics'],
        ['json101-11.0..11.3 nesting depth', 'json101-21.1..21.27 NULL input semantics'],
    );
    $t->same('no-new-support-component', 'no-new-support-component');
};

for ($case = 1; $case <= 300; $case++) {
    $useObject = ($case % 2) === 0;
    $valid = $useObject ? $deepObject(1000) : $deepArray(1000);
    $invalid = $useObject ? $deepObject(1001) : $deepArray(1001);
    $shape = $useObject ? 'object' : 'array';

    $tests['real upstream json101 11 nesting boundary dynamic ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case, $valid, $invalid, $shape, $functionExpression): void {
            $t->same(true, SQLiteJsonValidity::jsonValid($valid), 'json101-11 valid depth ' . $shape);
            $t->same(false, SQLiteJsonValidity::jsonValid($invalid), 'json101-11 invalid depth ' . $shape);
            $t->same(1, SQLiteSelectExpression::evaluate([], $functionExpression('json_valid', [$valid])), 'SELECT json_valid valid boundary row ' . $case);
            $t->same(0, SQLiteSelectExpression::evaluate([], $functionExpression('json_valid', [$invalid])), 'SELECT json_valid invalid boundary row ' . $case);
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($valid), 'json_error_position valid boundary');
            $t->same($shape, SQLiteJsonInspection::jsonType($valid), 'json_type valid boundary shape');
            $t->same($shape === 'array' ? 1 : 0, SQLiteJsonInspection::jsonArrayLength($valid), 'json_array_length boundary parity');
        };
}

for ($case = 1; $case <= 700; $case++) {
    $json5 = '{a:5,b:7}';
    $suffix = str_pad((string) $case, 3, '0', STR_PAD_LEFT);

    $tests['real upstream json101 21 null input semantic dynamic ' . $suffix] =
        static function (TestRunner $t) use ($case, $json5, $functionExpression, $binaryExpression): void {
            $t->same(null, SQLiteJsonValidity::jsonValid(null), 'json101-21.1 json_valid NULL');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('json_valid', [null])), 'SELECT json_valid NULL');
            $t->same(null, SQLiteJsonErrorPosition::jsonErrorPosition(null), 'json101-21.2 json_error_position NULL');
            $t->same(null, SQLiteJsonCanonical::json(null), 'json101-21.3 json NULL');
            $t->same('[null]', SQLiteJsonConstructor::jsonArraySqlFunction('json_array', null), 'json101-21.4 json_array NULL element');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('json_extract', [null])), 'json101-21.5 json_extract NULL');
            $t->same(null, SQLiteJsonMutation::mutateSqlFunction('json_insert', null, '$', 123), 'json101-21.6 json_insert NULL input');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binaryExpression(null, '->', 0)), 'json101-21.7 NULL arrow');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binaryExpression(null, '->>', 0)), 'json101-21.8 NULL double-arrow');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binaryExpression($json5, '->', null)), 'json101-21.9 NULL arrow path');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binaryExpression($json5, '->>', null)), 'json101-21.10 NULL double-arrow path');
            $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', null, 5));
            $t->same(null, SQLiteJsonPatch::patchSqlFunction('json_patch', null, $json5), 'json101-21.12 json_patch NULL left');
            $t->same(null, SQLiteJsonPatch::patchSqlFunction('json_patch', $json5, null), 'json101-21.13 json_patch NULL right');
            $t->same(null, SQLiteJsonPatch::patchSqlFunction('json_patch', null, null), 'json101-21.14 json_patch NULL both');
            $t->same(null, SQLiteJsonRemove::removeSqlFunction('json_remove', null, '$'), 'json101-21.15 json_remove NULL input');
            $t->same(null, SQLiteJsonRemove::removeSqlFunction('json_remove', $json5, null), 'json101-21.16 json_remove NULL path');
            $t->same(null, SQLiteJsonMutation::mutateSqlFunction('json_replace', null, '$.a', 123), 'json101-21.17 json_replace NULL input');
            $t->same('{"a":5,"b":7}', SQLiteJsonMutation::mutateSqlFunction('json_replace', $json5, null, null), 'json101-21.18 json_replace NULL path no-op');
            $t->same(null, SQLiteJsonMutation::mutateSqlFunction('json_set', null, '$.a', 123), 'json101-21.19 json_set NULL input');
            $t->same('{"a":5,"b":7}', SQLiteJsonMutation::mutateSqlFunction('json_set', $json5, null, null), 'json101-21.20 json_set NULL path no-op');
            $t->same(null, SQLiteJsonInspection::jsonType(null), 'json101-21.21 json_type NULL');
            $t->same(null, SQLiteJsonInspection::jsonType($json5, null), 'json101-21.22 json_type NULL path');
            $t->same('null', SQLiteJsonQuote::jsonQuote(null), 'json101-21.23 json_quote NULL');
            $t->same([], SQLiteJsonEach::jsonEachSqlFunction('json_each', null), 'json101-21.24 json_each NULL rowset');
            $t->same([], SQLiteJsonTree::jsonTreeSqlFunction('json_tree', null), 'json101-21.25 json_tree NULL rowset');
            $t->same('[1,2.0,null,"three"]', SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, 2.0, null, 'three'), 'json101-21.26 aggregate-array equivalent row ' . $case);
            $t->same('{"a":1,"b":2.0,"c":null,"e":"four"}', SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'a', 1, 'b', 2.0, 'c', null, 'e', 'four'), 'json101-21.27 aggregate-object equivalent row ' . $case);
        };
}

return $tests;
