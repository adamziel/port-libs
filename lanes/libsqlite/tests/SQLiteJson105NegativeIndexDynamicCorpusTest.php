<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;

$tests = [];

$baseJson = '{"a":1,"b":[1,[2,3],4],"c":99}';
$baseJsonb = new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($baseJson, false, 512, JSON_THROW_ON_ERROR)));

$jsonbText = static function (SQLiteBlobValue $value): string {
    return json_encode(SQLiteJsonB::decodeForJsonEncoding($value->bytes), JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
};

$json105ExtractCases = [
    '10 append slot is missing' => ['$.b[#]', null],
    '20 last array item' => ['$.b[#-1]', 4],
    '30 penultimate nested array' => ['$.b[#-2]', '[2,3]'],
    '31 leading zero penultimate nested array' => ['$.b[#-02]', '[2,3]'],
    '40 first item from tail' => ['$.b[#-3]', 1],
    '50 before first item' => ['$.b[#-4]', null],
    '51 huge tail miss one' => ['$.b[#-4296967295]', null],
    '52 huge tail miss two' => ['$.b[#-4296967296]', null],
    '53 huge tail miss three' => ['$.b[#-4296967297]', null],
    '54 huge tail miss four' => ['$.b[#-42969672950]', null],
    '55 huge tail miss five' => ['$.b[#-42969672960]', null],
    '60 nested tail lookup' => ['$.b[#-2][#-1]', 3],
    '100 tail lookup on scalar' => ['$.a[#-1]', null],
    '110 padded tail lookup' => ['$.b[#-000001]', 4],
];

foreach ($json105ExtractCases as $name => [$path, $expected]) {
    $tests["upstream json105 extract text {$name}"] = static function (TestRunner $t) use ($baseJson, $path, $expected): void {
        $t->same($expected, SQLiteJsonExtract::extract($baseJson, $path));
    };
    $tests["upstream json105 extract jsonb input {$name}"] = static function (TestRunner $t) use ($baseJsonb, $path, $expected): void {
        $t->same($expected, SQLiteJsonExtract::extract($baseJsonb, $path));
    };
    $tests["upstream json105 jsonb_extract text {$name}"] = static function (TestRunner $t) use ($baseJson, $path, $expected, $jsonbText): void {
        $actual = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $baseJson, $path);
        $t->same($expected === '[2,3]' ? [2, 3] : $expected, $actual instanceof SQLiteBlobValue ? json_decode($jsonbText($actual), true, 512, JSON_THROW_ON_ERROR) : $actual);
    };
    $tests["upstream json105 jsonb_extract jsonb {$name}"] = static function (TestRunner $t) use ($baseJsonb, $path, $expected, $jsonbText): void {
        $actual = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $baseJsonb, $path);
        $t->same($expected === '[2,3]' ? [2, 3] : $expected, $actual instanceof SQLiteBlobValue ? json_decode($jsonbText($actual), true, 512, JSON_THROW_ON_ERROR) : $actual);
    };
}

$json105RemoveCases = [
    '10 append slot no-op' => [['$.b[#]'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    '20 zero tail no-op' => [['$.b[#-0]'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    '30 remove last' => [['$.b[#-1]'], '{"a":1,"b":[1,[2,3]],"c":99}'],
    '40 remove nested array' => [['$.b[#-2]'], '{"a":1,"b":[1,4],"c":99}'],
    '50 remove first' => [['$.b[#-3]'], '{"a":1,"b":[[2,3],4],"c":99}'],
    '60 before first no-op' => [['$.b[#-4]'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    '70 nested remove last' => [['$.b[#-2][#-1]'], '{"a":1,"b":[1,[2],4],"c":99}'],
    '100 remove first then last' => [['$.b[0]', '$.b[#-1]'], '{"a":1,"b":[[2,3]],"c":99}'],
    '110 remove last then first' => [['$.b[#-1]', '$.b[0]'], '{"a":1,"b":[[2,3]],"c":99}'],
    '120 repeated moving tail' => [['$.b[#-1]', '$.b[#-2]'], '{"a":1,"b":[[2,3]],"c":99}'],
    '130 repeated last' => [['$.b[#-1]', '$.b[#-1]'], '{"a":1,"b":[1],"c":99}'],
    '140 penultimate then last' => [['$.b[#-2]', '$.b[#-1]'], '{"a":1,"b":[1],"c":99}'],
];

foreach ($json105RemoveCases as $name => [$paths, $expected]) {
    $tests["upstream json105 remove text {$name}"] = static function (TestRunner $t) use ($baseJson, $paths, $expected): void {
        $t->same($expected, SQLiteJsonRemove::remove($baseJson, ...$paths));
    };
    $tests["upstream json105 remove jsonb input {$name}"] = static function (TestRunner $t) use ($baseJsonb, $paths, $expected): void {
        $t->same($expected, SQLiteJsonRemove::remove($baseJsonb, ...$paths));
    };
    $tests["upstream json105 jsonb_remove text {$name}"] = static function (TestRunner $t) use ($baseJson, $paths, $expected, $jsonbText): void {
        $actual = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $baseJson, ...$paths);
        $t->same($expected, $actual instanceof SQLiteBlobValue ? $jsonbText($actual) : $actual);
    };
    $tests["upstream json105 jsonb_remove jsonb {$name}"] = static function (TestRunner $t) use ($baseJsonb, $paths, $expected, $jsonbText): void {
        $actual = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $baseJsonb, ...$paths);
        $t->same($expected, $actual instanceof SQLiteBlobValue ? $jsonbText($actual) : $actual);
    };
}

$json105MutationCases = [
    'insert 10 append' => ['json_insert', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4,"AAA"],"c":99}'],
    'insert 20 nested append' => ['json_insert', ['$.b[1][#]', 'AAA'], '{"a":1,"b":[1,[2,3,"AAA"],4],"c":99}'],
    'insert 30 nested and root append' => ['json_insert', ['$.b[1][#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3,"AAA"],4,"BBB"],"c":99}'],
    'insert 40 repeated append' => ['json_insert', ['$.b[#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4,"AAA","BBB"],"c":99}'],
    'set 10 append' => ['json_set', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4,"AAA"],"c":99}'],
    'set 20 nested append' => ['json_set', ['$.b[1][#]', 'AAA'], '{"a":1,"b":[1,[2,3,"AAA"],4],"c":99}'],
    'set 30 nested and root append' => ['json_set', ['$.b[1][#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3,"AAA"],4,"BBB"],"c":99}'],
    'set 40 repeated append' => ['json_set', ['$.b[#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4,"AAA","BBB"],"c":99}'],
    'set 50 replace last' => ['json_set', ['$.b[#-1]', 'AAA'], '{"a":1,"b":[1,[2,3],"AAA"],"c":99}'],
    'set 60 replace nested last' => ['json_set', ['$.b[1][#-1]', 'AAA'], '{"a":1,"b":[1,[2,"AAA"],4],"c":99}'],
    'set 70 nested then root tail' => ['json_set', ['$.b[1][#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,"AAA"],"BBB"],"c":99}'],
    'set 80 repeated tail' => ['json_set', ['$.b[#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,3],"BBB"],"c":99}'],
    'replace 10 append no-op' => ['json_replace', ['$.b[#]', 'AAA'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'replace 20 nested append no-op' => ['json_replace', ['$.b[1][#]', 'AAA'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'replace 30 append pair no-op' => ['json_replace', ['$.b[1][#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'replace 40 repeated append no-op' => ['json_replace', ['$.b[#]', 'AAA', '$.b[#]', 'BBB'], '{"a":1,"b":[1,[2,3],4],"c":99}'],
    'replace 50 replace last' => ['json_replace', ['$.b[#-1]', 'AAA'], '{"a":1,"b":[1,[2,3],"AAA"],"c":99}'],
    'replace 60 replace nested last' => ['json_replace', ['$.b[1][#-1]', 'AAA'], '{"a":1,"b":[1,[2,"AAA"],4],"c":99}'],
    'replace 70 nested then root tail' => ['json_replace', ['$.b[1][#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,"AAA"],"BBB"],"c":99}'],
    'replace 80 repeated tail' => ['json_replace', ['$.b[#-1]', 'AAA', '$.b[#-1]', 'BBB'], '{"a":1,"b":[1,[2,3],"BBB"],"c":99}'],
];

foreach ($json105MutationCases as $name => [$function, $arguments, $expected]) {
    $jsonbFunction = str_replace('json_', 'jsonb_', $function);
    $tests["upstream json105 {$function} text {$name}"] = static function (TestRunner $t) use ($function, $baseJson, $arguments, $expected): void {
        $t->same($expected, SQLiteJsonMutation::mutateSqlFunctionArguments($function, [$baseJson, ...$arguments]));
    };
    $tests["upstream json105 {$function} jsonb input {$name}"] = static function (TestRunner $t) use ($function, $baseJsonb, $arguments, $expected): void {
        $t->same($expected, SQLiteJsonMutation::mutateSqlFunctionArguments($function, [$baseJsonb, ...$arguments]));
    };
    $tests["upstream json105 {$jsonbFunction} text {$name}"] = static function (TestRunner $t) use ($jsonbFunction, $baseJson, $arguments, $expected, $jsonbText): void {
        $actual = SQLiteJsonMutation::mutateSqlFunctionArguments($jsonbFunction, [$baseJson, ...$arguments]);
        $t->same($expected, $actual instanceof SQLiteBlobValue ? $jsonbText($actual) : $actual);
    };
    $tests["upstream json105 {$jsonbFunction} jsonb {$name}"] = static function (TestRunner $t) use ($jsonbFunction, $baseJsonb, $arguments, $expected, $jsonbText): void {
        $actual = SQLiteJsonMutation::mutateSqlFunctionArguments($jsonbFunction, [$baseJsonb, ...$arguments]);
        $t->same($expected, $actual instanceof SQLiteBlobValue ? $jsonbText($actual) : $actual);
    };
}

for ($length = 1; $length <= 120; $length++) {
    $array = range(1, $length);
    $document = json_encode(['b' => $array], JSON_THROW_ON_ERROR);
    $documentJsonb = new SQLiteBlobValue(SQLiteJsonB::encode(['b' => $array]));
    $last = $array[$length - 1];
    $first = $array[0];
    $middleIndex = intdiv($length - 1, 2);
    $fromTail = $length - $middleIndex;
    $withoutLast = json_encode(['b' => array_slice($array, 0, -1)], JSON_THROW_ON_ERROR);
    $withoutFirst = json_encode(['b' => array_slice($array, 1)], JSON_THROW_ON_ERROR);
    $setLast = $array;
    $setLast[$length - 1] = 'tail';
    $setLastJson = json_encode(['b' => $setLast], JSON_THROW_ON_ERROR);
    $appendedJson = json_encode(['b' => [...$array, 'tail']], JSON_THROW_ON_ERROR);

    $tests["upstream json105 dynamic tail extract text length {$length}"] = static function (TestRunner $t) use ($document, $last): void {
        $t->same($last, SQLiteJsonExtract::extract($document, '$.b[#-1]'));
    };
    $tests["upstream json105 dynamic tail extract jsonb length {$length}"] = static function (TestRunner $t) use ($documentJsonb, $last): void {
        $t->same($last, SQLiteJsonExtract::extract($documentJsonb, '$.b[#-1]'));
    };
    $tests["upstream json105 dynamic first from tail text length {$length}"] = static function (TestRunner $t) use ($document, $first, $length): void {
        $t->same($first, SQLiteJsonExtract::extract($document, '$.b[#-' . $length . ']'));
    };
    $tests["upstream json105 dynamic middle from tail jsonb length {$length}"] = static function (TestRunner $t) use ($documentJsonb, $middleIndex, $fromTail): void {
        $t->same($middleIndex + 1, SQLiteJsonExtract::extract($documentJsonb, '$.b[#-' . $fromTail . ']'));
    };
    $tests["upstream json105 dynamic append slot text length {$length}"] = static function (TestRunner $t) use ($document): void {
        $t->same(null, SQLiteJsonExtract::extract($document, '$.b[#]'));
    };
    $tests["upstream json105 dynamic remove last text length {$length}"] = static function (TestRunner $t) use ($document, $withoutLast): void {
        $t->same($withoutLast, SQLiteJsonRemove::remove($document, '$.b[#-1]'));
    };
    $tests["upstream json105 dynamic remove first jsonb length {$length}"] = static function (TestRunner $t) use ($documentJsonb, $withoutFirst, $length): void {
        $t->same($withoutFirst, SQLiteJsonRemove::remove($documentJsonb, '$.b[#-' . $length . ']'));
    };
    $tests["upstream json105 dynamic set last text length {$length}"] = static function (TestRunner $t) use ($document, $setLastJson): void {
        $t->same($setLastJson, SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [$document, '$.b[#-1]', 'tail']));
    };
    $tests["upstream json105 dynamic set last jsonb length {$length}"] = static function (TestRunner $t) use ($documentJsonb, $setLastJson): void {
        $t->same($setLastJson, SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [$documentJsonb, '$.b[#-1]', 'tail']));
    };
    $tests["upstream json105 dynamic insert append text length {$length}"] = static function (TestRunner $t) use ($document, $appendedJson): void {
        $t->same($appendedJson, SQLiteJsonMutation::mutateSqlFunctionArguments('json_insert', [$document, '$.b[#]', 'tail']));
    };
}

return $tests;
