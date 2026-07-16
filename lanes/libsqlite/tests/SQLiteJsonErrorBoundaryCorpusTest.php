<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;

$tests = [];

/*
 * Expected offsets were selected against SQLite 3.51.2 json_error_position().
 * This corpus locks already-matching malformed JSON5/text edge offsets without
 * repeating JSON table malformed JSONB planner cases.
 */
$textCases = [
    'empty input starts at first byte' => ['', 1],
    'unterminated object reports byte after opener' => ['{', 2],
    'unterminated array reports byte after opener' => ['[', 2],
    'missing object value after label colon' => ['{a:', 4],
    'duplicate object comma' => ['{a:1,,}', 6],
    'duplicate array comma' => ['[1,,2]', 4],
    'trailing duplicate array comma' => ['[1,2,,]', 6],
    'unterminated array after whitespace' => ['[1, 2', 6],
    'hex literal without digits' => ['{a:0x}', 5],
    'sign without number' => ['{a:+}', 4],
    'exponent without digits' => ['{a:1e}', 5],
    'hex literal invalid suffix boundary' => ['{a:0xZZ}', 5],
    'leading zero integer boundary' => ['{a:01}', 5],
    'infinity token boundary' => ['{a:Infinityx}', 4],
    'nan token boundary' => ['{a:NaNx}', 4],
    'unterminated block comment before value' => ['{a:/*unterminated}', 4],
    'unicode identifier duplicate comma' => ["{\u{03C0}:1,,}", 6],
    'nested array duplicate comma' => ['{a:{b:[1,2,,]}}', 12],
    'nested object missing value after trailing array' => ['{a:{b:[1,2,], c:}}', 17],
    'array object duplicate comma' => ['{a:[1, {b:2,,}]}', 13],
    'block comment before array close is accepted' => ['{a:[1, /*c*/]}', 0],
    'line comment before array close is accepted' => ["{a:[1, // comment\n]}", 0],
    'hash token in array' => ['{a:[1, #]}', 8],
    'hex literal after valid hex member' => ['{a:[1, 0x10, 0x]}', 15],
    'json5 numeric spellings then unknown token' => ['{a:[1, +Inf, -Infinity, NaN, QNaN, SNaN, bad]}', 42],
    'unicode escape outside string value' => ['{a:\u00E9}', 4],
    'array missing comma' => ['[1 2]', 4],
    'array trailing content' => ['[1,2]x', 1],
    'literal trailing content' => ['true false', 1],
    'truncated null literal' => ['nul', 1],
    'root hex literal without digits' => ['0x', 1],
    'root malformed fractional number' => ['.e1', 1],
    'root exponent without digit' => ['1.e', 3],
    'double sign root number' => ['--1', 1],
    'deep nested array missing element' => ['{a:[[[[[[[[[[,]]]]]]]]]]}', 14],
];

foreach ($textCases as $name => [$json, $expected]) {
    $tests['json error boundary text ' . $name] = static function (TestRunner $t) use ($json, $expected): void {
        $t->same($expected, SQLiteJsonErrorPosition::jsonErrorPosition($json));
    };
}

$dispatchCases = [
    'uppercase function valid json5' => ['JSON_ERROR_POSITION', ['{a:1,}'], 0],
    'mixed case function duplicate comma' => ['Json_Error_Position', ['{a:1,,}'], 6],
    'argument vector valid json5' => ['json_error_position', ['{a:[1,2,],}'], 0],
    'argument vector malformed array' => ['json_error_position', ['[1,,2]'], 4],
    'text blob delegates to text boundary' => ['json_error_position', [new SQLiteBlobValue('{a:{b:[1,2,,]}}')], 12],
    'sql null remains null' => ['json_error_position', [null], null],
    'valid jsonb blob remains zero' => ['json_error_position', [new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['enabled' => true]]))], 0],
];

foreach ($dispatchCases as $name => [$function, $arguments, $expected]) {
    $tests['json error boundary dispatch ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected): void {
        $t->same($expected, SQLiteJsonErrorPosition::jsonErrorPositionSqlFunctionArguments($function, $arguments));
    };
}

return $tests;
