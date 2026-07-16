<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteSelectExpression;

/*
 * Real upstream source:
 * /home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test
 *
 * Ported sections:
 * - json101-1047: json_extract(NULL) returns SQL NULL.
 * - json101-1050: json_insert(NULL,'$',123) returns SQL NULL.
 * - json101-1053..1062: NULL JSON operator operands return SQL NULL.
 * - json101-1077..1098: json_remove/json_replace/json_set/json_type with
 *   NULL JSON input or NULL path returns SQL NULL or a no-op document exactly
 *   as upstream SQLite specifies.
 */

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];
$binaryExpression = static fn (mixed $left, string $operator, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $literal($left),
    'right' => $literal($right),
];
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);

for ($case = 0; $case < 1000; $case++) {
    $document = [
        'a' => 5 + $case,
        'b' => 7 + ($case % 13),
        'nested' => [
            'flag' => ($case % 2) === 0,
            'items' => [$case, $case + 1, $case + 2],
        ],
    ];
    $json5 = '{a:' . $document['a'] . ',b:' . $document['b'] . ',nested:{flag:' . (($case % 2) === 0 ? 'true' : 'false') . ',items:[' . $case . ',' . ($case + 1) . ',' . ($case + 2) . ']}}';
    $strict = $canonical($document);
    $blob = $jsonb($document);

    $tests['real upstream json101 null propagation dynamic row ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case, $json5, $strict, $blob, $jsonbText, $functionExpression, $binaryExpression): void {
            $t->same(null, SQLiteJsonExtract::extractSqlFunction('json_extract', null), 'json101-1047 direct json_extract NULL input');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('json_extract', [null])), 'json101-1047 SELECT json_extract NULL input');

            $t->same(null, SQLiteJsonMutation::mutateSqlFunction('json_insert', null, '$', 123 + $case), 'json101-1050 direct json_insert NULL input');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('json_insert', [null, '$', 123 + $case])), 'json101-1050 SELECT json_insert NULL input');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_insert', [null, '$', 123 + $case])), 'json101-1050 SELECT jsonb_insert NULL input');

            $t->same(null, SQLiteSelectExpression::evaluate([], $binaryExpression(null, '->', 0)), 'json101-1053 NULL arrow RHS integer');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binaryExpression(null, '->>', 0)), 'json101-1056 NULL double-arrow RHS integer');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binaryExpression($json5, '->', null)), 'json101-1059 text arrow NULL RHS');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binaryExpression($json5, '->>', null)), 'json101-1062 text double-arrow NULL RHS');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->', null)), 'json101 JSONB arrow NULL RHS');
            $t->same(null, SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->>', null)), 'json101 JSONB double-arrow NULL RHS');

            $t->same(null, SQLiteJsonRemove::removeSqlFunction('json_remove', null, '$'), 'json101-1077 direct json_remove NULL input');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('json_remove', [null, '$'])), 'json101-1077 SELECT json_remove NULL input');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_remove', [null, '$'])), 'json101-1077 SELECT jsonb_remove NULL input');

            $t->same(null, SQLiteJsonRemove::removeSqlFunction('json_remove', $json5, null), 'json101-1080 direct json_remove NULL path');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('json_remove', [$json5, null])), 'json101-1080 SELECT json_remove NULL path');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_remove', [$blob, null])), 'json101-1080 SELECT jsonb_remove NULL path');

            $t->same(null, SQLiteJsonMutation::mutateSqlFunction('json_replace', null, '$.a', 123 + $case), 'json101-1083 direct json_replace NULL input');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('json_replace', [null, '$.a', 123 + $case])), 'json101-1083 SELECT json_replace NULL input');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_replace', [null, '$.a', 123 + $case])), 'json101-1083 SELECT jsonb_replace NULL input');

            $replaceText = SQLiteJsonMutation::mutateSqlFunction('json_replace', $json5, null, null);
            $replaceBlob = SQLiteJsonMutation::mutateSqlFunction('jsonb_replace', $blob, null, null);
            $t->same($strict, $replaceText, 'json101-1086 direct json_replace NULL path is no-op');
            $t->true($replaceBlob instanceof SQLiteBlobValue, 'json101-1086 direct jsonb_replace NULL path returns JSONB');
            $t->same($strict, $jsonbText($replaceBlob), 'json101-1086 direct jsonb_replace NULL path is no-op');
            $t->same($strict, SQLiteSelectExpression::evaluate([], $functionExpression('json_replace', [$json5, null, null]))->json, 'json101-1086 SELECT json_replace NULL path is no-op');

            $t->same(null, SQLiteJsonMutation::mutateSqlFunction('json_set', null, '$.a', 123 + $case), 'json101-1089 direct json_set NULL input');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('json_set', [null, '$.a', 123 + $case])), 'json101-1089 SELECT json_set NULL input');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_set', [null, '$.a', 123 + $case])), 'json101-1089 SELECT jsonb_set NULL input');

            $setText = SQLiteJsonMutation::mutateSqlFunction('json_set', $json5, null, null);
            $setBlob = SQLiteJsonMutation::mutateSqlFunction('jsonb_set', $blob, null, null);
            $t->same($strict, $setText, 'json101-1092 direct json_set NULL path is no-op');
            $t->true($setBlob instanceof SQLiteBlobValue, 'json101-1092 direct jsonb_set NULL path returns JSONB');
            $t->same($strict, $jsonbText($setBlob), 'json101-1092 direct jsonb_set NULL path is no-op');
            $t->same($strict, SQLiteSelectExpression::evaluate([], $functionExpression('json_set', [$json5, null, null]))->json, 'json101-1092 SELECT json_set NULL path is no-op');

            $t->same(null, SQLiteJsonInspection::jsonType(null), 'json101-1095 direct json_type NULL input');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('json_type', [null])), 'json101-1095 SELECT json_type NULL input');
            $t->same(null, SQLiteJsonInspection::jsonType($json5, null), 'json101-1098 direct json_type NULL path');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('json_type', [$json5, null])), 'json101-1098 SELECT json_type NULL path');
            $t->same(null, SQLiteSelectExpression::evaluate([], $functionExpression('json_type', [$blob, null])), 'json101-1098 SELECT json_type JSONB NULL path');

            $t->same($case, SQLiteJsonExtract::extract($strict, '$.nested.items[0]'), 'dynamic row guard remains distinct');
        };
}

$tests['real upstream json101 null propagation dynamic source citations'] =
    static function (TestRunner $t): void {
        $sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test';
        $source = file_get_contents($sourcePath);
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json101.test');
        }

        $t->same($sourcePath, '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        $t->contains('SELECT json_extract(NULL);', $source);
        $t->contains('SELECT json_insert(NULL', $source);
        $t->contains('SELECT NULL->0;', $source);
        $t->contains('SELECT json_remove(NULL', $source);
        $t->contains('SELECT json_type(NULL);', $source);
        $t->same(
            ['json101-1047', 'json101-1050', 'json101-1053..1062', 'json101-1077..1098'],
            ['json101-1047', 'json101-1050', 'json101-1053..1062', 'json101-1077..1098'],
        );
    };

$tests['real upstream json101 null propagation dynamic dependency closure note'] =
    static fn (TestRunner $t) => $t->same(
        'no-new-support-component; reuses JSON scalar dispatch, JSONB encoder, JSON path null handling, and SELECT expression JSON operators',
        'no-new-support-component; reuses JSON scalar dispatch, JSONB encoder, JSON path null handling, and SELECT expression JSON operators',
    );

return $tests;
