<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;

$tests = [];

$documents = [
    'upstream base' => ['a' => 1, 'b' => [1, [2, 3], 4], 'c' => 99],
    'four scalar tail' => ['a' => 1, 'b' => [1, [2, 3], 4, 5], 'c' => 99],
    'nested leading array' => ['a' => 1, 'b' => [[0, 1], [2, 3], 4], 'c' => 99],
    'deep nested tail' => ['a' => 1, 'b' => [1, [2, [3, 4]], 5], 'c' => 99],
    'empty nested middle' => ['a' => 1, 'b' => [1, [], 4], 'c' => 99],
    'text tail' => ['a' => 1, 'b' => ['one', ['two', 'three'], 'four'], 'c' => 99],
    'null tail' => ['a' => 1, 'b' => [null, [2, null], 4], 'c' => 99],
    'boolean tail' => ['a' => 1, 'b' => [true, [false, true], false], 'c' => 99],
    'object tail' => ['a' => 1, 'b' => [['x' => 1], ['y' => 2, 'z' => 3], ['w' => 4]], 'c' => 99],
    'long tail' => ['a' => 1, 'b' => [0, 1, [2, 3], 4, 5, 6], 'c' => 99],
];

$extractPathSets = [
    'json105-1.10 append pseudo-index missing' => ['$.b[#]'],
    'json105-1.20 last element' => ['$.b[#-1]'],
    'json105-1.30 second from end' => ['$.b[#-2]'],
    'json105-1.31 zero-padded second from end' => ['$.b[#-02]'],
    'json105-1.40 third from end' => ['$.b[#-3]'],
    'json105-1.50 fourth from end maybe missing' => ['$.b[#-4]'],
    'json105-1.60 nested last element' => ['$.b[#-2][#-1]'],
    'json105-1.70 first and last multi-path' => ['$.b[0]', '$.b[#-1]'],
    'json105-1.100 reverse index on scalar path is null' => ['$.a[#-1]'],
    'json105-1.110 zero-padded last element' => ['$.b[#-000001]'],
    'json105 derived nested first element' => ['$.b[#-2][#-2]'],
    'json105 derived nested append pseudo-index missing' => ['$.b[#-2][#]'],
    'json105 derived first and nested-last multi-path' => ['$.b[0]', '$.b[#-2][#-1]'],
];

$removePathSets = [
    'json105-2.10 append pseudo-index leaves input' => ['$.b[#]'],
    'json105-2.20 zero reverse index leaves input' => ['$.b[#-0]'],
    'json105-2.30 remove last element' => ['$.b[#-1]'],
    'json105-2.40 remove second from end' => ['$.b[#-2]'],
    'json105-2.50 remove third from end' => ['$.b[#-3]'],
    'json105-2.60 remove fourth from end maybe missing' => ['$.b[#-4]'],
    'json105-2.70 remove nested last element' => ['$.b[#-2][#-1]'],
    'json105-2.100 remove first then last' => ['$.b[0]', '$.b[#-1]'],
    'json105-2.110 remove last then first' => ['$.b[#-1]', '$.b[0]'],
    'json105-2.120 remove last then second from end' => ['$.b[#-1]', '$.b[#-2]'],
    'json105-2.130 remove last twice' => ['$.b[#-1]', '$.b[#-1]'],
    'json105-2.140 remove second from end then last' => ['$.b[#-2]', '$.b[#-1]'],
    'json105 derived remove nested append pseudo-index no-op' => ['$.b[#-2][#]'],
];

$mutationPathValueSets = [
    'json105-3.10 append through json_insert' => ['$.b[#]', 'AAA'],
    'json105-3.20 nested append through json_insert' => ['$.b[#-2][#]', 'AAA'],
    'json105-3.30 nested append then outer append' => ['$.b[#-2][#]', 'AAA', '$.b[#]', 'BBB'],
    'json105-3.40 append twice' => ['$.b[#]', 'AAA', '$.b[#]', 'BBB'],
    'json105-4.50 set last element' => ['$.b[#-1]', 'AAA'],
    'json105-4.60 set nested last element' => ['$.b[#-2][#-1]', 'AAA'],
    'json105-4.70 set nested last then outer last' => ['$.b[#-2][#-1]', 'AAA', '$.b[#-1]', 'BBB'],
    'json105-4.80 set last twice' => ['$.b[#-1]', 'AAA', '$.b[#-1]', 'BBB'],
    'json105-5.50 replace last element' => ['$.b[#-1]', 'AAA'],
    'json105-5.60 replace nested last element' => ['$.b[#-2][#-1]', 'AAA'],
    'json105-5.70 replace nested last then outer last' => ['$.b[#-2][#-1]', 'AAA', '$.b[#-1]', 'BBB'],
    'json105-5.80 replace last twice' => ['$.b[#-1]', 'AAA', '$.b[#-1]', 'BBB'],
    'json105 derived set append at outer end' => ['$.b[#]', 'TAIL'],
    'json105 derived set nested append' => ['$.b[#-2][#]', 'TAIL'],
    'json105 derived replace append remains unchanged' => ['$.b[#]', 'TAIL'],
];

$canonical = static fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
$jsonb = static fn (string $json): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, false, 512, JSON_THROW_ON_ERROR)));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$normalizeJsonbExtract = static function (mixed $value) use (&$normalizeJsonbExtract): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return $normalizeJsonbExtract(SQLiteJsonB::decode($value->bytes));
    }
    if ($value instanceof stdClass) {
        return array_map($normalizeJsonbExtract, get_object_vars($value));
    }
    if (is_array($value)) {
        return array_map($normalizeJsonbExtract, $value);
    }

    return $value;
};

$pathSegments = static function (string $path): array {
    if ($path === '$') {
        return [];
    }

    $segments = [];
    $offset = 1;
    $length = strlen($path);
    while ($offset < $length) {
        if ($path[$offset] === '.') {
            $offset++;
            $end = $offset;
            while ($end < $length && $path[$end] !== '.' && $path[$end] !== '[') {
                $end++;
            }
            $segments[] = ['member', substr($path, $offset, $end - $offset)];
            $offset = $end;
            continue;
        }
        if ($path[$offset] === '[') {
            $end = strpos($path, ']', $offset);
            if ($end === false) {
                throw new RuntimeException("Malformed test path: {$path}");
            }
            $token = substr($path, $offset + 1, $end - $offset - 1);
            if ($token === '#') {
                $segments[] = ['append', null];
            } elseif (str_starts_with($token, '#-')) {
                $segments[] = ['reverse', (int) substr($token, 2)];
            } else {
                $segments[] = ['index', (int) $token];
            }
            $offset = $end + 1;
            continue;
        }
        throw new RuntimeException("Malformed test path: {$path}");
    }

    return $segments;
};

$arrayIndex = static function (array $segment, int $count, bool $forMutation): ?int {
    [$type, $index] = $segment;
    if ($type === 'append') {
        return $forMutation ? $count : null;
    }
    if ($type === 'reverse') {
        $index = $count - (int) $index;
    }
    if (!is_int($index) || $index < 0 || $index > $count || (!$forMutation && $index === $count)) {
        return null;
    }

    return $index;
};

$locate = static function (mixed $value, string $path) use ($pathSegments, $arrayIndex): array {
    foreach ($pathSegments($path) as $segment) {
        if ($segment[0] === 'member') {
            if (!is_array($value) || array_is_list($value) || !array_key_exists($segment[1], $value)) {
                return ['found' => false, 'value' => null];
            }
            $value = $value[$segment[1]];
            continue;
        }

        if (!is_array($value) || !array_is_list($value)) {
            return ['found' => false, 'value' => null];
        }
        $index = $arrayIndex($segment, count($value), false);
        if ($index === null || !array_key_exists($index, $value)) {
            return ['found' => false, 'value' => null];
        }
        $value = $value[$index];
    }

    return ['found' => true, 'value' => $value];
};

$sqliteExtractValue = static function (array $located) use ($canonical): mixed {
    if (!$located['found']) {
        return null;
    }
    $value = $located['value'];
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

$extractExpected = static function (array $document, array $paths) use ($locate, $sqliteExtractValue, $canonical): mixed {
    $located = array_map(static fn (string $path): array => $locate($document, $path), $paths);
    if (count($paths) === 1) {
        return $sqliteExtractValue($located[0]);
    }

    return $canonical(array_map(static fn (array $item): mixed => $item['found'] ? $item['value'] : null, $located));
};

$jsonbExtractExpected = static function (array $document, array $paths) use ($locate, $sqliteExtractValue): mixed {
    $located = array_map(static fn (string $path): array => $locate($document, $path), $paths);
    if (count($paths) === 1) {
        $value = $located[0]['value'];
        return $located[0]['found'] && (is_array($value) || is_object($value))
            ? $value
            : $sqliteExtractValue($located[0]);
    }

    return array_map(static fn (array $item): mixed => $item['found'] ? $item['value'] : null, $located);
};

$removePath = static function (array &$document, string $path) use ($pathSegments, $arrayIndex): void {
    $segments = $pathSegments($path);
    if ($segments === []) {
        $document = [];
        return;
    }

    $target =& $document;
    $last = count($segments) - 1;
    for ($index = 0; $index < $last; $index++) {
        $segment = $segments[$index];
        if ($segment[0] === 'member') {
            if (!is_array($target) || array_is_list($target) || !array_key_exists($segment[1], $target)) {
                return;
            }
            $target =& $target[$segment[1]];
            continue;
        }
        if (!is_array($target) || !array_is_list($target)) {
            return;
        }
        $arrayOffset = $arrayIndex($segment, count($target), false);
        if ($arrayOffset === null || !array_key_exists($arrayOffset, $target)) {
            return;
        }
        $target =& $target[$arrayOffset];
    }

    $segment = $segments[$last];
    if ($segment[0] === 'member') {
        if (is_array($target) && !array_is_list($target)) {
            unset($target[$segment[1]]);
        }
        return;
    }
    if (!is_array($target) || !array_is_list($target)) {
        return;
    }
    $arrayOffset = $arrayIndex($segment, count($target), false);
    if ($arrayOffset !== null && array_key_exists($arrayOffset, $target)) {
        array_splice($target, $arrayOffset, 1);
    }
};

$removeExpected = static function (array $document, array $paths) use ($removePath, $canonical): string {
    foreach ($paths as $path) {
        $removePath($document, $path);
    }

    return $canonical($document);
};

$mutatePath = static function (array &$document, string $path, mixed $replacement, string $operation) use ($pathSegments, $arrayIndex, &$mutatePath): void {
    $segments = $pathSegments($path);
    $target =& $document;
    foreach ($segments as $offset => $segment) {
        $last = $offset === count($segments) - 1;
        if ($segment[0] === 'member') {
            if (!is_array($target) || array_is_list($target)) {
                return;
            }
            $exists = array_key_exists($segment[1], $target);
            if ($last) {
                if ($exists) {
                    if ($operation !== 'insert') {
                        $target[$segment[1]] = $replacement;
                    }
                } elseif ($operation !== 'replace') {
                    $target[$segment[1]] = $replacement;
                }
                return;
            }
            if (!$exists) {
                return;
            }
            $target =& $target[$segment[1]];
            continue;
        }
        if (!is_array($target) || !array_is_list($target)) {
            return;
        }
        $arrayOffset = $arrayIndex($segment, count($target), true);
        if ($arrayOffset === null) {
            return;
        }
        if ($last) {
            if ($arrayOffset < count($target)) {
                if ($operation === 'insert') {
                    return;
                }
                $target[$arrayOffset] = $replacement;
                return;
            }
            if ($operation !== 'replace') {
                $target[] = $replacement;
            }
            return;
        }
        if (!array_key_exists($arrayOffset, $target)) {
            return;
        }
        $target =& $target[$arrayOffset];
    }
};

$mutationExpected = static function (array $document, array $pathValues, string $operation) use ($mutatePath, $canonical): string {
    for ($offset = 0; $offset < count($pathValues); $offset += 2) {
        $mutatePath($document, $pathValues[$offset], $pathValues[$offset + 1], $operation);
    }

    return $canonical($document);
};

foreach ($documents as $documentName => $document) {
    $json = $canonical($document);
    $jsonbValue = $jsonb($json);

    foreach ($extractPathSets as $caseName => $paths) {
        $expectedText = $extractExpected($document, $paths);
        $expectedJsonb = $jsonbExtractExpected($document, $paths);

        $tests["upstream json105 reverse-index dynamic extract text {$caseName} {$documentName}"] = static function (TestRunner $t) use ($json, $paths, $expectedText): void {
            $t->same($expectedText, SQLiteJsonExtract::extract($json, ...$paths));
        };
        $tests["upstream json105 reverse-index dynamic extract jsonb input {$caseName} {$documentName}"] = static function (TestRunner $t) use ($jsonbValue, $paths, $expectedText): void {
            $t->same($expectedText, SQLiteJsonExtract::extract($jsonbValue, ...$paths));
        };
        $tests["upstream json105 reverse-index dynamic jsonb_extract text {$caseName} {$documentName}"] = static function (TestRunner $t) use ($json, $paths, $expectedJsonb, $normalizeJsonbExtract): void {
            $t->same($expectedJsonb, $normalizeJsonbExtract(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $json, ...$paths)));
        };
        $tests["upstream json105 reverse-index dynamic jsonb_extract jsonb {$caseName} {$documentName}"] = static function (TestRunner $t) use ($jsonbValue, $paths, $expectedJsonb, $normalizeJsonbExtract): void {
            $t->same($expectedJsonb, $normalizeJsonbExtract(SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonbValue, ...$paths)));
        };
    }

    foreach ($removePathSets as $caseName => $paths) {
        $expected = $removeExpected($document, $paths);

        $tests["upstream json105 reverse-index dynamic remove text {$caseName} {$documentName}"] = static function (TestRunner $t) use ($json, $paths, $expected): void {
            $t->same($expected, SQLiteJsonRemove::remove($json, ...$paths));
        };
        $tests["upstream json105 reverse-index dynamic remove jsonb input {$caseName} {$documentName}"] = static function (TestRunner $t) use ($jsonbValue, $paths, $expected): void {
            $t->same($expected, SQLiteJsonRemove::remove($jsonbValue, ...$paths));
        };
        $tests["upstream json105 reverse-index dynamic jsonb_remove text {$caseName} {$documentName}"] = static function (TestRunner $t) use ($json, $paths, $expected, $jsonbText): void {
            $result = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $json, ...$paths);
            $t->same($expected, $result instanceof SQLiteBlobValue ? $jsonbText($result) : $result);
        };
        $tests["upstream json105 reverse-index dynamic jsonb_remove jsonb {$caseName} {$documentName}"] = static function (TestRunner $t) use ($jsonbValue, $paths, $expected, $jsonbText): void {
            $result = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonbValue, ...$paths);
            $t->same($expected, $result instanceof SQLiteBlobValue ? $jsonbText($result) : $result);
        };
    }

    foreach ($mutationPathValueSets as $caseName => $pathValues) {
        foreach (['insert' => 'json_insert', 'set' => 'json_set', 'replace' => 'json_replace'] as $operation => $function) {
            $expected = $mutationExpected($document, $pathValues, $operation);
            $jsonbFunction = 'jsonb_' . $operation;
            $firstPath = $pathValues[0];
            $firstValue = $pathValues[1];
            $tail = array_slice($pathValues, 2);

            $tests["upstream json105 reverse-index dynamic {$function} text {$caseName} {$documentName}"] = static function (TestRunner $t) use ($function, $json, $firstPath, $firstValue, $tail, $expected): void {
                $t->same($expected, SQLiteJsonMutation::mutateSqlFunction($function, $json, $firstPath, $firstValue, ...$tail));
            };
            $tests["upstream json105 reverse-index dynamic {$function} jsonb input {$caseName} {$documentName}"] = static function (TestRunner $t) use ($function, $jsonbValue, $firstPath, $firstValue, $tail, $expected): void {
                $t->same($expected, SQLiteJsonMutation::mutateSqlFunction($function, $jsonbValue, $firstPath, $firstValue, ...$tail));
            };
            $tests["upstream json105 reverse-index dynamic {$jsonbFunction} text {$caseName} {$documentName}"] = static function (TestRunner $t) use ($jsonbFunction, $json, $firstPath, $firstValue, $tail, $expected, $jsonbText): void {
                $result = SQLiteJsonMutation::mutateSqlFunction($jsonbFunction, $json, $firstPath, $firstValue, ...$tail);
                $t->same($expected, $result instanceof SQLiteBlobValue ? $jsonbText($result) : $result);
            };
            $tests["upstream json105 reverse-index dynamic {$jsonbFunction} jsonb {$caseName} {$documentName}"] = static function (TestRunner $t) use ($jsonbFunction, $jsonbValue, $firstPath, $firstValue, $tail, $expected, $jsonbText): void {
                $result = SQLiteJsonMutation::mutateSqlFunction($jsonbFunction, $jsonbValue, $firstPath, $firstValue, ...$tail);
                $t->same($expected, $result instanceof SQLiteBlobValue ? $jsonbText($result) : $result);
            };
        }
    }
}

foreach (['$.b[#-]', '$.b[#9]', '$.b[#+2]', '$.b[#-1', '$.b[#-1x]'] as $badPath) {
    foreach (['json_extract', 'jsonb_extract'] as $function) {
        $tests["upstream json105 reverse-index dynamic rejects malformed path {$function} {$badPath}"] = static function (TestRunner $t) use ($function, $badPath): void {
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extractSqlFunction($function, '{"a":1,"b":[1,[2,3],4],"c":99}', $badPath));
        };
    }
}

return $tests;
