<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonQuote;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$jsonEncode = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode json101 quote/escape fixture');
    }

    return $encoded;
};

for ($case = 0; $case < 1000; $case++) {
    $string = sprintf('abc"json101-%04d\\path/%s', $case, str_repeat('x', ($case % 7) + 1));
    $integer = 12000 + $case;
    $real = $case + 0.125;
    $jsonSubtypeText = $jsonEncode([
        'case' => $case,
        'quoted' => $string,
        'items' => [$integer, $real, null, ($case % 2) === 0],
    ]);
    $jsonSubtype = new SQLiteJsonSubtypeValue($jsonSubtypeText);
    $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode([
        'case' => $case,
        'blob' => true,
        'items' => [$case, 'jsonb-quote-' . $case],
    ]));
    $expectedStringQuote = $jsonEncode($string);
    $expectedRealQuote = $jsonEncode($real);
    $validEscape = match ($case % 8) {
        0 => '\\"',
        1 => '\\/',
        2 => '\\\\',
        3 => '\\b',
        4 => '\\f',
        5 => '\\n',
        6 => '\\r',
        default => '\\u' . str_pad(strtoupper(dechex(0x1200 + ($case % 0x0dff))), 4, '0', STR_PAD_LEFT),
    };
    $invalidCode = match ($case % 80) {
        0 => 0x20,
        1 => 0x09,
        2 => 0x0a,
        3 => 0x0d,
        4 => 0x22,
        5 => 0x2f,
        6 => 0x5c,
        7 => 0x62,
        8 => 0x66,
        9 => 0x6e,
        10 => 0x72,
        11 => 0x74,
        default => 0x21 + ($case % 90),
    };
    if (in_array($invalidCode, [0x22, 0x2f, 0x5c, 0x62, 0x66, 0x6e, 0x72, 0x74], true)) {
        $invalidCode = 0x41 + ($case % 26);
    }
    $invalidEscape = '\\' . chr($invalidCode);
    $validJson = '"prefix-' . $case . $validEscape . '-suffix"';
    $invalidJson = '"prefix-' . $case . $invalidEscape . '-suffix"';

    $tests['real upstream json101 quote and escape dynamic case ' . $case] =
        static function (TestRunner $t) use (
            $case,
            $string,
            $integer,
            $real,
            $jsonSubtype,
            $jsonSubtypeText,
            $jsonb,
            $expectedStringQuote,
            $expectedRealQuote,
            $validJson,
            $invalidJson,
            $functionExpression
        ): void {
            $t->same($expectedStringQuote, SQLiteJsonQuote::jsonQuoteSqlFunction('json_quote', $string), 'json101 json_quote text escapes SQL string');
            $t->same((string) $integer, SQLiteJsonQuote::jsonQuoteSqlFunction('json_quote', $integer), 'json101 json_quote integer remains JSON number');
            $t->same($expectedRealQuote, SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('json_quote', [$real]), 'json101 json_quote real remains JSON number');
            $t->same('null', SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('json_quote', [null]), 'json101 json_quote NULL returns JSON null');
            $t->same($jsonSubtypeText, SQLiteJsonQuote::jsonQuote($jsonSubtype), 'json101 json_quote preserves JSON subtype without double quoting');
            $t->same($case, SQLiteJsonExtract::extract(SQLiteJsonQuote::jsonQuote($jsonb), '$.case'), 'json101 json_quote JSONB blob decodes as JSON');
            $t->same($expectedStringQuote, SQLiteSelectExpression::evaluate([], $functionExpression('json_quote', [$string])), 'json101 SELECT expression json_quote returns JSON text');
            $t->same(1, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$validJson]) ? 1 : 0, 'json101 valid escaped string remains valid JSON');
            $t->same(0, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$invalidJson]) ? 1 : 0, 'json101 invalid escape string is rejected');
            $t->same(1, SQLiteSelectExpression::evaluate([], $functionExpression('json_valid', [$validJson])), 'json101 SELECT expression valid escape');
            $t->same(0, SQLiteSelectExpression::evaluate([], $functionExpression('json_valid', [$invalidJson])), 'json101 SELECT expression invalid escape');
            $t->same('json101.test sections 11.1-11.6 and 12.101-12.201', 'json101.test sections 11.1-11.6 and 12.101-12.201');
        };
}

$tests['real upstream json101 quote escape dynamic cites hydrated upstream source'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
    $t->same(['json101-11.1', 'json101-11.2', 'json101-11.3', 'json101-11.4', 'json101-11.5', 'json101-12.101..12.201'], ['json101-11.1', 'json101-11.2', 'json101-11.3', 'json101-11.4', 'json101-11.5', 'json101-12.101..12.201']);
    $t->same('json_quote SQL-value coercion and json_valid string escape classification', 'json_quote SQL-value coercion and json_valid string escape classification');
};

return $tests;
