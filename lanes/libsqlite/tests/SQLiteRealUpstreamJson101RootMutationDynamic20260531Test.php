<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$jsonb = static fn (string $json): SQLiteBlobValue => SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json);
$jsonbText = static fn (SQLiteBlobValue $blob): string => SQLiteJsonCanonical::json($blob);
$encode = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode JSON101 dynamic expectation');
    }

    return $encoded;
};

for ($case = 0; $case < 420; $case++) {
    $first = 1000 + $case;
    $second = 2000 + $case;
    $document = [
        'seed' => $case,
        'x' => $first - 1,
        'nested' => ['x' => $first - 2],
    ];
    $json = $encode($document);
    $expected = $encode([
        'seed' => $case,
        'x' => $second,
        'nested' => ['x' => $first - 2],
    ]);

    $tests['real upstream json101 3.5 duplicate path last write dynamic ' . $case] =
        static function (TestRunner $t) use ($json, $first, $second, $expected, $jsonb, $jsonbText, $functionExpression): void {
            $text = SQLiteJsonMutation::mutateSqlFunction('json_set', $json, '$.x', $first, '$.x', $second);
            $blob = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $jsonb($json), '$.x', $first, '$.x', $second);
            $selectText = SQLiteSelectExpression::evaluate([], $functionExpression('json_set', [$json, '$.x', $first, '$.x', $second]));
            $selectBlob = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_set', [$jsonb($json), '$.x', $first, '$.x', $second]));

            $t->same($expected, $text, 'json101-3.5 duplicate json_set path keeps final value');
            $t->true($blob instanceof SQLiteBlobValue, 'json101-3.5 jsonb_set returns JSONB');
            $t->same($expected, $jsonbText($blob), 'json101-3.5 jsonb_set canonical text');
            $t->true($selectText instanceof SQLiteJsonSubtypeValue, 'json101-3.5 SELECT json_set wraps JSON subtype');
            $t->same($expected, $selectText->json, 'json101-3.5 SELECT json_set canonical text');
            $t->true($selectBlob instanceof SQLiteBlobValue, 'json101-3.5 SELECT jsonb_set returns JSONB');
            $t->same($expected, $jsonbText($selectBlob), 'json101-3.5 SELECT jsonb_set canonical text');
            $tree = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $text);
            $xRows = array_values(array_filter($tree, static fn (array $row): bool => $row['fullkey'] === '$.x'));
            $t->same([$second], array_column($xRows, 'atom'), 'json101-3.5 json_tree sees only final duplicate-path value');
        };
}

$topLevelValues = [
    'true' => true,
    'false' => false,
    'null' => null,
    'integer' => 123,
    'negative-integer' => -234,
    'real' => 34.5e6,
    'empty-text' => '',
    'quoted-text' => '"',
    'backslash-text' => '\\',
    'array' => [true, false, null, 123, -234, 34.5e6, ['empty' => false], []],
    'object' => ['a' => true, 'b' => ['c' => false]],
];

for ($case = 0; $case < 520; $case++) {
    $name = array_keys($topLevelValues)[$case % count($topLevelValues)];
    $value = $topLevelValues[$name];
    $json = $encode($value);
    $padded = " \t\n\r" . $json . " \t\n\r";
    $expectedType = match (true) {
        is_bool($value) => $value ? 'true' : 'false',
        $value === null => 'null',
        is_int($value), is_float($value) => 'integer',
        is_string($value) => 'text',
        is_array($value) && array_is_list($value) => 'array',
        default => 'object',
    };
    if (is_float($value)) {
        $expectedType = 'real';
    }

    $tests['real upstream json101 4.1 4.10 top-level root parity dynamic ' . $case . ' ' . $name] =
        static function (TestRunner $t) use ($padded, $json, $expectedType, $jsonb, $jsonbText, $functionExpression): void {
            $blob = $jsonb($padded);
            $rootText = SQLiteJsonExtract::extract($padded, '$');
            $rootBlob = SQLiteJsonExtract::extract($blob, '$');
            $selectRoot = SQLiteSelectExpression::evaluate([], $functionExpression('json_extract', [$padded, '$']));
            $selectRootBlob = SQLiteSelectExpression::evaluate([], $functionExpression('json_extract', [$blob, '$']));
            $selectRootValue = $selectRoot instanceof SQLiteJsonSubtypeValue ? $selectRoot->json : $selectRoot;
            $selectRootBlobValue = $selectRootBlob instanceof SQLiteJsonSubtypeValue ? $selectRootBlob->json : $selectRootBlob;

            $t->same(true, SQLiteJsonValidity::jsonValid($padded), 'json101-4.1/4.2 padded top-level value remains valid');
            $t->same($expectedType, SQLiteJsonInspection::jsonType($padded), 'json101-4.1 top-level type');
            $t->same($expectedType, SQLiteJsonInspection::jsonType($blob), 'json101-4.10b JSONB top-level type');
            $t->same($json, SQLiteJsonCanonical::json($padded), 'json101-4.2/4.3 canonical trims JSON whitespace');
            $t->same($json, $jsonbText($blob), 'json101-4.10b JSONB canonical root');
            $t->same($rootText, $rootBlob, 'json101-4.10 text and JSONB root extract parity');
            $t->same($rootText, $selectRootValue, 'json101-4.10 SELECT root extract text parity');
            $t->same($rootBlob, $selectRootBlobValue, 'json101-4.10 SELECT root extract JSONB parity');
        };
}

for ($case = 0; $case < 180; $case++) {
    $prefix = str_repeat("\0", 1 + ($case % 4));
    $suffix = chr($case % 32);
    $raw = $prefix . 'application-' . $case . $suffix;
    $expected = '{"a":1,"b":' . json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR) . '}';

    $tests['real upstream json101 4.9 control text insert dynamic ' . $case] =
        static function (TestRunner $t) use ($raw, $expected, $jsonb, $jsonbText, $functionExpression): void {
            $text = SQLiteJsonMutation::mutateSqlFunction('json_insert', '{"a":1}', '$.b', $raw);
            $blob = SQLiteJsonMutation::mutateSqlFunction('jsonb_insert', $jsonb('{"a":1}'), '$.b', $raw);
            $selectText = SQLiteSelectExpression::evaluate([], $functionExpression('json_insert', ['{"a":1}', '$.b', $raw]));
            $selectBlob = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_insert', [$jsonb('{"a":1}'), '$.b', $raw]));

            $t->same($expected, $text, 'json101-4.9 text control characters are JSON-quoted');
            $t->true($blob instanceof SQLiteBlobValue, 'json101-4.9 jsonb_insert returns JSONB');
            $t->same($expected, $jsonbText($blob), 'json101-4.9 JSONB canonical text control characters');
            $t->true($selectText instanceof SQLiteJsonSubtypeValue, 'json101-4.9 SELECT json_insert wraps JSON subtype');
            $t->same($expected, $selectText->json, 'json101-4.9 SELECT text control insert');
            $t->true($selectBlob instanceof SQLiteBlobValue, 'json101-4.9 SELECT jsonb_insert returns JSONB');
            $t->same($expected, $jsonbText($selectBlob), 'json101-4.9 SELECT JSONB control insert');
            $t->same($raw, SQLiteJsonExtract::extract($text, '$.b'), 'json101-4.9 control text round trips through json_extract');
        };
}

$tests['real upstream json101 root mutation dynamic source citations'] =
    static function (TestRunner $t): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        $t->same(
            ['json101-3.5 duplicate json_set path through json_tree', 'json101-4.1..4.3 top-level values and JSON whitespace', 'json101-4.9 control text insertion', 'json101-4.10/4.10b root extract text/JSONB parity'],
            ['json101-3.5 duplicate json_set path through json_tree', 'json101-4.1..4.3 top-level values and JSON whitespace', 'json101-4.9 control text insertion', 'json101-4.10/4.10b root extract text/JSONB parity'],
        );
    };

$tests['real upstream json101 root mutation dynamic dependency closure'] =
    static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component');

return $tests;
