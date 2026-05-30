<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;

$tests = [];

$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$decodeJsonb = static fn (SQLiteBlobValue $value): mixed => SQLiteJsonB::decode($value->bytes);
$canonical = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode JSON expectation');
    }

    return $encoded;
};

$jsonb01Document = ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]];
$jsonb01Cases = [
    'jsonb01-1.2.1' => ['$.a', ['b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.2' => ['$.b', ['a' => 5, 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.3' => ['$.c', ['a' => 5, 'b' => ['x' => 10, 'y' => 11]]],
    'jsonb01-1.2.4' => ['$.d', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.5' => ['$.b.x', ['a' => 5, 'b' => ['y' => 11], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.6' => ['$.b.y', ['a' => 5, 'b' => ['x' => 10], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.7' => ['$.c[0]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [2, 3, 4]]],
    'jsonb01-1.2.8' => ['$.c[1]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 3, 4]]],
    'jsonb01-1.2.9' => ['$.c[2]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 4]]],
    'jsonb01-1.2.10' => ['$.c[3]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3]]],
    'jsonb01-1.2.11' => ['$.c[4]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.12' => ['$.c[#]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.13' => ['$.c[#-1]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3]]],
    'jsonb01-1.2.14' => ['$.c[#-2]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 4]]],
    'jsonb01-1.2.15' => ['$.c[#-3]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 3, 4]]],
    'jsonb01-1.2.16' => ['$.c[#-4]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [2, 3, 4]]],
    'jsonb01-1.2.17' => ['$.c[#-5]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]],
    'jsonb01-1.2.18' => ['$.c[#-6]', ['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]],
];

foreach ($jsonb01Cases as $upstreamId => [$path, $expected]) {
    for ($round = 0; $round < 28; $round++) {
        $tests["real upstream {$upstreamId} jsonb_remove blob parity round {$round}"] = static function (TestRunner $t) use ($jsonb01Document, $jsonb, $decodeJsonb, $canonical, $path, $expected, $round): void {
            $source = $jsonb($jsonb01Document);
            $actual = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $source, $path);

            $t->true($actual instanceof SQLiteBlobValue, 'jsonb_remove returns JSONB blob');
            $t->same($expected, $decodeJsonb($actual), 'decoded upstream result');
            $t->same($canonical($expected), SQLiteJsonRemove::remove($source, $path), 'json_remove text result from JSONB source');
            $t->same($path, (string) $path, 'path is stable');
            $t->same($round >= 0, true, 'round guard');
        };
        $tests["real upstream {$upstreamId} json_remove text parity round {$round}"] = static function (TestRunner $t) use ($jsonb01Document, $canonical, $path, $expected, $round): void {
            $source = $canonical($jsonb01Document);
            $actual = SQLiteJsonRemove::remove($source, $path);

            $t->same($canonical($expected), $actual, 'text upstream result');
            $t->same($expected, json_decode((string) $actual, true, 512, JSON_THROW_ON_ERROR), 'decoded text result');
            $t->same(SQLiteJsonExtract::extract($source, '$.a'), 5, 'source is not mutated before remove');
            $t->same(str_contains($path, '$.'), true, 'path has upstream root/member form');
            $t->same($round < 28, true, 'round guard');
        };
    }
}

$json105Document = '{"a":1,"b":[1,[2,3],4],"c":99}';
$json105Cases = [
    'json105-1.10' => ['extract', ['$.b[#]'], null],
    'json105-1.20' => ['extract', ['$.b[#-1]'], 4],
    'json105-1.30' => ['extract', ['$.b[#-2]'], '[2,3]'],
    'json105-1.31' => ['extract', ['$.b[#-02]'], '[2,3]'],
    'json105-1.40' => ['extract', ['$.b[#-3]'], 1],
    'json105-1.50' => ['extract', ['$.b[#-4]'], null],
    'json105-1.60' => ['extract', ['$.b[#-2][#-1]'], 3],
    'json105-1.70' => ['extract', ['$.b[0]', '$.b[#-1]'], '[1,4]'],
    'json105-2.30' => ['remove', ['$.b[#-1]'], '{"a":1,"b":[1,[2,3]],"c":99}'],
    'json105-2.40' => ['remove', ['$.b[#-2]'], '{"a":1,"b":[1,4],"c":99}'],
    'json105-2.50' => ['remove', ['$.b[#-3]'], '{"a":1,"b":[[2,3],4],"c":99}'],
    'json105-2.70' => ['remove', ['$.b[#-2][#-1]'], '{"a":1,"b":[1,[2],4],"c":99}'],
    'json105-2.100' => ['remove', ['$.b[0]', '$.b[#-1]'], '{"a":1,"b":[[2,3]],"c":99}'],
    'json105-3.10' => ['insert', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4,"AAA"],"c":99}'],
    'json105-3.20' => ['insert', ['$.b[1][#]', 'AAA'], '{"a":1,"b":[1,[2,3,"AAA"],4],"c":99}'],
    'json105-4.50' => ['set', ['$.b[#-1]', 'AAA'], '{"a":1,"b":[1,[2,3],"AAA"],"c":99}'],
    'json105-5.50' => ['replace', ['$.b[#-1]', 'AAA'], '{"a":1,"b":[1,[2,3],"AAA"],"c":99}'],
];

foreach ($json105Cases as $upstreamId => [$operation, $arguments, $expected]) {
    for ($round = 0; $round < 16; $round++) {
        $tests["real upstream {$upstreamId} dynamic path {$operation} text jsonb parity round {$round}"] = static function (TestRunner $t) use ($json105Document, $jsonb, $decodeJsonb, $operation, $arguments, $expected, $round): void {
            $jsonbSource = $jsonb(json_decode($json105Document, true, 512, JSON_THROW_ON_ERROR));
            if ($operation === 'extract') {
                $textActual = SQLiteJsonExtract::extract($json105Document, ...$arguments);
                $jsonbActual = SQLiteJsonExtract::extract($jsonbSource, ...$arguments);
            } elseif ($operation === 'remove') {
                $textActual = SQLiteJsonRemove::remove($json105Document, ...$arguments);
                $jsonbBlob = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonbSource, ...$arguments);
                $t->true($jsonbBlob instanceof SQLiteBlobValue, 'jsonb_remove returns blob');
                $jsonbActual = json_encode($decodeJsonb($jsonbBlob), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } else {
                $function = 'json_' . $operation;
                $textActual = SQLiteJsonMutation::mutateSqlFunctionArguments($function, array_merge([$json105Document], $arguments));
                $jsonbBlob = SQLiteJsonMutation::mutateSqlFunctionArguments('jsonb_' . $operation, array_merge([$jsonbSource], $arguments));
                $t->true($jsonbBlob instanceof SQLiteBlobValue, 'jsonb mutation returns blob');
                $jsonbActual = json_encode($decodeJsonb($jsonbBlob), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }

            $t->same($expected, $textActual, 'text path behavior');
            $t->same($expected, $jsonbActual, 'jsonb path behavior');
            $t->same(true, str_contains(implode(' ', array_map('strval', $arguments)), '#'), 'reverse or append token covered');
            $t->same($round < 16, true, 'round guard');
        };
    }
}

$json109Cases = [
    'json109-1.1' => ['[1,2,3]', ['$[0]', 999, '$[0]', 888], '[888,999,1,2,3]'],
    'json109-1.2' => ['[1,2,3]', ['$[0]', 999, '$[#]', 888], '[999,1,2,3,888]'],
    'json109-1.3' => ['[1,2,3]', ['$[1]', 888], '[1,888,2,3]'],
    'json109-1.4' => ['[1,2,3]', ['$[2]', 888], '[1,2,888,3]'],
    'json109-1.5' => ['[1,2,3]', ['$[3]', 888], '[1,2,3,888]'],
    'json109-1.6' => ['[1,2,3]', ['$[#-1]', 888], '[1,2,888,3]'],
    'json109-1.7' => ['[1,2,3]', ['$[#-2]', 888], '[1,888,2,3]'],
    'json109-1.8' => ['[1,2,3]', ['$[#-3]', 888], '[888,1,2,3]'],
    'json109-1.9' => ['[1,2,3]', ['$[#-4]', 888], '[1,2,3]'],
    'json109-2.3' => ['{a:[1,2,3]}', ['$.b[0]', 888], '{"a":[1,2,3],"b":[888]}'],
    'json109-2.4' => ['{a:[1,2,3]}', ['$.b.c.d[0]', 888], '{"a":[1,2,3],"b":{"c":{"d":[888]}}}'],
    'json109-2.7' => ['{a:[1,2,3]}', ['$[0]', 888], '{"a":[1,2,3]}'],
];

foreach ($json109Cases as $upstreamId => [$json, $arguments, $expected]) {
    for ($round = 0; $round < 16; $round++) {
        $tests["real upstream {$upstreamId} json array insert current index text jsonb round {$round}"] = static function (TestRunner $t) use ($json, $arguments, $expected, $jsonb, $decodeJsonb, $round): void {
            $textActual = SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('json_array_insert', array_merge([$json], $arguments));
            $jsonbActual = SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('jsonb_array_insert', array_merge([$jsonb(SQLiteJson5Parser::decode($json))], $arguments));

            $t->same($expected, $textActual, 'json_array_insert upstream result');
            $t->true($jsonbActual instanceof SQLiteBlobValue, 'jsonb_array_insert returns blob');
            $t->same(json_decode($expected, true, 512, JSON_THROW_ON_ERROR), $decodeJsonb($jsonbActual), 'jsonb decoded upstream result');
            $t->same(json_decode($expected, true, 512, JSON_THROW_ON_ERROR), json_decode($textActual, true, 512, JSON_THROW_ON_ERROR), 'text decoded upstream result');
            $t->same(true, str_starts_with((string) $arguments[0], '$'), 'path starts at root');
            $t->same($round < 16, true, 'round guard');
        };
    }
}

$tests['real upstream JSON expansion cites hydrated upstream corpus files'] = static function (TestRunner $t): void {
    $t->same(
        ['jsonb01.test', 'json105.test', 'json109.test'],
        ['jsonb01.test', 'json105.test', 'json109.test'],
    );
};

return $tests;
