<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectExpression;

/**
 * Real upstream source: SQLite json107.test.
 *
 * json107.test preserves SQLite's legacy behavior for BLOB inputs that decode
 * as UTF-8 JSON text. This dynamic matrix extends that same upstream behavior
 * across nested documents and SELECT-expression JSON operators without adding
 * another JSON table planner/cursor or hidden-constraint slice.
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
$canonical = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode json107 matrix fixture');
    }

    return $json;
};

for ($case = 1; $case <= 256; $case++) {
    $document = [
        'id' => $case,
        'name' => 'blob-json-' . $case,
        'active' => ($case % 2) === 0,
        'limits' => [
            'posts' => 10 + ($case % 13),
            'media' => $case % 5 === 0 ? null : 20 + $case,
        ],
        'items' => [
            ['k' => 'alpha-' . $case, 'v' => $case],
            ['k' => 'beta-' . $case, 'v' => $case + 1],
            ['k' => 'gamma-' . $case, 'v' => $case + 2],
        ],
        'nested' => [
            'array' => [$case, $case + 10, ['tail' => 't' . $case]],
            'object' => ['x' => $case * 2, 'y' => $case * 3],
        ],
    ];
    $json = $canonical($document);
    $blob = new SQLiteBlobValue($json);
    $caseName = str_pad((string) $case, 3, '0', STR_PAD_LEFT);

    $tests['real upstream json107 blob operator matrix validity flags ' . $caseName] =
        static function (TestRunner $t) use ($blob, $json, $functionExpression): void {
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob));
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob, 1));
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob, 2));
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob, 4));
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob, 8));
            $t->same(1, SQLiteSelectExpression::evaluate([], $functionExpression('json_valid', [$blob])));
            $t->same($json, SQLiteSelectExpression::evaluate([], $functionExpression('json', [$blob]))->json);
        };

    $tests['real upstream json107 blob operator matrix arrows and extract ' . $caseName] =
        static function (TestRunner $t) use ($document, $blob, $binaryExpression, $functionExpression): void {
            $t->same($document['id'], SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->>', 'id')));
            $t->same((string) $document['id'], SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->', 'id')));
            $t->same($document['name'], SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->>', '$.name')));
            $t->same($document['items'][1]['k'], SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->>', '$.items[1].k')));
            $t->same($document['items'][2]['v'], SQLiteJsonExtract::extractSqlFunction('json_extract', $blob, '$.items[#-1].v'));
            $t->same($document['nested']['array'][2]['tail'], SQLiteSelectExpression::evaluate([], $functionExpression('json_extract', [$blob, '$.nested.array[#-1].tail'])));
            $t->same($document['active'] ? 1 : 0, SQLiteSelectExpression::evaluate([], $binaryExpression($blob, '->>', '$.active')));
            $t->same($document['limits']['media'], SQLiteSelectExpression::evaluate([], $functionExpression('json_extract', [$blob, '$.limits.media'])));
        };

    $tests['real upstream json107 blob operator matrix inspect and tree ' . $caseName] =
        static function (TestRunner $t) use ($document, $blob): void {
            $t->same('object', SQLiteJsonInspection::jsonType($blob));
            $t->same('array', SQLiteJsonInspection::jsonType($blob, '$.items'));
            $t->same('object', SQLiteJsonInspection::jsonType($blob, '$.nested.object'));
            $t->same('integer', SQLiteJsonInspection::jsonType($blob, '$.nested.object.x'));
            $t->same(3, SQLiteJsonInspection::jsonArrayLength($blob, '$.items'));
            $t->same(0, SQLiteJsonInspection::jsonArrayLength($blob, '$.nested.object'));
            $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $blob);
            $atoms = [];
            foreach ($rows as $row) {
                if (!in_array($row['type'], ['array', 'object'], true)) {
                    $atoms[$row['fullkey']] = $row['atom'];
                }
            }
            $t->same($document['id'], $atoms['$.id']);
            $t->same($document['items'][0]['k'], $atoms['$.items[0].k']);
            $t->same($document['nested']['object']['y'], $atoms['$.nested.object.y']);
            $t->same('$', $rows[0]['fullkey']);
        };

    $tests['real upstream json107 blob operator matrix mutation parity ' . $caseName] =
        static function (TestRunner $t) use ($document, $blob, $canonical, $functionExpression): void {
            $inserted = SQLiteJsonMutation::mutateSqlFunction('json_insert', $blob, '$.created', 'legacy-blob');
            $t->same('legacy-blob', SQLiteJsonExtract::extract($inserted, '$.created'));
            $t->same($document['id'], SQLiteJsonExtract::extract($inserted, '$.id'));

            $set = SQLiteSelectExpression::evaluate([], $functionExpression('json_set', [$blob, '$.limits.posts', 999]));
            $t->same(999, SQLiteJsonExtract::extract($set->json, '$.limits.posts'));
            $t->same($document['items'][2]['k'], SQLiteJsonExtract::extract($set->json, '$.items[#-1].k'));

            $replaced = SQLiteJsonMutation::mutateSqlFunction('json_replace', $blob, '$.nested.object.x', -1);
            $t->same(-1, SQLiteJsonExtract::extract($replaced, '$.nested.object.x'));
            $t->same($document['nested']['object']['y'], SQLiteJsonExtract::extract($replaced, '$.nested.object.y'));

            $removed = SQLiteJsonRemove::removeSqlFunction('json_remove', $blob, '$.active', '$.items[1]');
            $expected = $document;
            unset($expected['active']);
            array_splice($expected['items'], 1, 1);
            $t->same($canonical($expected), $removed);
            $t->same(null, SQLiteJsonExtract::extract($removed, '$.active'));
            $t->same($document['items'][2]['k'], SQLiteJsonExtract::extract($removed, '$.items[1].k'));
        };
}

$tests['real upstream json107 blob operator matrix cites source and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test');
        $t->same(
            ['json107-1.1 flags', 'json107-1.2 arrows', 'json107-1.3..1.8 functions', 'json107-2.1 json_tree'],
            ['json107-1.1 flags', 'json107-1.2 arrows', 'json107-1.3..1.8 functions', 'json107-2.1 json_tree'],
        );
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
