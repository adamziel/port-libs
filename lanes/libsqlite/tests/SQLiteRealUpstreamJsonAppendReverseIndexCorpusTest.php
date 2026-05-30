<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;

/**
 * Real upstream source: SQLite json105.test sections 1.10-6.50 and
 * jsonb01.test section 1.2.  The upstream Tcl files exercise JSON path [#]
 * append markers, [#-N] reverse indexes, left-to-right multi-path mutation,
 * malformed [#...] path rejection, and equivalent JSONB remove behavior.
 *
 * This dynamic PHP corpus keeps that behavior cluster and expands the upstream
 * examples across varied nested application documents so each TestRunner PASS
 * case checks a distinct JSON1/JSONB path or mutation result.
 */

/**
 * @return list<array{name:string,json:string,decoded:array<string,mixed>}>
 */
$documents = static function (): array {
    $rows = [];
    for ($i = 0; $i < 24; $i++) {
        $decoded = [
            'a' => $i + 1,
            'b' => [
                $i,
                [$i + 1, $i + 2, ['deep' => $i + 3]],
                $i + 4,
                ['tail' => $i + 5, 'label' => 'node-' . $i],
            ],
            'c' => 99 - $i,
            'tags' => ['alpha-' . $i, 'beta-' . $i, 'gamma-' . $i],
            'meta' => [
                'flags' => [$i % 2 === 0, $i % 3 === 0, null],
                'scores' => [$i * 10, $i * 10 + 1, $i * 10 + 2],
            ],
        ];
        $rows[] = [
            'name' => 'doc-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'decoded' => $decoded,
            'json' => json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ];
    }

    return $rows;
};

$encode = static fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

/**
 * @return list<array{kind:string,key?:string,index?:int,append?:bool,fromEnd?:int}>
 */
$segments = static function (string $path): array {
    if ($path === '$') {
        return [];
    }
    preg_match_all('/\\.([A-Za-z_][A-Za-z0-9_]*)|\\[(#(?:-\\d+)?|\\d+)\\]/', $path, $matches, PREG_SET_ORDER);
    $segments = [];
    foreach ($matches as $match) {
        if (($match[1] ?? '') !== '') {
            $segments[] = ['kind' => 'member', 'key' => $match[1]];
            continue;
        }
        $token = $match[2];
        if ($token === '#') {
            $segments[] = ['kind' => 'index', 'append' => true];
            continue;
        }
        if (str_starts_with($token, '#-')) {
            $segments[] = ['kind' => 'index', 'fromEnd' => (int) substr($token, 2)];
            continue;
        }
        $segments[] = ['kind' => 'index', 'index' => (int) $token];
    }

    return $segments;
};

$arrayIndex = static function (array $segment, int $count, bool $forAppend = false): ?int {
    if (($segment['append'] ?? false) === true) {
        return $forAppend ? $count : null;
    }
    if (array_key_exists('fromEnd', $segment)) {
        $index = $count - (int) $segment['fromEnd'];
        return $index >= 0 ? $index : null;
    }

    return $segment['index'] ?? null;
};

$getPath = static function (array $value, string $path) use ($segments, $arrayIndex): array {
    $current = $value;
    foreach ($segments($path) as $segment) {
        if ($segment['kind'] === 'member') {
            $key = $segment['key'];
            if (!is_array($current) || array_is_list($current) || !array_key_exists($key, $current)) {
                return ['found' => false, 'value' => null];
            }
            $current = $current[$key];
            continue;
        }

        if (!is_array($current) || !array_is_list($current)) {
            return ['found' => false, 'value' => null];
        }
        $index = $arrayIndex($segment, count($current));
        if ($index === null || !array_key_exists($index, $current)) {
            return ['found' => false, 'value' => null];
        }
        $current = $current[$index];
    }

    return ['found' => true, 'value' => $current];
};

$setPath = static function (array $value, string $path, mixed $replacement, string $mode) use ($segments, $arrayIndex): array {
    $pathSegments = $segments($path);
    $cursor = &$value;
    foreach ($pathSegments as $offset => $segment) {
        $last = $offset === array_key_last($pathSegments);
        if ($segment['kind'] === 'member') {
            $key = $segment['key'];
            if (!is_array($cursor) || array_is_list($cursor)) {
                return $value;
            }
            if ($last) {
                $exists = array_key_exists($key, $cursor);
                if (($mode === 'insert' && $exists) || ($mode === 'replace' && !$exists)) {
                    return $value;
                }
                $cursor[$key] = $replacement;
                return $value;
            }
            if (!array_key_exists($key, $cursor)) {
                return $value;
            }
            $cursor = &$cursor[$key];
            continue;
        }

        if (!is_array($cursor) || !array_is_list($cursor)) {
            return $value;
        }
        $index = $arrayIndex($segment, count($cursor), $last && $mode !== 'replace');
        if ($index === null || $index > count($cursor) || (!$last && !array_key_exists($index, $cursor))) {
            return $value;
        }
        if ($last) {
            $exists = array_key_exists($index, $cursor);
            if (($mode === 'insert' && $exists) || ($mode === 'replace' && !$exists)) {
                return $value;
            }
            if ($index === count($cursor)) {
                $cursor[] = $replacement;
            } else {
                $cursor[$index] = $replacement;
            }
            return $value;
        }
        $cursor = &$cursor[$index];
    }

    return $value;
};

$removePath = static function (array $value, string ...$paths) use ($segments, $arrayIndex): array {
    foreach ($paths as $path) {
        $pathSegments = $segments($path);
        $cursor = &$value;
        foreach ($pathSegments as $offset => $segment) {
            $last = $offset === array_key_last($pathSegments);
            if ($segment['kind'] === 'member') {
                $key = $segment['key'];
                if (!is_array($cursor) || array_is_list($cursor) || !array_key_exists($key, $cursor)) {
                    break;
                }
                if ($last) {
                    unset($cursor[$key]);
                    break;
                }
                $cursor = &$cursor[$key];
                continue;
            }

            if (!is_array($cursor) || !array_is_list($cursor)) {
                break;
            }
            $index = $arrayIndex($segment, count($cursor));
            if ($index === null || !array_key_exists($index, $cursor)) {
                break;
            }
            if ($last) {
                array_splice($cursor, $index, 1);
                break;
            }
            $cursor = &$cursor[$index];
        }
        unset($cursor);
    }

    return $value;
};

$sqliteScalar = static function (mixed $value) use ($encode): mixed {
    if (is_array($value)) {
        return $encode($value);
    }
    if ($value === true) {
        return 1;
    }
    if ($value === false) {
        return 0;
    }

    return $value;
};

$jsonbText = static function (SQLiteBlobValue|string|null $value): ?string {
    if ($value === null || is_string($value)) {
        return $value;
    }

    return SQLiteJsonCanonical::json($value);
};

$jsonValue = static function (mixed $value) use ($encode): mixed {
    return is_array($value) ? new PortLibs\LibSqlite\SQLiteJsonSubtypeValue($encode($value)) : $value;
};

$extractPaths = ['$.b[#]', '$.b[#-1]', '$.b[#-2]', '$.b[#-02]', '$.b[#-3]', '$.b[#-4]', '$.b[#-5]', '$.b[#-2][#-1]', '$.b[0]', '$.tags[#-1]', '$.meta.flags[#-1]', '$.meta.scores[#-2]'];
$removePaths = ['$.b[#]', '$.b[#-0]', '$.b[#-1]', '$.b[#-2]', '$.b[#-3]', '$.b[#-4]', '$.b[#-2][#-1]', '$.tags[#-1]', '$.meta.flags[#-2]', '$.meta.scores[#-3]'];
$appendPaths = ['$.b[#]' => 'append-b', '$.b[1][#]' => 'append-nested', '$.tags[#]' => 'append-tag', '$.meta.flags[#]' => true, '$.meta.scores[#]' => 777];
$reverseSetPaths = ['$.b[#-1]' => 'tail-set', '$.b[1][#-1]' => 'nested-tail-set', '$.tags[#-2]' => 'tag-set', '$.meta.flags[#-1]' => false, '$.meta.scores[#-3]' => 444];
$invalidPaths = ['$.b[#-]', '$.b[#9]', '$.b[#+2]', '$.b[#-1', '$.b[#-1x]'];

$tests = [];

foreach ($documents() as $document) {
    $name = $document['name'];
    $json = $document['json'];
    $decoded = $document['decoded'];
    $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode($decoded));

    foreach ($extractPaths as $path) {
        $located = $getPath($decoded, $path);
        $tests["real upstream corpus json105 {$name} extract {$path}"] = static function (TestRunner $t) use ($json, $path, $located, $sqliteScalar): void {
            $t->same($located['found'] ? $sqliteScalar($located['value']) : null, SQLiteJsonExtract::extract($json, $path));
        };
        $tests["real upstream corpus json105 {$name} jsonb extract {$path}"] = static function (TestRunner $t) use ($jsonb, $path, $located, $sqliteScalar, $jsonbText, $encode): void {
            $actual = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb, $path);
            if (!$located['found']) {
                $t->same(null, $actual);
                return;
            }
            if (is_array($located['value'])) {
                $t->same($encode($located['value']), $jsonbText($actual));
                return;
            }
            $t->same($sqliteScalar($located['value']), $actual);
        };
    }

    foreach ($removePaths as $path) {
        $expected = $encode($removePath($decoded, $path));
        $tests["real upstream corpus json105 {$name} remove {$path}"] = static function (TestRunner $t) use ($json, $path, $expected): void {
            $t->same($expected, SQLiteJsonRemove::remove($json, $path));
        };
        $tests["real upstream corpus jsonb01 {$name} jsonb remove {$path}"] = static function (TestRunner $t) use ($jsonb, $path, $expected, $jsonbText): void {
            $t->same($expected, $jsonbText(SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonb, $path)));
        };
    }

    $expected = $encode($removePath($decoded, '$.b[#-1]', '$.b[0]'));
    $tests["real upstream corpus json105 {$name} remove ordered tail then head"] = static function (TestRunner $t) use ($json, $expected): void {
        $t->same($expected, SQLiteJsonRemove::remove($json, '$.b[#-1]', '$.b[0]'));
    };

    $expected = $encode($removePath($decoded, '$.b[0]', '$.b[#-1]'));
    $tests["real upstream corpus json105 {$name} remove ordered head then tail"] = static function (TestRunner $t) use ($json, $expected): void {
        $t->same($expected, SQLiteJsonRemove::remove($json, '$.b[0]', '$.b[#-1]'));
    };

    foreach ($appendPaths as $path => $replacement) {
        $expected = $encode($setPath($decoded, $path, $replacement, 'insert'));
        $tests["real upstream corpus json105 {$name} insert append {$path}"] = static function (TestRunner $t) use ($json, $path, $replacement, $expected, $jsonValue): void {
            $t->same($expected, SQLiteJsonMutation::mutateSqlFunction('json_insert', $json, $path, $jsonValue($replacement)));
        };

        $expected = $encode($setPath($decoded, $path, $replacement, 'set'));
        $tests["real upstream corpus json105 {$name} set append {$path}"] = static function (TestRunner $t) use ($json, $path, $replacement, $expected, $jsonValue): void {
            $t->same($expected, SQLiteJsonMutation::mutateSqlFunction('json_set', $json, $path, $jsonValue($replacement)));
        };

        $tests["real upstream corpus json105 {$name} replace ignores append {$path}"] = static function (TestRunner $t) use ($json, $path, $replacement, $jsonValue): void {
            $t->same($json, SQLiteJsonMutation::mutateSqlFunction('json_replace', $json, $path, $jsonValue($replacement)));
        };
    }

    foreach ($reverseSetPaths as $path => $replacement) {
        $expected = $encode($setPath($decoded, $path, $replacement, 'set'));
        $tests["real upstream corpus json105 {$name} set reverse index {$path}"] = static function (TestRunner $t) use ($json, $path, $replacement, $expected, $jsonValue): void {
            $t->same($expected, SQLiteJsonMutation::mutateSqlFunction('json_set', $json, $path, $jsonValue($replacement)));
        };

        $expected = $encode($setPath($decoded, $path, $replacement, 'replace'));
        $tests["real upstream corpus json105 {$name} replace reverse index {$path}"] = static function (TestRunner $t) use ($json, $path, $replacement, $expected, $jsonValue): void {
            $t->same($expected, SQLiteJsonMutation::mutateSqlFunction('json_replace', $json, $path, $jsonValue($replacement)));
        };
    }
}

foreach ($invalidPaths as $path) {
    $tests["real upstream corpus json105 malformed path rejects {$path}"] = static function (TestRunner $t) use ($path): void {
        try {
            SQLiteJsonExtract::extract('{"b":[1,2,3]}', $path);
            $t->fail('Expected malformed JSON path exception');
        } catch (InvalidArgumentException $exception) {
            $t->contains('JSON path', $exception->getMessage());
        }
    };
}

return $tests;
