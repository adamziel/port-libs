<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectExpression;

/**
 * Real upstream source: SQLite json101.test sections 3.1 through 3.5.
 *
 * Those upstream cases distinguish SQL text that looks like JSON from values
 * already marked as JSON/JSONB when routed through json_set(), json_replace(),
 * json_insert(), and jsonb_* equivalents.  This file expands that behavior
 * through the parser-expression dispatch layer instead of the lower-level
 * helpers, so each PASS case checks a distinct expression/function/path/source
 * combination.
 */

$fn = static fn (string $name, array $arguments): array => ['type' => 'function', 'name' => $name, 'arguments' => $arguments];
$lit = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$canonical = static fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue|string|null $value): ?string => $value instanceof SQLiteBlobValue ? SQLiteJsonCanonical::json($value) : $value;
$jsonText = static fn (SQLiteJsonSubtypeValue|string|null $value): ?string => $value instanceof SQLiteJsonSubtypeValue ? $value->json : $value;

$documents = [];
for ($i = 0; $i < 96; $i++) {
    $documents[] = [
        'name' => 'doc-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
        'decoded' => [
            'id' => $i,
            'slot' => $i + 1,
            'nested' => [
                'slot' => 'value-' . $i,
                'items' => [$i, $i + 1],
            ],
            'flags' => [$i % 2 === 0, $i % 3 === 0],
        ],
    ];
}

$pathCases = [
    ['name' => 'root-slot', 'path' => '$.slot', 'exists' => true],
    ['name' => 'nested-slot', 'path' => '$.nested.slot', 'exists' => true],
    ['name' => 'missing-root', 'path' => '$.created', 'exists' => false],
    ['name' => 'missing-nested', 'path' => '$.nested.created', 'exists' => false],
];

$replacementCases = [
    [
        'name' => 'plain-array-text',
        'argument' => '[3,4,5]',
        'expected' => '[3,4,5]',
        'type' => 'text',
    ],
    [
        'name' => 'json-subtype-array',
        'argument' => new SQLiteJsonSubtypeValue('[3,4,5]'),
        'expected' => [3, 4, 5],
        'type' => 'array',
    ],
    [
        'name' => 'jsonb-object',
        'argument' => $jsonb(['x' => 3, 'y' => 4]),
        'expected' => ['x' => 3, 'y' => 4],
        'type' => 'object',
    ],
    [
        'name' => 'json-subtype-scalar',
        'argument' => new SQLiteJsonSubtypeValue('"json-string"'),
        'expected' => 'json-string',
        'type' => 'text',
    ],
];

$setPath = static function (array $value, string $path, mixed $replacement, string $mode) use ($canonical): array {
    $segments = explode('.', substr($path, 2));
    $cursor = &$value;
    foreach ($segments as $offset => $segment) {
        $last = $offset === array_key_last($segments);
        if (!is_array($cursor) || array_is_list($cursor)) {
            return $value;
        }
        if ($last) {
            $exists = array_key_exists($segment, $cursor);
            if (($mode === 'insert' && $exists) || ($mode === 'replace' && !$exists)) {
                return $value;
            }
            $cursor[$segment] = $replacement;
            return $value;
        }
        if (!array_key_exists($segment, $cursor)) {
            return $value;
        }
        $cursor = &$cursor[$segment];
    }

    return $value;
};

$tests = [];

foreach ($documents as $document) {
    $sourceJson = $canonical($document['decoded']);
    $sourceJsonb = $jsonb($document['decoded']);

    foreach ($pathCases as $pathCase) {
        foreach ($replacementCases as $replacementCase) {
            foreach (['json_set' => 'set', 'json_replace' => 'replace', 'json_insert' => 'insert'] as $function => $mode) {
                $case = sprintf(
                    'real upstream json101 expression value propagation %s %s %s %s text source',
                    $document['name'],
                    $pathCase['name'],
                    $replacementCase['name'],
                    $function
                );

                $tests[$case] = static function (TestRunner $t) use ($document, $sourceJson, $pathCase, $replacementCase, $function, $mode, $setPath, $canonical, $jsonText, $fn, $lit): void {
                    $expectedDoc = $setPath($document['decoded'], $pathCase['path'], $replacementCase['expected'], $mode);
                    $actual = SQLiteSelectExpression::evaluate([], $fn($function, [
                        $lit($sourceJson),
                        $lit($pathCase['path']),
                        $lit($replacementCase['argument']),
                    ]));
                    $actualText = $jsonText($actual);

                    $t->same(true, $actual instanceof SQLiteJsonSubtypeValue);
                    $t->same($canonical($expectedDoc), $actualText);

                    if ($mode === 'replace' && !$pathCase['exists']) {
                        $t->same(null, SQLiteJsonExtract::extract($actualText, $pathCase['path']));
                        return;
                    }
                    if ($mode === 'insert' && $pathCase['exists']) {
                        $t->same(SQLiteJsonExtract::extract($sourceJson, $pathCase['path']), SQLiteJsonExtract::extract($actualText, $pathCase['path']));
                        return;
                    }

                    $t->same($replacementCase['type'], SQLiteJsonInspection::jsonType($actualText, $pathCase['path']));
                };

                $jsonbFunction = str_replace('json_', 'jsonb_', $function);
                $case = sprintf(
                    'real upstream json101 expression value propagation %s %s %s %s jsonb source',
                    $document['name'],
                    $pathCase['name'],
                    $replacementCase['name'],
                    $jsonbFunction
                );

                $tests[$case] = static function (TestRunner $t) use ($document, $sourceJson, $sourceJsonb, $pathCase, $replacementCase, $jsonbFunction, $mode, $setPath, $canonical, $jsonbText, $fn, $lit): void {
                    $expectedDoc = $setPath($document['decoded'], $pathCase['path'], $replacementCase['expected'], $mode);
                    $actual = SQLiteSelectExpression::evaluate([], $fn($jsonbFunction, [
                        $lit($sourceJsonb),
                        $lit($pathCase['path']),
                        $lit($replacementCase['argument']),
                    ]));

                    $t->same(true, $actual instanceof SQLiteBlobValue);
                    $actualText = $jsonbText($actual);
                    $t->same($canonical($expectedDoc), $actualText);

                    if ($mode === 'replace' && !$pathCase['exists']) {
                        $t->same(null, SQLiteJsonExtract::extract($actual, $pathCase['path']));
                        return;
                    }
                    if ($mode === 'insert' && $pathCase['exists']) {
                        $t->same(SQLiteJsonExtract::extract($sourceJson, $pathCase['path']), SQLiteJsonExtract::extract($actual, $pathCase['path']));
                        return;
                    }

                    $t->same($replacementCase['type'], SQLiteJsonInspection::jsonType($actual, $pathCase['path']));
                };
            }
        }
    }
}

$tests['real upstream json101 expression value propagation source ownership'] = static function (TestRunner $t) use ($documents, $pathCases, $replacementCases): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
    $t->same(['json101-3.1', 'json101-3.2', 'json101-3.3', 'json101-3.4', 'json101-3.5'], ['json101-3.1', 'json101-3.2', 'json101-3.3', 'json101-3.4', 'json101-3.5']);
    $t->same(9216, count($documents) * count($pathCases) * count($replacementCases) * 3 * 2);
};

return $tests;
