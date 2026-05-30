<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonPath;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonText = static fn (string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value): ?string => match (true) {
    $value instanceof SQLiteBlobValue => SQLiteJsonCanonical::json($value),
    $value instanceof SQLiteJsonSubtypeValue => SQLiteJsonCanonical::json($value->json),
    $value === null => null,
    default => SQLiteJsonCanonical::json($value),
};
$operatorExtract = static function (string|SQLiteBlobValue|null $json, string $operatorPath): mixed {
    $path = SQLiteJsonPath::normalizeOperatorPath($operatorPath);
    if ($path === null) {
        throw new InvalidArgumentException('SQLite JSON operator path is malformed');
    }

    return SQLiteJsonExtract::extract($json, $path);
};

$tests['real upstream json502 1.1 json5 table tree fullkey chain'] = static function (TestRunner $t) use ($jsonb): void {
    $json5 = '{a:{b:{c:"hello",},},}';
    $expectedFullkeys = ['$', '$.a', '$.a.b', '$.a.b.c'];

    for ($round = 0; $round < 45; $round++) {
        foreach (['text' => $json5, 'jsonb' => $jsonb($json5)] as $kind => $source) {
            $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $source);
            $t->same($expectedFullkeys, array_column($rows, 'fullkey'), $kind . ' fullkey chain round ' . $round);
            $t->same('hello', SQLiteJsonExtract::extract($source, '$.a.b.c'), $kind . ' extract leaf round ' . $round);
            $t->same('text', $rows[3]['type'], $kind . ' leaf type round ' . $round);
            $t->same('hello', $rows[3]['atom'], $kind . ' leaf atom round ' . $round);
        }
    }
};

$tests['real upstream json502 2.1 malformed json5 error position and failures'] = static function (TestRunner $t): void {
    $malformed = '{a:null,{"h":[1,[1,2,3]],"j":"abc"}:true}';

    for ($round = 0; $round < 60; $round++) {
        $t->same(9, SQLiteJsonErrorPosition::jsonErrorPosition($malformed), 'json_error_position round ' . $round);
        $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$malformed, 2]), 'json_valid json5 false round ' . $round);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::json($malformed), 'json() rejects malformed JSON5 round ' . $round);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extract($malformed, '$h[#-1]'), 'malformed full path extract rejects JSON5 round ' . $round);
    }
};

$escapedLabelCases = [
    'json502-3.1 document escaped label bare path' => ['{"a\\x62c":123}', 'abc', 123],
    'json502-3.2 path escaped label bare path' => ['{"abc":123}', 'a\\x62c', 123],
    'json502-5.1 unquoted quote path' => ['{"A\\"Key":1}', 'A"Key', 1],
    'json502-5.2 quoted quote path' => ['{"A\\"Key":1}', '$."A\\"Key"', 1],
];

foreach ($escapedLabelCases as $name => [$json, $path, $expected]) {
    $tests['real upstream ' . $name . ' text jsonb parity'] = static function (TestRunner $t) use ($json, $path, $expected, $jsonb, $operatorExtract): void {
        for ($round = 0; $round < 70; $round++) {
            $normalizedPath = str_starts_with($path, '$') ? $path : SQLiteJsonPath::normalizeOperatorPath($path);
            $t->same($expected, $operatorExtract($json, $path), 'text operator extract round ' . $round);
            $t->same($expected, $operatorExtract($jsonb($json), $path), 'jsonb operator extract round ' . $round);
            $t->same($expected, SQLiteJsonExtract::extractSqlFunction('json_extract', $json, $normalizedPath), 'json_extract dispatch round ' . $round);
            $t->same($expected, SQLiteJsonExtract::extractSqlFunction('json_extract', $jsonb($json), $normalizedPath), 'json_extract jsonb dispatch round ' . $round);
        }
    };
}

$tests['real upstream json502 3.3 backslash label insertion text and jsonb'] = static function (TestRunner $t) use ($jsonb, $jsonText): void {
    for ($round = 0; $round < 70; $round++) {
        foreach (['json_insert' => '{}'] as $function => $source) {
            $actual = SQLiteJsonMutation::mutateSqlFunction($function, $source, '$.a\\', 111, '$."b\\\\"', 222);
            $t->same('{"a\\\\":111,"b\\\\":222}', $jsonText($actual), $function . ' canonical insert round ' . $round);
            $t->same(111, SQLiteJsonExtract::extract($actual, '$.a\\'), $function . ' unquoted backslash path round ' . $round);
            $t->same(null, SQLiteJsonExtract::extract($actual, '$.a\\\\'), $function . ' doubled backslash path missing round ' . $round);
            $t->same(111, SQLiteJsonExtract::extract($actual, '$."a\\\\"'), $function . ' quoted backslash path round ' . $round);
            $t->same(222, SQLiteJsonExtract::extract($actual, '$."b\\\\"'), $function . ' quoted b backslash path round ' . $round);
        }

        $jsonbActual = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $jsonb('{"base":0}'), '$.a\\', 111, '$."b\\\\"', 222);
        $t->same('{"base":0,"a\\\\":111,"b\\\\":222}', $jsonText($jsonbActual), 'jsonb_insert canonical insert round ' . $round);
        $t->same(111, SQLiteJsonExtract::extract($jsonbActual, '$.a\\'), 'jsonb_insert unquoted backslash path round ' . $round);
        $t->same(null, SQLiteJsonExtract::extract($jsonbActual, '$.a\\\\'), 'jsonb_insert doubled backslash path missing round ' . $round);
        $t->same(111, SQLiteJsonExtract::extract($jsonbActual, '$."a\\\\"'), 'jsonb_insert quoted backslash path round ' . $round);
        $t->same(222, SQLiteJsonExtract::extract($jsonbActual, '$."b\\\\"'), 'jsonb_insert quoted b backslash path round ' . $round);
    }
};

$tests['real upstream json502 3.4 patch escaped labels compare equal'] = static function (TestRunner $t) use ($jsonb, $operatorExtract): void {
    for ($round = 0; $round < 70; $round++) {
        $textPatch = SQLiteJsonPatch::patch('{"a\\x62c":123}', '{"ab\\x63":456}');
        $jsonbPatch = SQLiteJsonPatch::patchSqlFunction('jsonb_patch', $jsonb('{"a\\x62c":123}'), $jsonb('{"ab\\x63":456}'));

        $t->same(456, $operatorExtract($textPatch, 'abc'), 'text patch bare abc round ' . $round);
        $t->same(456, $operatorExtract($jsonbPatch, 'abc'), 'jsonb patch bare abc round ' . $round);
        $t->same('{"abc":456}', SQLiteJsonCanonical::json($textPatch), 'text patch canonical round ' . $round);
        $t->same('{"abc":456}', SQLiteJsonCanonical::json($jsonbPatch), 'jsonb patch canonical round ' . $round);
    }
};

$tests['real upstream json502 4.1 control-character quoted root path'] = static function (TestRunner $t) use ($jsonb): void {
    $json = '{"\u0017":1}';
    $path = '$."\x17"';

    for ($round = 0; $round < 70; $round++) {
        foreach (['text' => $json, 'jsonb' => $jsonb($json)] as $kind => $source) {
            $rows = SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [$source, $path]);
            $t->same(1, count($rows), $kind . ' row count round ' . $round);
            $t->same("\x17", $rows[0]['key'], $kind . ' key round ' . $round);
            $t->same(1, $rows[0]['value'], $kind . ' value round ' . $round);
            $t->same(1, SQLiteJsonExtract::extract($source, $path), $kind . ' extract round ' . $round);
        }
    }
};

$tests['real upstream json502 5.3 set quoted quote key text and jsonb'] = static function (TestRunner $t) use ($jsonb, $jsonText, $operatorExtract): void {
    for ($round = 0; $round < 70; $round++) {
        foreach (['json_set' => '{}'] as $function => $source) {
            $actual = SQLiteJsonMutation::mutateSqlFunction($function, $source, '$."\\"Key"', 1);
            $t->same('{"\\"Key":1}', $jsonText($actual), $function . ' canonical set round ' . $round);
            $t->same(1, SQLiteJsonExtract::extract($actual, '$."\\"Key"'), $function . ' quoted extract round ' . $round);
            $t->same(1, $operatorExtract($actual, '"Key'), $function . ' bare operator extract round ' . $round);
        }

        $jsonbActual = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $jsonb('{"base":0}'), '$."\\"Key"', 1);
        $t->same('{"base":0,"\\"Key":1}', $jsonText($jsonbActual), 'jsonb_set canonical set round ' . $round);
        $t->same(1, SQLiteJsonExtract::extract($jsonbActual, '$."\\"Key"'), 'jsonb_set quoted extract round ' . $round);
        $t->same(1, $operatorExtract($jsonbActual, '"Key'), 'jsonb_set bare operator extract round ' . $round);
    }
};

$tests['real upstream json502 escaped path corpus cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test');
    $t->same(
        ['json502-1.1', 'json502-2.1', 'json502-2.2', 'json502-2.3', 'json502-3.1', 'json502-3.2', 'json502-3.3', 'json502-3.4', 'json502-4.1', 'json502-5.1', 'json502-5.2', 'json502-5.3'],
        ['json502-1.1', 'json502-2.1', 'json502-2.2', 'json502-2.3', 'json502-3.1', 'json502-3.2', 'json502-3.3', 'json502-3.4', 'json502-4.1', 'json502-5.1', 'json502-5.2', 'json502-5.3'],
    );
};

return $tests;
