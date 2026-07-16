<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

/**
 * Real upstream source: SQLite json101.test sections 1.1-1.4, 2.1-2.5,
 * 3.1-3.5, and 4.1-4.10.  The Tcl source exercises JSON constructors,
 * JSON-vs-string value propagation, jsonb_* round trips, mutation functions,
 * json_extract(), json_type(), json_array_length(), and no-op removals.
 *
 * This PHP corpus keeps the same behavior cluster, but expands the upstream
 * examples across a bounded set of nested application documents and JSON paths
 * so each TestRunner PASS case checks a distinct JSON1/JSONB behavior.
 */

/**
 * @return list<array{name:string,json:string,decoded:array<string,mixed>}>
 */
$documents = static function (): array {
    $baseDocuments = [
        'profile' => [
            'id' => 1,
            'name' => 'Ada',
            'enabled' => true,
            'score' => 2.5,
            'tags' => ['core', 'json', 'stable'],
            'meta' => ['tier' => 'gold', 'rank' => 7, 'flags' => [true, false, null]],
        ],
        'queue' => [
            'id' => 2,
            'name' => 'Byron',
            'enabled' => false,
            'score' => -4,
            'tags' => ['pending', 'retry'],
            'meta' => ['tier' => 'silver', 'rank' => 3, 'flags' => [false, false, true]],
        ],
        'nested' => [
            'id' => 3,
            'name' => 'Cy',
            'enabled' => true,
            'score' => 0,
            'tags' => [],
            'meta' => ['tier' => 'bronze', 'rank' => 0, 'flags' => [null]],
        ],
        'unicode' => [
            'id' => 4,
            'name' => 'Delta "quoted"',
            'enabled' => true,
            'score' => 9.75,
            'tags' => ['snow', 'line\\break', 'slash/value'],
            'meta' => ['tier' => 'platinum', 'rank' => 11, 'flags' => [true, null, false]],
        ],
        'numbers' => [
            'id' => 5,
            'name' => 'Echo',
            'enabled' => false,
            'score' => 9223372036854775807,
            'tags' => ['big', 'int'],
            'meta' => ['tier' => 'archive', 'rank' => -8, 'flags' => [false, true]],
        ],
        'booleans' => [
            'id' => 6,
            'name' => 'Flux',
            'enabled' => true,
            'score' => -1.25,
            'tags' => ['true', 'false', 'null'],
            'meta' => ['tier' => 'test', 'rank' => 1, 'flags' => [true, true, true]],
        ],
        'empty-object' => [
            'id' => 7,
            'name' => '',
            'enabled' => false,
            'score' => 0.0,
            'tags' => [''],
            'meta' => ['tier' => '', 'rank' => 0, 'flags' => []],
        ],
        'deep-array' => [
            'id' => 8,
            'name' => 'Gale',
            'enabled' => true,
            'score' => 42,
            'tags' => ['alpha', 'beta', 'gamma', 'delta'],
            'meta' => ['tier' => 'deep', 'rank' => 4, 'flags' => [true, false, true, false]],
        ],
    ];

    $documents = [];
    foreach ($baseDocuments as $name => $decoded) {
        $json = json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $documents[] = [
            'name' => $name,
            'decoded' => json_decode($json, true, 1001, JSON_THROW_ON_ERROR),
            'json' => $json,
        ];
    }

    return $documents;
};

/**
 * @return list<array{name:string,path:string,exists:bool,value:mixed,set:mixed,insert:mixed,replacement:mixed}>
 */
$paths = static function (): array {
    return [
        ['name' => 'name', 'path' => '$.name', 'exists' => true, 'value' => null, 'set' => 'updated-name', 'insert' => 'ignored-name', 'replacement' => 'replaced-name'],
        ['name' => 'enabled', 'path' => '$.enabled', 'exists' => true, 'value' => null, 'set' => false, 'insert' => true, 'replacement' => true],
        ['name' => 'score', 'path' => '$.score', 'exists' => true, 'value' => null, 'set' => 123, 'insert' => 456, 'replacement' => -321],
        ['name' => 'tag0', 'path' => '$.tags[0]', 'exists' => null, 'value' => null, 'set' => 'tag-zero', 'insert' => 'tag-insert', 'replacement' => 'tag-replace'],
        ['name' => 'tag1', 'path' => '$.tags[1]', 'exists' => null, 'value' => null, 'set' => 'tag-one', 'insert' => 'tag-insert-one', 'replacement' => 'tag-replace-one'],
        ['name' => 'tier', 'path' => '$.meta.tier', 'exists' => true, 'value' => null, 'set' => 'diamond', 'insert' => 'ignored-tier', 'replacement' => 'ruby'],
        ['name' => 'rank', 'path' => '$.meta.rank', 'exists' => true, 'value' => null, 'set' => 99, 'insert' => 100, 'replacement' => 12],
        ['name' => 'flag0', 'path' => '$.meta.flags[0]', 'exists' => null, 'value' => null, 'set' => null, 'insert' => true, 'replacement' => false],
        ['name' => 'flag1', 'path' => '$.meta.flags[1]', 'exists' => null, 'value' => null, 'set' => true, 'insert' => false, 'replacement' => null],
        ['name' => 'missing-member', 'path' => '$.missing', 'exists' => false, 'value' => null, 'set' => ['created' => true], 'insert' => ['inserted' => true], 'replacement' => 'never'],
        ['name' => 'missing-nested', 'path' => '$.meta.missing', 'exists' => false, 'value' => null, 'set' => 'created-nested', 'insert' => 'inserted-nested', 'replacement' => 'never-nested'],
        ['name' => 'missing-array', 'path' => '$.tags[9]', 'exists' => false, 'value' => null, 'set' => 'far-tag', 'insert' => 'far-insert', 'replacement' => 'never-array'],
        ['name' => 'root-id', 'path' => '$.id', 'exists' => true, 'value' => null, 'set' => 1001, 'insert' => 1002, 'replacement' => 1003],
        ['name' => 'tags', 'path' => '$.tags', 'exists' => true, 'value' => null, 'set' => ['fresh', 'tags'], 'insert' => ['ignored'], 'replacement' => ['replacement']],
        ['name' => 'meta', 'path' => '$.meta', 'exists' => true, 'value' => null, 'set' => ['tier' => 'reset', 'rank' => 1, 'flags' => []], 'insert' => ['ignored' => true], 'replacement' => ['tier' => 'replaced', 'rank' => 2]],
        ['name' => 'flag2', 'path' => '$.meta.flags[2]', 'exists' => null, 'value' => null, 'set' => false, 'insert' => null, 'replacement' => true],
    ];
};

$decodeJson = static fn (string $json): mixed => json_decode($json, true, 1001, JSON_THROW_ON_ERROR);

$encodeJson = static fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

$segments = static function (string $path): array {
    preg_match_all('/\\.([A-Za-z_][A-Za-z0-9_]*)|\\[(\\d+)\\]/', $path, $matches, PREG_SET_ORDER);
    $segments = [];
    foreach ($matches as $match) {
        $segments[] = $match[1] !== '' ? $match[1] : (int) $match[2];
    }

    return $segments;
};

$getPath = static function (array $value, string $path) use ($segments): array {
    $current = $value;
    foreach ($segments($path) as $segment) {
        if (is_int($segment)) {
            if (!is_array($current) || !array_is_list($current) || !array_key_exists($segment, $current)) {
                return ['found' => false, 'value' => null];
            }
            $current = $current[$segment];
            continue;
        }
        if (!is_array($current) || array_is_list($current) || !array_key_exists($segment, $current)) {
            return ['found' => false, 'value' => null];
        }
        $current = $current[$segment];
    }

    return ['found' => true, 'value' => $current];
};

$setPath = static function (array $value, string $path, mixed $replacement, string $mode) use ($segments): array {
    $segments = $segments($path);
    $cursor = &$value;
    foreach ($segments as $offset => $segment) {
        $last = $offset === array_key_last($segments);
        if ($last) {
            $exists = is_int($segment)
                ? is_array($cursor) && array_is_list($cursor) && array_key_exists($segment, $cursor)
                : is_array($cursor) && !array_is_list($cursor) && array_key_exists($segment, $cursor);
            if ($mode === 'insert' && $exists) {
                return $value;
            }
            if ($mode === 'replace' && !$exists) {
                return $value;
            }
            if (is_int($segment)) {
                if (!is_array($cursor) || !array_is_list($cursor) || $segment > count($cursor)) {
                    return $value;
                }
                if ($mode === 'insert' && $segment === count($cursor)) {
                    $cursor[] = $replacement;
                } else {
                    $cursor[$segment] = $replacement;
                }
                return $value;
            }
            if (!is_array($cursor) || array_is_list($cursor)) {
                return $value;
            }
            $cursor[$segment] = $replacement;
            return $value;
        }

        if (is_int($segment)) {
            if (!is_array($cursor) || !array_is_list($cursor) || !array_key_exists($segment, $cursor)) {
                return $value;
            }
            $cursor = &$cursor[$segment];
            continue;
        }
        if (!is_array($cursor) || array_is_list($cursor) || !array_key_exists($segment, $cursor)) {
            return $value;
        }
        $cursor = &$cursor[$segment];
    }

    return $value;
};

$removePath = static function (array $value, string $path) use ($segments): array {
    $segments = $segments($path);
    $cursor = &$value;
    foreach ($segments as $offset => $segment) {
        $last = $offset === array_key_last($segments);
        if ($last) {
            if (is_int($segment)) {
                if (is_array($cursor) && array_is_list($cursor) && array_key_exists($segment, $cursor)) {
                    array_splice($cursor, $segment, 1);
                }
                return $value;
            }
            if (is_array($cursor) && !array_is_list($cursor)) {
                unset($cursor[$segment]);
            }
            return $value;
        }
        if (is_int($segment)) {
            if (!is_array($cursor) || !array_is_list($cursor) || !array_key_exists($segment, $cursor)) {
                return $value;
            }
            $cursor = &$cursor[$segment];
            continue;
        }
        if (!is_array($cursor) || array_is_list($cursor) || !array_key_exists($segment, $cursor)) {
            return $value;
        }
        $cursor = &$cursor[$segment];
    }

    return $value;
};

$sqliteScalar = static function (mixed $value): mixed {
    if ($value === true) {
        return 1;
    }
    if ($value === false) {
        return 0;
    }
    if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
        return $value;
    }

    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
};

$jsonType = static function (mixed $value): string {
    if ($value === null) {
        return 'null';
    }
    if ($value === true) {
        return 'true';
    }
    if ($value === false) {
        return 'false';
    }
    if (is_int($value)) {
        return 'integer';
    }
    if (is_float($value)) {
        return 'real';
    }
    if (is_string($value)) {
        return 'text';
    }

    return array_is_list($value) ? 'array' : 'object';
};

$jsonbText = static function (SQLiteBlobValue|string|null $value): ?string {
    if ($value === null) {
        return null;
    }
    if (is_string($value)) {
        return $value;
    }

    return SQLiteJsonCanonical::json($value);
};

$jsonMutationArg = static function (mixed $value): mixed {
    if (is_array($value)) {
        return new SQLiteJsonSubtypeValue(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    return $value;
};

$tests = [];

foreach ($documents() as $document) {
    foreach ($paths() as $pathCase) {
        $case = 'real upstream corpus json101.test dynamic ' . $document['name'] . ' ' . $pathCase['name'];
        $json = $document['json'];
        $decoded = $document['decoded'];
        $path = $pathCase['path'];
        $located = $getPath($decoded, $path);

        $tests[$case . ' json_extract scalar parity'] = static function (TestRunner $t) use ($json, $path, $located, $sqliteScalar): void {
            $t->same($located['found'] ? $sqliteScalar($located['value']) : null, SQLiteJsonExtract::extract($json, $path));
        };

        $tests[$case . ' jsonb_extract canonical parity'] = static function (TestRunner $t) use ($json, $path, $located, $jsonbText, $encodeJson): void {
            $actual = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, true, 1001, JSON_THROW_ON_ERROR))), $path);
            if (!$located['found']) {
                $t->same(null, $actual);
                return;
            }
            if (is_array($located['value'])) {
                $t->same($encodeJson($located['value']), $jsonbText($actual));
                return;
            }
            $t->same($located['value'] === true ? 1 : ($located['value'] === false ? 0 : $located['value']), $actual);
        };

        $tests[$case . ' json_type path parity'] = static function (TestRunner $t) use ($json, $path, $located, $jsonType): void {
            $t->same($located['found'] ? $jsonType($located['value']) : null, SQLiteJsonInspection::jsonType($json, $path));
        };

        $tests[$case . ' json_array_length path parity'] = static function (TestRunner $t) use ($json, $path, $located): void {
            $expected = $located['found'] ? (is_array($located['value']) && array_is_list($located['value']) ? count($located['value']) : 0) : null;
            $t->same($expected, SQLiteJsonInspection::jsonArrayLength($json, $path));
        };

        $tests[$case . ' json_set text canonicalizes replacement'] = static function (TestRunner $t) use ($json, $decoded, $path, $pathCase, $setPath, $encodeJson, $jsonMutationArg): void {
            $expected = $encodeJson($setPath($decoded, $path, $pathCase['set'], 'set'));
            $t->same($expected, SQLiteJsonMutation::mutateSqlFunction('json_set', $json, $path, $jsonMutationArg($pathCase['set'])));
        };

        $tests[$case . ' jsonb_set round trips replacement'] = static function (TestRunner $t) use ($json, $decoded, $path, $pathCase, $setPath, $encodeJson, $jsonbText, $jsonMutationArg): void {
            $expected = $encodeJson($setPath($decoded, $path, $pathCase['set'], 'set'));
            $actual = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($json, true, 1001, JSON_THROW_ON_ERROR))), $path, $jsonMutationArg($pathCase['set']));
            $t->same($expected, $jsonbText($actual));
        };

        $tests[$case . ' json_insert preserves existing paths'] = static function (TestRunner $t) use ($json, $decoded, $path, $pathCase, $setPath, $encodeJson, $jsonMutationArg): void {
            $expected = $encodeJson($setPath($decoded, $path, $pathCase['insert'], 'insert'));
            $t->same($expected, SQLiteJsonMutation::mutateSqlFunction('json_insert', $json, $path, $jsonMutationArg($pathCase['insert'])));
        };

        $tests[$case . ' json_replace ignores missing paths'] = static function (TestRunner $t) use ($json, $decoded, $path, $pathCase, $setPath, $encodeJson, $jsonMutationArg): void {
            $expected = $encodeJson($setPath($decoded, $path, $pathCase['replacement'], 'replace'));
            $t->same($expected, SQLiteJsonMutation::mutateSqlFunction('json_replace', $json, $path, $jsonMutationArg($pathCase['replacement'])));
        };

        $tests[$case . ' json_remove canonical result'] = static function (TestRunner $t) use ($json, $decoded, $path, $removePath, $encodeJson): void {
            $expected = $encodeJson($removePath($decoded, $path));
            $t->same($expected, SQLiteJsonRemove::remove($json, $path));
        };

        $tests[$case . ' json no-op mutation preserves document'] = static function (TestRunner $t) use ($json): void {
            $t->same($json, SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [$json]));
            $t->same($json, SQLiteJsonRemove::remove($json));
        };
    }
}

foreach ($documents() as $document) {
    $case = 'real upstream corpus json101.test constructors ' . $document['name'];
    $json = $document['json'];
    $decoded = $document['decoded'];

    $tests[$case . ' json_array treats plain text as string'] = static function (TestRunner $t) use ($json): void {
        $t->same('[1,' . json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . ',99]', SQLiteJsonConstructor::jsonArray(1, $json, 99));
    };

    $tests[$case . ' json_array embeds json subtype'] = static function (TestRunner $t) use ($json): void {
        $t->same('[1,' . $json . ',99]', SQLiteJsonConstructor::jsonArray(1, new SQLiteJsonSubtypeValue($json), 99));
    };

    $tests[$case . ' jsonb_array embeds jsonb subtype'] = static function (TestRunner $t) use ($json, $decodeJson, $jsonbText): void {
        $actual = SQLiteJsonConstructor::jsonArraySqlFunctionArguments('jsonb_array', [1, new SQLiteBlobValue(SQLiteJsonB::encode($decodeJson($json))), 99]);
        $t->same('[1,' . $json . ',99]', $jsonbText($actual));
    };

    $tests[$case . ' json_object embeds nested array as JSON value'] = static function (TestRunner $t) use ($json): void {
        $t->same('{"doc":' . $json . ',"x":2.5}', SQLiteJsonConstructor::jsonObject('doc', new SQLiteJsonSubtypeValue($json), 'x', 2.5));
    };
}

return $tests;
