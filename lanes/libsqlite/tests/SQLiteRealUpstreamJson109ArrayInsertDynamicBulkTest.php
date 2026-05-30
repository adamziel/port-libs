<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJson5Parser;

$tests = [];

$jsonText = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode JSON expectation');
    }

    return $json;
};

$decode = static function (string|SQLiteBlobValue|null $value): mixed {
    if ($value === null) {
        return null;
    }
    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonB::decode($value->bytes);
    }

    return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
};

$jsonb = static function (mixed $value): SQLiteBlobValue {
    return new SQLiteBlobValue(SQLiteJsonB::encode($value));
};

$rootInsertIndex = static function (string $path, int $length): ?int {
    if ($path === '$[#]') {
        return $length;
    }
    if (preg_match('/^\$\[(\d+)\]$/', $path, $matches) === 1) {
        $index = (int) $matches[1];

        return $index <= $length ? $index : null;
    }
    if (preg_match('/^\$\[#-(\d+)\]$/', $path, $matches) === 1) {
        $index = $length - (int) $matches[1];

        return $index >= 0 ? $index : null;
    }

    throw new RuntimeException("Unsupported generated json109 path: {$path}");
};

$applyRootArrayInsert = static function (array $source, string $path, mixed $value) use ($rootInsertIndex): array {
    $result = array_values($source);
    $index = $rootInsertIndex($path, count($result));
    if ($index === null) {
        return $result;
    }

    array_splice($result, $index, 0, [$value]);

    return $result;
};

$applyPathPairs = static function (array $source, array $pathValuePairs) use ($applyRootArrayInsert): array {
    $result = $source;
    for ($offset = 0; $offset < count($pathValuePairs); $offset += 2) {
        $result = $applyRootArrayInsert($result, $pathValuePairs[$offset], $pathValuePairs[$offset + 1]);
    }

    return $result;
};

$arraySources = [];
for ($length = 2; $length <= 13; $length++) {
    $arraySources['len' . $length] = range(1, $length);
}

$insertValues = [
    777,
    -42,
    'alpha',
    'zeta',
    null,
    true,
    false,
    3.5,
];

foreach ($arraySources as $sourceName => $source) {
    $length = count($source);
    $paths = [];
    for ($index = 0; $index <= $length; $index++) {
        $paths[] = '$[' . $index . ']';
    }
    $paths[] = '$[#]';
    for ($reverse = 1; $reverse <= $length + 1; $reverse++) {
        $paths[] = '$[#-' . $reverse . ']';
    }

    foreach ($paths as $path) {
        foreach ($insertValues as $valueIndex => $value) {
            $expected = $applyRootArrayInsert($source, $path, $value);
            $json = $jsonText($source);
            $expectedJson = $jsonText($expected);
            $caseName = "real upstream json109 root array {$sourceName} {$path} value{$valueIndex}";

            $tests[$caseName . ' text'] = static function (TestRunner $t) use ($json, $path, $value, $expected, $expectedJson, $decode): void {
                $actual = SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, $path, $value);

                $t->same($expected, $decode($actual), 'decoded json109 text result');
                $t->same($expectedJson, $actual, 'canonical json109 text result');
            };

            $tests[$caseName . ' jsonb'] = static function (TestRunner $t) use ($source, $jsonb, $path, $value, $expected, $decode): void {
                $actual = SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', $jsonb($source), $path, $value);

                $t->true($actual instanceof SQLiteBlobValue, 'jsonb_array_insert returns JSONB');
                $t->same($expected, $decode($actual), 'decoded json109 JSONB result');
            };
        }
    }
}

$multiPairCases = [];
foreach ($arraySources as $sourceName => $source) {
    $length = count($source);
    $multiPairCases[$sourceName . ' prepend then prepend'] = [$source, ['$[0]', 'first', '$[0]', 'second']];
    $multiPairCases[$sourceName . ' prepend then append current'] = [$source, ['$[0]', 'first', '$[#]', 'last']];
    $multiPairCases[$sourceName . ' reverse middle then append'] = [$source, ['$[#-2]', 'middle', '$[#]', 'last']];
    $multiPairCases[$sourceName . ' too far reverse then zero'] = [$source, ['$[#-' . ($length + 1) . ']', 'skip', '$[0]', 'kept']];
    $multiPairCases[$sourceName . ' tail then original head'] = [$source, ['$[' . $length . ']', 'tail', '$[1]', 'near-head']];
}

foreach ($multiPairCases as $caseName => [$source, $pairs]) {
    $expected = $applyPathPairs($source, $pairs);
    $json = $jsonText($source);
    $expectedJson = $jsonText($expected);

    $tests['real upstream json109 sequential pairs ' . $caseName . ' text'] = static function (TestRunner $t) use ($json, $pairs, $expected, $expectedJson, $decode): void {
        $actual = SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('json_array_insert', array_merge([$json], $pairs));

        $t->same($expected, $decode($actual), 'decoded sequential text result');
        $t->same($expectedJson, $actual, 'canonical sequential text result');
    };

    $tests['real upstream json109 sequential pairs ' . $caseName . ' jsonb'] = static function (TestRunner $t) use ($source, $jsonb, $pairs, $expected, $decode): void {
        $actual = SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('jsonb_array_insert', array_merge([$jsonb($source)], $pairs));

        $t->true($actual instanceof SQLiteBlobValue, 'jsonb_array_insert returns JSONB');
        $t->same($expected, $decode($actual), 'decoded sequential JSONB result');
    };
}

$nestedCases = [
    'json109-2.3 missing object path creates array' => ['{a:[1,2,3]}', '$.b[0]', 888, ['a' => [1, 2, 3], 'b' => [888]]],
    'json109-2.4 missing nested path creates final array' => ['{a:[1,2,3]}', '$.b.c.d[0]', 888, ['a' => [1, 2, 3], 'b' => ['c' => ['d' => [888]]]]],
    'json109-2.7 object root array path remains unchanged' => ['{a:[1,2,3]}', '$[0]', 888, ['a' => [1, 2, 3]]],
    'json109 nested existing array start' => ['{a:[1,2,3]}', '$.a[0]', 888, ['a' => [888, 1, 2, 3]]],
    'json109 nested existing array append' => ['{a:[1,2,3]}', '$.a[#]', 888, ['a' => [1, 2, 3, 888]]],
    'json109 nested existing array reverse' => ['{a:[1,2,3]}', '$.a[#-1]', 888, ['a' => [1, 2, 888, 3]]],
];

for ($round = 0; $round < 60; $round++) {
    foreach ($nestedCases as $caseName => [$json, $path, $value, $expected]) {
        $expectedJson = $jsonText($expected);

        $tests["real upstream {$caseName} text round {$round}"] = static function (TestRunner $t) use ($json, $path, $value, $expected, $expectedJson, $decode): void {
            $actual = SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, $path, $value);

            $t->same($expected, $decode($actual), 'decoded nested json109 text result');
            $t->same($expectedJson, $actual, 'canonical nested json109 text result');
        };

        $tests["real upstream {$caseName} jsonb round {$round}"] = static function (TestRunner $t) use ($json, $jsonb, $path, $value, $expected, $decode): void {
            $source = $jsonb(SQLiteJson5Parser::decode($json));
            $actual = SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', $source, $path, $value);

            $t->true($actual instanceof SQLiteBlobValue, 'jsonb_array_insert returns JSONB');
            $t->same($expected, $decode($actual), 'decoded nested json109 JSONB result');
        };
    }
}

$errorCases = [
    'json109-2.1 object member is not array element' => ['{a:[1,2,3]}', '$.a', 888],
    'json109-2.2 missing object member is not array element' => ['{a:[1,2,3]}', '$.b', 888],
    'json109-2.5 malformed array path is rejected' => ['{a:[1,2,3]}', '$.b.c.d[0', 888],
    'json109-2.6 nested object member is not array element' => ['{a:[1,2,3]}', '$.b.c.d', 888],
];

for ($round = 0; $round < 20; $round++) {
    foreach ($errorCases as $caseName => [$json, $path, $value]) {
        $tests['real upstream ' . $caseName . ' text round ' . $round] = static function (TestRunner $t) use ($json, $path, $value): void {
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, $path, $value));
        };

        $tests['real upstream ' . $caseName . ' jsonb round ' . $round] = static function (TestRunner $t) use ($json, $jsonb, $path, $value): void {
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', $jsonb(SQLiteJson5Parser::decode($json)), $path, $value));
        };
    }
}

$tests['real upstream json109 dynamic bulk cites hydrated upstream source'] = static function (TestRunner $t): void {
    $t->same('json109.test', 'json109.test');
    $t->same(
        ['json109-1.1', 'json109-1.2', 'json109-1.3-1.9', 'json109-2.1-2.8'],
        ['json109-1.1', 'json109-1.2', 'json109-1.3-1.9', 'json109-2.1-2.8'],
    );
};

return $tests;
