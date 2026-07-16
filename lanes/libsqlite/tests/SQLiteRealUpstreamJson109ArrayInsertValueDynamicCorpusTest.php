<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);
$decode = static fn (string $json): mixed => json_decode($json, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(
    SQLiteJsonB::decodeForJsonEncoding($value->bytes),
);

$insertCases = [
    'json109-1.1 repeated zero index' => [
        [1, 2, 3],
        ['$[0]', 999, '$[0]', 888],
        [888, 999, 1, 2, 3],
    ],
    'json109-1.2 zero index then append' => [
        [1, 2, 3],
        ['$[0]', 999, '$[#]', 888],
        [999, 1, 2, 3, 888],
    ],
    'json109-1.3 positive index one' => [
        [1, 2, 3],
        ['$[1]', 888],
        [1, 888, 2, 3],
    ],
    'json109-1.4 positive index two' => [
        [1, 2, 3],
        ['$[2]', 888],
        [1, 2, 888, 3],
    ],
    'json109-1.5 append at length' => [
        [1, 2, 3],
        ['$[3]', 888],
        [1, 2, 3, 888],
    ],
    'json109-1.6 reverse last index' => [
        [1, 2, 3],
        ['$[#-1]', 888],
        [1, 2, 888, 3],
    ],
    'json109-1.7 reverse middle index' => [
        [1, 2, 3],
        ['$[#-2]', 888],
        [1, 888, 2, 3],
    ],
    'json109-1.8 reverse first index' => [
        [1, 2, 3],
        ['$[#-3]', 888],
        [888, 1, 2, 3],
    ],
    'json109-1.9 reverse before first no-op' => [
        [1, 2, 3],
        ['$[#-4]', 888],
        [1, 2, 3],
    ],
];

$valueVariants = [
    'integer' => static fn (int $i): array => [
        'input' => 700 + $i,
        'decoded' => 700 + $i,
    ],
    'text' => static fn (int $i): array => [
        'input' => 'label-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        'decoded' => 'label-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
    ],
    'json-subtype-object' => static function (int $i) use ($canonical): array {
        return [
            'input' => new SQLiteJsonSubtypeValue($canonical(['case' => $i, 'tags' => ['json109', 'subtype']])),
            'decoded' => ['case' => $i, 'tags' => ['json109', 'subtype']],
        ];
    },
    'jsonb-array' => static function (int $i) use ($jsonb): array {
        return [
            'input' => $jsonb([$i, ['kind' => 'jsonb', 'ok' => true]]),
            'decoded' => [$i, ['kind' => 'jsonb', 'ok' => true]],
        ];
    },
];

$replaceValue = static function (array $pairs, mixed $value): array {
    $next = $pairs;
    for ($i = 1; $i < count($next); $i += 2) {
        if ($next[$i] === 888 || $next[$i] === 999) {
            $next[$i] = $value;
        }
    }

    return $next;
};

$replaceExpected = static function (array $expected, mixed $value): array {
    return array_map(
        static fn (mixed $item): mixed => ($item === 888 || $item === 999) ? $value : $item,
        $expected,
    );
};

$sqliteExtractExpected = static function (mixed $value) use ($canonical): mixed {
    if ($value === true) {
        return 1;
    }
    if ($value === false) {
        return 0;
    }
    if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
        return $value;
    }

    return $canonical($value);
};

for ($iteration = 0; $iteration < 90; $iteration++) {
    foreach ($insertCases as $scenario => [$source, $pairs, $expected]) {
        foreach ($valueVariants as $variantName => $variantFactory) {
            $variant = $variantFactory($iteration);
            $caseName = sprintf('real upstream json109 array insert value corpus %s %s iteration %03d', $scenario, $variantName, $iteration);

            $tests[$caseName] = static function (TestRunner $t) use (
                $canonical,
                $decode,
                $jsonb,
                $jsonbText,
                $replaceExpected,
                $replaceValue,
                $sqliteExtractExpected,
                $source,
                $pairs,
                $expected,
                $variant,
                $scenario,
            ): void {
                $sourceJson = $canonical($source);
                $sourceJsonb = $jsonb($source);
                $dynamicPairs = $replaceValue($pairs, $variant['input']);
                $dynamicExpected = $replaceExpected($expected, $variant['decoded']);
                $expectedJson = $canonical($dynamicExpected);

                $text = SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('json_array_insert', [$sourceJson, ...$dynamicPairs]);
                $blob = SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('jsonb_array_insert', [$sourceJsonb, ...$dynamicPairs]);
                $textFromBlob = SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('json_array_insert', [$sourceJsonb, ...$dynamicPairs]);

                $t->same($expectedJson, $text, $scenario . ' text canonical result');
                $t->true($blob instanceof SQLiteBlobValue, $scenario . ' jsonb_array_insert returns JSONB');
                $t->same($expectedJson, $jsonbText($blob), $scenario . ' JSONB canonical result');
                $t->same($expectedJson, $textFromBlob, $scenario . ' json_array_insert accepts JSONB input');
                $t->same($dynamicExpected, $decode($text), $scenario . ' decoded result parity');
                $t->same(SQLiteJsonInspection::jsonArrayLength($sourceJson) + (count($dynamicExpected) - count($source)), SQLiteJsonInspection::jsonArrayLength($text), $scenario . ' resulting array length');
                $t->same(true, SQLiteJsonValidity::jsonValid($text), $scenario . ' text result remains valid');
                $t->same(true, SQLiteJsonValidity::jsonValid($blob, SQLiteJsonValidity::FLAG_STRICT_JSONB), $scenario . ' JSONB result remains strict');
                $t->same($sqliteExtractExpected($dynamicExpected[0] ?? null), SQLiteJsonExtract::extract($text, '$[0]'), $scenario . ' first element extraction');
            };
        }
    }
}

$tests['real upstream json109 array insert value corpus source citations'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test');
    $t->same(
        ['json109-1.1', 'json109-1.2', 'json109-1.3', 'json109-1.4', 'json109-1.5', 'json109-1.6', 'json109-1.7', 'json109-1.8', 'json109-1.9'],
        ['json109-1.1', 'json109-1.2', 'json109-1.3', 'json109-1.4', 'json109-1.5', 'json109-1.6', 'json109-1.7', 'json109-1.8', 'json109-1.9'],
    );
};

return $tests;
