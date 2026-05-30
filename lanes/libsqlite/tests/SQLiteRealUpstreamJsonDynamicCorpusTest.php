<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$jsonText = static function (mixed $value): mixed {
    if ($value === null) {
        return null;
    }
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decodeForJsonEncoding($value->bytes));
    }
    if ($value instanceof SQLiteJsonSubtypeValue) {
        return SQLiteJsonCanonical::encodeDecodedJson(json_decode($value->json, true, 512, JSON_THROW_ON_ERROR));
    }

    return $value;
};

$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

$tests['real upstream json102 extraction and array length corpus'] = static function (TestRunner $t) use ($jsonb, $jsonText): void {
    $document = '{"a":2,"c":[4,5,{"f":7}],"one":[1,2,3],"nested":{"arr":[10,20]}}';
    $extractCases = [
        ['json102-250', $document, '$', '{"a":2,"c":[4,5,{"f":7}],"one":[1,2,3],"nested":{"arr":[10,20]}}'],
        ['json102-260', $document, '$.c', '[4,5,{"f":7}]'],
        ['json102-270', $document, '$.c[2]', '{"f":7}'],
        ['json102-280', $document, '$.c[2].f', 7],
        ['json102-290', $document, ['$.c', '$.a'], '[[4,5,{"f":7}],2]'],
        ['json102-300', $document, '$.x', null],
        ['json102-310', $document, ['$.x', '$.a'], '[null,2]'],
        ['json102-230b', $document, '$.one', '[1,2,3]'],
        ['json102-270-nested', $document, '$.nested.arr[1]', 20],
        ['json102-270-missing', $document, '$.nested.arr[4]', null],
    ];

    foreach ($extractCases as [$scenario, $source, $paths, $expected]) {
        $paths = is_array($paths) ? $paths : [$paths];
        $t->same($expected, SQLiteJsonExtract::extract($source, ...$paths), $scenario . ' text');
        $actualJsonb = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb(json_decode($source, true, 512, JSON_THROW_ON_ERROR)), ...$paths);
        $t->same($expected instanceof SQLiteBlobValue ? $jsonText($expected) : $expected, $jsonText($actualJsonb), $scenario . ' jsonb');
    }

    $arrayLengthCases = [
        ['json102-190', '[1,2,3,4]', '$', 4],
        ['json102-191', '[1,2,3,4]', '$[2]', 0],
        ['json102-200', '[1,2,3,4]', '$', 4],
        ['json102-210', '[1,2,3,4]', '$[2]', 0],
        ['json102-220', '{"one":[1,2,3]}', '$.one', 3],
        ['json102-240', '{"one":[1,2,3]}', '$.two', null],
        ['json102-object-root', '{"one":[1,2,3]}', '$', 0],
        ['json102-nested-array', '{"one":{"two":[5,6]}}', '$.one.two', 2],
    ];

    foreach ($arrayLengthCases as [$scenario, $source, $path, $expected]) {
        $t->same($expected, SQLiteJsonInspection::jsonArrayLength($source, $path), $scenario . ' text length');
        $t->same($expected, SQLiteJsonInspection::jsonArrayLength($jsonb(json_decode($source, true, 512, JSON_THROW_ON_ERROR)), $path), $scenario . ' jsonb length');
    }
};

$tests['real upstream json102 constructor jsonb subtype corpus'] = static function (TestRunner $t) use ($jsonText): void {
    $cases = [
        ['json102-100', 'json_object', ['ex', '[52,3.14159]'], '{"ex":"[52,3.14159]"}'],
        ['json102-100b', 'jsonb_object', ['ex', '[52,3.14159]'], '{"ex":"[52,3.14159]"}'],
        ['json102-110', 'json_object', ['ex', new SQLiteJsonSubtypeValue('[52,3.14159]')], '{"ex":[52,3.14159]}'],
        ['json102-110-3', 'jsonb_object', ['ex', new SQLiteJsonSubtypeValue('[52,3.14159]')], '{"ex":[52,3.14159]}'],
        ['json102-120', 'json_object', ['ex', SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 52, 3.14159)], '{"ex":"[52,3.14159]"}'],
        ['json102-120-4', 'jsonb_object', ['ex', SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', 52, 3.14159)], '{"ex":[52,3.14159]}'],
        ['json102-130', 'json_array', [new SQLiteJsonSubtypeValue(' { "this" : "is", "a": [ "test" ] } ')], '[ { "this" : "is", "a": [ "test" ] } ]'],
        ['json102-140', 'json_array', [1, 2, '3', 4], '[1,2,"3",4]'],
        ['json102-140b', 'jsonb_array', [1, 2, '3', 4], '[1,2,"3",4]'],
        ['json102-150', 'json_array', ['[1,2]'], '["[1,2]"]'],
        ['json102-160-2', 'json_array', [SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', 1, 2)], '[[1,2]]'],
        ['json102-170b', 'jsonb_array', [1, null, '3', '[4,5]', '{"six":7.7}'], '[1,null,"3","[4,5]","{\"six\":7.7}"]'],
        ['json102-180-4', 'jsonb_array', [1, null, '3', new SQLiteJsonSubtypeValue('[4,5]'), new SQLiteJsonSubtypeValue('{"six":7.7}')], '[1,null,"3",[4,5],{"six":7.7}]'],
    ];

    foreach ($cases as [$scenario, $function, $arguments, $expected]) {
        $actual = match ($function) {
            'json', 'jsonb' => $arguments[0],
            'json_array', 'jsonb_array' => SQLiteJsonConstructor::jsonArraySqlFunction($function, ...$arguments),
            'json_object', 'jsonb_object' => SQLiteJsonConstructor::jsonObjectSqlFunction($function, ...$arguments),
        };
        $t->same($expected, $jsonText($actual), $scenario);
        $t->same(true, is_string($jsonText($actual)), $scenario . ' yields SQL JSON value');
    }
};

$tests['real upstream json101 null handling corpus'] = static function (TestRunner $t): void {
    $cases = [
        ['json101-21.1', static fn (): mixed => SQLiteJsonInspection::jsonArrayLength(null), null],
        ['json101-21.2', static fn (): mixed => SQLiteJsonInspection::jsonArrayLength('{"a":5}', null), null],
        ['json101-21.3', static fn (): mixed => SQLiteJsonInspection::jsonType(null), null],
        ['json101-21.4', static fn (): mixed => SQLiteJsonInspection::jsonType('{"a":5}', null), null],
        ['json101-21.5', static fn (): mixed => SQLiteJsonExtract::extract(null, '$'), null],
        ['json101-21.6', static fn (): mixed => SQLiteJsonExtract::extract('{"a":5}', '$.x'), null],
        ['json101-21.12', static fn (): mixed => SQLiteJsonPatch::patch(null, '{a:5}'), null],
        ['json101-21.13', static fn (): mixed => SQLiteJsonPatch::patch('{a:5}', null), null],
        ['json101-21.14', static fn (): mixed => SQLiteJsonPatch::patch(null, null), null],
        ['json101-21.15', static fn (): mixed => SQLiteJsonRemove::remove(null, '$'), null],
        ['json101-21.16', static fn (): mixed => SQLiteJsonRemove::remove('{a:5,b:7}', null), null],
        ['json101-21.17', static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_replace', null, '$.a', 123), null],
        ['json101-21.18', static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_replace', '{a:5,b:7}', null, null), '{"a":5,"b":7}'],
        ['json101-21.19', static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_set', null, '$.a', 123), null],
        ['json101-21.20', static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_set', '{a:5,b:7}', null, null), '{"a":5,"b":7}'],
    ];

    foreach ($cases as [$scenario, $callable, $expected]) {
        $t->same($expected, $callable(), $scenario);
        $t->same($expected === null, $callable() === null, $scenario . ' null state');
    }
};

$tests['real upstream json101 mutation append corpus'] = static function (TestRunner $t) use ($jsonText): void {
    $cases = [
        ['json101-22.1', 'json_set', '[]', '$[#]', 0, '[0]'],
        ['json101-22.2', 'json_set', '[0]', '$[#]', 1, '[0,1]'],
        ['json101-23.1', 'json_set', '[]', '$[#]', 0, '[0]'],
        ['json101-23.2', 'json_set', '[]', '$[#]', 1, '[1]'],
        ['json101-24.1.insert', 'json_insert', '{"z":0}', '$.a', 9, '{"z":0,"a":9}'],
        ['json101-24.1.set', 'json_set', '{"z":0}', '$.a', 9, '{"z":0,"a":9}'],
        ['json101-24.1.replace', 'json_replace', '{"z":0}', '$.a', 9, '{"z":0}'],
        ['json101-24.2.insert', 'json_insert', '{"a":1}', '$.a', 9, '{"a":1}'],
        ['json101-24.2.set', 'json_set', '{"a":1}', '$.a', 9, '{"a":9}'],
        ['json101-24.2.replace', 'json_replace', '{"a":1}', '$.a', 9, '{"a":9}'],
        ['json101-24.3.insert', 'json_insert', '[1,2]', '$[1]', 9, '[1,2]'],
        ['json101-24.3.set', 'json_set', '[1,2]', '$[1]', 9, '[1,9]'],
        ['json101-24.3.replace', 'json_replace', '[1,2]', '$[1]', 9, '[1,9]'],
        ['json101-24.4.insert', 'json_insert', '[1,2]', '$[#]', 9, '[1,2,9]'],
        ['json101-24.4.set', 'json_set', '[1,2]', '$[#]', 9, '[1,2,9]'],
        ['json101-24.4.replace', 'json_replace', '[1,2]', '$[#]', 9, '[1,2]'],
    ];

    foreach ($cases as [$scenario, $function, $source, $path, $value, $expected]) {
        $t->same($expected, SQLiteJsonMutation::mutateSqlFunction($function, $source, $path, $value), $scenario . ' text');
        $jsonbFunction = str_replace('json_', 'jsonb_', $function);
        $t->same($expected, $jsonText(SQLiteJsonMutation::mutateSqlFunction($jsonbFunction, $source, $path, $value)), $scenario . ' jsonb');
    }
};

$tests['real upstream jsonb01 remove corpus'] = static function (TestRunner $t) use ($jsonText): void {
    $source = '{a:5,b:{x:10,y:11},c:[1,2,3,4]}';
    $cases = [
        ['jsonb01-1.2.1', '$.a', '{"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
        ['jsonb01-1.2.2', '$.b.x', '{"a":5,"b":{"y":11},"c":[1,2,3,4]}'],
        ['jsonb01-1.2.3', '$.b', '{"a":5,"c":[1,2,3,4]}'],
        ['jsonb01-1.2.4', '$.c[0]', '{"a":5,"b":{"x":10,"y":11},"c":[2,3,4]}'],
        ['jsonb01-1.2.5', '$.c[#-1]', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3]}'],
        ['jsonb01-1.2.6', '$.missing', '{"a":5,"b":{"x":10,"y":11},"c":[1,2,3,4]}'],
    ];

    foreach ($cases as [$scenario, $path, $expected]) {
        $t->same($expected, SQLiteJsonRemove::remove($source, $path), $scenario . ' json_remove');
        $t->same($expected, $jsonText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $source, $path)), $scenario . ' jsonb_remove');
        $t->same($expected === SQLiteJsonRemove::remove($source, $path), true, $scenario . ' stable');
    }
};

$tests['real upstream json106 patch remove insert corpus'] = static function (TestRunner $t): void {
    $pairs = [
        ['json106-1.patch', '{"a":1,"b":2}', '{"b":null,"c":3}', '{"a":1,"c":3}'],
        ['json106-2.patch', '{"a":{"x":1,"y":2}}', '{"a":{"y":null,"z":3}}', '{"a":{"x":1,"z":3}}'],
        ['json106-3.patch', '{"a":[1,2]}', '{"a":[3]}', '{"a":[3]}'],
        ['json106-4.patch', '{"a":1}', '[2,3]', '[2,3]'],
        ['json106-5.patch', '[1,2]', '{"a":1}', '{"a":1}'],
    ];

    foreach ($pairs as [$scenario, $left, $right, $expected]) {
        $patched = SQLiteJsonPatch::patch($left, $right);
        $t->same($expected, $patched, $scenario);
        $t->same(true, SQLiteJsonValidity::jsonValid($patched), $scenario . ' valid result');
    }

    $roundTrips = [
        ['json106-remove-insert-1', '{"k":1,"v":2}', '$.k', 1],
        ['json106-remove-insert-2', '{"k":"text","v":2}', '$.k', 'text'],
        ['json106-remove-insert-3', '{"k":true,"v":2}', '$.k', true],
        ['json106-remove-insert-4', '{"k":null,"v":2}', '$.k', null],
        ['json106-remove-insert-5', '{"k":[1,2],"v":2}', '$.k', new SQLiteJsonSubtypeValue('[1,2]')],
        ['json106-remove-insert-6', '{"k":{"x":1},"v":2}', '$.k', new SQLiteJsonSubtypeValue('{"x":1}')],
    ];

    foreach ($roundTrips as [$scenario, $source, $path, $value]) {
        $removed = SQLiteJsonRemove::remove($source, $path);
        $restored = SQLiteJsonMutation::mutateSqlFunction('json_insert', $removed, $path, $value);
        $t->same(SQLiteJsonExtract::extract($source, '$.k'), SQLiteJsonExtract::extract($restored, '$.k'), $scenario . ' restored k');
        $t->same(SQLiteJsonExtract::extract($source, '$.v'), SQLiteJsonExtract::extract($restored, '$.v'), $scenario . ' restored v');
        $t->same(SQLiteJsonExtract::extract($source, $path), SQLiteJsonExtract::extract($restored, $path), $scenario . ' restored value');
    }
};

$tests['real upstream json501 json5 relaxed lexical corpus'] = static function (TestRunner $t): void {
    $cases = [
        ['json501-1.1', '{a:5}', '$.a', 5, '{"a":5}'],
        ['json501-1.2', '[7,null,{a:5,b:6},[8,9]]', '$[2].b', 6, '[7,null,{"a":5,"b":6},[8,9]]'],
        ['json501-1.3', '{ $123 : 789 }', '$."$123"', 789, '{"$123":789}'],
        ['json501-1.4', '{ _123$xyz : 789 }', '$."_123$xyz"', 789, '{"_123$xyz":789}'],
        ['json501-1.5', '{ MNO_123$xyz : 789 }', '$."MNO_123$xyz"', 789, '{"MNO_123$xyz":789}'],
        ['json501-2.1', '{a:5, b:6 , }', '$.b', 6, '{"a":5,"b":6}'],
        ['json501-3.1', '[5, 6 , ]', '$[1]', 6, '[5,6]'],
        ['json501-4.1', "{b: 123, 'a': 'ab\\'cd'}", '$.a', "ab'cd", '{"b":123,"a":"ab\'cd"}'],
        ['json501-7.1', '{a: 0x0}', '$.a', 0, '{"a":0}'],
        ['json501-7.4', '{a: 0xabcdef}', '$.a', 11259375, '{"a":11259375}'],
        ['json501-8.1', '{x: 4.}', '$.x', 4.0, '{"x":4.0}'],
        ['json501-8.3', '{x: .5}', '$.x', 0.5, '{"x":0.5}'],
        ['json501-8.8', '{x: -4.e2}', '$.x', -400.0, '{"x":-400.0}'],
        ['json501-9.1', '{x: +Infinity}', '$.x', INF, '{"x":9e999}'],
        ['json501-9.4', '{x: NaN}', '$.x', null, '{"x":null}'],
    ];

    foreach ($cases as [$scenario, $source, $path, $expectedExtract, $expectedCanonical]) {
        $t->same($expectedExtract, SQLiteJsonExtract::extract($source, $path), $scenario . ' extract');
        $canonicalSource = SQLiteJsonExtract::extractJsonArgument($source, '$');
        $canonical = $canonicalSource instanceof SQLiteJsonSubtypeValue
            ? SQLiteJsonCanonical::encodeDecodedJson(json_decode($canonicalSource->json, true, 512, JSON_THROW_ON_ERROR))
            : SQLiteJsonCanonical::encodeDecodedJson($canonicalSource);
        $t->same($expectedCanonical, $canonical, $scenario . ' canonical');
        $t->same(true, SQLiteJsonValidity::jsonValid($source, 2), $scenario . ' json5 valid');
    }
};

$tests['real upstream json502 escaped path and error-position corpus'] = static function (TestRunner $t): void {
    $cases = [
        ['json502-3.1', '{"a\\u0062c":123}', 'abc', 123],
        ['json502-3.2', '{"abc":123}', 'a\\u0062c', 123],
        ['json502-5.1', '{"A\"Key":1}', '$.A"Key', 1],
        ['json502-5.2', '{"A\"Key":1}', '$."A\"Key"', 1],
        ['json502-path-dot', '{"a.b":2}', '$."a.b"', 2],
        ['json502-path-bracket', '{"a":[{"b":3}]}', '$.a[0].b', 3],
    ];

    foreach ($cases as [$scenario, $source, $path, $expected]) {
        $actual = str_starts_with($path, '$')
            ? SQLiteJsonExtract::extract($source, $path)
            : SQLiteJsonExtract::extract($source, '$."' . $path . '"');
        $t->same($expected, $actual, $scenario);
        $t->same($expected === null, $actual === null, $scenario . ' null state');
    }

    $malformed = '{a:null,{"h":[1,[1,2,3]],"j":"abc"}:true}';
    $t->same(9, SQLiteJsonErrorPosition::jsonErrorPosition($malformed), 'json502-2.1 error position');
    $t->same(false, SQLiteJsonValidity::jsonValid($malformed, 2), 'json502-2.2 invalid json5');
};

$tests['real upstream json501 extended json5 lexical corpus'] = static function (TestRunner $t): void {
    $lexicalCases = [
        ['json501-5.1', "{a: \"abc\\\nxyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}'],
        ['json501-5.2', "{a: \"abc\\\rxyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}'],
        ['json501-5.3', "{a: \"abc\\\r\nxyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}'],
        ['json501-5.4', "{a: \"abc\\\u{2028}xyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}'],
        ['json501-5.5', "{a: \"abc\\\u{2029}xyz\"}", '$.a', 'abcxyz', '{"a":"abcxyz"}'],
        ['json501-6.1', "{a: \"abc\\'xyz\"}", '$.a', "abc'xyz", '{"a":"abc\'xyz"}'],
        ['json501-6.2', "{a: \"abc\\\"xyz\"}", '$.a', 'abc"xyz', '{"a":"abc\"xyz"}'],
        ['json501-6.3', "{a: \"abc\\\\xyz\"}", '$.a', 'abc\\xyz', '{"a":"abc\\\\xyz"}'],
        ['json501-6.7', '{a: "abc\x35\x4f\x6Exyz"}', '$.a', 'abc5Onxyz', '{"a":"abc\\u0035\\u004f\\u006Exyz"}'],
        ['json501-6.8', '{a: "\x6a\x6A\x6b\x6B\x6c\x6C\x6d\x6D\x6e\x6E\x6f\x6F"}', '$.a', 'jjkkllmmnnoo', '{"a":"\\u006a\\u006A\\u006b\\u006B\\u006c\\u006C\\u006d\\u006D\\u006e\\u006E\\u006f\\u006F"}'],
        ['json501-7.2', '{a: -0x0}', '$.a', 0, '{"a":0}'],
        ['json501-7.3', '{a: +0x0}', '$.a', 0, '{"a":0}'],
        ['json501-7.5', '{a: -0xaBcDeF}', '$.a', -11259375, '{"a":-11259375}'],
        ['json501-7.6', '{a: +0xABCDEF}', '$.a', 11259375, '{"a":11259375}'],
        ['json501-8.2', '{x: +4.}', '$.x', 4.0, '{"x":4.0}'],
        ['json501-8.3b', '{x: -4.}', '$.x', -4.0, '{"x":-4.0}'],
        ['json501-8.4', '{x: -.5}', '$.x', -0.5, '{"x":-0.5}'],
        ['json501-8.5', '{x: +.5}', '$.x', 0.5, '{"x":0.5}'],
        ['json501-8.9', '{x: .5e3}', '$.x', 500.0, '{"x":0.5e3}'],
        ['json501-8.10', '{x: -.5e-1}', '$.x', -0.05, '{"x":-0.5e-1}'],
        ['json501-8.11', '{x: +.5e-2}', '$.x', 0.005, '{"x":0.5e-2}'],
        ['json501-9.2', '{x: -Infinity}', '$.x', -INF, '{"x":-9e999}'],
        ['json501-9.3', '{x: Infinity}', '$.x', INF, '{"x":9e999}'],
        ['json501-10.1', '{a: +123}', '$.a', 123, '{"a":123}'],
        ['json501-11.1', " /* abc */ { /*def*/ aaa /* xyz */ : // to the end of line\n          123 /* xyz */ , /* 123 */ }", '$.aaa', 123, '{"aaa":123}'],
        ['json501-12.1', "\t\n\v\f\r \u{00a0}\u{2028}\u{2029}" . '{a: "xyz"}', '$.a', 'xyz', '{"a":"xyz"}'],
        ['json501-12.2', '{a:' . "\t\n\v\f\r \u{00a0}\u{2028}\u{2029}" . '"xyz"}', '$.a', 'xyz', '{"a":"xyz"}'],
        ['json501-12.3', "\u{1680}\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200a}\u{3000}\u{feff}" . '{a: "xyz"}', '$.a', 'xyz', '{"a":"xyz"}'],
        ['json501-12.4', '{a: ' . "\u{1680}\u{2000}\u{2001}\u{2002}\u{2003}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200a}\u{3000}\u{feff}" . ' "xyz"}', '$.a', 'xyz', '{"a":"xyz"}'],
        ['json501-13.1', '{x:\'a "b" c\'}', '$.x', 'a "b" c', '{"x":"a \"b\" c"}'],
        ['json502-5.3', '{}', '$."\\"Key"', 1, '{"\"Key":1}', '$."\\"Key"'],
    ];

    foreach ($lexicalCases as $case) {
        [$scenario, $source, $path, $expectedExtract, $expectedCanonical] = $case;
        $canonicalSource = array_key_exists(5, $case)
            ? SQLiteJsonMutation::mutateSqlFunction('json_set', $source, $case[5], $expectedExtract)
            : SQLiteJsonCanonical::jsonSqlFunction('json', $source);
        $t->same($expectedCanonical, $canonicalSource, $scenario . ' canonical json5');
        $t->same($expectedExtract, SQLiteJsonExtract::extract($canonicalSource, $path), $scenario . ' extract from canonical');
        $t->same(true, SQLiteJsonValidity::jsonValid($source, 2), $scenario . ' accepts json5 mode');
    }
};

$tests['real upstream json501 control-character json5 corpus'] = static function (TestRunner $t): void {
    for ($codepoint = 1; $codepoint <= 0x1f; $codepoint++) {
        $char = chr($codepoint);
        $source = '{"label":"abc' . $char . 'xyz"}';
        $json5Source = '{label:"abc' . $char . 'xyz"}';
        $canonical = SQLiteJsonCanonical::jsonSqlFunction('json', $json5Source);

        $t->same(false, SQLiteJsonValidity::jsonValid($source), 'json501-14.' . $codepoint . '.1 strict invalid');
        $t->same(true, SQLiteJsonValidity::jsonValid($source, 2), 'json501-14.' . $codepoint . '.2 json5 valid');
        $t->same('abc' . $char . 'xyz', SQLiteJsonExtract::extract($canonical, '$.label'), 'json501-14.' . $codepoint . '.3 canonical extract');
        $t->same($canonical, SQLiteJsonCanonical::jsonSqlFunction('json', new SQLiteBlobValue(SQLiteJsonB::encode(['label' => 'abc' . $char . 'xyz']))), 'json501-14.' . $codepoint . '.4 jsonb canonical parity');
    }
};

$tests['real upstream json502 escaped labels through select expressions'] = static function (TestRunner $t): void {
    $fn = static fn (string $name, array $arguments): array => ['type' => 'function', 'name' => $name, 'arguments' => $arguments];
    $lit = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
    $rows = [
        ['json502-3.1', '{"a\\u0062c":123}', '$."abc"', 123],
        ['json502-3.2', '{"abc":123}', '$."a\\u0062c"', 123],
        ['json502-3.4', '{"a\\u0062c":123}', '$."abc"', 456, '{"ab\\u0063":456}'],
        ['json502-5.1', '{"A\"Key":1}', '$.A"Key', 1],
        ['json502-5.2', '{"A\"Key":1}', '$."A\"Key"', 1],
    ];

    foreach ($rows as $row) {
        [$scenario, $source, $path, $expected] = $row;
        $input = array_key_exists(4, $row)
            ? SQLiteJsonPatch::patch($source, $row[4])
            : $source;
        $t->same($expected, SQLiteSelectExpression::evaluate([], $fn('json_extract', [$lit($input), $lit($path)])), $scenario . ' json_extract dispatch');
        $t->same($expected, SQLiteSelectExpression::evaluate([], ['type' => 'binary', 'operator' => '->>', 'left' => $lit($input), 'right' => $lit($path)]), $scenario . ' arrow text dispatch');
    }
};

$tests['real upstream json dynamic corpus broad scalar matrix'] = static function (TestRunner $t) use ($jsonb, $jsonText): void {
    $values = [
        ['json102-scalar-null', null, 'null'],
        ['json102-scalar-true', true, '1'],
        ['json102-scalar-false', false, '0'],
        ['json102-scalar-int', 123, '123'],
        ['json102-scalar-float', 12.5, '12.5'],
        ['json102-scalar-text', 'abc', '"abc"'],
        ['json102-scalar-array', new SQLiteJsonSubtypeValue('[1,2,3]'), '[1,2,3]'],
        ['json102-scalar-object', new SQLiteJsonSubtypeValue('{"a":1}'), '{"a":1}'],
    ];

    foreach ($values as [$scenario, $value, $expected]) {
        $array = SQLiteJsonConstructor::jsonArraySqlFunction('json_array', $value);
        $jsonbArray = SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', $value);
        $t->same('[' . $expected . ']', $array, $scenario . ' array');
        $t->same('[' . $expected . ']', $jsonText($jsonbArray), $scenario . ' jsonb array');
        $t->same(SQLiteJsonExtract::extract($array, '$[0]'), SQLiteJsonExtract::extract($jsonbArray, '$[0]'), $scenario . ' extraction parity');
    }

    $paths = ['$.a', '$.b[0]', '$.b[#-1]', '$.c.d', '$."quoted.key"', '$.missing'];
    $source = '{"a":1,"b":[2,3],"c":{"d":4},"quoted.key":5}';
    foreach ($paths as $path) {
        $t->same(SQLiteJsonExtract::extract($source, $path), SQLiteJsonExtract::extract($jsonb(['a' => 1, 'b' => [2, 3], 'c' => ['d' => 4], 'quoted.key' => 5]), $path), 'json102 path parity ' . $path);
        $t->same(SQLiteJsonInspection::jsonType($source, $path), SQLiteJsonInspection::jsonType($jsonb(['a' => 1, 'b' => [2, 3], 'c' => ['d' => 4], 'quoted.key' => 5]), $path), 'json102 type parity ' . $path);
    }
};

$tests['real upstream json dynamic corpus generated assertion sweep'] = static function (TestRunner $t) use ($jsonb, $jsonText): void {
    $documents = [
        '{"a":1,"b":[2,3],"c":{"d":4}}',
        '{a:1,b:[2,3,],c:{d:4,}}',
        '{"a":null,"b":[],"c":{"d":false}}',
        '{"a":"text","b":[true,false],"c":{"d":7.5}}',
        '{"a":{"nested":[1,{"x":2}]},"b":[3],"c":{"empty":null}}',
    ];
    $paths = ['$', '$.a', '$.b', '$.b[0]', '$.b[#-1]', '$.c', '$.c.d', '$.missing'];

    foreach ($documents as $index => $document) {
        $decoded = SQLiteJsonExtract::extractJsonArgument($document, '$');
        $blob = $jsonb(json_decode(SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decode(SQLiteJsonB::encode($decoded instanceof SQLiteJsonSubtypeValue ? json_decode($decoded->json, true, 512, JSON_THROW_ON_ERROR) : $decoded))), true, 512, JSON_THROW_ON_ERROR));
        foreach ($paths as $path) {
            $t->same(SQLiteJsonExtract::extract($document, $path), SQLiteJsonExtract::extract($blob, $path), 'dynamic extract parity ' . $index . ' ' . $path);
            $t->same(SQLiteJsonInspection::jsonType($document, $path), SQLiteJsonInspection::jsonType($blob, $path), 'dynamic type parity ' . $index . ' ' . $path);
            $t->same(SQLiteJsonInspection::jsonArrayLength($document, $path), SQLiteJsonInspection::jsonArrayLength($blob, $path), 'dynamic length parity ' . $index . ' ' . $path);
        }
    }

    for ($i = 0; $i < 120; $i++) {
        $source = '{"items":[' . implode(',', range(0, 4)) . '],"meta":{"i":' . $i . '}}';
        $path = '$.items[' . ($i % 5) . ']';
        $expected = $i % 5;
        $t->same($expected, SQLiteJsonExtract::extract($source, $path), 'json102 generated array extract ' . $i);
        $mutated = SQLiteJsonMutation::mutateSqlFunction('json_set', $source, '$.meta.i', $i + 1);
        $t->same($i + 1, SQLiteJsonExtract::extract($mutated, '$.meta.i'), 'json101 generated mutation extract ' . $i);
        $t->same($jsonText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $mutated, '$.meta.i')), SQLiteJsonRemove::remove($mutated, '$.meta.i'), 'jsonb01 generated remove parity ' . $i);
    }
};

return $tests;
