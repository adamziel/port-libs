<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$jsonbText = static function (SQLiteBlobValue $value): string {
    return SQLiteJsonCanonical::json($value);
};

$validCases = [
    'json501-1.1 unquoted identifiers' => ['{a:5,b:6}', '$.a', 5, '{"a":5,"b":6}'],
    'json501-1.2 nested unquoted identifier' => ['[7,null,{a:5,b:6},[8,9]]', '$[2].b', 6, '[7,null,{"a":5,"b":6},[8,9]]'],
    'json501-1.3 dollar identifier key' => ['{ $123 : 789 }', '$."$123"', 789, '{"$123":789}'],
    'json501-1.4 underscore dollar identifier key' => ['{ _123$xyz : 789 }', '$."_123$xyz"', 789, '{"_123$xyz":789}'],
    'json501-1.5 alpha numeric identifier key' => ['{ MNO_123$xyz : 789 }', '$."MNO_123$xyz"', 789, '{"MNO_123$xyz":789}'],
    'json501-1.11 unicode identifier key' => ['{ MNO_123æxyz : 789 }', '$."MNO_123æxyz"', 789, '{"MNO_123æxyz":789}'],
    'json501-2.1 object trailing comma' => ['{"a":5, "b":6, }', '$.b', 6, '{"a":5,"b":6}'],
    'json501-2.2 object identifier trailing comma' => ['{a:5, b:6 , }', '$.b', 6, '{"a":5,"b":6}'],
    'json501-3.1 array trailing comma' => ['[5, 6,]', '$[1]', 6, '[5,6]'],
    'json501-3.2 array whitespace trailing comma' => ['[5, 6 , ]', '$[1]', 6, '[5,6]'],
    'json501-4.1 single quoted value' => ['{"a": \'abcd\'}', '$.a', 'abcd', '{"a":"abcd"}'],
    'json501-4.2 quoted identifier and escaped quote' => ['{b: 123, \'a\': \'ab\\\'cd\'}', '$.a', "ab'cd", '{"b":123,"a":"ab\'cd"}'],
    'json501-5.1 escaped line feed continuation' => ["{a: \"abc\\\nxyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}'],
    'json501-5.2 escaped carriage return continuation' => ["{a: \"abc\\\rxyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}'],
    'json501-5.3 escaped crlf continuation' => ["{a: \"abc\\\r\nxyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}'],
    'json501-5.4 escaped line separator continuation' => ["{a: \"abc\\\u{2028}xyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}'],
    'json501-5.5 escaped paragraph separator continuation' => ["{a: \"abc\\\u{2029}xyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}'],
    'json501-6.1 escaped single quote' => ['{a: "abc\\\'xyz"}', '$.a', "abc'xyz", '{"a":"abc\'xyz"}'],
    'json501-6.2 escaped double quote' => ['{a: "abc\\"xyz"}', '$.a', 'abc"xyz', '{"a":"abc\"xyz"}'],
    'json501-6.3 escaped backslash' => ['{a: "abc\\\\xyz"}', '$.a', 'abc\\xyz', '{"a":"abc\\\\xyz"}'],
    'json501-6.4 backspace escape' => ['{a: "abc\\bxyz"}', '$.a', "abc\x08xyz", '{"a":"abc\bxyz"}'],
    'json501-6.5 control escape group' => ['{a: "abc\\f\\n\\r\\t\\vxyz"}', '$.a', "abc\f\n\r\t\x0bxyz", '{"a":"abc\f\n\r\t\u000bxyz"}'],
    'json501-6.6 nul escape' => ['{a: "abc\\0xyz"}', '$.a', "abc\0xyz", '{"a":"abc\u0000xyz"}'],
    'json501-6.7 hex escapes' => ['{a: "abc\\x35\\x4f\\x6Exyz"}', '$.a', 'abc5Onxyz', '{"a":"abc\\u0035\\u004f\\u006Exyz"}'],
    'json501-6.8 repeated hex escapes' => ['{a: "\\x6a\\x6A\\x6b\\x6B\\x6c\\x6C\\x6d\\x6D\\x6e\\x6E\\x6f\\x6F"}', '$.a', 'jjkkllmmnnoo', '{"a":"\\u006a\\u006A\\u006b\\u006B\\u006c\\u006C\\u006d\\u006D\\u006e\\u006E\\u006f\\u006F"}'],
    'json501-7.1 hex zero' => ['{a: 0x0}', '$.a', 0, '{"a":0}'],
    'json501-7.2 negative hex zero' => ['{a: -0x0}', '$.a', 0, '{"a":0}'],
    'json501-7.3 positive hex zero' => ['{a: +0x0}', '$.a', 0, '{"a":0}'],
    'json501-7.4 hex integer' => ['{a: 0xabcdef}', '$.a', 11259375, '{"a":11259375}'],
    'json501-7.5 negative mixed-case hex integer' => ['{a: -0xaBcDeF}', '$.a', -11259375, '{"a":-11259375}'],
    'json501-7.6 positive mixed-case hex integer' => ['{a: +0xABCDEF}', '$.a', 11259375, '{"a":11259375}'],
    'json501-8.1 trailing decimal point' => ['{x: 4.}', '$.x', 4.0, '{"x":4.0}'],
    'json501-8.2 positive trailing decimal point' => ['{x: +4.}', '$.x', 4.0, '{"x":4.0}'],
    'json501-8.3 negative trailing decimal point' => ['{x: -4.}', '$.x', -4.0, '{"x":-4.0}'],
    'json501-8.4 leading decimal point' => ['{x: .5}', '$.x', 0.5, '{"x":0.5}'],
    'json501-8.5 negative leading decimal point' => ['{x: -.5}', '$.x', -0.5, '{"x":-0.5}'],
    'json501-8.6 positive leading decimal point' => ['{x: +.5}', '$.x', 0.5, '{"x":0.5}'],
    'json501-8.7 decimal exponent' => ['{x: 4.e0}', '$.x', 4.0, '{"x":4.0e0}'],
    'json501-8.8 positive decimal exponent' => ['{x: +4.e1}', '$.x', 40.0, '{"x":4.0e1}'],
    'json501-8.9 negative decimal exponent' => ['{x: -4.e2}', '$.x', -400.0, '{"x":-4.0e2}'],
    'json501-8.10 leading decimal exponent' => ['{x: .5e3}', '$.x', 500.0, '{"x":0.5e3}'],
    'json501-8.11 negative leading decimal exponent' => ['{x: -.5e-1}', '$.x', -0.05, '{"x":-0.5e-1}'],
    'json501-8.12 positive leading decimal exponent' => ['{x: +.5e-2}', '$.x', 0.005, '{"x":0.5e-2}'],
    'json501-9.1 positive infinity' => ['{x: +Infinity}', '$.x', INF, '{"x":9e999}'],
    'json501-9.2 negative infinity' => ['{x: -Infinity}', '$.x', -INF, '{"x":-9e999}'],
    'json501-9.3 bare infinity' => ['{x: Infinity}', '$.x', INF, '{"x":9e999}'],
    'json501-9.4 nan maps to null' => ['{x: NaN}', '$.x', null, '{"x":null}'],
    'json501-10.1 explicit plus integer' => ['{a: +123}', '$.a', 123, '{"a":123}'],
    'json501-11.1 line and block comments' => [" /* abc */ { /*def*/ aaa /* xyz */ : // to the end of line\n          123 /* xyz */ , /* 123 */ }", '$.aaa', 123, '{"aaa":123}'],
    'json501-12.1 leading whitespace set' => ["\t\n\v\f\r \u{00a0}\u{2028}\u{2029}" . '{a: "xyz"}', '$.a', 'xyz', '{"a":"xyz"}'],
    'json501-12.2 value whitespace set' => ['{a:' . "\t\n\v\f\r \u{00a0}\u{2028}\u{2029}" . '"xyz"}', '$.a', 'xyz', '{"a":"xyz"}'],
    'json501-12.3 extended leading whitespace set' => ["\u{1680}\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200a}\u{3000}\u{feff}" . '{a: "xyz"}', '$.a', 'xyz', '{"a":"xyz"}'],
    'json501-12.4 extended value whitespace set' => ['{a:' . "\u{1680}\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200a}\u{3000}\u{feff}" . '"xyz"}', '$.a', 'xyz', '{"a":"xyz"}'],
    'json501-13.1 single quote containing double quote' => ['{x:\'a "b" c\'}', '$.x', 'a "b" c', '{"x":"a \"b\" c"}'],
];

$invalidCases = [
    'json501-1.10 slash in identifier is rejected' => '{ MNO_123/xyz : 789 }',
    'json501-2.3 double object comma is rejected' => '{a:5, b:6 ,, }',
    'json501-2.4 comma after comma before close is rejected' => '{a:5, b:6, ,}',
    'json501-3.3 double array comma is rejected' => '[5, 6,,]',
    'json501-3.4 separated array comma is rejected' => '[5, 6 , , ]',
];

foreach ($validCases as $name => [$json5, $path, $expectedValue, $canonical]) {
    $tests["upstream json501 JSON5 extract {$name}"] = static function (TestRunner $t) use ($json5, $path, $expectedValue): void {
        $t->same($expectedValue, SQLiteJsonExtract::extract($json5, $path));
    };

    $tests["upstream json501 JSON5 canonical {$name}"] = static function (TestRunner $t) use ($json5, $canonical): void {
        $t->same($canonical, SQLiteJsonCanonical::json($json5));
    };

    $tests["upstream json501 JSON5 validity {$name}"] = static function (TestRunner $t) use ($json5): void {
        $t->same(false, SQLiteJsonValidity::jsonValid($json5));
        $t->same(true, SQLiteJsonValidity::jsonValid($json5, SQLiteJsonValidity::FLAG_JSON5_TEXT));
        $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($json5));
    };

    $tests["upstream json501 JSON5 jsonb round trip {$name}"] = static function (TestRunner $t) use ($json5, $path, $expectedValue, $jsonbText): void {
        $jsonb = SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json5);
        $t->true($jsonb instanceof SQLiteBlobValue);
        $t->same($expectedValue, SQLiteJsonExtract::extract($jsonb, $path));
        $t->same(SQLiteJsonCanonical::json($jsonb), $jsonbText($jsonb));
        $t->same(true, SQLiteJsonValidity::jsonValid($jsonb, SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB));
        $t->same(true, SQLiteJsonValidity::jsonValid($jsonb, SQLiteJsonValidity::FLAG_STRICT_JSONB));
    };
}

foreach ($invalidCases as $name => $json5) {
    $tests["upstream json501 malformed JSON5 canonical rejects {$name}"] = static function (TestRunner $t) use ($json5): void {
        $t->throws(InvalidArgumentException::class, static fn (): ?string => SQLiteJsonCanonical::json($json5));
    };

    $tests["upstream json501 malformed JSON5 validity rejects {$name}"] = static function (TestRunner $t) use ($json5): void {
        $t->same(false, SQLiteJsonValidity::jsonValid($json5, SQLiteJsonValidity::FLAG_JSON5_TEXT));
        $t->true(SQLiteJsonErrorPosition::jsonErrorPosition($json5) > 0);
    };
}

$controlEscapeCases = [];
for ($codepoint = 1; $codepoint <= 0x1f; $codepoint++) {
    $escaped = match ($codepoint) {
        8 => '\\b',
        9 => '\\t',
        10 => '\\n',
        12 => '\\f',
        13 => '\\r',
        default => sprintf('\\u00%02x', $codepoint),
    };
    $controlEscapeCases[sprintf('json501-14.%d control literal', $codepoint)] = [
        '{label:"abc' . chr($codepoint) . 'xyz"}',
        '{"label":"abc' . $escaped . 'xyz"}',
    ];
}

foreach ($controlEscapeCases as $name => [$json5, $canonical]) {
    $tests["upstream json501 control character JSON5 validity {$name}"] = static function (TestRunner $t) use ($json5): void {
        $t->same(false, SQLiteJsonValidity::jsonValid('"abc' . $json5[11] . 'xyz"'));
        $t->same(true, SQLiteJsonValidity::jsonValid('"abc' . $json5[11] . 'xyz"', SQLiteJsonValidity::FLAG_JSON5_TEXT));
    };

    $tests["upstream json501 control character canonical {$name}"] = static function (TestRunner $t) use ($json5, $canonical): void {
        $t->same($canonical, SQLiteJsonCanonical::json($json5));
    };

    $tests["upstream json501 control character jsonb {$name}"] = static function (TestRunner $t) use ($json5, $canonical, $jsonbText): void {
        $jsonb = SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json5);
        $t->true($jsonb instanceof SQLiteBlobValue);
        $t->same($canonical, $jsonbText($jsonb));
    };
}

return $tests;
