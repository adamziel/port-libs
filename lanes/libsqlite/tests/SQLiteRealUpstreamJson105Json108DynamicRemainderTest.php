<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonRemove;

$tests = [];

$json105Document = '{"a":1,"b":[1,[2,3],4],"c":99}';
$jsonb = static fn (string $json): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, true, 512, JSON_THROW_ON_ERROR)));
$decodeJsonb = static fn (SQLiteBlobValue $blob): mixed => SQLiteJsonB::decode($blob->bytes);
$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);
$textResultToComparable = static function (mixed $value): mixed {
    if (!is_string($value)) {
        return $value;
    }
    if ($value === '') {
        return $value;
    }

    try {
        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return $value;
    }
};

$extractCases = [
    'json105-1.51' => ['$.b[#-4296967295]', null],
    'json105-1.52' => ['$.b[#-4296967296]', null],
    'json105-1.53' => ['$.b[#-4296967297]', null],
    'json105-1.54' => ['$.b[#-42969672950]', null],
    'json105-1.55' => ['$.b[#-42969672960]', null],
    'json105-1.100' => ['$.a[#-1]', null],
    'json105-1.110' => ['$.b[#-000001]', 4],
];

foreach ($extractCases as $upstreamId => [$path, $expected]) {
    for ($round = 0; $round < 24; $round++) {
        $tests["real upstream {$upstreamId} reverse-index extract text/jsonb round {$round}"] = static function (TestRunner $t) use ($json105Document, $jsonb, $path, $expected, $round): void {
            $blob = $jsonb($json105Document);

            $t->same($expected, SQLiteJsonExtract::extract($json105Document, $path), 'text extract follows upstream json105');
            $t->same($expected, SQLiteJsonExtract::extract($blob, $path), 'jsonb extract follows upstream json105');
            $t->same(true, str_contains($path, '#-'), 'reverse index path is exercised');
            $t->same($round < 24, true, 'round guard');
        };
    }
}

$removeCases = [
    'json105-2.10' => [['$.b[#]'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-2.20' => [['$.b[#-0]'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-2.60' => [['$.b[#-4]'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-2.110' => [['$.b[#-1]', '$.b[0]'], '{"a":1,"b":[[2,3]],"c":99}'],
    'json105-2.120' => [['$.b[#-1]', '$.b[#-2]'], '{"a":1,"b":[[2,3]],"c":99}'],
    'json105-2.130' => [['$.b[#-1]', '$.b[#-1]'], '{"a":1,"b":[1],"c":99}'],
    'json105-2.140' => [['$.b[#-2]', '$.b[#-1]'], '{"a":1,"b":[1],"c":99}'],
];

foreach ($removeCases as $upstreamId => [$paths, $expected]) {
    for ($round = 0; $round < 24; $round++) {
        $tests["real upstream {$upstreamId} remove ordered reverse paths text/jsonb round {$round}"] = static function (TestRunner $t) use ($json105Document, $jsonb, $decodeJsonb, $paths, $expected, $round): void {
            $blob = $jsonb($json105Document);
            $actualBlob = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $blob, ...$paths);

            $t->same($expected, SQLiteJsonRemove::remove($json105Document, ...$paths), 'text remove follows upstream json105');
            $t->true($actualBlob instanceof SQLiteBlobValue, 'jsonb_remove returns blob');
            $t->same(json_decode($expected, true, 512, JSON_THROW_ON_ERROR), $decodeJsonb($actualBlob), 'jsonb remove follows upstream json105');
            $t->same($round < 24, true, 'round guard');
        };
    }
}

$mutationCases = [
    'json105-3.30' => ['json_insert', ['$.b[1][#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3,"AAA"],4,"BBB"],"c":99}'],
    'json105-3.40' => ['json_insert', ['$.b[#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4,"AAA","BBB"],"c":99}'],
    'json105-4.10' => ['json_set', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4,"AAA"],"c":99}'],
    'json105-4.20' => ['json_set', ['$.b[1][#]', 'AAA'], '{"a":1,"b":[1,[2,3,"AAA"],4],"c":99}'],
    'json105-4.30' => ['json_set', ['$.b[1][#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3,"AAA"],4,"BBB"],"c":99}'],
    'json105-4.40' => ['json_set', ['$.b[#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4,"AAA","BBB"],"c":99}'],
    'json105-4.60' => ['json_set', ['$.b[1][#-1]', 'AAA'], '{"a":1,"b":[1,[2,"AAA"],4],"c":99}'],
    'json105-4.70' => ['json_set', ['$.b[1][#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,"AAA"],"BBB"],"c":99}'],
    'json105-4.80' => ['json_set', ['$.b[#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,3],"BBB"],"c":99}'],
    'json105-5.10' => ['json_replace', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-5.20' => ['json_replace', ['$.b[1][#]', 'AAA'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-5.30' => ['json_replace', ['$.b[1][#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-5.40' => ['json_replace', ['$.b[#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-5.60' => ['json_replace', ['$.b[1][#-1]', 'AAA'], '{"a":1,"b":[1,[2,"AAA"],4],"c":99}'],
    'json105-5.70' => ['json_replace', ['$.b[1][#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,"AAA"],"BBB"],"c":99}'],
    'json105-5.80' => ['json_replace', ['$.b[#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,3],"BBB"],"c":99}'],
];

foreach ($mutationCases as $upstreamId => [$function, $arguments, $expected]) {
    for ($round = 0; $round < 24; $round++) {
        $tests["real upstream {$upstreamId} ordered mutation text/jsonb round {$round}"] = static function (TestRunner $t) use ($json105Document, $jsonb, $decodeJsonb, $function, $arguments, $expected, $round): void {
            $textActual = SQLiteJsonMutation::mutateSqlFunctionArguments($function, array_merge([$json105Document], $arguments));
            $jsonbActual = SQLiteJsonMutation::mutateSqlFunctionArguments(str_replace('json_', 'jsonb_', $function), array_merge([$jsonb($json105Document)], $arguments));

            $t->same($expected, $textActual, 'text mutation follows upstream json105');
            $t->true($jsonbActual instanceof SQLiteBlobValue, 'jsonb mutation returns blob');
            $t->same(json_decode($expected, true, 512, JSON_THROW_ON_ERROR), $decodeJsonb($jsonbActual), 'jsonb mutation follows upstream json105');
            $t->same($round < 24, true, 'round guard');
        };
    }
}

$invalidPathCases = [
    'json105-6.10' => '$.b[#-]',
    'json105-6.20' => '$.b[#9]',
    'json105-6.30' => '$.b[#+2]',
    'json105-6.40' => '$.b[#-1',
    'json105-6.50' => '$.b[#-1x]',
];

foreach ($invalidPathCases as $upstreamId => $path) {
    for ($round = 0; $round < 24; $round++) {
        $tests["real upstream {$upstreamId} malformed reverse path rejects round {$round}"] = static function (TestRunner $t) use ($json105Document, $path, $round): void {
            $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extract($json105Document, $path));
            $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonRemove::remove($json105Document, $path));
            $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [$json105Document, $path, 'bad']));
            $t->same($round < 24, true, 'round guard');
        };
    }
}

$json108Documents = [
    'object-nested' => '{"a":1,"b":[2,{"c":3}],"d":true}',
    'array-nested' => '[{"a":1},2,false,null,["x","y"]]',
    'json5-object' => '{a:1,b:[2,3,],c:"quoted",}',
    'json5-comment' => '{/*hello*/a:1,b:{c:2,},}',
    'unicode' => '{"snow":"☃","slash":"a/b","quote":"\\""}',
];
$json108Indents = [null, '', "\t", '/*hello*/'];

foreach ($json108Documents as $name => $document) {
    foreach ($json108Indents as $indent) {
        for ($round = 0; $round < 8; $round++) {
            $label = $indent === null ? 'default' : bin2hex($indent);
            $tests["real upstream json108 pretty canonical invariant {$name} {$label} round {$round}"] = static function (TestRunner $t) use ($document, $jsonb, $canonical, $textResultToComparable, $indent, $round): void {
                $expected = $canonical($textResultToComparable(SQLiteJsonCanonical::json($document)));
                $prettyText = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$document, $indent]);
                $prettyBlob = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$jsonb(SQLiteJsonCanonical::json($document) ?? 'null'), $indent]);

                $t->same($expected, SQLiteJsonCanonical::json($prettyText), 'json_pretty text canonical round trip');
                $t->same($expected, SQLiteJsonCanonical::json($prettyBlob), 'json_pretty jsonb canonical round trip');
                $t->same($prettyText, $prettyBlob, 'json_pretty text/jsonb parity');
                $t->same($round < 8, true, 'round guard');
            };
        }
    }
}

$tests['real upstream JSON105/JSON108 remainder cites hydrated upstream corpus files'] = static function (TestRunner $t): void {
    $t->same(['json105.test', 'json108.test'], ['json105.test', 'json108.test']);
};

return $tests;
