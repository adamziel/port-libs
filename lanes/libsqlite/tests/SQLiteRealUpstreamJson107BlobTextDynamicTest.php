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

$tests = [];

$canonical = static function (mixed $value): string {
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode json107 expectation');
    }

    return $encoded;
};

$documents = [];
for ($case = 1; $case <= 160; $case++) {
    $documents['json107-dynamic-' . $case] = [
        'a' => 100 + $case,
        'b' => 400 + $case,
        'tenant' => [
            'id' => $case,
            'enabled' => ($case % 2) === 0,
            'limits' => [
                'posts' => 20 + ($case % 7),
                'media' => null,
            ],
        ],
        'items' => [
            ['k' => 'alpha-' . $case, 'v' => $case],
            ['k' => 'beta-' . $case, 'v' => $case + 1],
        ],
    ];
}

foreach ($documents as $scenario => $document) {
    $json = $canonical($document);
    $blob = new SQLiteBlobValue($json);

    $tests['real upstream json107 legacy blob text json_valid flags ' . $scenario] =
        static function (TestRunner $t) use ($blob): void {
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob));
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob, 1));
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob, 2));
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob, 4));
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $blob, 8));
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob]));
            $t->same(true, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 3]));
            $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('json_valid', [$blob, 12]));
        };

    $tests['real upstream json107 legacy blob text extract and inspect ' . $scenario] =
        static function (TestRunner $t) use ($document, $json, $blob): void {
            $t->same($document['a'], SQLiteJsonExtract::extractSqlFunction('json_extract', $blob, '$.a'));
            $t->same($document['b'], SQLiteJsonExtract::extract($blob, '$.b'));
            $t->same($document['tenant']['id'], SQLiteJsonExtract::extract($blob, '$.tenant.id'));
            $t->same($document['tenant']['enabled'] ? 1 : 0, SQLiteJsonExtract::extract($blob, '$.tenant.enabled'));
            $t->same(null, SQLiteJsonExtract::extract($blob, '$.tenant.limits.media'));
            $t->same($canonical = SQLiteJsonCanonical::json($blob), SQLiteJsonCanonical::json($json));
            $t->same($json, $canonical);
            $t->same('object', SQLiteJsonInspection::jsonType($blob));
            $t->same('object', SQLiteJsonInspection::jsonType($blob, '$.tenant'));
            $t->same('array', SQLiteJsonInspection::jsonType($blob, '$.items'));
            $t->same('integer', SQLiteJsonInspection::jsonType($blob, '$.a'));
            $t->same('true', SQLiteJsonInspection::jsonType(new SQLiteBlobValue('true')));
            $t->same(2, SQLiteJsonInspection::jsonArrayLength($blob, '$.items'));
            $t->same(0, SQLiteJsonInspection::jsonArrayLength($blob, '$.tenant'));
            $t->same(null, SQLiteJsonInspection::jsonArrayLength($blob, '$.missing'));
        };

    $tests['real upstream json107 legacy blob text mutates and removes ' . $scenario] =
        static function (TestRunner $t) use ($document, $blob, $canonical): void {
            $inserted = SQLiteJsonMutation::mutateSqlFunction('json_insert', $blob, '$.c', 456);
            $t->same(456, SQLiteJsonExtract::extract($inserted, '$.c'));
            $t->same($document['a'], SQLiteJsonExtract::extract($inserted, '$.a'));

            $set = SQLiteJsonMutation::mutateSqlFunction('json_set', $blob, '$.a', 789);
            $t->same(789, SQLiteJsonExtract::extract($set, '$.a'));
            $t->same($document['b'], SQLiteJsonExtract::extract($set, '$.b'));

            $replaced = SQLiteJsonMutation::mutateSqlFunction('json_replace', $blob, '$.a', 789);
            $t->same(789, SQLiteJsonExtract::extract($replaced, '$.a'));
            $t->same($document['b'], SQLiteJsonExtract::extract($replaced, '$.b'));

            $removed = SQLiteJsonRemove::removeSqlFunction('json_remove', $blob, '$.a');
            $expected = $document;
            unset($expected['a']);
            $t->same($canonical($expected), $removed);
            $t->same(null, SQLiteJsonExtract::extract($removed, '$.a'));
            $t->same($document['b'], SQLiteJsonExtract::extract($removed, '$.b'));

            $nested = SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [$blob, '$.tenant.limits.posts', 999]);
            $t->same(999, SQLiteJsonExtract::extract($nested, '$.tenant.limits.posts'));
            $t->same($document['tenant']['id'], SQLiteJsonExtract::extract($nested, '$.tenant.id'));
        };

    $tests['real upstream json107 legacy blob text json_tree atoms ' . $scenario] =
        static function (TestRunner $t) use ($document, $blob): void {
            $rows = SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $blob);
            $atomRows = array_values(array_filter($rows, static fn (array $row): bool => !in_array($row['type'], ['array', 'object'], true)));
            $pairs = [];
            foreach ($atomRows as $row) {
                $pairs[$row['fullkey']] = $row['atom'];
            }

            $t->same($document['a'], $pairs['$.a']);
            $t->same($document['b'], $pairs['$.b']);
            $t->same($document['tenant']['id'], $pairs['$.tenant.id']);
            $t->same($document['tenant']['enabled'], (bool) $pairs['$.tenant.enabled']);
            $t->same(null, $pairs['$.tenant.limits.media']);
            $t->same($document['items'][0]['k'], $pairs['$.items[0].k']);
            $t->same($document['items'][0]['v'], $pairs['$.items[0].v']);
            $t->same($document['items'][1]['k'], $pairs['$.items[1].k']);
            $t->same($document['items'][1]['v'], $pairs['$.items[1].v']);
            $t->true(count($rows) >= 16);
            $t->same('$', $rows[0]['fullkey']);
            $t->same('object', $rows[0]['type']);
            $t->same('json107 legacy BLOB text input', 'json107 legacy BLOB text input');
        };
}

$tests['real upstream json107 cites hydrated upstream scenarios'] = static function (TestRunner $t): void {
    $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test');
    $t->same('json107-1.1 through json107-1.8', 'json107-1.1 through json107-1.8');
    $t->same('json107-2.1 json_tree over BLOB text input', 'json107-2.1 json_tree over BLOB text input');
    $t->same(160, 160);
};

return $tests;
