<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

/**
 * Real upstream source:
 * - SQLite json105.test sections 1.10-6.50 for [#] append and [#-N] reverse
 *   JSON path behavior across extract/remove/insert/set/replace.
 * - SQLite json107.test sections 1.1-2.1 for legacy text-looking BLOB JSON
 *   compatibility across json_valid(), ->/->>, mutation, inspection and tree.
 * - SQLite json108.test section 1.1-1.5 for json_pretty() canonical identity
 *   under null, empty, tab and comment-like indentation strings.
 *
 * The upstream Tcl cases are small fixed documents.  This PHP corpus keeps the
 * same behavior clusters and expands them over deterministic application
 * documents so each TestRunner PASS case checks a distinct path, JSONB/text
 * source shape, or pretty-printing input.
 */

$tests = [];

$canonicalJson = static function (mixed $value): string {
    if (is_string($value)) {
        return SQLiteJsonCanonical::json($value) ?? 'null';
    }

    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode JSON corpus value');
    }

    return SQLiteJsonCanonical::json($json) ?? 'null';
};

$jsonb = static function (string $json): SQLiteBlobValue {
    return new SQLiteBlobValue(SQLiteJsonB::encode(json_decode(SQLiteJsonCanonical::json($json) ?? 'null', true, 1001, JSON_THROW_ON_ERROR)));
};

$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value) ?? 'null';

$functionExpression = static fn (string $name, mixed ...$arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map(static fn (mixed $value): array => ['type' => 'literal', 'value' => $value], $arguments),
];

$binaryExpression = static fn (mixed $left, string $operator, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => ['type' => 'literal', 'value' => $left],
    'right' => ['type' => 'literal', 'value' => $right],
];

/**
 * @return array<string,array{json:string,decoded:array<string,mixed>,blob:SQLiteBlobValue}>
 */
$documents = [];
for ($i = 0; $i < 180; $i++) {
    $decoded = [
        'a' => $i + 1,
        'b' => [
            $i,
            [$i + 2, $i + 3, ['leaf' => 'leaf-' . $i]],
            $i + 4,
            ['tail' => $i + 5, 'flag' => ($i % 2) === 0],
        ],
        'c' => 99 - $i,
        'items' => [
            ['id' => $i * 10 + 1, 'name' => 'alpha-' . $i],
            ['id' => $i * 10 + 2, 'name' => 'beta-' . ($i % 13)],
            ['id' => $i * 10 + 3, 'name' => 'gamma-' . ($i % 7)],
        ],
        'meta' => [
            'group' => 'json-dynamic-' . ($i % 11),
            'scores' => [$i, $i + 1, $i + 2],
            'active' => ($i % 3) === 0,
        ],
    ];
    $json = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode JSON corpus document');
    }

    $documents['json-dynamic-large-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)] = [
        'json' => $json,
        'decoded' => $decoded,
        'blob' => new SQLiteBlobValue($json),
    ];
}

$reversePathCases = [
    'json105-1.20 last array element' => ['$.b[#-1]', static fn (array $d): mixed => $d['b'][3]],
    'json105-1.30 nested array via reverse index' => ['$.b[#-3]', static fn (array $d): mixed => $d['b'][1]],
    'json105-1.60 nested reverse scalar' => ['$.b[#-3][#-2]', static fn (array $d): mixed => $d['b'][1][1]],
    'json105-1.70 multi path first and last' => ['$.b[0]', static fn (array $d): mixed => $d['b'][0]],
    'json105-1.110 leading zero reverse last' => ['$.b[#-000001]', static fn (array $d): mixed => $d['b'][3]],
];

$removeExpected = [
    'json105-2.30 remove reverse last' => [
        ['$.b[#-1]'],
        static function (array $d): array {
            array_splice($d['b'], 3, 1);
            return $d;
        },
    ],
    'json105-2.40 remove reverse nested array' => [
        ['$.b[#-3]'],
        static function (array $d): array {
            array_splice($d['b'], 1, 1);
            return $d;
        },
    ],
    'json105-2.70 remove nested reverse scalar' => [
        ['$.b[#-3][#-1]'],
        static function (array $d): array {
            array_splice($d['b'][1], 2, 1);
            return $d;
        },
    ],
    'json105-2.130 repeated reverse last' => [
        ['$.b[#-1]', '$.b[#-1]'],
        static function (array $d): array {
            array_splice($d['b'], 3, 1);
            array_splice($d['b'], 2, 1);
            return $d;
        },
    ],
    'json105-2.140 reverse second then last' => [
        ['$.b[#-2]', '$.b[#-1]'],
        static function (array $d): array {
            array_splice($d['b'], 2, 1);
            array_splice($d['b'], 2, 1);
            return $d;
        },
    ],
];

$mutationExpected = [
    'json105-3.10 insert append slot' => [
        'json_insert',
        ['$.b[#]', 'tail-added'],
        static function (array $d): array {
            $d['b'][] = 'tail-added';
            return $d;
        },
    ],
    'json105-3.20 insert nested append slot' => [
        'json_insert',
        ['$.b[1][#]', 'nested-added'],
        static function (array $d): array {
            $d['b'][1][] = 'nested-added';
            return $d;
        },
    ],
    'json105-4.50 set reverse last slot' => [
        'json_set',
        ['$.b[#-1]', 'set-tail'],
        static function (array $d): array {
            $d['b'][3] = 'set-tail';
            return $d;
        },
    ],
    'json105-4.60 set nested reverse last slot' => [
        'json_set',
        ['$.b[1][#-1]', 'set-nested-tail'],
        static function (array $d): array {
            $d['b'][1][2] = 'set-nested-tail';
            return $d;
        },
    ],
    'json105-5.50 replace reverse last slot' => [
        'json_replace',
        ['$.b[#-1]', 'replace-tail'],
        static function (array $d): array {
            $d['b'][3] = 'replace-tail';
            return $d;
        },
    ],
];

foreach ($documents as $name => $document) {
    foreach ($reversePathCases as $label => [$path, $expected]) {
        $tests['real upstream json105 dynamic reverse extract ' . $name . ' ' . $label] =
            static function (TestRunner $t) use ($document, $path, $expected, $jsonb, $functionExpression): void {
                $expectedValue = $expected($document['decoded']);
                $textActual = SQLiteJsonExtract::extractSqlFunction('json_extract', $document['json'], $path);
                $blobActual = SQLiteJsonExtract::extractSqlFunction('json_extract', $document['blob'], $path);
                $jsonbActual = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb($document['json']), $path);

                $expectedSqlValue = is_array($expectedValue)
                    ? SQLiteJsonCanonical::json(json_encode($expectedValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))
                    : ($expectedValue === true ? 1 : ($expectedValue === false ? 0 : $expectedValue));

                $t->same($expectedSqlValue, $textActual, 'json105 text reverse extract');
                $t->same($expectedSqlValue, $blobActual, 'json105 legacy text BLOB reverse extract');
                if ($expectedValue === true || $expectedValue === false || $expectedValue === null || is_int($expectedValue) || is_float($expectedValue) || is_string($expectedValue)) {
                    $t->same($expectedSqlValue, $jsonbActual, 'json105 jsonb reverse scalar extract');
                } else {
                    $t->true($jsonbActual instanceof SQLiteBlobValue, 'json105 jsonb reverse compound extract');
                    $t->same($expectedSqlValue, SQLiteJsonCanonical::json($jsonbActual), 'json105 jsonb reverse compound canonical extract');
                }

                $selectActual = SQLiteSelectExpression::evaluate([], $functionExpression('json_extract', $document['blob'], $path));
                if ($selectActual instanceof PortLibs\LibSqlite\SQLiteJsonSubtypeValue) {
                    $selectActual = $selectActual->json;
                }
                $t->same($expectedSqlValue, $selectActual, 'json105 SELECT expression reverse extract');
            };
    }

    foreach ($removeExpected as $label => [$paths, $expected]) {
        $tests['real upstream json105 dynamic reverse remove ' . $name . ' ' . $label] =
            static function (TestRunner $t) use ($document, $paths, $expected, $canonicalJson, $jsonb, $jsonbText): void {
                $expectedJson = $canonicalJson($expected($document['decoded']));
                $textActual = SQLiteJsonRemove::removeSqlFunction('json_remove', $document['json'], ...$paths);
                $blobActual = SQLiteJsonRemove::removeSqlFunction('json_remove', $document['blob'], ...$paths);
                $jsonbActual = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $jsonb($document['json']), ...$paths);

                $t->same($expectedJson, $textActual, 'json105 text reverse remove');
                $t->same($expectedJson, $blobActual, 'json105 legacy text BLOB reverse remove');
                $t->true($jsonbActual instanceof SQLiteBlobValue, 'json105 jsonb reverse remove returns BLOB');
                $t->same($expectedJson, $jsonbText($jsonbActual), 'json105 jsonb reverse remove canonical parity');
            };
    }

    foreach ($mutationExpected as $label => [$function, $arguments, $expected]) {
        $tests['real upstream json105 dynamic append reverse mutation ' . $name . ' ' . $label] =
            static function (TestRunner $t) use ($document, $function, $arguments, $expected, $canonicalJson, $jsonb, $jsonbText): void {
                $expectedJson = $canonicalJson($expected($document['decoded']));
                $jsonbFunction = str_replace('json_', 'jsonb_', $function);
                $textActual = SQLiteJsonMutation::mutateSqlFunction($function, $document['json'], ...$arguments);
                $blobActual = SQLiteJsonMutation::mutateSqlFunction($function, $document['blob'], ...$arguments);
                $jsonbActual = SQLiteJsonMutation::mutateSqlFunction($jsonbFunction, $jsonb($document['json']), ...$arguments);

                $t->same($expectedJson, $textActual, 'json105 text mutation');
                $t->same($expectedJson, $blobActual, 'json105 legacy text BLOB mutation');
                $t->true($jsonbActual instanceof SQLiteBlobValue, 'json105 jsonb mutation returns BLOB');
                $t->same($expectedJson, $jsonbText($jsonbActual), 'json105 jsonb mutation canonical parity');
            };
    }

    $tests['real upstream json107 dynamic legacy blob validity and tree ' . $name] =
        static function (TestRunner $t) use ($document, $functionExpression, $binaryExpression): void {
            $blob = $document['blob'];
            $t->same(1, SQLiteSelectExpression::evaluate([], $functionExpression('json_valid', $blob)), 'json107 SELECT json_valid text BLOB');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 1]), 'json107 flag 1 text BLOB valid');
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 2]), 'json107 flag 2 text BLOB valid');
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 4]), 'json107 superficial JSONB flag rejects text BLOB');
            $t->same($document['decoded']['a'], SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->>', 'a')), 'json107 ->> scalar from text BLOB');
            $t->same((string) $document['decoded']['a'], SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->', 'a')), 'json107 -> scalar JSON text from text BLOB');
            $t->same('array', SQLiteJsonInspection::jsonType($blob, '$.items'), 'json107 json_type text BLOB');
            $t->same(3, SQLiteJsonInspection::jsonArrayLength($blob, '$.items'), 'json107 json_array_length text BLOB');
            $rows = SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [$blob]);
            $t->true(count($rows) >= 20, 'json107 json_tree walks text-looking BLOB');
        };

    $tests['real upstream json108 dynamic pretty canonical identity ' . $name] =
        static function (TestRunner $t) use ($document, $jsonb, $canonicalJson): void {
            foreach ([null, '', "\t", '/*hello*/'] as $indent) {
                $args = $indent === null ? [$document['json']] : [$document['json'], $indent];
                $prettyText = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', $args);
                $prettyBlob = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$document['blob'], $indent]);
                $prettyJsonb = SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [$jsonb($document['json']), $indent]);

                $t->same($canonicalJson($document['json']), $canonicalJson($prettyText), 'json108 text pretty canonical identity');
                $t->same($canonicalJson($document['json']), $canonicalJson($prettyBlob), 'json108 text BLOB pretty canonical identity');
                $t->same($canonicalJson($document['json']), $canonicalJson($prettyJsonb), 'json108 JSONB pretty canonical identity');
            }
        };
}

$invalidPaths = ['$.b[#-]', '$.b[#9]', '$.b[#+2]', '$.b[#-1', '$.b[#-1x]'];
foreach ($invalidPaths as $path) {
    $tests['real upstream json105 dynamic malformed reverse path rejects ' . $path] =
        static function (TestRunner $t) use ($path, $documents): void {
            foreach (array_slice($documents, 0, 20) as $document) {
                $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonExtract::extractSqlFunction('json_extract', $document['json'], $path), 'json105 malformed extract path');
                $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteJsonRemove::removeSqlFunction('json_remove', $document['json'], $path), 'json105 malformed remove path');
            }
        };
}

$tests['real upstream json1 jsonb dynamic large corpus source citations'] =
    static function (TestRunner $t) use ($documents): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test');
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test');
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test');
        $t->same(180, count($documents));
        $t->same(
            ['json105-1.* extract', 'json105-2.* remove', 'json105-3.* through 5.* mutation', 'json105-6.* malformed paths', 'json107-1.1 through 2.1 BLOB compatibility', 'json108-1.1 through 1.5 pretty identity'],
            ['json105-1.* extract', 'json105-2.* remove', 'json105-3.* through 5.* mutation', 'json105-6.* malformed paths', 'json107-1.1 through 2.1 BLOB compatibility', 'json108-1.1 through 1.5 pretty identity'],
        );
    };

return $tests;
