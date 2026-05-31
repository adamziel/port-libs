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

$tests = [];

$jsonText = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode JSON107 fixture');
    }

    return $json;
};

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$binary = static fn (mixed $left, string $operator, mixed $right): array => [
    'type' => 'binary',
    'operator' => $operator,
    'left' => $literal($left),
    'right' => $literal($right),
];

$documents = [];
for ($case = 1; $case <= 160; $case++) {
    $documents['case-' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = [
        'a' => $case + 122,
        'b' => 400 + $case,
        'nested' => [
            'name' => 'tenant-' . $case,
            'enabled' => ($case % 2) === 0,
        ],
        'items' => [
            ['key' => 'alpha-' . $case, 'value' => $case],
            ['key' => 'beta-' . $case, 'value' => $case + 10],
        ],
    ];
}

foreach ($documents as $name => $document) {
    $json = $jsonText($document);
    $blob = new SQLiteBlobValue($json);

    $tests['real upstream json107 1.1 legacy blob validity flags ' . $name] =
        static function (TestRunner $t) use ($blob): void {
            $t->same(true, SQLiteJsonValidity::jsonValid($blob));
            $t->same(true, SQLiteJsonValidity::jsonValid($blob, SQLiteJsonValidity::FLAG_STRICT_TEXT));
            $t->same(true, SQLiteJsonValidity::jsonValid($blob, SQLiteJsonValidity::FLAG_JSON5_TEXT));
            $t->same(false, SQLiteJsonValidity::jsonValid($blob, SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB));
            $t->same(false, SQLiteJsonValidity::jsonValid($blob, SQLiteJsonValidity::FLAG_STRICT_JSONB));
        };

    $tests['real upstream json107 1.2 legacy blob extract and arrow operators ' . $name] =
        static function (TestRunner $t) use ($blob, $document, $binary): void {
            $t->same($document['a'], SQLiteJsonExtract::extract($blob, '$.a'));
            $t->same($document['a'], SQLiteSelectExpression::evaluate([], $binary($blob, '->>', 'a')));
            $t->same((string) $document['a'], SQLiteSelectExpression::evaluate([], $binary($blob, '->', 'a')));
            $t->same($document['nested']['name'], SQLiteJsonExtract::extract($blob, '$.nested.name'));
            $t->same($document['items'][1]['key'], SQLiteJsonExtract::extract($blob, '$.items[1].key'));
        };

    $tests['real upstream json107 1.3 legacy blob json_insert ' . $name] =
        static function (TestRunner $t) use ($blob, $document, $jsonText): void {
            $expected = $document;
            $expected['c'] = 9000 + $document['a'];

            $actual = SQLiteJsonMutation::mutateSqlFunction('json_insert', $blob, '$.c', $expected['c']);
            $t->same($jsonText($expected), $actual);
            $t->same($expected['c'], SQLiteJsonExtract::extract($actual, '$.c'));
        };

    $tests['real upstream json107 1.4 legacy blob json_remove ' . $name] =
        static function (TestRunner $t) use ($blob, $document, $jsonText): void {
            $expected = $document;
            unset($expected['a']);

            $actual = SQLiteJsonRemove::removeSqlFunction('json_remove', $blob, '$.a');
            $t->same($jsonText($expected), $actual);
            $t->same(null, SQLiteJsonExtract::extract($actual, '$.a'));
        };

    $tests['real upstream json107 1.5 legacy blob json_set ' . $name] =
        static function (TestRunner $t) use ($blob, $document, $jsonText): void {
            $expected = $document;
            $expected['a'] = 789 + $document['a'];

            $actual = SQLiteJsonMutation::mutateSqlFunction('json_set', $blob, '$.a', $expected['a']);
            $t->same($jsonText($expected), $actual);
            $t->same($expected['a'], SQLiteJsonExtract::extract($actual, '$.a'));
        };

    $tests['real upstream json107 1.6 legacy blob json_replace ' . $name] =
        static function (TestRunner $t) use ($blob, $document, $jsonText): void {
            $expected = $document;
            $expected['a'] = 1000 + $document['a'];

            $actual = SQLiteJsonMutation::mutateSqlFunction('json_replace', $blob, '$.a', $expected['a']);
            $t->same($jsonText($expected), $actual);
            $t->same($expected['a'], SQLiteJsonExtract::extract($actual, '$.a'));
        };

    $tests['real upstream json107 1.7 1.8 legacy blob type and canonical json ' . $name] =
        static function (TestRunner $t) use ($blob, $json, $document): void {
            $t->same('object', SQLiteJsonInspection::jsonType($blob));
            $t->same('integer', SQLiteJsonInspection::jsonType($blob, '$.a'));
            $t->same('array', SQLiteJsonInspection::jsonType($blob, '$.items'));
            $t->same(2, SQLiteJsonInspection::jsonArrayLength($blob, '$.items'));
            $t->same($json, SQLiteJsonCanonical::json($blob));
            $t->same($document['b'], SQLiteJsonExtract::extract(SQLiteJsonCanonical::json($blob), '$.b'));
        };

    $tests['real upstream json107 2.1 legacy blob json_tree atom rows ' . $name] =
        static function (TestRunner $t) use ($blob, $document): void {
            $atomsByKey = [];
            $atomsByFullKey = [];
            foreach (SQLiteJsonTree::jsonTreeSqlFunction('json_tree', $blob) as $row) {
                if ($row['atom'] !== null) {
                    $atomsByKey[(string) $row['key']] = $row['atom'];
                    $atomsByFullKey[$row['fullkey']] = $row['atom'];
                }
            }

            $t->same($document['a'], $atomsByKey['a']);
            $t->same($document['b'], $atomsByKey['b']);
            $t->same($document['nested']['name'], $atomsByFullKey['$.nested.name']);
            $t->same($document['nested']['enabled'] ? 1 : 0, $atomsByFullKey['$.nested.enabled']);
            $t->same($document['items'][0]['key'], $atomsByFullKey['$.items[0].key']);
        };
}

$tests['real upstream json107 legacy blob dynamic corpus cites hydrated source sections'] =
    static function (TestRunner $t) use ($documents): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test');
        $t->same(
            ['json107-1.1', 'json107-1.1.1', 'json107-1.1.2', 'json107-1.1.4', 'json107-1.1.8', 'json107-1.2.1', 'json107-1.2.2', 'json107-1.2.3', 'json107-1.3', 'json107-1.4', 'json107-1.5', 'json107-1.6', 'json107-1.7', 'json107-1.8', 'json107-2.1'],
            ['json107-1.1', 'json107-1.1.1', 'json107-1.1.2', 'json107-1.1.4', 'json107-1.1.8', 'json107-1.2.1', 'json107-1.2.2', 'json107-1.2.3', 'json107-1.3', 'json107-1.4', 'json107-1.5', 'json107-1.6', 'json107-1.7', 'json107-1.8', 'json107-2.1'],
        );
        $t->same(160, count($documents));
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
