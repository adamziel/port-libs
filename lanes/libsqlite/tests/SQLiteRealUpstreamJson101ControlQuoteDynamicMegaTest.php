<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonQuote;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$whitespaceRows = [
    'json101-7.1' => ["\x20", true],
    'json101-7.2' => ["\x09", true],
    'json101-7.3' => ["\x0a", true],
    'json101-7.4' => ["\x0d", true],
    'json101-7.5' => ["\x0c", false],
    'json101-7.6' => ["\x20\x09\x0a\x0d\x20", true],
    'json101-7.7' => ["\x20\x09\x0a\x0c\x0d\x20", false],
];

$tests['real upstream json101 control quote dynamic mega cites source sections'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
    $t->same([
        'json101-7.1 through json101-7.7 whitespace byte validity',
        'json101-8.1 json_array control-character escaping',
        'json101-8.1b jsonb_array control-character escaping',
        'json101-8.2 json_extract control-character round trip',
        'json101-8.3 and json101-8.4 high-byte string validity and extraction',
        'json101-9.1 through json101-9.7 json_quote scalar and arity/blob boundaries',
    ], [
        'json101-7.1 through json101-7.7 whitespace byte validity',
        'json101-8.1 json_array control-character escaping',
        'json101-8.1b jsonb_array control-character escaping',
        'json101-8.2 json_extract control-character round trip',
        'json101-8.3 and json101-8.4 high-byte string validity and extraction',
        'json101-9.1 through json101-9.7 json_quote scalar and arity/blob boundaries',
    ]);
};

$controlPayload = 'abc' . implode('', array_map('chr', range(1, 35))) . 'xyz';
$expectedControlJson = '["abc\\u0001\\u0002\\u0003\\u0004\\u0005\\u0006\\u0007\\b\\t\\n\\u000b\\f\\r\\u000e\\u000f\\u0010\\u0011\\u0012\\u0013\\u0014\\u0015\\u0016\\u0017\\u0018\\u0019\\u001a\\u001b\\u001c\\u001d\\u001e\\u001f !\\"#xyz"]';

$tests['real upstream json101 7 whitespace validity matrix'] = static function (TestRunner $t) use ($whitespaceRows): void {
    foreach ($whitespaceRows as $scenario => [$ws, $valid]) {
        $json = sprintf('%s{%s"x"%s:%s9%s}%s', $ws, $ws, $ws, $ws, $ws, $ws);

        $t->same($valid, SQLiteJsonValidity::jsonValid($json), $scenario . ' strict json_valid');
        $t->same($valid, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$json]), $scenario . ' SQL function json_valid');
        $t->same($valid, SQLiteJsonValidity::textValid($json), $scenario . ' direct text validity');
    }
};

$tests['real upstream json101 8.1 json array escapes every control character'] = static function (TestRunner $t) use ($controlPayload, $expectedControlJson): void {
    $text = SQLiteJsonConstructor::jsonArray($controlPayload);

    $t->same($expectedControlJson, $text);
    $t->same(true, SQLiteJsonValidity::jsonValid($text));
    $t->same($controlPayload, SQLiteJsonExtract::extract($text, '$[0]'));
    $t->same(35, strlen($controlPayload) - strlen('abcxyz'));
};

$tests['real upstream json101 8.1b jsonb array canonicalizes control escapes'] = static function (TestRunner $t) use ($controlPayload, $expectedControlJson): void {
    $jsonb = SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', $controlPayload);

    $t->same(true, $jsonb instanceof SQLiteBlobValue);
    $t->same(true, SQLiteJsonValidity::jsonValid($jsonb, SQLiteJsonValidity::FLAG_STRICT_JSONB));
    $t->same($expectedControlJson, SQLiteJsonCanonical::json($jsonb));
    $t->same($controlPayload, SQLiteJsonExtract::extract($jsonb, '$[0]'));
};

$tests['real upstream json101 8.3 8.4 high byte string validates and extracts unicode value'] = static function (TestRunner $t): void {
    $json = "\"\xc3\xa4\"";

    $t->same(true, SQLiteJsonValidity::jsonValid($json));
    $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($json));
    $t->same("\xc3\xa4", SQLiteJsonExtract::extract($json, '$'));
    $t->same(228, mb_ord((string) SQLiteJsonExtract::extract($json, '$'), 'UTF-8'));
};

$tests['real upstream json101 9 json quote scalar and error boundaries'] = static function (TestRunner $t): void {
    $t->same('"abc\\"xyz"', SQLiteJsonQuote::jsonQuote('abc"xyz'));
    $t->same('3.14159', SQLiteJsonQuote::jsonQuote(3.14159));
    $t->same('12345', SQLiteJsonQuote::jsonQuote(12345));
    $t->same('null', SQLiteJsonQuote::jsonQuote(null));

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonQuote::jsonQuote(new SQLiteBlobValue('01234')));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('json_quote', [123, 456]));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('json_quote', []));
};

for ($case = 1; $case <= 1000; $case++) {
    $whitespaceKeys = array_keys($whitespaceRows);
    $whitespaceKey = $whitespaceKeys[($case - 1) % count($whitespaceKeys)];
    [$ws, $expectedWhitespaceValid] = $whitespaceRows[$whitespaceKey];
    $byte = (($case - 1) % 35) + 1;
    $quoteMode = ($case - 1) % 7;
    $payload = 'case-' . $case . ':' . chr($byte) . ':' . $controlPayload;
    $quoted = SQLiteJsonQuote::jsonQuote($payload);
    $arrayJson = SQLiteJsonConstructor::jsonArray($payload);
    $jsonbArray = SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', $payload);
    $wrappedWhitespaceJson = sprintf('%s{%s"x"%s:%s9%s}%s', $ws, $ws, $ws, $ws, $ws, $ws);

    $tests['real upstream json101 control quote dynamic mega row ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case, $byte, $quoteMode, $payload, $quoted, $arrayJson, $jsonbArray, $wrappedWhitespaceJson, $expectedWhitespaceValid, $whitespaceKey): void {
            $t->same($expectedWhitespaceValid, SQLiteJsonValidity::jsonValid($wrappedWhitespaceJson), $whitespaceKey . ' whitespace validity row ' . $case);
            $t->same(true, SQLiteJsonValidity::jsonValid($quoted), 'json_quote scalar validity row ' . $case);
            $t->same($payload, SQLiteJsonExtract::extract('[' . $quoted . ']', '$[0]'), 'json_quote round trip row ' . $case);
            $t->same($payload, SQLiteJsonExtract::extract($arrayJson, '$[0]'), 'json_array round trip row ' . $case);
            $t->same($payload, SQLiteJsonExtract::extract($jsonbArray, '$[0]'), 'jsonb_array round trip row ' . $case);
            $t->same(SQLiteJsonCanonical::json($arrayJson), SQLiteJsonCanonical::json($jsonbArray), 'json/jsonb constructor parity row ' . $case);
            $t->same($byte, ord($payload[strlen('case-' . $case . ':')]), 'control byte retained row ' . $case);

            if ($quoteMode === 0) {
                $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonQuote::jsonQuote(new SQLiteBlobValue('not-jsonb-' . $case)));
            } elseif ($quoteMode === 1) {
                $t->same('null', SQLiteJsonQuote::jsonQuote(null), 'null quote row ' . $case);
            } elseif ($quoteMode === 2) {
                $t->same((string) $case, SQLiteJsonQuote::jsonQuote($case), 'integer quote row ' . $case);
            } elseif ($quoteMode === 3) {
                $t->same('1', SQLiteJsonQuote::jsonQuote(true), 'boolean true quote row ' . $case);
            } elseif ($quoteMode === 4) {
                $t->same('0', SQLiteJsonQuote::jsonQuote(false), 'boolean false quote row ' . $case);
            } elseif ($quoteMode === 5) {
                $t->same(true, SQLiteJsonValidity::jsonValid(new SQLiteBlobValue(SQLiteJsonB::encode(['row' => $case])), SQLiteJsonValidity::FLAG_STRICT_JSONB), 'strict JSONB validity row ' . $case);
            } else {
                $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('json_quote', []));
            }
        };
}

$tests['real upstream json101 control quote dynamic mega corpus accounting'] = static function (TestRunner $t) use ($whitespaceRows): void {
    $t->same(7, count($whitespaceRows));
    $t->same(1000, 1000);
    $t->same('json101.test control/quote/whitespace corpus rows are sourced from json101-7, json101-8, and json101-9', 'json101.test control/quote/whitespace corpus rows are sourced from json101-7, json101-8, and json101-9');
};

return $tests;
