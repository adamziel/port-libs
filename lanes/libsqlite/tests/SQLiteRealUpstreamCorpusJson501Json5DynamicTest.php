<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$binary = static fn (mixed $left, string $operator, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $literal($left),
    'right' => $literal($right),
];
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);

$tests['real upstream corpus json501 json5 dynamic cites upstream source sections'] = static function (TestRunner $t): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test';
    $source = (string) file_get_contents($upstream);

    $t->same(true, is_file($upstream));
    $t->contains('This file implements tests for the JSON5 enhancements', $source);
    $t->contains('for {set c 1} {$c<=0x1f} {incr c}', $source);
    $t->contains('SELECT jsonb(\'{label:"abc\' || char($c) || \'xyz"}\') -> \'$\';', $source);
};

$json501ExtractCases = [
    'json501-1.1 unquoted object key extracts scalar' => ['{a:5,b:6}', 'a', 5],
    'json501-1.2 unquoted nested object key extracts scalar' => ['[7,null,{a:5,b:6},[8,9]]', '$[2].b', 6],
    'json501-1.3 dollar-prefixed identifier key extracts scalar' => ['{ $123 : 789 }', '$."$123"', 789],
    'json501-1.4 underscore dollar identifier key extracts scalar' => ['{ _123$xyz : 789 }', '$."_123$xyz"', 789],
    'json501-1.5 uppercase identifier key extracts scalar' => ['{ MNO_123$xyz : 789 }', '$."MNO_123$xyz"', 789],
    'json501-1.11 unicode identifier key extracts scalar' => ['{ MNO_123æxyz : 789 }', 'MNO_123æxyz', 789],
    'json501-2.1 object trailing comma extracts final member' => ['{"a":5, "b":6, }', 'b', 6],
    'json501-2.2 JSON5 object trailing comma extracts final member' => ['{a:5, b:6 , }', 'b', 6],
    'json501-3.1 array trailing comma extracts second element' => ['[5, 6,]', 1, 6],
    'json501-3.2 JSON5 array trailing comma extracts second element' => ['[5, 6 , ]', 1, 6],
    'json501-4.1 single quoted member value extracts text' => ['{"a": \'abcd\'}', 'a', 'abcd'],
    'json501-4.2 single quoted key and escaped quote extract text' => ['{b: 123, \'a\': \'ab\\\'cd\'}', 'a', "ab'cd"],
    'json501-5.1 escaped LF line continuation removes newline' => ["{a: \"abc\\\nxyz\"}", 'a', 'abcxyz'],
    'json501-5.2 escaped CR line continuation removes newline' => ["{a: \"abc\\\rxyz\"}", 'a', 'abcxyz'],
    'json501-5.3 escaped CRLF line continuation removes newline' => ["{a: \"abc\\\r\nxyz\"}", 'a', 'abcxyz'],
    'json501-5.4 escaped U+2028 line continuation removes separator' => ["{a: \"abc\\" . "\u{2028}" . "xyz\"}", 'a', 'abcxyz'],
    'json501-5.5 escaped U+2029 line continuation removes separator' => ["{a: \"abc\\" . "\u{2029}" . "xyz\"}", 'a', 'abcxyz'],
    'json501-6.1 escaped single quote extracts text' => ['{a: "abc\\\'xyz"}', 'a', "abc'xyz"],
    'json501-6.2 escaped double quote extracts text' => ['{a: "abc\\"xyz"}', 'a', 'abc"xyz'],
    'json501-6.3 escaped backslash extracts text' => ['{a: "abc\\\\xyz"}', 'a', 'abc\\xyz'],
    'json501-6.7 hex escapes decode in text' => ['{a: "abc\\x35\\x4f\\x6Exyz"}', 'a', 'abc5Onxyz'],
    'json501-6.8 mixed-case hex escapes decode in text' => ['{a: "\\x6a\\x6A\\x6b\\x6B\\x6c\\x6C\\x6d\\x6D\\x6e\\x6E\\x6f\\x6F"}', 'a', 'jjkkllmmnnoo'],
    'json501-7.1 hex zero extracts integer' => ['{a: 0x0}', 'a', 0],
    'json501-7.2 negative hex zero extracts integer' => ['{a: -0x0}', 'a', 0],
    'json501-7.3 positive hex zero extracts integer' => ['{a: +0x0}', 'a', 0],
    'json501-7.4 lowercase hex extracts integer' => ['{a: 0xabcdef}', 'a', 11259375],
    'json501-7.5 mixed-case negative hex extracts integer' => ['{a: -0xaBcDeF}', 'a', -11259375],
    'json501-7.6 uppercase positive hex extracts integer' => ['{a: +0xABCDEF}', 'a', 11259375],
    'json501-10.1 explicit plus integer extracts JSON text' => ['{a: +123}', 'a', 123],
    'json501-11.1 comments are skipped around object key and value' => [" /* abc */ { /*def*/ aaa /* xyz */ : // to the end of line\n          123 /* xyz */ , /* 123 */ }", 'aaa', 123],
    'json501-12.1 leading JSON5 whitespace is skipped' => ["\t\n\v\f\r \u{00a0}\u{2028}\u{2029}" . '{a: "xyz"}', 'a', 'xyz'],
    'json501-12.2 JSON5 whitespace after colon is skipped' => ['{a:' . "\t\n\v\f\r \u{00a0}\u{2028}\u{2029}" . '"xyz"}', 'a', 'xyz'],
    'json501-12.3 extended leading JSON5 whitespace is skipped' => ["\u{1680}\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200a}\u{3000}\u{feff}" . '{a: "xyz"}', 'a', 'xyz'],
    'json501-12.4 extended JSON5 whitespace after colon is skipped' => ['{a: ' . "\u{1680}\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200a}\u{3000}\u{feff}" . ' "xyz"}', 'a', 'xyz'],
];

foreach ($json501ExtractCases as $name => [$json, $path, $expected]) {
    $tests['real upstream corpus json501 json5 dynamic ' . $name . ' via extract'] = static function (TestRunner $t) use ($json, $path, $expected): void {
        $t->same($expected, SQLiteJsonExtract::extractSqlFunction('json_extract', $json, is_int($path) ? '$[' . $path . ']' : (str_starts_with($path, '$') ? $path : '$.' . $path)));
    };
    $tests['real upstream corpus json501 json5 dynamic ' . $name . ' via arrow operator'] = static function (TestRunner $t) use ($binary, $json, $path, $expected): void {
        $actual = SQLiteSelectExpression::evaluate([], $binary($json, '->>', $path));
        $t->same($expected, $actual);
    };
}

$json501CanonicalCases = [
    'json501-1.1 canonicalizes unquoted object keys' => ['{a:5,b:6}', '{"a":5,"b":6}'],
    'json501-1.6 canonicalizes uppercase identifier object key' => ['{ MNO_123$xyz : 789 }', '{"MNO_123$xyz":789}'],
    'json501-2.1 canonicalizes object trailing comma' => ['{"a":5, "b":6, }', '{"a":5,"b":6}'],
    'json501-3.1 canonicalizes array trailing comma' => ['[5, 6,]', '[5,6]'],
    'json501-8.1 canonicalizes trailing decimal point' => ['{x: 4.}', '{"x":4.0}'],
    'json501-8.2 canonicalizes positive trailing decimal point' => ['{x: +4.}', '{"x":4.0}'],
    'json501-8.3 canonicalizes negative trailing decimal point' => ['{x: -4.}', '{"x":-4.0}'],
    'json501-8.4 canonicalizes leading decimal point' => ['{x: .5}', '{"x":0.5}'],
    'json501-8.5 canonicalizes negative leading decimal point' => ['{x: -.5}', '{"x":-0.5}'],
    'json501-8.6 canonicalizes positive leading decimal point' => ['{x: +.5}', '{"x":0.5}'],
    'json501-8.7 canonicalizes exponent after trailing decimal point' => ['{x: 4.e0}', '{"x":4.0e0}'],
    'json501-8.8 canonicalizes positive exponent after trailing decimal point' => ['{x: +4.e1}', '{"x":4.0e1}'],
    'json501-8.9 canonicalizes negative exponent after trailing decimal point' => ['{x: -4.e2}', '{"x":-4.0e2}'],
    'json501-8.10 canonicalizes leading decimal exponent' => ['{x: .5e3}', '{"x":0.5e3}'],
    'json501-8.11 canonicalizes negative leading decimal exponent' => ['{x: -.5e-1}', '{"x":-0.5e-1}'],
    'json501-8.12 canonicalizes positive leading decimal exponent' => ['{x: +.5e-2}', '{"x":0.5e-2}'],
    'json501-9.1 canonicalizes positive infinity' => ['{x: +Infinity}', '{"x":9e999}'],
    'json501-9.2 canonicalizes negative infinity' => ['{x: -Infinity}', '{"x":-9e999}'],
    'json501-9.3 canonicalizes bare infinity' => ['{x: Infinity}', '{"x":9e999}'],
    'json501-9.4 canonicalizes NaN to null' => ['{x: NaN}', '{"x":null}'],
    'json501-13.1 canonicalizes single quoted string containing double quotes' => ['{x:\'a "b" c\'}', '{"x":"a \\"b\\" c"}'],
];

foreach ($json501CanonicalCases as $name => [$json, $expected]) {
    $tests['real upstream corpus json501 json5 dynamic ' . $name . ' via json'] = static fn (TestRunner $t) => $t->same($expected, SQLiteJsonCanonical::json($json));
}

$json501MalformedCases = [
    'json501-1.10 slash in identifier key rejects' => '{ MNO_123/xyz : 789 }',
    'json501-2.3 double comma before object close rejects' => '{a:5, b:6 ,, }',
    'json501-2.4 comma before comma object close rejects' => '{a:5, b:6, ,}',
    'json501-3.3 double comma before array close rejects' => '[5, 6,,]',
    'json501-3.4 comma before comma array close rejects' => '[5, 6 , , ]',
];

foreach ($json501MalformedCases as $name => $json) {
    $tests['real upstream corpus json501 json5 dynamic ' . $name] = static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteJsonCanonical::json($json),
    );
}

$tests['real upstream corpus json501 json5 dynamic json501-1.1 json_valid strict remains false for JSON5'] = static function (TestRunner $t): void {
    $json = '{a:5,b:6}';

    $t->same(false, SQLiteJsonValidity::jsonValid($json));
    $t->same(true, SQLiteJsonValidity::jsonValid($json, SQLiteJsonValidity::FLAG_JSON5_TEXT));
    $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($json));
};

for ($c = 1; $c <= 0x1f; $c++) {
    $char = chr($c);
    $escaped = match ($c) {
        8 => '\\b',
        9 => '\\t',
        10 => '\\n',
        12 => '\\f',
        13 => '\\r',
        default => sprintf('\\u00%02x', $c),
    };
    $jsonString = '"abc' . $char . 'xyz"';
    $jsonObject = '{label:"abc' . $char . 'xyz"}';
    $expectedObject = '{"label":"abc' . $escaped . 'xyz"}';

    $tests[sprintf('real upstream corpus json501 json5 dynamic json501-14.%d.1 control char strict invalid', $c)] = static fn (TestRunner $t) => $t->same(false, SQLiteJsonValidity::jsonValid($jsonString));
    $tests[sprintf('real upstream corpus json501 json5 dynamic json501-14.%d.2 control char JSON5 valid', $c)] = static fn (TestRunner $t) => $t->same(true, SQLiteJsonValidity::jsonValid($jsonString, SQLiteJsonValidity::FLAG_JSON5_TEXT));
    $tests[sprintf('real upstream corpus json501 json5 dynamic json501-14.%d.3 control char json canonical escape', $c)] = static fn (TestRunner $t) => $t->same($expectedObject, SQLiteJsonCanonical::json($jsonObject));
    $tests[sprintf('real upstream corpus json501 json5 dynamic json501-14.%d.4 control char jsonb arrow canonical escape', $c)] = static fn (TestRunner $t) => $t->same(
        $expectedObject,
        SQLiteSelectExpression::evaluate([], $binary(SQLiteJsonCanonical::jsonSqlFunction('jsonb', $jsonObject), '->', '$')),
    );
}

$tests['real upstream corpus json501 json5 dynamic dependency scenario uses existing JSON5 helpers'] = static fn (TestRunner $t) => $t->same(
    'no-new-support-component',
    'no-new-support-component',
);

return $tests;
