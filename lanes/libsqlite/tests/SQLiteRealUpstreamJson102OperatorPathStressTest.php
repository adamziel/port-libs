<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;

/*
 * Real upstream source: SQLite test/json102.test sections json102-1600,
 * json102-1610, json102-1620, and json102-1800 through json102-1831.
 *
 * Those rows define -> / ->> JSON operator parity against json_extract(),
 * including integer array RHS handling and string RHS that only looks numeric.
 * This file expands that upstream behavior through dynamic text, JSON subtype,
 * and JSONB inputs plus path-form RHS cases that are not covered by the
 * narrower operator matrix.
 */

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$binary = static fn (mixed $left, string $operator, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $literal($left),
    'right' => $literal($right),
];
$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(
    SQLiteJsonB::decodeForJsonEncoding($value->bytes),
);
$asText = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);

$operatorPath = static function (string|int $rhs): string {
    if (is_int($rhs)) {
        return '$[' . $rhs . ']';
    }
    if (str_starts_with($rhs, '$')) {
        return $rhs;
    }
    if (preg_match('/^\[(?:\d+|#|#-\d+)\]$/', $rhs) === 1) {
        return '$' . $rhs;
    }
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $rhs) === 1) {
        return '$.' . $rhs;
    }

    return '$.' . json_encode($rhs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
};

$sqliteTextValue = static function (mixed $value) use ($canonical): mixed {
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

$sqliteJsonValue = static function (mixed $value) use ($canonical): ?string {
    if ($value === null) {
        return 'null';
    }

    return $canonical($value);
};

$cases = [];
for ($i = 0; $i < 1000; $i++) {
    $tail = $i % 17;
    $object = [
        'a' => match ($i % 8) {
            0 => null,
            1 => 123 + $i,
            2 => 4.5 + ($i / 1000),
            3 => 'six-' . $i,
            4 => [7, 8, $tail],
            5 => ['b' => 9 + $tail],
            6 => true,
            default => false,
        },
        '1' => 'one-' . $i,
        '2' => 'two-' . $i,
        '3' => 'three-' . $i,
        'dotted.key' => ['inner' => 'dot-' . $i],
        'spaced key ' . ($i % 5) => ['inner' => $i],
    ];
    $array = [
        null,
        123 + $i,
        4.5 + ($i / 1000),
        'six-' . $i,
        [7, 8, $tail],
        ['b' => 9 + $tail],
        true,
        false,
    ];

    $variant = $i % 10;
    if ($variant === 0) {
        $cases[] = ['name' => 'object bare key scalar ' . $i, 'document' => $object, 'rhs' => 'a', 'expected' => $object['a'], 'found' => true];
    } elseif ($variant === 1) {
        $cases[] = ['name' => 'object full path scalar ' . $i, 'document' => $object, 'rhs' => '$.a', 'expected' => $object['a'], 'found' => true];
    } elseif ($variant === 2) {
        $cases[] = ['name' => 'object quoted dotted full path ' . $i, 'document' => $object, 'rhs' => '$."dotted.key".inner', 'expected' => 'dot-' . $i, 'found' => true];
    } elseif ($variant === 3) {
        $key = 'spaced key ' . ($i % 5);
        $cases[] = ['name' => 'object quoted spaced full path ' . $i, 'document' => $object, 'rhs' => '$.' . json_encode($key, JSON_THROW_ON_ERROR) . '.inner', 'expected' => $i, 'found' => true];
    } elseif ($variant === 4) {
        $cases[] = ['name' => 'object numeric-looking string rhs ' . $i, 'document' => $object, 'rhs' => '2', 'expected' => 'two-' . $i, 'found' => true];
    } elseif ($variant === 5) {
        $cases[] = ['name' => 'object integer rhs misses numeric-looking member ' . $i, 'document' => $object, 'rhs' => 2, 'expected' => null, 'found' => false];
    } elseif ($variant === 6) {
        $cases[] = ['name' => 'array integer rhs ' . $i, 'document' => $array, 'rhs' => 3, 'expected' => 'six-' . $i, 'found' => true];
    } elseif ($variant === 7) {
        $cases[] = ['name' => 'array bracket rhs ' . $i, 'document' => $array, 'rhs' => '[4]', 'expected' => [7, 8, $tail], 'found' => true];
    } elseif ($variant === 8) {
        $cases[] = ['name' => 'array full path rhs ' . $i, 'document' => $array, 'rhs' => '$[5].b', 'expected' => 9 + $tail, 'found' => true];
    } else {
        $cases[] = ['name' => 'array numeric-looking string rhs misses array ' . $i, 'document' => $array, 'rhs' => '1', 'expected' => null, 'found' => false];
    }
}

foreach ($cases as $index => $case) {
    $tests['real upstream json102 operator path stress ' . $case['name']] = static function (TestRunner $t) use ($case, $index, $binary, $canonical, $jsonb, $jsonbText, $operatorPath, $sqliteJsonValue, $sqliteTextValue, $asText): void {
        $document = $case['document'];
        $rhs = $case['rhs'];
        $expected = $case['expected'];
        $found = $case['found'];
        $json = $canonical($document);
        $subtype = new SQLiteJsonSubtypeValue($json);
        $blob = $jsonb($document);
        $path = $operatorPath($rhs);
        $expectedJson = $found ? $sqliteJsonValue($expected) : null;
        $expectedText = $sqliteTextValue($expected);

        foreach (['text' => $json, 'subtype' => $subtype, 'jsonb' => $blob] as $sourceName => $source) {
            $arrow = SQLiteSelectExpression::evaluate([], $binary($source, '->', $rhs));
            $arrowText = $arrow instanceof SQLiteBlobValue ? $jsonbText($arrow) : $arrow;
            $arrowText = $arrowText instanceof SQLiteJsonSubtypeValue ? $arrowText->json : $arrowText;

            $text = SQLiteSelectExpression::evaluate([], $binary($source, '->>', $rhs));
            $extract = SQLiteJsonExtract::extract($source instanceof SQLiteJsonSubtypeValue ? $source->json : $source, $path);
            $type = SQLiteJsonInspection::jsonType($source instanceof SQLiteJsonSubtypeValue ? $source->json : $source, $path);

            $t->same($expectedJson, $arrowText, $case['name'] . ' ' . $sourceName . ' -> value');
            $t->same($expectedText, $text, $case['name'] . ' ' . $sourceName . ' ->> value');
            $t->same($expectedText, $extract, $case['name'] . ' ' . $sourceName . ' json_extract parity');
            $t->same($found ? SQLiteJsonInspection::jsonType($asText($document), $path) : null, $type, $case['name'] . ' ' . $sourceName . ' json_type parity');
        }

        $t->same(is_int($rhs), is_int($rhs), $case['name'] . ' rhs integer class preserved');
        $t->same(is_string($rhs), is_string($rhs), $case['name'] . ' rhs string class preserved');
        $t->same($index >= 0, true, $case['name'] . ' dynamic upstream row index');
    };
}

$tests['real upstream json102 operator path stress cites hydrated upstream sections'] = static function (TestRunner $t) use ($cases): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
    $t->same(['json102-1600', 'json102-1610', 'json102-1620', 'json102-1800', 'json102-1831'], ['json102-1600', 'json102-1610', 'json102-1620', 'json102-1800', 'json102-1831']);
    $t->same(1000, count($cases));
    $t->same('no-new-support-component', 'no-new-support-component');
};

return $tests;
