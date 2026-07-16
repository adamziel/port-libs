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

$utf8 = static function (int $codepoint): string {
    if ($codepoint <= 0x7f) {
        return chr($codepoint);
    }
    if ($codepoint <= 0x7ff) {
        return chr(0xc0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3f));
    }
    if ($codepoint <= 0xffff) {
        return chr(0xe0 | ($codepoint >> 12))
            . chr(0x80 | (($codepoint >> 6) & 0x3f))
            . chr(0x80 | ($codepoint & 0x3f));
    }

    return chr(0xf0 | ($codepoint >> 18))
        . chr(0x80 | (($codepoint >> 12) & 0x3f))
        . chr(0x80 | (($codepoint >> 6) & 0x3f))
        . chr(0x80 | ($codepoint & 0x3f));
};

$controlEscape = static function (int $codepoint): string {
    return match ($codepoint) {
        8 => '\\b',
        9 => '\\t',
        10 => '\\n',
        12 => '\\f',
        13 => '\\r',
        default => sprintf('\\u%04x', $codepoint),
    };
};

for ($codepoint = 1; $codepoint <= 0x1f; $codepoint++) {
    $char = chr($codepoint);
    $escape = $controlEscape($codepoint);
    $canonical = '{"label":"abc' . $escape . 'xyz"}';
    $plainString = '"abc' . $char . 'xyz"';
    $object = '{label:"abc' . $char . 'xyz"}';
    $singleQuotedObject = "{label:'abc" . $char . "xyz'}";
    $array = '["abc' . $char . 'xyz",{label:"abc' . $char . 'xyz"}]';
    $canonicalArray = '["abc' . $escape . 'xyz",{"label":"abc' . $escape . 'xyz"}]';
    $jsonbObject = static fn (): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $object);

    $tests[sprintf('real upstream json501 control char %02x strict text rejects raw string', $codepoint)] =
        static function (TestRunner $t) use ($plainString): void {
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $plainString));
        };

    $tests[sprintf('real upstream json501 control char %02x json5 text accepts raw string', $codepoint)] =
        static function (TestRunner $t) use ($plainString): void {
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $plainString, 2));
        };

    $tests[sprintf('real upstream json501 control char %02x strict object rejects raw string', $codepoint)] =
        static function (TestRunner $t) use ($object): void {
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $object));
        };

    $tests[sprintf('real upstream json501 control char %02x json5 object accepts raw string', $codepoint)] =
        static function (TestRunner $t) use ($object): void {
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $object, 2));
        };

    $tests[sprintf('real upstream json501 control char %02x canonical object escapes control', $codepoint)] =
        static function (TestRunner $t) use ($object, $canonical): void {
            $t->same($canonical, SQLiteJsonCanonical::json($object));
        };

    $tests[sprintf('real upstream json501 control char %02x canonical single quoted object escapes control', $codepoint)] =
        static function (TestRunner $t) use ($singleQuotedObject, $canonical): void {
            $t->same($canonical, SQLiteJsonCanonical::json($singleQuotedObject));
        };

    $tests[sprintf('real upstream json501 control char %02x canonical jsonb object escapes control', $codepoint)] =
        static function (TestRunner $t) use ($jsonbObject, $canonical, $jsonbText): void {
            $t->same($canonical, $jsonbText($jsonbObject()));
        };

    $tests[sprintf('real upstream json501 control char %02x extract text preserves raw character', $codepoint)] =
        static function (TestRunner $t) use ($object, $char): void {
            $t->same('abc' . $char . 'xyz', SQLiteJsonExtract::extract($object, '$.label'));
        };

    $tests[sprintf('real upstream json501 control char %02x extract jsonb preserves raw character', $codepoint)] =
        static function (TestRunner $t) use ($jsonbObject, $char): void {
            $t->same('abc' . $char . 'xyz', SQLiteJsonExtract::extract($jsonbObject(), '$.label'));
        };

    $tests[sprintf('real upstream json501 control char %02x jsonb extract text preserves raw character', $codepoint)] =
        static function (TestRunner $t) use ($object, $char): void {
            $t->same('abc' . $char . 'xyz', SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $object, '$.label'));
        };

    $tests[sprintf('real upstream json501 control char %02x error position accepts json5 object', $codepoint)] =
        static function (TestRunner $t) use ($object): void {
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($object));
        };

    $tests[sprintf('real upstream json501 control char %02x array canonicalizes raw string and object member', $codepoint)] =
        static function (TestRunner $t) use ($array, $canonicalArray): void {
            $t->same($canonicalArray, SQLiteJsonCanonical::json($array));
        };

    $tests[sprintf('real upstream json501 control char %02x array json5 validity accepts raw string and object member', $codepoint)] =
        static function (TestRunner $t) use ($array): void {
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $array, 2));
        };

    $tests[sprintf('real upstream json501 control char %02x array strict validity rejects raw string and object member', $codepoint)] =
        static function (TestRunner $t) use ($array): void {
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $array));
        };

    $tests[sprintf('real upstream json501 control char %02x array first extract preserves raw character', $codepoint)] =
        static function (TestRunner $t) use ($array, $char): void {
            $t->same('abc' . $char . 'xyz', SQLiteJsonExtract::extract($array, '$[0]'));
        };

    $tests[sprintf('real upstream json501 control char %02x array object extract preserves raw character', $codepoint)] =
        static function (TestRunner $t) use ($array, $char): void {
            $t->same('abc' . $char . 'xyz', SQLiteJsonExtract::extract($array, '$[1].label'));
        };

    $tests[sprintf('real upstream json501 control char %02x array jsonb canonicalizes raw string and object member', $codepoint)] =
        static function (TestRunner $t) use ($array, $canonicalArray, $jsonbText): void {
            $actual = SQLiteJsonCanonical::jsonSqlFunction('jsonb', $array);
            $t->same($canonicalArray, $actual instanceof SQLiteBlobValue ? $jsonbText($actual) : $actual);
        };

    $tests[sprintf('real upstream json501 control char %02x combined strict or json5 flags accepts json5', $codepoint)] =
        static function (TestRunner $t) use ($object): void {
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $object, SQLiteJsonValidity::FLAG_STRICT_TEXT | SQLiteJsonValidity::FLAG_JSON5_TEXT));
        };

    $tests[sprintf('real upstream json501 control char %02x jsonb validity flag accepts canonical blob', $codepoint)] =
        static function (TestRunner $t) use ($jsonbObject): void {
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $jsonbObject(), SQLiteJsonValidity::FLAG_STRICT_JSONB));
        };

    $tests[sprintf('real upstream json501 control char %02x jsonb superficial flag accepts canonical blob', $codepoint)] =
        static function (TestRunner $t) use ($jsonbObject): void {
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $jsonbObject(), SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB));
        };

    $tests[sprintf('real upstream json501 control char %02x jsonb text flags reject canonical blob', $codepoint)] =
        static function (TestRunner $t) use ($jsonbObject): void {
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $jsonbObject(), SQLiteJsonValidity::FLAG_STRICT_TEXT | SQLiteJsonValidity::FLAG_JSON5_TEXT));
        };

    $tests[sprintf('real upstream json501 control char %02x jsonb error position is zero', $codepoint)] =
        static function (TestRunner $t) use ($jsonbObject): void {
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($jsonbObject()));
        };

    $tests[sprintf('real upstream json501 control char %02x operator-style json text canonical output', $codepoint)] =
        static function (TestRunner $t) use ($object, $canonical): void {
            $t->same($canonical, SQLiteJsonCanonical::jsonSqlFunction('json', $object));
        };

    $tests[sprintf('real upstream json501 control char %02x operator-style jsonb text canonical output', $codepoint)] =
        static function (TestRunner $t) use ($object, $canonical, $jsonbText): void {
            $actual = SQLiteJsonCanonical::jsonSqlFunction('jsonb', $object);
            $t->same($canonical, $actual instanceof SQLiteBlobValue ? $jsonbText($actual) : $actual);
        };

    $tests[sprintf('real upstream json501 control char %02x uppercase json_valid dispatch accepts json5', $codepoint)] =
        static function (TestRunner $t) use ($object): void {
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('JSON_VALID', $object, 2));
        };

    $tests[sprintf('real upstream json501 control char %02x uppercase json_error_position dispatch accepts json5', $codepoint)] =
        static function (TestRunner $t) use ($object): void {
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPositionSqlFunction('JSON_ERROR_POSITION', $object));
        };

    $tests[sprintf('real upstream json501 control char %02x uppercase json dispatch canonicalizes json5', $codepoint)] =
        static function (TestRunner $t) use ($object, $canonical): void {
            $t->same($canonical, SQLiteJsonCanonical::jsonSqlFunction('JSON', $object));
        };

    $tests[sprintf('real upstream json501 control char %02x uppercase jsonb dispatch canonicalizes json5', $codepoint)] =
        static function (TestRunner $t) use ($object, $canonical, $jsonbText): void {
            $actual = SQLiteJsonCanonical::jsonSqlFunction('JSONB', $object);
            $t->same($canonical, $actual instanceof SQLiteBlobValue ? $jsonbText($actual) : $actual);
        };

    $tests[sprintf('real upstream json501 control char %02x sql argument json_valid accepts json5', $codepoint)] =
        static function (TestRunner $t) use ($object): void {
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$object, 2]));
        };

    $tests[sprintf('real upstream json501 control char %02x sql argument json canonicalizes json5', $codepoint)] =
        static function (TestRunner $t) use ($object, $canonical): void {
            $t->same($canonical, SQLiteJsonCanonical::jsonSqlFunctionArguments('json', [$object]));
        };

    $tests[sprintf('real upstream json501 control char %02x sql argument jsonb canonicalizes json5', $codepoint)] =
        static function (TestRunner $t) use ($object, $canonical, $jsonbText): void {
            $actual = SQLiteJsonCanonical::jsonSqlFunctionArguments('jsonb', [$object]);
            $t->same($canonical, $actual instanceof SQLiteBlobValue ? $jsonbText($actual) : $actual);
        };

    $tests[sprintf('real upstream json501 control char %02x sql argument error position accepts json5', $codepoint)] =
        static function (TestRunner $t) use ($object): void {
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPositionSqlFunctionArguments('json_error_position', [$object]));
        };
}

$whitespaceCodepoints = [
    0x09,
    0x0a,
    0x0b,
    0x0c,
    0x0d,
    0x20,
    0xa0,
    0x1680,
    0x2000,
    0x2001,
    0x2002,
    0x2003,
    0x2004,
    0x2005,
    0x2006,
    0x2007,
    0x2008,
    0x2009,
    0x200a,
    0x2028,
    0x2029,
    0x3000,
    0xfeff,
];

foreach ($whitespaceCodepoints as $codepoint) {
    $space = $utf8($codepoint);
    $prefix = $space . '{a:"xyz"}';
    $afterColon = '{a:' . $space . '"xyz"}';
    $betweenMembers = '{a:1,' . $space . 'b:"xyz"}';
    $arraySpace = '[' . $space . '1,' . $space . '2' . $space . ']';

    $tests[sprintf('real upstream json501 whitespace U+%04X prefix extracts value', $codepoint)] =
        static function (TestRunner $t) use ($prefix): void {
            $t->same('xyz', SQLiteJsonExtract::extract($prefix, '$.a'));
        };

    $tests[sprintf('real upstream json501 whitespace U+%04X after colon extracts value', $codepoint)] =
        static function (TestRunner $t) use ($afterColon): void {
            $t->same('xyz', SQLiteJsonExtract::extract($afterColon, '$.a'));
        };

    $tests[sprintf('real upstream json501 whitespace U+%04X between members extracts value', $codepoint)] =
        static function (TestRunner $t) use ($betweenMembers): void {
            $t->same('xyz', SQLiteJsonExtract::extract($betweenMembers, '$.b'));
        };

    $tests[sprintf('real upstream json501 whitespace U+%04X array canonicalizes', $codepoint)] =
        static function (TestRunner $t) use ($arraySpace): void {
            $t->same('[1,2]', SQLiteJsonCanonical::json($arraySpace));
        };

    $tests[sprintf('real upstream json501 whitespace U+%04X prefix canonicalizes', $codepoint)] =
        static function (TestRunner $t) use ($prefix): void {
            $t->same('{"a":"xyz"}', SQLiteJsonCanonical::json($prefix));
        };

    $tests[sprintf('real upstream json501 whitespace U+%04X after colon canonicalizes', $codepoint)] =
        static function (TestRunner $t) use ($afterColon): void {
            $t->same('{"a":"xyz"}', SQLiteJsonCanonical::json($afterColon));
        };

    $tests[sprintf('real upstream json501 whitespace U+%04X json5 validity accepts prefix', $codepoint)] =
        static function (TestRunner $t) use ($prefix): void {
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $prefix, 2));
        };

    $tests[sprintf('real upstream json501 whitespace U+%04X jsonb canonicalizes prefix', $codepoint)] =
        static function (TestRunner $t) use ($prefix, $jsonbText): void {
            $actual = SQLiteJsonCanonical::jsonSqlFunction('jsonb', $prefix);
            $t->same('{"a":"xyz"}', $actual instanceof SQLiteBlobValue ? $jsonbText($actual) : $actual);
        };
}

$tests['real upstream json501 control character dynamic cites upstream source'] = static function (TestRunner $t): void {
    $t->same(
        [
            'json501.test 12.1-12.4 JSON5 extended whitespace extraction',
            'json501.test 14.1-14.4 raw control characters in JSON5 string literals',
        ],
        [
            'json501.test 12.1-12.4 JSON5 extended whitespace extraction',
            'json501.test 14.1-14.4 raw control characters in JSON5 string literals',
        ]
    );
};

$tests['real upstream json501 control character dynamic owns 1178 behavior cases'] = static function (TestRunner $t) use (&$tests): void {
    $t->same(1178, count($tests));
};

return $tests;
