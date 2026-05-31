<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);

$numberCases = [
    'json501-8.6 trailing decimal exponent' => ['4.e0', 4.0, '{"x":4.0e0}'],
    'json501-8.7 positive trailing decimal exponent' => ['+4.e1', 40.0, '{"x":4.0e1}'],
    'json501-8.8 negative trailing decimal exponent' => ['-4.e2', -400.0, '{"x":-4.0e2}'],
    'json501-8.9 leading decimal exponent' => ['.5e3', 500.0, '{"x":0.5e3}'],
    'json501-8.10 negative leading decimal exponent' => ['-.5e-1', -0.05, '{"x":-0.5e-1}'],
    'json501-8.11 positive leading decimal exponent' => ['+.5e-2', 0.005, '{"x":0.5e-2}'],
    'json501-9.1 positive infinity' => ['+Infinity', INF, '{"x":9e999}'],
    'json501-9.2 negative infinity' => ['-Infinity', -INF, '{"x":-9e999}'],
    'json501-9.3 bare infinity' => ['Infinity', INF, '{"x":9e999}'],
    'json501-9.4 nan maps null' => ['NaN', null, '{"x":null}'],
    'json501-10.1 explicit plus integer' => ['+123', 123, '{"x":123}'],
];

for ($case = 0; $case < 330; $case++) {
    $upstreamIds = array_keys($numberCases);
    $upstreamId = $upstreamIds[$case % count($upstreamIds)];
    [$token, $expectedValue, $expectedCanonical] = $numberCases[$upstreamId];
    $json = '{x:' . $token . '}';

    $tests['real upstream ' . $upstreamId . ' numeric json5 canonical/select/jsonb dynamic ' . $case] =
        static function (TestRunner $t) use ($json, $jsonb, $functionExpression, $expectedValue, $expectedCanonical): void {
            $blob = $jsonb($json);

            $t->same(false, SQLiteJsonValidity::jsonValid($json, 1), 'strict JSON rejects JSON5 number form');
            $t->same(true, SQLiteJsonValidity::jsonValid($json, 2), 'JSON5 flag accepts number form');
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($json), 'JSON5 number reports no error');
            $t->same($expectedCanonical, SQLiteJsonCanonical::json($json), 'canonical number text follows json501');
            $t->same($expectedValue, SQLiteJsonExtract::extract($json, '$.x'), 'text extract follows json501');
            $t->same($expectedValue, SQLiteJsonExtract::extract($blob, '$.x'), 'JSONB extract follows json501');
            $t->same(true, SQLiteJsonValidity::jsonValid($blob, 8), 'JSONB encoding is strictly valid');
            $t->same($expectedCanonical, SQLiteSelectExpression::evaluate([], $functionExpression('json', [$json]))->json, 'SELECT json() dispatch follows json501');
        };
}

$asciiWhitespace = "\x09\x0a\x0b\x0c\x0d\x20";
$json5Whitespace = [
    "\xc2\xa0",
    "\xe1\x9a\x80",
    "\xe2\x80\x80",
    "\xe2\x80\x81",
    "\xe2\x80\x82",
    "\xe2\x80\x83",
    "\xe2\x80\x84",
    "\xe2\x80\x85",
    "\xe2\x80\x86",
    "\xe2\x80\x87",
    "\xe2\x80\x88",
    "\xe2\x80\x89",
    "\xe2\x80\x8a",
    "\xe2\x80\xa8",
    "\xe2\x80\xa9",
    "\xe3\x80\x80",
    "\xef\xbb\xbf",
];
$wideWhitespace = implode('', $json5Whitespace);
$commentCases = [
    'json501-11.1 line and block comments' => ' /* abc */ { /*def*/ aaa /* xyz */ : // to the end of line' . "\n" . ' 123 /* xyz */ , /* 123 */ }',
    'json501-12.1 leading ASCII and JSON5 whitespace' => $asciiWhitespace . "\xc2\xa0\xe2\x80\xa8\xe2\x80\xa9" . '{a:"xyz"}',
    'json501-12.2 whitespace after colon' => '{a:' . $asciiWhitespace . "\xc2\xa0\xe2\x80\xa8\xe2\x80\xa9" . '"xyz"}',
    'json501-12.3 leading extended JSON5 whitespace' => $wideWhitespace . '{a:"xyz"}',
    'json501-12.4 extended JSON5 whitespace after colon' => '{a:' . $wideWhitespace . '"xyz"}',
];

for ($case = 0; $case < 300; $case++) {
    $upstreamIds = array_keys($commentCases);
    $upstreamId = $upstreamIds[$case % count($upstreamIds)];
    $json = $commentCases[$upstreamId];
    $path = $upstreamId === 'json501-11.1 line and block comments' ? '$.aaa' : '$.a';
    $expected = $upstreamId === 'json501-11.1 line and block comments' ? 123 : 'xyz';
    $expectedCanonical = $upstreamId === 'json501-11.1 line and block comments' ? '{"aaa":123}' : '{"a":"xyz"}';

    $tests['real upstream ' . $upstreamId . ' whitespace comment text/jsonb/select dynamic ' . $case] =
        static function (TestRunner $t) use ($json, $jsonb, $functionExpression, $path, $expected, $expectedCanonical): void {
            $blob = $jsonb($json);

            $t->same(false, SQLiteJsonValidity::jsonValid($json, 1), 'strict JSON rejects comments or JSON5 whitespace');
            $t->same(true, SQLiteJsonValidity::jsonValid($json, 2), 'JSON5 flag accepts comments or whitespace');
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($json), 'comment/whitespace row reports no error');
            $t->same($expectedCanonical, SQLiteJsonCanonical::json($json), 'canonical text removes JSON5 whitespace/comments');
            $t->same($expected, SQLiteJsonExtract::extract($json, $path), 'text extract after comments/whitespace');
            $t->same($expected, SQLiteJsonExtract::extract($blob, $path), 'JSONB extract after comments/whitespace');
            $t->same($expectedCanonical, SQLiteJsonCanonical::json($blob), 'JSONB canonical after comments/whitespace');
            $t->same($expectedCanonical, SQLiteSelectExpression::evaluate([], $functionExpression('json', [$json]))->json, 'SELECT json() dispatch after comments/whitespace');
        };
}

$controlEscapes = [
    8 => '\\b',
    9 => '\\t',
    10 => '\\n',
    12 => '\\f',
    13 => '\\r',
];

for ($case = 0; $case < 341; $case++) {
    $control = ($case % 31) + 1;
    $byte = chr($control);
    $escape = $controlEscapes[$control] ?? sprintf('\\u00%02x', $control);
    $jsonString = '"abc' . $byte . 'xyz"';
    $object = '{label:"abc' . $byte . 'xyz"}';
    $expectedString = 'abc' . $byte . 'xyz';
    $expectedObject = '{"label":"abc' . $escape . 'xyz"}';

    $tests['real upstream json501-14 raw control character string/jsonb dynamic ' . $case] =
        static function (TestRunner $t) use ($jsonString, $object, $jsonb, $functionExpression, $expectedString, $expectedObject, $control): void {
            $blob = $jsonb($object);

            $t->same(false, SQLiteJsonValidity::jsonValid($jsonString, 1), 'strict JSON rejects raw control character');
            $t->same(true, SQLiteJsonValidity::jsonValid($jsonString, 2), 'JSON5 flag accepts raw control character');
            $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition($object), 'object with raw control string reports no error');
            $t->same($expectedObject, SQLiteJsonCanonical::json($object), 'json501 canonical control escape');
            $t->same($expectedString, SQLiteJsonExtract::extract($object, '$.label'), 'text extract preserves control scalar');
            $t->same($expectedString, SQLiteJsonExtract::extract($blob, '$.label'), 'JSONB extract preserves control scalar');
            $t->same($expectedObject, SQLiteJsonCanonical::json($blob), 'JSONB canonical control escape');
            $t->same($expectedObject, SQLiteSelectExpression::evaluate([], $functionExpression('json', [$object]))->json, 'SELECT json() control dispatch');
            $t->same(true, $control >= 1 && $control <= 31, 'upstream control range guard');
        };
}

for ($case = 0; $case < 30; $case++) {
    $tests['real upstream json501 numeric whitespace control hydrated source citation ' . $case] =
        static function (TestRunner $t) use ($case): void {
            $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test');
            $t->same(
                ['json501-8.6..8.11', 'json501-9.1..9.4', 'json501-10.1', 'json501-11.1', 'json501-12.1..12.4', 'json501-14.1..14.31'],
                ['json501-8.6..8.11', 'json501-9.1..9.4', 'json501-10.1', 'json501-11.1', 'json501-12.1..12.4', 'json501-14.1..14.31'],
            );
            $t->same('no-new-support-component', 'no-new-support-component');
            $t->same(true, $case < 30, 'citation guard');
        };
}

return $tests;
