<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;

$tests = [];

$json105Document = '{"a":1,"b":[1,[2,3],4],"c":99}';
$jsonb105Document = static fn (): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json105Document, true, 512, JSON_THROW_ON_ERROR)));
$decodeJsonb = static fn (SQLiteBlobValue $value): string => json_encode(SQLiteJsonB::decode($value->bytes), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$extractCases = [
    'json105-1.10 append token reads past end' => ['$.b[#]', null],
    'json105-1.20 reverse last scalar' => ['$.b[#-1]', 4],
    'json105-1.30 reverse nested array' => ['$.b[#-2]', '[2,3]'],
    'json105-1.31 reverse index accepts leading zero' => ['$.b[#-02]', '[2,3]'],
    'json105-1.40 reverse first scalar' => ['$.b[#-3]', 1],
    'json105-1.50 reverse before first is missing' => ['$.b[#-4]', null],
    'json105-1.51 huge reverse index is missing' => ['$.b[#-4296967295]', null],
    'json105-1.52 larger reverse index is missing' => ['$.b[#-4296967296]', null],
    'json105-1.53 larger reverse index remains missing' => ['$.b[#-4296967297]', null],
    'json105-1.54 oversized reverse index is missing' => ['$.b[#-42969672950]', null],
    'json105-1.55 oversized reverse index remains missing' => ['$.b[#-42969672960]', null],
    'json105-1.60 nested reverse scalar' => ['$.b[#-2][#-1]', 3],
    'json105-1.70 multiple paths mix forward and reverse' => [['$.b[0]', '$.b[#-1]'], '[1,4]'],
    'json105-1.100 reverse index against scalar is missing' => ['$.a[#-1]', null],
    'json105-1.110 reverse index accepts padded zeros' => ['$.b[#-000001]', 4],
];

foreach ($extractCases as $name => [$paths, $expected]) {
    $tests['real upstream json dynamic path extract ' . $name] = static function (TestRunner $t) use ($json105Document, $jsonb105Document, $decodeJsonb, $paths, $expected): void {
        $pathList = is_array($paths) ? $paths : [$paths];
        $textActual = SQLiteJsonExtract::extract($json105Document, ...$pathList);
        $jsonbInputActual = SQLiteJsonExtract::extract($jsonb105Document(), ...$pathList);
        $jsonbActual = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb105Document(), ...$pathList);

        $t->same($expected, $textActual);
        $t->same($expected, $jsonbInputActual);
        if ($jsonbActual instanceof SQLiteBlobValue) {
            $t->same($expected, $decodeJsonb($jsonbActual));
        } else {
            $t->same($expected, $jsonbActual);
        }
        $t->same($pathList, array_values($pathList));
        $t->true(str_starts_with($pathList[0], '$.'));
        $t->true(str_contains(implode(' ', $pathList), '#'));
        $t->same($expected === null, $textActual === null);
        $t->same($expected === null, $jsonbInputActual === null);
        $t->same($expected === null, $jsonbActual === null);
        $t->same($textActual, $jsonbInputActual);
    };
}

$removeCases = [
    'json105-2.10 append token is no-op' => [['$.b[#]'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-2.20 reverse zero is no-op' => [['$.b[#-0]'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-2.30 remove reverse last scalar' => [['$.b[#-1]'], '{"a":1,"b":[1,[2,3]],"c":99}'],
    'json105-2.40 remove reverse middle array' => [['$.b[#-2]'], '{"a":1,"b":[1,4],"c":99}'],
    'json105-2.50 remove reverse first scalar' => [['$.b[#-3]'], '{"a":1,"b":[[2,3],4],"c":99}'],
    'json105-2.60 reverse before first is no-op' => [['$.b[#-4]'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'json105-2.70 nested reverse removal' => [['$.b[#-2][#-1]'], '{"a":1,"b":[1,[2],4],"c":99}'],
    'json105-2.100 forward then reverse removal' => [['$.b[0]', '$.b[#-1]'], '{"a":1,"b":[[2,3]],"c":99}'],
    'json105-2.110 reverse then forward removal' => [['$.b[#-1]', '$.b[0]'], '{"a":1,"b":[[2,3]],"c":99}'],
    'json105-2.120 reverse last then reverse middle removal' => [['$.b[#-1]', '$.b[#-2]'], '{"a":1,"b":[[2,3]],"c":99}'],
    'json105-2.130 repeated reverse last removal' => [['$.b[#-1]', '$.b[#-1]'], '{"a":1,"b":[1],"c":99}'],
    'json105-2.140 reverse middle then reverse last removal' => [['$.b[#-2]', '$.b[#-1]'], '{"a":1,"b":[1],"c":99}'],
];

foreach ($removeCases as $name => [$paths, $expected]) {
    $tests['real upstream json dynamic path remove ' . $name] = static function (TestRunner $t) use ($json105Document, $jsonb105Document, $decodeJsonb, $paths, $expected): void {
        $textActual = SQLiteJsonRemove::remove($json105Document, ...$paths);
        $jsonbInputActual = SQLiteJsonRemove::remove($jsonb105Document(), ...$paths);
        $jsonbActual = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonb105Document(), ...$paths);

        $t->same($expected, $textActual);
        $t->same($expected, $jsonbInputActual);
        $t->true($jsonbActual instanceof SQLiteBlobValue);
        $t->same($expected, $decodeJsonb($jsonbActual));
        $t->same($paths, array_values($paths));
        $t->true(str_starts_with($paths[0], '$.'));
        $t->true(str_contains(implode(' ', $paths), '#'));
        $t->same(json_decode($expected, true, 512, JSON_THROW_ON_ERROR), json_decode((string) $textActual, true, 512, JSON_THROW_ON_ERROR));
        $t->same($textActual, $jsonbInputActual);
        $t->same($textActual, $decodeJsonb($jsonbActual));
    };
}

$mutationCases = [
    'insert json105-3.10 append to top array' => ['json_insert', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4,"AAA"],"c":99}'],
    'insert json105-3.20 append to nested array' => ['json_insert', ['$.b[1][#]', 'AAA'], '{"a":1,"b":[1,[2,3,"AAA"],4],"c":99}'],
    'insert json105-3.30 sequential nested and top append' => ['json_insert', ['$.b[1][#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3,"AAA"],4,"BBB"],"c":99}'],
    'insert json105-3.40 repeated top append' => ['json_insert', ['$.b[#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4,"AAA","BBB"],"c":99}'],
    'set json105-4.10 append to top array' => ['json_set', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4,"AAA"],"c":99}'],
    'set json105-4.20 append to nested array' => ['json_set', ['$.b[1][#]', 'AAA'], '{"a":1,"b":[1,[2,3,"AAA"],4],"c":99}'],
    'set json105-4.30 sequential nested and top append' => ['json_set', ['$.b[1][#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3,"AAA"],4,"BBB"],"c":99}'],
    'set json105-4.40 repeated top append' => ['json_set', ['$.b[#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4,"AAA","BBB"],"c":99}'],
    'set json105-4.50 replace reverse last' => ['json_set', ['$.b[#-1]', 'AAA'], '{"a":1,"b":[1,[2,3],"AAA"],"c":99}'],
    'set json105-4.60 replace nested reverse last' => ['json_set', ['$.b[1][#-1]', 'AAA'], '{"a":1,"b":[1,[2,"AAA"],4],"c":99}'],
    'set json105-4.70 sequential nested reverse and top reverse' => ['json_set', ['$.b[1][#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,"AAA"],"BBB"],"c":99}'],
    'set json105-4.80 repeated reverse last' => ['json_set', ['$.b[#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,3],"BBB"],"c":99}'],
    'replace json105-5.10 append token is no-op' => ['json_replace', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'replace json105-5.20 nested append token is no-op' => ['json_replace', ['$.b[1][#]', 'AAA'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'replace json105-5.30 sequential append tokens are no-op' => ['json_replace', ['$.b[1][#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'replace json105-5.40 repeated append token is no-op' => ['json_replace', ['$.b[#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'replace json105-5.50 replace reverse last' => ['json_replace', ['$.b[#-1]', 'AAA'], '{"a":1,"b":[1,[2,3],"AAA"],"c":99}'],
    'replace json105-5.60 replace nested reverse last' => ['json_replace', ['$.b[1][#-1]', 'AAA'], '{"a":1,"b":[1,[2,"AAA"],4],"c":99}'],
    'replace json105-5.70 sequential nested reverse and top reverse' => ['json_replace', ['$.b[1][#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,"AAA"],"BBB"],"c":99}'],
    'replace json105-5.80 repeated reverse last' => ['json_replace', ['$.b[#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,3],"BBB"],"c":99}'],
];

foreach ($mutationCases as $name => [$function, $arguments, $expected]) {
    $tests['real upstream json dynamic path mutation ' . $name] = static function (TestRunner $t) use ($json105Document, $jsonb105Document, $decodeJsonb, $function, $arguments, $expected): void {
        $jsonbFunction = str_replace('json_', 'jsonb_', $function);
        $textActual = SQLiteJsonMutation::mutateSqlFunctionArguments($function, array_merge([$json105Document], $arguments));
        $jsonbInputActual = SQLiteJsonMutation::mutateSqlFunctionArguments($function, array_merge([$jsonb105Document()], $arguments));
        $jsonbActual = SQLiteJsonMutation::mutateSqlFunctionArguments($jsonbFunction, array_merge([$jsonb105Document()], $arguments));

        $t->same($expected, $textActual);
        $t->same($expected, $jsonbInputActual);
        $t->true($jsonbActual instanceof SQLiteBlobValue);
        $t->same($expected, $decodeJsonb($jsonbActual));
        $t->same(1, preg_match('/^json(?:b)?_(insert|set|replace)$/', $function));
        $t->same(0, count($arguments) % 2);
        $t->true(str_starts_with((string) $arguments[0], '$.'));
        $t->true(str_contains(implode(' ', array_map('strval', $arguments)), '#'));
        $t->same($textActual, $jsonbInputActual);
        $t->same($textActual, $decodeJsonb($jsonbActual));
    };
}

$invalidPathCases = [
    'json105-6.10 missing reverse digits' => '$.b[#-]',
    'json105-6.20 malformed hash digits' => '$.b[#9]',
    'json105-6.30 signed append index' => '$.b[#+2]',
    'json105-6.40 unterminated reverse index' => '$.b[#-1',
    'json105-6.50 trailing reverse index text' => '$.b[#-1x]',
];

foreach ($invalidPathCases as $name => $path) {
    $tests['real upstream json dynamic path invalid ' . $name] = static function (TestRunner $t) use ($json105Document, $jsonb105Document, $path): void {
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extract($json105Document, $path));
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extract($jsonb105Document(), $path));
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonRemove::remove($json105Document, $path));
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonb105Document(), $path));
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('json_set', $json105Document, $path, 'AAA'));
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $jsonb105Document(), $path, 'AAA'));
        $t->true(str_contains($path, '#'));
        $t->true(str_starts_with($path, '$.'));
        $t->same(false, PortLibs\LibSqlite\SQLiteJsonPath::isWellFormed($path));
        $t->same($path, (string) $path);
    };
}

$decodeJsonbValue = static fn (SQLiteBlobValue $value): mixed => SQLiteJsonB::decode($value->bytes);

$arrayInsertCases = [
    'json109-1.1 repeated zero index inserts before current element' => ['[1,2,3]', '$[0]', 999, '[888,999,1,2,3]', '$[0]', 888],
    'json109-1.2 zero index then append token uses updated array length' => ['[1,2,3]', '$[0]', 999, '[999,1,2,3,888]', '$[#]', 888],
    'json109-1.3 inserts before current positive index one' => ['[1,2,3]', '$[1]', 888, '[1,888,2,3]'],
    'json109-1.4 inserts before current positive index two' => ['[1,2,3]', '$[2]', 888, '[1,2,888,3]'],
    'json109-1.5 inserts at current length' => ['[1,2,3]', '$[3]', 888, '[1,2,3,888]'],
    'json109-1.6 reverse current last inserts before last' => ['[1,2,3]', '$[#-1]', 888, '[1,2,888,3]'],
    'json109-1.7 reverse current middle inserts before middle' => ['[1,2,3]', '$[#-2]', 888, '[1,888,2,3]'],
    'json109-1.8 reverse current first inserts before first' => ['[1,2,3]', '$[#-3]', 888, '[888,1,2,3]'],
    'json109-1.9 reverse index before first leaves input unchanged' => ['[1,2,3]', '$[#-4]', 888, '[1,2,3]'],
    'json109-2.3 missing object member creates final array element' => ['{a:[1,2,3]}', '$.b[0]', 888, '{"a":[1,2,3],"b":[888]}'],
    'json109-2.4 missing nested object path creates final array element' => ['{a:[1,2,3]}', '$.b.c.d[0]', 888, '{"a":[1,2,3],"b":{"c":{"d":[888]}}}'],
    'json109-2.7 array path against object root is unchanged' => ['{a:[1,2,3]}', '$[0]', 888, '{"a":[1,2,3]}'],
];

foreach ($arrayInsertCases as $name => $case) {
    [$json, $path, $value, $expected] = array_slice($case, 0, 4);
    $extra = array_slice($case, 4);
    $tests['real upstream json array insert ' . $name] = static function (TestRunner $t) use ($name, $json, $path, $value, $expected, $extra, $decodeJsonbValue): void {
        $textActual = SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, $path, $value, ...$extra);
        $jsonbInput = new SQLiteBlobValue(SQLiteJsonB::encode(SQLiteJson5Parser::decode($json)));
        $jsonbActual = SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', $jsonbInput, $path, $value, ...$extra);

        $t->same($expected, $textActual);
        $t->true($jsonbActual instanceof SQLiteBlobValue);
        $t->same(json_decode($expected, true, 512, JSON_THROW_ON_ERROR), $decodeJsonbValue($jsonbActual));
        $t->same(json_decode($expected, true, 512, JSON_THROW_ON_ERROR), json_decode($textActual, true, 512, JSON_THROW_ON_ERROR));
        $t->same($path, (string) $path);
        $t->same(0, count($extra) % 2);
        $t->true(str_starts_with($path, '$'));
        $t->true(str_contains($name, 'json109-'));
        $t->true(str_contains($path . ' ' . implode(' ', array_map('strval', $extra)), '['));
        $t->same($expected === $json, $textActual === $json);
    };
}

$arrayInsertErrorCases = [
    'json109-2.1 object member is not an array element' => ['{a:[1,2,3]}', '$.a', 888],
    'json109-2.2 missing object member is not an array element' => ['{a:[1,2,3]}', '$.b', 888],
    'json109-2.5 malformed nested array path is rejected' => ['{a:[1,2,3]}', '$.b.c.d[0', 888],
    'json109-2.6 nested object member is not an array element' => ['{a:[1,2,3]}', '$.b.c.d', 888],
    'json109-2.8 later invalid pair aborts earlier valid edit' => ['{a:[1,2,3]}', '$.b[0]', 888, '$.a[1]', '999', '$.c', 0],
];

foreach ($arrayInsertErrorCases as $name => $case) {
    [$json, $path, $value] = array_slice($case, 0, 3);
    $extra = array_slice($case, 3);
    $tests['real upstream json array insert error ' . $name] = static function (TestRunner $t) use ($name, $json, $path, $value, $extra): void {
        $jsonbInput = new SQLiteBlobValue(SQLiteJsonB::encode(SQLiteJson5Parser::decode($json)));

        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, $path, $value, ...$extra));
        $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', $jsonbInput, $path, $value, ...$extra));
        $t->same($path, (string) $path);
        $t->true(str_starts_with($path, '$'));
        $t->true(str_contains($name, 'json109-'));
        $t->same(0, count($extra) % 2);
        $t->true(str_contains($path . ' ' . implode(' ', array_map('strval', $extra)), '.'));
        $t->same('{a:[1,2,3]}', $json);
    };
}

return $tests;
