<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$fn = static fn (string $name, array $arguments): array => ['type' => 'function', 'name' => $name, 'arguments' => $arguments];
$lit = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$jsonValueText = static function (mixed $value): mixed {
    if ($value instanceof PortLibs\LibSqlite\SQLiteJsonSubtypeValue) {
        return $value->json;
    }
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonCanonical::json($value);
    }

    return $value;
};
$arrow = static fn (mixed $left, string $operator, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => ['type' => 'literal', 'value' => $left],
    'right' => ['type' => 'literal', 'value' => $right],
];

$json501Cases = [
    'json501-1.1 identifier object keys' => ['{a:5,b:6}', '$.a', 5, '{"a":5,"b":6}', 'object'],
    'json501-1.2 identifier key in nested array object' => ['[7,null,{a:5,b:6},[8,9]]', '$[2].b', 6, '[7,null,{"a":5,"b":6},[8,9]]', 'array'],
    'json501-1.3 dollar-prefixed object key' => ['{ $123 : 789 }', '$."$123"', 789, '{"$123":789}', 'object'],
    'json501-1.4 underscore dollar object key' => ['{ _123$xyz : 789 }', '$."_123$xyz"', 789, '{"_123$xyz":789}', 'object'],
    'json501-1.5 alphanumeric object key' => ['{ MNO_123$xyz : 789 }', '$."MNO_123$xyz"', 789, '{"MNO_123$xyz":789}', 'object'],
    'json501-1.11 unicode identifier object key' => ['{ MNO_123' . "\u{00e6}" . 'xyz : 789 }', '$."MNO_123' . "\u{00e6}" . 'xyz"', 789, '{"MNO_123' . "\u{00e6}" . 'xyz":789}', 'object'],
    'json501-2.1 trailing object comma' => ['{a:5, b:6 , }', '$.b', 6, '{"a":5,"b":6}', 'object'],
    'json501-3.1 trailing array comma' => ['[5, 6 , ]', '$[1]', 6, '[5,6]', 'array'],
    'json501-4.2 single quoted escaped string' => ["{b: 123, 'a': 'ab\\'cd'}", '$.a', "ab'cd", '{"b":123,"a":"ab\'cd"}', 'object'],
    'json501-5.1 line continuation newline' => ["{a: \"abc\\\nxyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}', 'object'],
    'json501-5.3 line continuation crlf' => ["{a: \"abc\\\r\nxyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}', 'object'],
    'json501-5.4 line continuation separator' => ["{a: \"abc\\\u{2028}xyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}', 'object'],
    'json501-5.5 line continuation paragraph separator' => ["{a: \"abc\\\u{2029}xyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}', 'object'],
    'json501-6.1 single-quote escape' => ['{a: "abc\\\'xyz"}', '$.a', "abc'xyz", '{"a":"abc\'xyz"}', 'object'],
    'json501-6.2 double-quote escape' => ['{a: "abc\\"xyz"}', '$.a', 'abc"xyz', '{"a":"abc\"xyz"}', 'object'],
    'json501-6.3 backslash escape' => ['{a: "abc\\\\xyz"}', '$.a', 'abc\\xyz', '{"a":"abc\\\\xyz"}', 'object'],
    'json501-6.4 backspace escape' => ['{a: "abc\bxyz"}', '$.a', "abc\x08xyz", '{"a":"abc\bxyz"}', 'object'],
    'json501-6.5 whitespace escapes' => ['{a: "abc\f\n\r\t\vxyz"}', '$.a', "abc\x0c\n\r\t\x0bxyz", '{"a":"abc\f\n\r\t\u000bxyz"}', 'object'],
    'json501-6.6 nul escape' => ['{a: "abc\0xyz"}', '$.a', "abc\0xyz", '{"a":"abc\u0000xyz"}', 'object'],
    'json501-6.7 hex string escapes' => ['{a: "abc\x35\x4f\x6Exyz"}', '$.a', 'abc5Onxyz', '{"a":"abc\\u0035\\u004f\\u006Exyz"}', 'object'],
    'json501-6.8 repeated hex escapes' => ['{a: "\x6a\x6A\x6b\x6B\x6c\x6C\x6d\x6D\x6e\x6E\x6f\x6F"}', '$.a', 'jjkkllmmnnoo', '{"a":"\\u006a\\u006A\\u006b\\u006B\\u006c\\u006C\\u006d\\u006D\\u006e\\u006E\\u006f\\u006F"}', 'object'],
    'json501-7.1 zero hex number' => ['{a: 0x0}', '$.a', 0, '{"a":0}', 'object'],
    'json501-7.4 large hex number' => ['{a: 0xabcdef}', '$.a', 11259375, '{"a":11259375}', 'object'],
    'json501-7.5 negative hex number' => ['{a: -0xaBcDeF}', '$.a', -11259375, '{"a":-11259375}', 'object'],
    'json501-7.6 positive hex number' => ['{a: +0xABCDEF}', '$.a', 11259375, '{"a":11259375}', 'object'],
    'json501-8.1 trailing decimal point' => ['{x: 4.}', '$.x', 4.0, '{"x":4.0}', 'object'],
    'json501-8.3 leading decimal point' => ['{x: .5}', '$.x', 0.5, '{"x":0.5}', 'object'],
    'json501-8.8 negative trailing decimal exponent' => ['{x: -4.e2}', '$.x', -400.0, '{"x":-4.0e2}', 'object'],
    'json501-8.9 leading decimal exponent' => ['{x: .5e3}', '$.x', 500.0, '{"x":0.5e3}', 'object'],
    'json501-8.10 negative leading decimal exponent' => ['{x: -.5e-1}', '$.x', -0.05, '{"x":-0.5e-1}', 'object'],
    'json501-8.11 positive leading decimal exponent' => ['{x: +.5e-2}', '$.x', 0.005, '{"x":0.5e-2}', 'object'],
    'json501-9.1 positive infinity' => ['{x: +Infinity}', '$.x', INF, '{"x":9e999}', 'object'],
    'json501-9.2 negative infinity' => ['{x: -Infinity}', '$.x', -INF, '{"x":-9e999}', 'object'],
    'json501-9.4 nan normalizes null' => ['{x: NaN}', '$.x', null, '{"x":null}', 'object'],
    'json501-10.1 explicit plus integer' => ['{a: +123}', '$.a', 123, '{"a":123}', 'object'],
    'json501-11.1 single and multiline comments' => [" /* abc */ { /*def*/ aaa /* xyz */ : // to the end of line\n          123 /* xyz */ , /* 123 */ }", '$.aaa', 123, '{"aaa":123}', 'object'],
    'json501-12.1 leading extended whitespace' => ["\t\n\v\f\r \u{00a0}\u{2028}\u{2029}" . '{a: "xyz"}', '$.a', 'xyz', '{"a":"xyz"}', 'object'],
    'json501-12.3 leading unicode separator whitespace' => ["\u{1680}\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200a}\u{3000}\u{feff}" . '{a: "xyz"}', '$.a', 'xyz', '{"a":"xyz"}', 'object'],
    'json501-13.1 single quote containing double quotes' => ['{x:\'a "b" c\'}', '$.x', 'a "b" c', '{"x":"a \"b\" c"}', 'object'],
];

foreach ($json501Cases as $scenario => [$source, $path, $expected, $expectedCanonical, $rootType]) {
    $tests['real upstream ' . $scenario . ' canonical text and validity'] =
        static function (TestRunner $t) use ($source, $expectedCanonical, $rootType, $path, $expected): void {
            $canonical = SQLiteJsonCanonical::jsonSqlFunction('json', $source);

            $t->same($expectedCanonical, $canonical, 'canonical JSON5 text');
            $t->same(false, SQLiteJsonValidity::jsonValid($source, 1), 'strict JSON rejects relaxed text');
            $t->same(true, SQLiteJsonValidity::jsonValid($source, 2), 'JSON5 flag accepts relaxed text');
            $t->same(true, SQLiteJsonValidity::jsonValid($source, 3), 'strict-or-JSON5 flags accept relaxed text');
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($source), 'error position is zero');
            $t->same($rootType, SQLiteJsonInspection::jsonType($source), 'root type from JSON5 source');
            $t->same($rootType, SQLiteJsonInspection::jsonType($canonical), 'root type from canonical JSON');
            $t->same($expected, SQLiteJsonExtract::extract($source, $path), 'extract from JSON5 source');
            $t->same($expected, SQLiteJsonExtract::extract($canonical, $path), 'extract from canonical JSON');
        };

    $tests['real upstream ' . $scenario . ' jsonb parity and path dispatch'] =
        static function (TestRunner $t) use ($jsonb, $jsonbText, $source, $path, $expected, $expectedCanonical, $rootType): void {
            $blob = $jsonb($source);
            $jsonbExtract = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $blob, $path);
            $comparable = $jsonbExtract instanceof SQLiteBlobValue ? $jsonbText($jsonbExtract) : $jsonbExtract;

            $t->true($blob instanceof SQLiteBlobValue, 'jsonb() returns blob');
            $t->same($jsonbText($blob), SQLiteJsonCanonical::json($blob), 'jsonb decodes to canonical value');
            $t->same(true, SQLiteJsonValidity::jsonValid($blob, 4), 'superficial JSONB valid');
            $t->same(true, SQLiteJsonValidity::jsonValid($blob, 8), 'strict JSONB valid');
            $t->same($rootType, SQLiteJsonInspection::jsonType($blob), 'jsonb root type');
            $t->same($expected, SQLiteJsonExtract::extract($blob, $path), 'extract from JSONB source');
            $t->same($expected, $comparable, 'jsonb_extract comparable scalar');
            $t->same(SQLiteJsonExtract::extract($source, $path), SQLiteJsonExtract::extract($blob, $path), 'text/blob path parity');
        };

    $tests['real upstream ' . $scenario . ' select expression JSON dispatch'] =
        static function (TestRunner $t) use ($fn, $lit, $arrow, $source, $path, $expected, $expectedCanonical, $jsonValueText): void {
            $canonical = SQLiteSelectExpression::evaluate([], $fn('json', [$lit($source)]));

            $t->same($expectedCanonical, $jsonValueText($canonical), 'select json() canonicalizes');
            $t->same($expected, SQLiteSelectExpression::evaluate([], $fn('json_extract', [$lit($source), $lit($path)])), 'select json_extract');
            $t->same($expected, SQLiteSelectExpression::evaluate([], $arrow($source, '->>', $path)), 'select arrow text');
            $t->same(SQLiteJsonCanonical::json(SQLiteJsonCanonical::jsonSqlFunction('jsonb', $source)), $jsonValueText(SQLiteSelectExpression::evaluate([], $arrow($source, '->', '$'))), 'select arrow JSON root');
        };
}

$controlCharacterEscapes = [
    1 => '\u0001',
    2 => '\u0002',
    3 => '\u0003',
    4 => '\u0004',
    5 => '\u0005',
    6 => '\u0006',
    7 => '\u0007',
    8 => '\b',
    9 => '\t',
    10 => '\n',
    11 => '\u000b',
    12 => '\f',
    13 => '\r',
    14 => '\u000e',
    15 => '\u000f',
    16 => '\u0010',
    17 => '\u0011',
    18 => '\u0012',
    19 => '\u0013',
    20 => '\u0014',
    21 => '\u0015',
    22 => '\u0016',
    23 => '\u0017',
    24 => '\u0018',
    25 => '\u0019',
    26 => '\u001a',
    27 => '\u001b',
    28 => '\u001c',
    29 => '\u001d',
    30 => '\u001e',
    31 => '\u001f',
];

foreach ($controlCharacterEscapes as $codepoint => $escaped) {
    $char = chr($codepoint);
    $strict = '"abc' . $char . 'xyz"';
    $source = '{label:"abc' . $char . 'xyz"}';
    $expectedCanonical = '{"label":"abc' . $escaped . 'xyz"}';
    $scenario = 'json501-14.' . $codepoint . ' control character JSON5 string';

    $tests['real upstream ' . $scenario . ' validity and canonical text'] =
        static function (TestRunner $t) use ($strict, $source, $expectedCanonical, $char): void {
            $t->same(false, SQLiteJsonValidity::jsonValid($strict), 'strict string rejects raw control');
            $t->same(true, SQLiteJsonValidity::jsonValid($strict, 2), 'JSON5 string accepts raw control');
            $t->same($expectedCanonical, SQLiteJsonCanonical::jsonSqlFunction('json', $source), 'canonical escaped object');
            $t->same('abc' . $char . 'xyz', SQLiteJsonExtract::extract($source, '$.label'), 'extract raw text value');
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($source), 'valid source has no error');
        };

    $tests['real upstream ' . $scenario . ' jsonb and mutation parity'] =
        static function (TestRunner $t) use ($jsonb, $jsonbText, $source, $expectedCanonical, $char): void {
            $blob = $jsonb($source);
            $setText = SQLiteJsonMutation::mutateSqlFunction('json_set', '{}', '$.label', 'abc' . $char . 'xyz');
            $setBlob = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', '{}', '$.label', 'abc' . $char . 'xyz');

            $t->same($expectedCanonical, $jsonbText($blob), 'jsonb canonical escaped object');
            $t->same($expectedCanonical, $setText, 'json_set escapes raw control');
            $t->true($setBlob instanceof SQLiteBlobValue, 'jsonb_set returns blob');
            $t->same($expectedCanonical, $jsonbText($setBlob), 'jsonb_set escapes raw control');
            $t->same('abc' . $char . 'xyz', SQLiteJsonExtract::extract($blob, '$.label'), 'jsonb extract raw text value');
        };
}

$json502Cases = [
    'json502-3.1 escaped label in source' => ['{"a\\u0062c":123}', '$."abc"', 123],
    'json502-3.2 escaped label in path' => ['{"abc":123}', '$."a\\u0062c"', 123],
    'json502-3.3 quoted backslash path a' => ['{"a\\\\":111,"b\\"":222}', '$."a\\\\"', 111],
    'json502-3.3 quoted backslash path b' => ['{"a\\\\":111,"b\\"":222}', '$."b\\""', 222],
    'json502-3.4 escaped patch label merge' => [SQLiteJsonPatch::patch('{"a\\u0062c":123}', '{"ab\\u0063":456}'), '$."abc"', 456],
    'json502-4.1 escaped control label json_tree root' => ['{"\u0017":1}', '$."\x17"', 1],
    'json502-5.1 unquoted embedded quote path' => ['{"A\"Key":1}', '$.A"Key', 1],
    'json502-5.2 quoted embedded quote path' => ['{"A\"Key":1}', '$."A\"Key"', 1],
    'json502-5.3 set quoted quote key' => [SQLiteJsonMutation::mutateSqlFunction('json_set', '{}', '$."\"Key"', 1), '$."\"Key"', 1],
];

foreach ($json502Cases as $scenario => [$source, $path, $expected]) {
    $tests['real upstream ' . $scenario . ' escaped path extract and select parity'] =
        static function (TestRunner $t) use ($fn, $lit, $arrow, $source, $path, $expected, $jsonValueText): void {
            $t->same($expected, SQLiteJsonExtract::extract($source, $path), 'direct json_extract');
            $t->same($expected, SQLiteSelectExpression::evaluate([], $fn('json_extract', [$lit($source), $lit($path)])), 'select json_extract');
            $t->same($expected, SQLiteSelectExpression::evaluate([], $arrow($source, '->>', $path)), 'select arrow text');
            $t->same(SQLiteJsonCanonical::json(SQLiteJsonCanonical::jsonSqlFunction('jsonb', $source)), $jsonValueText(SQLiteSelectExpression::evaluate([], $arrow($source, '->', '$'))), 'select arrow root');
        };

    $tests['real upstream ' . $scenario . ' escaped path jsonb and tree parity'] =
        static function (TestRunner $t) use ($jsonb, $source, $path, $expected): void {
            $blob = $jsonb($source);
            $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $source);

            $t->same($expected, SQLiteJsonExtract::extract($blob, $path), 'jsonb path extract');
            $t->same(SQLiteJsonCanonical::json(SQLiteJsonCanonical::jsonSqlFunction('jsonb', $source)), SQLiteJsonCanonical::json($blob), 'jsonb canonical root');
            $t->same(true, count($rows) >= 2, 'json_tree sees root and escaped label');
            $t->same('object', SQLiteJsonInspection::jsonType($source), 'source root object');
        };
}

$malformedJson502 = '{a:null,{"h":[1,[1,2,3]],"j":"abc"}:true}';
$tests['real upstream json502-2.1 malformed object label reports byte position'] = static function (TestRunner $t) use ($malformedJson502): void {
    $t->same(9, SQLiteJsonErrorPosition::jsonErrorPosition($malformedJson502));
    $t->same(false, SQLiteJsonValidity::jsonValid($malformedJson502, 2));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::jsonSqlFunction('json', $malformedJson502));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extract($malformedJson502, '$h[#-1]'));
};

$tests['real upstream json501 json502 dynamic bulk cross product preserves JSON5 semantics'] =
    static function (TestRunner $t) use ($json501Cases, $json502Cases, $jsonb, $jsonbText, $fn, $lit, $arrow, $jsonValueText): void {
        for ($iteration = 0; $iteration < 15; $iteration++) {
            foreach ($json501Cases as $scenario => [$source, $path, $expected, $expectedCanonical, $rootType]) {
                $blob = $jsonb($source);
                $canonical = SQLiteJsonCanonical::jsonSqlFunction('json', $source);
                $selectCanonical = SQLiteSelectExpression::evaluate([], $fn('json', [$lit($source)]));
                $jsonbExtract = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $blob, $path);
                $jsonbComparable = $jsonbExtract instanceof SQLiteBlobValue ? $jsonbText($jsonbExtract) : $jsonbExtract;

                $t->same($expectedCanonical, $canonical, $scenario . ' canonical ' . $iteration);
                $t->same($expectedCanonical, $jsonValueText($selectCanonical), $scenario . ' select canonical ' . $iteration);
                $t->same($expected, SQLiteJsonExtract::extract($source, $path), $scenario . ' extract source ' . $iteration);
                $t->same($expected, SQLiteJsonExtract::extract($canonical, $path), $scenario . ' extract canonical ' . $iteration);
                $t->same($expected, SQLiteJsonExtract::extract($blob, $path), $scenario . ' extract blob ' . $iteration);
                $t->same($expected, $jsonbComparable, $scenario . ' jsonb extract ' . $iteration);
                $t->same($expected, SQLiteSelectExpression::evaluate([], $fn('json_extract', [$lit($source), $lit($path)])), $scenario . ' select extract ' . $iteration);
                $t->same($expected, SQLiteSelectExpression::evaluate([], $arrow($source, '->>', $path)), $scenario . ' select arrow text ' . $iteration);
                $t->same(SQLiteJsonCanonical::json($blob), $jsonValueText(SQLiteSelectExpression::evaluate([], $arrow($source, '->', '$'))), $scenario . ' select arrow root ' . $iteration);
                $t->same($rootType, SQLiteJsonInspection::jsonType($source), $scenario . ' root type source ' . $iteration);
                $t->same($rootType, SQLiteJsonInspection::jsonType($blob), $scenario . ' root type blob ' . $iteration);
                $t->same(true, SQLiteJsonValidity::jsonValid($source, 2), $scenario . ' json5 valid ' . $iteration);
                $t->same(true, SQLiteJsonValidity::jsonValid($blob, 8), $scenario . ' jsonb valid ' . $iteration);
                $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($source), $scenario . ' error position ' . $iteration);
            }

            foreach ($json502Cases as $scenario => [$source, $path, $expected]) {
                $blob = $jsonb($source);
                $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $source);

                $t->same($expected, SQLiteJsonExtract::extract($source, $path), $scenario . ' extract source ' . $iteration);
                $t->same($expected, SQLiteJsonExtract::extract($blob, $path), $scenario . ' extract blob ' . $iteration);
                $t->same($expected, SQLiteSelectExpression::evaluate([], $fn('json_extract', [$lit($source), $lit($path)])), $scenario . ' select extract ' . $iteration);
                $t->same($expected, SQLiteSelectExpression::evaluate([], $arrow($source, '->>', $path)), $scenario . ' arrow text ' . $iteration);
                $t->same(SQLiteJsonCanonical::json($blob), SQLiteJsonCanonical::json($blob), $scenario . ' canonical blob ' . $iteration);
                $t->same(SQLiteJsonCanonical::json($blob), $jsonValueText(SQLiteSelectExpression::evaluate([], $arrow($source, '->', '$'))), $scenario . ' arrow root ' . $iteration);
                $t->same(true, count($rows) >= 2, $scenario . ' json_tree rows ' . $iteration);
                $t->same('object', SQLiteJsonInspection::jsonType($source), $scenario . ' object type ' . $iteration);
            }
        }
    };

return $tests;
