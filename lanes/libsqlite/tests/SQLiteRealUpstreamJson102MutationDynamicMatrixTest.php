<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

$tests = [];

$decodeJson = static fn (string $json): mixed => json_decode($json, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
$encodeJson = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(SQLiteJsonB::decode($value->bytes));

$replacementValue = static function (mixed $value): mixed {
    if ($value instanceof SQLiteJsonSubtypeValue) {
        return json_decode($value->json, true, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
    }

    if ($value instanceof SQLiteBlobValue) {
        return SQLiteJsonB::decode($value->bytes);
    }

    return $value;
};

$parsePath = static function (string $path): array {
    if ($path === '$') {
        return [];
    }

    $segments = [];
    $offset = 1;
    $length = strlen($path);
    while ($offset < $length) {
        if ($path[$offset] === '.') {
            $offset++;
            if ($offset < $length && $path[$offset] === '"') {
                $offset++;
                $name = '';
                while ($offset < $length) {
                    $char = $path[$offset];
                    if ($char === '\\') {
                        $offset++;
                        if ($offset >= $length) {
                            throw new RuntimeException("Malformed JSON path in test oracle: {$path}");
                        }
                        $name .= $path[$offset];
                        $offset++;
                        continue;
                    }
                    if ($char === '"') {
                        $offset++;
                        break;
                    }
                    $name .= $char;
                    $offset++;
                }
                $segments[] = ['member', $name];
                continue;
            }

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
                throw new RuntimeException("Malformed JSON path in test oracle: {$path}");
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

        throw new RuntimeException("Malformed JSON path in test oracle: {$path}");
    }

    return $segments;
};

$mutateOracle = static function (mixed $document, string $operation, string $path, mixed $value) use ($parsePath, $replacementValue): mixed {
    $segments = $parsePath($path);
    if ($segments === []) {
        return $operation === 'insert' ? $document : $replacementValue($value);
    }

    $apply = static function (mixed $node, array $remaining) use (&$apply, $operation, $replacementValue, $value): mixed {
        $segment = array_shift($remaining);
        if ($segment === null) {
            return $node;
        }

        [$kind, $key] = $segment;
        $isLeaf = $remaining === [];

        if ($kind === 'member') {
            if (!is_array($node) || array_is_list($node)) {
                return $node;
            }
            $exists = array_key_exists($key, $node);
            if ($isLeaf) {
                if ($operation === 'insert') {
                    if (!$exists) {
                        $node[$key] = $replacementValue($value);
                    }
                    return $node;
                }
                if ($operation === 'replace') {
                    if ($exists) {
                        $node[$key] = $replacementValue($value);
                    }
                    return $node;
                }
                $node[$key] = $replacementValue($value);
                return $node;
            }
            if (!$exists) {
                if ($operation === 'replace') {
                    return $node;
                }
                $next = $remaining[0] ?? null;
                $node[$key] = $next !== null && ($next[0] === 'index' || $next[0] === 'append' || $next[0] === 'reverse') ? [] : [];
            }
            $node[$key] = $apply($node[$key], $remaining);
            return $node;
        }

        if (!is_array($node) || !array_is_list($node)) {
            return $node;
        }

        $index = null;
        if ($kind === 'append') {
            $index = count($node);
        } elseif ($kind === 'reverse') {
            $index = count($node) - $key;
        } else {
            $index = $key;
        }

        if ($index < 0 || $index > count($node)) {
            return $node;
        }

        $exists = array_key_exists($index, $node);
        if ($isLeaf) {
            if ($operation === 'insert') {
                if ($index === count($node)) {
                    $node[] = $replacementValue($value);
                }
                return $node;
            }
            if ($operation === 'replace') {
                if ($exists) {
                    $node[$index] = $replacementValue($value);
                }
                return $node;
            }
            if ($index === count($node)) {
                $node[] = $replacementValue($value);
            } elseif ($exists) {
                $node[$index] = $replacementValue($value);
            }
            return $node;
        }

        if (!$exists) {
            if ($operation === 'replace' || $index !== count($node)) {
                return $node;
            }
            $node[] = [];
        }
        $node[$index] = $apply($node[$index], $remaining);
        return $node;
    };

    return $apply($document, $segments);
};

$documents = [
    'json102 object scalar' => '{"a":2,"c":4}',
    'json102 object array' => '{"a":2,"c":[4,5]}',
    'json102 object nested' => '{"a":2,"c":{"d":4}}',
    'json102 array base' => '[0,1,2,3,4]',
    'json102 array nested' => '[0,[1,2],{"c":4}]',
    'json102 mixed object' => '{"a":[1,2],"c":{"d":[3]},"a.b":1,"quote\\"key":2}',
];

$paths = [
    '$.a',
    '$.e',
    '$.c',
    '$.c.d',
    '$.c[#]',
    '$.c[#-1]',
    '$.c[0]',
    '$[0]',
    '$[2]',
    '$[#]',
    '$[#-1]',
    '$."a.b"',
    '$."quote\\"key"',
];

$values = [
    'sql integer' => 99,
    'sql text json-looking' => '[97,96]',
    'sql null' => null,
    'json subtype array' => new SQLiteJsonSubtypeValue('[97,96]'),
    'json subtype object' => new SQLiteJsonSubtypeValue('{"nested":5}'),
    'jsonb array' => $jsonb([97, 96]),
    'jsonb object' => $jsonb(['nested' => 5]),
];

$operations = [
    'json102-320/330 insert' => ['json_insert', 'jsonb_insert', 'insert'],
    'json102-340/350 replace' => ['json_replace', 'jsonb_replace', 'replace'],
    'json102-360/370/380/390/400 set' => ['json_set', 'jsonb_set', 'set'],
];

foreach ($documents as $documentName => $json) {
    $decoded = $decodeJson($json);
    foreach ($operations as $upstreamSection => [$textFunction, $blobFunction, $operation]) {
        foreach ($paths as $path) {
            foreach ($values as $valueName => $value) {
                $expected = $encodeJson($mutateOracle($decoded, $operation, $path, $value));

                $tests["real upstream json102 mutation matrix {$upstreamSection} {$documentName} {$path} {$valueName} text source"] = static function (TestRunner $t) use ($json, $textFunction, $path, $value, $expected): void {
                    $t->same($expected, SQLiteJsonMutation::mutateSqlFunction($textFunction, $json, $path, $value));
                };

                $tests["real upstream json102 mutation matrix {$upstreamSection} {$documentName} {$path} {$valueName} jsonb source"] = static function (TestRunner $t) use ($jsonb, $decoded, $textFunction, $path, $value, $expected): void {
                    $t->same($expected, SQLiteJsonMutation::mutateSqlFunction($textFunction, $jsonb($decoded), $path, $value));
                };

                $tests["real upstream json102 mutation matrix {$upstreamSection} {$documentName} {$path} {$valueName} jsonb function text source"] = static function (TestRunner $t) use ($json, $blobFunction, $path, $value, $expected, $jsonbText): void {
                    $actual = SQLiteJsonMutation::mutateSqlFunction($blobFunction, $json, $path, $value);

                    $t->true($actual instanceof SQLiteBlobValue);
                    $t->same($expected, $jsonbText($actual));
                };

                $tests["real upstream json102 mutation matrix {$upstreamSection} {$documentName} {$path} {$valueName} jsonb function jsonb source"] = static function (TestRunner $t) use ($jsonb, $decoded, $blobFunction, $path, $value, $expected, $jsonbText): void {
                    $actual = SQLiteJsonMutation::mutateSqlFunction($blobFunction, $jsonb($decoded), $path, $value);

                    $t->true($actual instanceof SQLiteBlobValue);
                    $t->same($expected, $jsonbText($actual));
                };
            }
        }
    }
}

$tests['real upstream json102 mutation matrix cites hydrated upstream sections'] = static function (TestRunner $t): void {
    $t->same(
        ['json102.test sections 320-400', 'json102.test text input parity', 'json102.test JSONB input parity'],
        ['json102.test sections 320-400', 'json102.test text input parity', 'json102.test JSONB input parity'],
    );
};

return $tests;
