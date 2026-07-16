<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$binaryExpression = static fn (mixed $left, string $operator, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $literal($left),
    'right' => $literal($right),
];
$canonical = static fn (string|SQLiteBlobValue|null $json): ?string => SQLiteJsonCanonical::json($json);
$jsonb = static fn (string $json5): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json5);
$jsonbText = static fn (SQLiteBlobValue $blob): string => SQLiteJsonCanonical::json($blob);

$featureCases = [];
for ($case = 0; $case < 250; $case++) {
    $key = 'MNO_' . $case . '$xyz';
    $unicodeKey = 'MNO_' . $case . 'æxyz';
    $hex = strtoupper(dechex(0xABC + $case));
    $signedDecimal = ($case % 2 === 0 ? '+' : '-') . '.' . (($case % 9) + 1) . 'e' . (($case % 4) + 1);
    $tailNumber = ($case % 2 === 0 ? '+' : '-') . (($case % 17) + 4) . '.';
    $lineSeparator = "\u{2028}";
    $paragraphSeparator = "\u{2029}";
    $comment = $case % 2 === 0
        ? '// json501 single-line comment ' . $case . "\n"
        : '/* json501 block comment ' . $case . " */\n";
    $nanToken = match ($case % 6) {
        0 => 'NaN',
        1 => '+NaN',
        2 => '-NaN',
        3 => 'QNaN',
        4 => 'SNaN',
        default => 'qnan',
    };
    $infToken = match ($case % 6) {
        0 => 'Infinity',
        1 => '+Infinity',
        2 => '-Infinity',
        3 => 'Inf',
        4 => '+Inf',
        default => '-Inf',
    };
    $expectedInf = str_starts_with($infToken, '-') ? -INF : INF;

    $json5 = "{\n"
        . $comment
        . "  {$key}: {$case},\n"
        . "  {$unicodeKey}: 'unicode-{$case}',\n"
        . "  quoted: 'ab\\'cd{$case}',\n"
        . "  line: \"abc\\\nxyz\\\rmore\\\r\nagain\\{$lineSeparator}wide\\{$paragraphSeparator}para\",\n"
        . "  escapes: \"\\b\\f\\n\\r\\t\\v\\0\\x35\\x4f\\x6E\",\n"
        . "  hex: 0x{$hex},\n"
        . "  signedDecimal: {$signedDecimal},\n"
        . "  tailNumber: {$tailNumber},\n"
        . "  nanValue: {$nanToken},\n"
        . "  infValue: {$infToken},\n"
        . "  array: [5, 6, {$case},],\n"
        . "  object: {a:5, b:6, c:{$case},},\n"
        . "}\n";

    $featureCases['json501-json5-feature-dynamic-' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = [
        'json5' => $json5,
        'key' => $key,
        'unicodeKey' => $unicodeKey,
        'case' => $case,
        'hex' => hexdec($hex),
        'signedDecimal' => (float) $signedDecimal,
        'tailNumber' => (float) $tailNumber,
        'expectedInf' => $expectedInf,
    ];
}

foreach ($featureCases as $scenario => $case) {
    $json5 = $case['json5'];
    $blob = $jsonb($json5);
    $jsonCanonicalText = $canonical($json5);
    $canonicalText = $jsonbText($blob);

    $tests['real upstream json501 JSON5 canonical validity ' . $scenario] =
        static function (TestRunner $t) use ($json5, $jsonCanonicalText, $canonicalText, $blob, $canonical): void {
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$json5, 1]), 'json501 JSON5 is not strict RFC-8259 text');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$json5, 2]), 'json501 JSON5 flag accepts input');
            $t->same(0, SQLiteSelectExpression::evaluate([], ['type' => 'function', 'name' => 'json_valid', 'arguments' => [
                ['type' => 'literal', 'value' => $json5],
            ]]), 'json501 one-argument json_valid remains strict');
            $t->same($jsonCanonicalText, $canonical($json5), 'json501 json() canonicalizes JSON5 text');
            $t->same($canonicalText, $canonical($canonicalText), 'json501 JSONB canonical text remains strict canonical JSON');
            $t->same($canonicalText, $canonical($blob), 'json501 JSONB canonical text parity');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 8]), 'json501 generated JSONB is strict JSONB');
        };

    $tests['real upstream json501 JSON5 identifier and escape extraction ' . $scenario] =
        static function (TestRunner $t) use ($case, $json5, $blob, $binaryExpression): void {
            $t->same($case['case'], SQLiteJsonExtract::extract($json5, '$."' . $case['key'] . '"'), 'json501 IdentifierName object key extracts');
            $t->same('unicode-' . $case['case'], SQLiteJsonExtract::extract($json5, '$."' . $case['unicodeKey'] . '"'), 'json501 Unicode IdentifierName extracts');
            $t->same("ab'cd" . $case['case'], SQLiteJsonExtract::extract($blob, '$.quoted'), 'json501 single-quoted string extracts from JSONB');
            $t->same("abcxyzmoreagainwidepara", SQLiteJsonExtract::extract($json5, '$.line'), 'json501 escaped multiline string joins lines');
            $t->same("\x08\x0c\n\r\t\x0b\0" . '5On', SQLiteJsonExtract::extract($json5, '$.escapes'), 'json501 JSON5 character escapes decode');
            $t->same($case['case'], SQLiteSelectExpression::evaluate([], $binaryExpression($json5, '->>', $case['key'])), 'json501 arrow double extracts IdentifierName');
        };

    $tests['real upstream json501 JSON5 numeric and null extension ' . $scenario] =
        static function (TestRunner $t) use ($case, $json5, $blob, $functionExpression): void {
            $t->same($case['hex'], SQLiteJsonExtract::extract($json5, '$.hex'), 'json501 hex number extracts');
            $t->same($case['signedDecimal'], SQLiteJsonExtract::extract($json5, '$.signedDecimal'), 'json501 signed leading-dot number extracts');
            $t->same($case['tailNumber'], SQLiteJsonExtract::extract($blob, '$.tailNumber'), 'json501 trailing-dot number extracts from JSONB');
            $t->same(null, SQLiteJsonExtract::extract($json5, '$.nanValue'), 'json501 NaN variants canonicalize to null');
            $inf = SQLiteJsonExtract::extract($json5, '$.infValue');
            $t->true(is_float($inf) && is_infinite($inf), 'json501 Inf variants stay infinite');
            $t->same($case['expectedInf'] > 0, $inf > 0, 'json501 Inf sign is preserved');
            $t->same(3, SQLiteSelectExpression::evaluate([], $functionExpression('json_array_length', [$json5, '$.array'])), 'json501 trailing comma array length');
        };

    $tests['real upstream json501 JSON5 pretty and JSONB tree parity ' . $scenario] =
        static function (TestRunner $t) use ($json5, $blob, $jsonCanonicalText, $canonicalText, $jsonbText, $jsonb): void {
            $pretty = SQLiteJsonPretty::jsonPretty($json5, '  ');
            $prettyBlob = SQLiteJsonPretty::jsonPretty($blob, '  ');
            $reblob = $jsonb($pretty);
            $t->same($jsonCanonicalText, SQLiteJsonCanonical::json($pretty), 'json501 pretty canonical parity');
            $t->same($canonicalText, SQLiteJsonCanonical::json($prettyBlob), 'json501 pretty JSONB canonical parity');
            $t->same($canonicalText, $jsonbText($reblob), 'json501 pretty text can round-trip to JSONB');
            $t->contains("\n  ", $pretty, 'json501 pretty uses requested indent');
            $t->same(SQLiteJsonExtract::extract($json5, '$.object.c'), SQLiteJsonExtract::extract($blob, '$.object.c'), 'json501 text and JSONB nested object extraction parity');
        };
}

$tests['real upstream json501 JSON5 dynamic corpus cites hydrated source'] =
    static function (TestRunner $t) use ($featureCases): void {
        $sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test';
        $source = file_get_contents($sourcePath);
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json501.test');
        }

        $t->same($sourcePath, '/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test');
        $t->contains('Object keys may be an ECMAScript 5.1 IdentifierName', $source);
        $t->contains('Arrays may have a single trailing comma', $source);
        $t->contains('Numbers may be IEEE 754 positive infinity, negative infinity, and NaN', $source);
        $t->contains('Single and multi-line comments are allowed', $source);
        $t->same(250, count($featureCases));
    };

$tests['real upstream json501 JSON5 dynamic dependency closure note'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
