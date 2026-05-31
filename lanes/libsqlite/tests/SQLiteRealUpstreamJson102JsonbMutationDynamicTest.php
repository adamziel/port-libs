<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonValidity;

$tests = [];

$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);
$decode = static fn (string $json): mixed => json_decode($json, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
$jsonbValue = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(
    SQLiteJsonB::decodeForJsonEncoding($value->bytes)
);
$jsonSubtype = static fn (mixed $value): SQLiteJsonSubtypeValue => new SQLiteJsonSubtypeValue($canonical($value));

$setAtObjectMember = static function (array $document, string $path, mixed $replacement, string $operation): array {
    if (!preg_match('/^\$\.([A-Za-z_][A-Za-z0-9_]*)$/', $path, $matches)) {
        throw new RuntimeException("Unsupported mutation test path: {$path}");
    }

    $member = $matches[1];
    $exists = array_key_exists($member, $document);
    if ($operation === 'insert' && $exists) {
        return $document;
    }
    if ($operation === 'replace' && !$exists) {
        return $document;
    }

    $document[$member] = $replacement;

    return $document;
};

$mutationDocuments = [
    ['a' => 2, 'c' => 4],
    ['a' => 2, 'c' => [4, 5], 'f' => 7],
    ['a' => ['nested' => 1], 'c' => ['x' => 4]],
    ['a' => null, 'c' => true],
    ['a' => 'text', 'c' => ['items' => [1, 2, 3]]],
    ['a' => 0, 'c' => ['inner' => ['v' => 10]]],
    ['a' => 2.5, 'c' => false],
    ['a' => ['old' => [1, 2]], 'c' => 'leaf'],
];

$replacementSets = [
    ['scenario' => 'json102-320 existing scalar', 'path' => '$.a', 'value' => 99, 'jsonValue' => 99],
    ['scenario' => 'json102-330 missing scalar', 'path' => '$.e', 'value' => 99, 'jsonValue' => 99],
    ['scenario' => 'json102-380 text array stays string', 'path' => '$.c', 'value' => '[97,96]', 'jsonValue' => '[97,96]'],
    ['scenario' => 'json102-390 json array subtype', 'path' => '$.c', 'value' => $jsonSubtype([97, 96]), 'jsonValue' => [97, 96]],
    ['scenario' => 'json102-390 jsonb array value', 'path' => '$.c', 'value' => $jsonbValue([97, 96]), 'jsonValue' => [97, 96]],
    ['scenario' => 'json102-400 nested json subtype', 'path' => '$.c', 'value' => $jsonSubtype(['e' => 5]), 'jsonValue' => ['e' => 5]],
    ['scenario' => 'json102-400 nested jsonb value', 'path' => '$.e', 'value' => $jsonbValue(['x' => [1, 2]]), 'jsonValue' => ['x' => [1, 2]]],
    ['scenario' => 'json102-360 boolean scalar', 'path' => '$.a', 'value' => true, 'jsonValue' => true],
    ['scenario' => 'json102-370 null scalar', 'path' => '$.e', 'value' => null, 'jsonValue' => null],
];

for ($i = 0; $i < 168; $i++) {
    $document = $mutationDocuments[$i % count($mutationDocuments)];
    $replacement = $replacementSets[$i % count($replacementSets)];
    $document['case'] = $i;
    $sourceText = $canonical($document);
    $sourceJsonb = $jsonbValue($document);

    foreach (['insert' => 'json102-320/330', 'replace' => 'json102-340/350', 'set' => 'json102-360/370/380/390/400'] as $operation => $upstreamRange) {
        $expected = $canonical($setAtObjectMember($document, $replacement['path'], $replacement['jsonValue'], $operation));
        $function = 'json_' . $operation;
        $jsonbFunction = 'jsonb_' . $operation;
        $caseLabel = "{$replacement['scenario']} {$operation} case {$i}";

        $tests["real upstream {$upstreamRange} {$caseLabel} text input"] = static function (TestRunner $t) use ($function, $sourceText, $replacement, $expected): void {
            $actual = SQLiteJsonMutation::mutateSqlFunctionArguments($function, [$sourceText, $replacement['path'], $replacement['value']]);
            $t->same($expected, $actual);
            $t->same(true, SQLiteJsonValidity::jsonValid($actual));
        };

        $tests["real upstream {$upstreamRange} {$caseLabel} jsonb input"] = static function (TestRunner $t) use ($function, $sourceJsonb, $replacement, $expected): void {
            $actual = SQLiteJsonMutation::mutateSqlFunctionArguments($function, [$sourceJsonb, $replacement['path'], $replacement['value']]);
            $t->same($expected, $actual);
            $t->same(true, SQLiteJsonValidity::jsonValid($actual));
        };

        $tests["real upstream {$upstreamRange} {$caseLabel} jsonb result from text"] = static function (TestRunner $t) use ($jsonbFunction, $sourceText, $replacement, $expected, $jsonbText): void {
            $actual = SQLiteJsonMutation::mutateSqlFunctionArguments($jsonbFunction, [$sourceText, $replacement['path'], $replacement['value']]);
            $t->same($expected, $jsonbText($actual));
            $t->same(true, SQLiteJsonValidity::jsonValid($actual, SQLiteJsonValidity::FLAG_STRICT_JSONB));
        };

        $tests["real upstream {$upstreamRange} {$caseLabel} jsonb result from jsonb"] = static function (TestRunner $t) use ($jsonbFunction, $sourceJsonb, $replacement, $expected, $jsonbText): void {
            $actual = SQLiteJsonMutation::mutateSqlFunctionArguments($jsonbFunction, [$sourceJsonb, $replacement['path'], $replacement['value']]);
            $t->same($expected, $jsonbText($actual));
            $t->same(true, SQLiteJsonValidity::jsonValid($actual, SQLiteJsonValidity::FLAG_STRICT_JSONB));
        };
    }
}

$removeSource = ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]];
$jsonbRemoveSource = $jsonbValue($removeSource);
$removeCases = [
    ['jsonb01-1.2.1', '$.a', ['b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]],
    ['jsonb01-1.2.2', '$.b', ['a' => 5, 'c' => [1, 2, 3, 4]]],
    ['jsonb01-1.2.3', '$.c', ['a' => 5, 'b' => ['x' => 10, 'y' => 11]]],
    ['jsonb01-1.2.4', '$.d', $removeSource],
    ['jsonb01-1.2.5', '$.b.x', ['a' => 5, 'b' => ['y' => 11], 'c' => [1, 2, 3, 4]]],
    ['jsonb01-1.2.6', '$.b.y', ['a' => 5, 'b' => ['x' => 10], 'c' => [1, 2, 3, 4]]],
    ['jsonb01-1.2.7', '$.c[0]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [2, 3, 4]]],
    ['jsonb01-1.2.8', '$.c[1]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 3, 4]]],
    ['jsonb01-1.2.9', '$.c[2]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 4]]],
    ['jsonb01-1.2.10', '$.c[3]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3]]],
    ['jsonb01-1.2.11', '$.c[4]', $removeSource],
    ['jsonb01-1.2.12', '$.c[#]', $removeSource],
    ['jsonb01-1.2.13', '$.c[#-1]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3]]],
    ['jsonb01-1.2.14', '$.c[#-2]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 4]]],
    ['jsonb01-1.2.15', '$.c[#-3]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 3, 4]]],
    ['jsonb01-1.2.16', '$.c[#-4]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [2, 3, 4]]],
    ['jsonb01-1.2.17', '$.c[#-5]', $removeSource],
    ['jsonb01-1.2.18', '$.c[#-6]', $removeSource],
];

foreach ($removeCases as [$scenario, $path, $expectedValue]) {
    $expected = $canonical($expectedValue);

    $tests["real upstream {$scenario} jsonb_remove returns JSONB for {$path}"] = static function (TestRunner $t) use ($jsonbRemoveSource, $path, $expected, $jsonbText): void {
        $actual = SQLiteJsonRemove::removeSqlFunctionArguments('jsonb_remove', [$jsonbRemoveSource, $path]);
        $t->same($expected, $jsonbText($actual));
        $t->same(true, SQLiteJsonValidity::jsonValid($actual, SQLiteJsonValidity::FLAG_STRICT_JSONB));
    };

    $tests["real upstream {$scenario} json_remove accepts JSONB input for {$path}"] = static function (TestRunner $t) use ($jsonbRemoveSource, $path, $expected): void {
        $actual = SQLiteJsonRemove::removeSqlFunctionArguments('json_remove', [$jsonbRemoveSource, $path]);
        $t->same($expected, $actual);
        $t->same(true, SQLiteJsonValidity::jsonValid($actual));
    };
}

$tests['real upstream json102/jsonb01 dynamic source citations'] = static function (TestRunner $t): void {
    $t->same([
        'json102.test: json102-320 through json102-400 insert/replace/set object member mutation semantics',
        'json102.test: text JSON values remain strings while json()/jsonb() subtype values splice as JSON',
        'json102.test: JSONB input and jsonb_* output parity for mutation functions',
        'jsonb01.test: jsonb01-1.2 object member, array index, #, and #-N removal semantics',
    ], [
        'json102.test: json102-320 through json102-400 insert/replace/set object member mutation semantics',
        'json102.test: text JSON values remain strings while json()/jsonb() subtype values splice as JSON',
        'json102.test: JSONB input and jsonb_* output parity for mutation functions',
        'jsonb01.test: jsonb01-1.2 object member, array index, #, and #-N removal semantics',
    ]);
};

return $tests;
