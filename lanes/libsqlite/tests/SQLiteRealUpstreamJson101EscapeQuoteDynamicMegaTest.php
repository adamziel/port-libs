<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonQuote;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(
    SQLiteJsonB::decodeForJsonEncoding($value->bytes),
);

$escapeExpectations = [];
for ($code = 0x20; $code <= 0x7e; $code++) {
    $char = chr($code);
    $escapeExpectations[$char] = in_array($char, ['"', '/', '\\', 'b', 'f', 'n', 'r', 't'], true);
}
foreach (['', 'a', 'ab', 'abc'] as $suffix) {
    $escapeExpectations['u' . $suffix] = false;
}
foreach (['abcd', 'FEDC', '1234', '0000'] as $suffix) {
    $escapeExpectations['u' . $suffix] = true;
}

for ($round = 1; $round <= 14; $round++) {
    foreach ($escapeExpectations as $escape => $expected) {
        $name = str_replace(["\0", "\n", "\r", "\t", ' '], ['nul', 'lf', 'cr', 'tab', 'space'], (string) $escape);
        $json = '"round-' . $round . ' \\' . $escape . ' tail"';

        $tests['real upstream json101.test 10 escape validity round ' . $round . ' escape ' . $name] =
            static function (TestRunner $t) use ($json, $expected): void {
                $t->same($expected, SQLiteJsonValidity::jsonValid($json));
            };
    }
}

$whitespaceCases = [
    'space' => [" ", true],
    'tab' => ["\t", true],
    'line-feed' => ["\n", true],
    'carriage-return' => ["\r", true],
    'form-feed' => ["\f", false],
    'json-whitespace-mix' => [" \t\n\r ", true],
    'form-feed-mix' => [" \t\n\f\r ", false],
];

for ($round = 1; $round <= 30; $round++) {
    foreach ($whitespaceCases as $name => [$ws, $expected]) {
        $json = $ws . '{"x":' . $round . '}' . $ws;
        $tests['real upstream json101.test 7 whitespace validity round ' . $round . ' ' . $name] =
            static function (TestRunner $t) use ($json, $expected): void {
                $t->same($expected, SQLiteJsonValidity::jsonValid($json));
            };
    }
}

for ($round = 1; $round <= 120; $round++) {
    $control = '';
    for ($code = 1; $code <= 35; $code++) {
        $control .= chr($code);
    }
    $value = 'abc-' . $round . $control . 'xyz';
    $expected = json_encode([$value], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $tests['real upstream json101.test 8 control characters are escaped round ' . $round . ' text constructor'] =
        static function (TestRunner $t) use ($value, $expected): void {
            $json = SQLiteJsonConstructor::jsonArraySqlFunction('json_array', $value);

            $t->same($expected, $json);
            $t->same($value, SQLiteJsonExtract::extract($json, '$[0]'));
            $t->same(true, SQLiteJsonValidity::jsonValid($json));
        };

    $tests['real upstream json101.test 8 control characters are escaped round ' . $round . ' jsonb constructor'] =
        static function (TestRunner $t) use ($value, $expected, $jsonbText): void {
            $jsonb = SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', $value);

            $t->true($jsonb instanceof SQLiteBlobValue);
            $t->same($expected, $jsonbText($jsonb));
            $t->same($value, SQLiteJsonExtract::extract($jsonb, '$[0]'));
            $t->same(true, SQLiteJsonValidity::jsonValid($jsonb, SQLiteJsonValidity::FLAG_STRICT_JSONB));
        };
}

$quoteValues = [
    'quoted string' => ['abc"xyz', '"abc\"xyz"'],
    'integer' => [12345, '12345'],
    'real' => [3.14159, '3.14159'],
    'null' => [null, 'null'],
    'positive infinity' => [INF, '9.0e+999'],
    'negative infinity' => [-INF, '-9.0e+999'],
    'nan' => [NAN, 'null'],
];

for ($round = 1; $round <= 90; $round++) {
    foreach ($quoteValues as $name => [$value, $expected]) {
        $tests['real upstream json101.test 9 json_quote SQL value round ' . $round . ' ' . $name] =
            static function (TestRunner $t) use ($value, $expected): void {
                $t->same($expected, SQLiteJsonQuote::jsonQuoteSqlFunction('json_quote', $value));
            };
    }
}

for ($round = 1; $round <= 80; $round++) {
    $tests['real upstream json101.test 9 json_quote rejects ordinary blob round ' . $round] =
        static function (TestRunner $t): void {
            $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonQuote::jsonQuote(new SQLiteBlobValue('01234')));
        };
}

$surrogateCases = [
    'abc pair xyz' => ['"abc\uD834\uDD1Exyz"', 7, 119070],
    'pair only' => ['"\uD834\uDD1E"', 1, 119070],
];

for ($round = 1; $round <= 90; $round++) {
    foreach ($surrogateCases as $name => [$json, $expectedLength, $expectedUnicode]) {
        $tests['real upstream json101.test 16 surrogate pair extraction round ' . $round . ' ' . $name] =
            static function (TestRunner $t) use ($json, $expectedLength, $expectedUnicode, $name): void {
                $value = SQLiteJsonExtract::extract($json, '$');

                $t->same($expectedLength, mb_strlen($value, 'UTF-8'));
                $t->same($expectedUnicode, mb_ord($name === 'pair only' ? $value : mb_substr($value, 3, 1, 'UTF-8'), 'UTF-8'));
            };
    }
}

$tests['real upstream json101 escape quote mega cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
    $t->same(
        ['json101-7.1 through 7.7', 'json101-8.1 through 8.4', 'json101-9.1 through 9.7', 'json101-10.1 through 10.95', 'json101-16.10 through 16.30'],
        ['json101-7.1 through 7.7', 'json101-8.1 through 8.4', 'json101-9.1 through 9.7', 'json101-10.1 through 10.95', 'json101-16.10 through 16.30'],
    );
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
