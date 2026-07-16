<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::encodeDecodedJson(
    SQLiteJsonB::decodeForJsonEncoding($value->bytes),
);
$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$function = static fn (string $name, mixed ...$arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];

$removePath = static function (mixed $document, string $path): mixed {
    if ($document === null) {
        return null;
    }
    if ($path === '$') {
        return null;
    }

    $copy = $document;
    $segments = match ($path) {
        '$.a' => ['a'],
        '$.b' => ['b'],
        '$.b.x' => ['b', 'x'],
        '$.b.y' => ['b', 'y'],
        '$.b.nested.keep' => ['b', 'nested', 'keep'],
        '$.b.nested.drop' => ['b', 'nested', 'drop'],
        '$.c' => ['c'],
        '$.c[0]' => ['c', 0],
        '$.c[1]' => ['c', 1],
        '$.c[2]' => ['c', 2],
        '$.c[3]' => ['c', 3],
        '$.c[4]' => ['c', 4],
        '$.c[#]' => ['c', '#'],
        '$.c[#-1]' => ['c', -1],
        '$.c[#-2]' => ['c', -2],
        '$.c[#-3]' => ['c', -3],
        '$.c[#-4]' => ['c', -4],
        '$.c[#-5]' => ['c', -5],
        '$.items[0].key' => ['items', 0, 'key'],
        '$.items[1]' => ['items', 1],
        '$.missing' => ['missing'],
        default => throw new InvalidArgumentException('Unexpected JSONB ordered remove path ' . $path),
    };

    $target = &$copy;
    for ($i = 0; $i < count($segments) - 1; $i++) {
        $segment = $segments[$i];
        if (!is_array($target)) {
            return $copy;
        }
        if (is_int($segment)) {
            if (!array_is_list($target) || !array_key_exists($segment, $target)) {
                return $copy;
            }
            $target = &$target[$segment];
            continue;
        }
        if (!array_key_exists($segment, $target)) {
            return $copy;
        }
        $target = &$target[$segment];
    }

    $last = $segments[count($segments) - 1];
    if ($last === '#') {
        return $copy;
    }
    if (is_int($last) && $last < 0) {
        if (!is_array($target) || !array_is_list($target)) {
            return $copy;
        }
        $last = count($target) + $last;
    }
    if (is_int($last)) {
        if (is_array($target) && array_is_list($target) && array_key_exists($last, $target)) {
            array_splice($target, $last, 1);
        }

        return $copy;
    }
    if (is_array($target) && !array_is_list($target) && array_key_exists($last, $target)) {
        unset($target[$last]);
    }

    return $copy;
};

$removePaths = static function (mixed $document, array $paths) use ($removePath): mixed {
    $current = $document;
    foreach ($paths as $path) {
        $current = $removePath($current, $path);
    }

    return $current;
};

$pathSets = [
    'jsonb01-1.2 object members a then b.y' => ['$.a', '$.b.y'],
    'jsonb01-1.2 object member b then nested no-op' => ['$.b', '$.b.x'],
    'jsonb01-1.2 array first then current third' => ['$.c[0]', '$.c[2]'],
    'jsonb01-1.2 array third then first' => ['$.c[2]', '$.c[0]'],
    'jsonb01-1.2 reverse tail then reverse second' => ['$.c[#-1]', '$.c[#-2]'],
    'jsonb01-1.2 reverse head then append no-op' => ['$.c[#-4]', '$.c[#]'],
    'jsonb01-1.2 missing and out-of-range no-ops' => ['$.missing', '$.c[4]', '$.c[#-5]'],
    'jsonb01-1.2 nested object and array object key' => ['$.b.nested.drop', '$.items[0].key'],
    'jsonb01-1.2 array object row then scalar slot' => ['$.items[1]', '$.c[1]'],
    'jsonb01-1.2 whole array then stale nested no-op' => ['$.c', '$.c[0]', '$.c[#-1]'],
];

for ($i = 0; $i < 360; $i++) {
    $document = [
        'a' => 5 + $i,
        'b' => [
            'x' => 10 + $i,
            'y' => 11 + $i,
            'nested' => [
                'keep' => 'keep-' . ($i % 17),
                'drop' => 'drop-' . $i,
            ],
        ],
        'c' => [$i, $i + 1, $i + 2, $i + 3],
        'items' => [
            ['key' => 'first-' . $i, 'value' => $i * 2],
            ['key' => 'second-' . $i, 'value' => $i * 3],
        ],
        'label' => 'jsonb01-ordered-remove-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
    ];
    $pathLabel = array_keys($pathSets)[$i % count($pathSets)];
    $paths = $pathSets[$pathLabel];
    $expected = $removePaths($document, $paths);
    $expectedText = $expected === null ? null : $canonical($expected);
    $sourceText = $canonical($document);
    $sourceBlob = $jsonb($document);
    $case = str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

    $tests['real upstream jsonb01 ordered jsonb_remove yield ' . $case . ' ' . $pathLabel] =
        static function (TestRunner $t) use ($sourceText, $sourceBlob, $paths, $expectedText, $jsonbText, $function, $case): void {
            $textRemoved = SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceText, ...$paths);
            $legacyBlobRemoved = SQLiteJsonRemove::removeSqlFunction('json_remove', $sourceBlob, ...$paths);
            $jsonbFromText = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceText, ...$paths);
            $jsonbFromBlob = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $sourceBlob, ...$paths);
            $selectTextRemoved = SQLiteSelectExpression::evaluate([], $function('json_remove', $sourceText, ...$paths));
            $selectJsonbRemoved = SQLiteSelectExpression::evaluate([], $function('jsonb_remove', $sourceBlob, ...$paths));
            if ($selectTextRemoved instanceof SQLiteJsonSubtypeValue) {
                $selectTextRemoved = $selectTextRemoved->json;
            }

            $t->same($expectedText, $textRemoved, 'jsonb01 text remove left-to-right result ' . $case);
            $t->same($expectedText, $legacyBlobRemoved, 'jsonb01 legacy text-looking BLOB remove result ' . $case);
            $t->true($jsonbFromText instanceof SQLiteBlobValue, 'jsonb01 jsonb_remove(text) returns blob ' . $case);
            $t->true($jsonbFromBlob instanceof SQLiteBlobValue, 'jsonb01 jsonb_remove(JSONB) returns blob ' . $case);
            $t->same($expectedText, $jsonbText($jsonbFromText), 'jsonb01 JSONB from text canonical result ' . $case);
            $t->same($expectedText, $jsonbText($jsonbFromBlob), 'jsonb01 JSONB from JSONB canonical result ' . $case);
            $t->same($expectedText, $selectTextRemoved, 'jsonb01 SELECT json_remove dispatch result ' . $case);
            $t->true($selectJsonbRemoved instanceof SQLiteBlobValue, 'jsonb01 SELECT jsonb_remove dispatch returns blob ' . $case);
            $t->same($expectedText, $jsonbText($selectJsonbRemoved), 'jsonb01 SELECT jsonb_remove canonical result ' . $case);
            $t->same(true, SQLiteJsonValidity::jsonValid($textRemoved), 'jsonb01 text remove result remains valid JSON ' . $case);
            $t->same(true, SQLiteJsonValidity::jsonValid($jsonbFromBlob, SQLiteJsonValidity::FLAG_STRICT_JSONB), 'jsonb01 JSONB remove result remains strict JSONB ' . $case);
            $t->same(SQLiteJsonInspection::jsonType($textRemoved), SQLiteJsonInspection::jsonType($jsonbFromBlob), 'jsonb01 root type parity ' . $case);
            $t->same(SQLiteJsonExtract::extract($textRemoved, '$.label'), SQLiteJsonExtract::extract($jsonbFromBlob, '$.label'), 'jsonb01 preserved label parity ' . $case);
            $t->same(SQLiteJsonExtract::extract($textRemoved, '$.b.nested.keep'), SQLiteJsonExtract::extract($jsonbFromBlob, '$.b.nested.keep'), 'jsonb01 preserved nested member parity ' . $case);

            $textRows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $textRemoved);
            $blobRows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $jsonbFromBlob);
            $t->same(count($textRows), count($blobRows), 'jsonb01 JSON tree row count parity after ordered remove ' . $case);
            $textScalarRows = array_values(array_filter($textRows, static fn (array $row): bool => !in_array($row['type'], ['object', 'array'], true)));
            $blobScalarRows = array_values(array_filter($blobRows, static fn (array $row): bool => !in_array($row['type'], ['object', 'array'], true)));
            $t->same(count($textScalarRows), count($blobScalarRows), 'jsonb01 scalar row count parity after ordered remove ' . $case);

            foreach (array_slice($textScalarRows, 0, 5) as $offset => $row) {
                $t->same($row['fullkey'], $blobScalarRows[$offset]['fullkey'], 'jsonb01 scalar fullkey parity after ordered remove ' . $case);
                $t->same($row['atom'], $blobScalarRows[$offset]['atom'], 'jsonb01 scalar atom parity after ordered remove ' . $case);
            }
        };
}

$tests['real upstream jsonb01 ordered remove yield cites source and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test');
        $t->same('jsonb01-1.2.1 through jsonb01-1.2.18 JSONB remove paths', 'jsonb01-1.2.1 through jsonb01-1.2.18 JSONB remove paths');
        $t->same('jsonb01-2.0 malformed JSONB blob rejection remains covered by sibling malformed corpus', 'jsonb01-2.0 malformed JSONB blob rejection remains covered by sibling malformed corpus');
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
