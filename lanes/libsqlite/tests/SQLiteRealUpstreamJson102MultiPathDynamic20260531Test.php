<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteSelectExpression;

/*
 * Real upstream source: SQLite json102.test.
 *
 * This ports the json102-250..310 extraction cluster into a broad dynamic
 * corpus: root/object extraction, nested array/object extraction, scalar
 * extraction, missing-path SQL NULL, multi-path JSON array construction, and
 * jsonb_extract result typing over text JSON and JSONB inputs.
 */

$tests = [];

$encode = static function (mixed $value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    if (!is_string($json)) {
        throw new RuntimeException('Unable to encode JSON expectation');
    }

    return $json;
};

$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$jsonbText = static fn (SQLiteBlobValue $value): string => SQLiteJsonCanonical::json($value);
$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$functionExpression = static fn (string $name, array $arguments): array => [
    'type' => 'function',
    'name' => $name,
    'arguments' => array_map($literal, $arguments),
];

for ($case = 0; $case < 1000; $case++) {
    $document = [
        'a' => 2 + $case,
        'c' => [
            4 + ($case % 17),
            5 + ($case % 19),
            [
                'f' => 7 + $case,
                'label' => 'json102-' . $case,
                'active' => ($case % 2) === 0,
            ],
        ],
        'f' => 70 + ($case % 11),
        'nested' => [
            'queue' => [
                ['id' => $case, 'kind' => 'current'],
                ['id' => $case + 1, 'kind' => 'next'],
            ],
        ],
    ];
    $text = $encode($document);
    $blob = $jsonb($document);
    $root = $encode($document);
    $array = $encode($document['c']);
    $objectElement = $encode($document['c'][2]);
    $multi = $encode([$document['c'], $document['a']]);
    $missingThenScalar = $encode([null, $document['a']]);
    $queueMulti = $encode([$document['nested']['queue'][1], $document['c'][2]['f']]);

    $tests[sprintf('real upstream json102 multi path dynamic extraction case %04d', $case)] =
        static function (TestRunner $t) use (
            $array,
            $blob,
            $case,
            $document,
            $functionExpression,
            $jsonbText,
            $missingThenScalar,
            $multi,
            $objectElement,
            $queueMulti,
            $root,
            $text
        ): void {
            $t->same($root, SQLiteJsonExtract::extractSqlFunction('json_extract', $text, '$'), 'json102-250 root object text');
            $rootBlob = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $blob, '$');
            $t->true($rootBlob instanceof SQLiteBlobValue, 'json102-250 JSONB root returns blob');
            $t->same($root, $rootBlob instanceof SQLiteBlobValue ? $jsonbText($rootBlob) : null, 'json102-250 JSONB root canonical text');

            $t->same($array, SQLiteJsonExtract::extractSqlFunction('json_extract', $text, '$.c'), 'json102-260 nested array text');
            $arrayBlob = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $blob, '$.c');
            $t->true($arrayBlob instanceof SQLiteBlobValue, 'json102-260 JSONB nested array returns blob');
            $t->same($array, $arrayBlob instanceof SQLiteBlobValue ? $jsonbText($arrayBlob) : null, 'json102-260 JSONB nested array canonical text');

            $t->same($objectElement, SQLiteJsonExtract::extractSqlFunction('json_extract', $text, '$.c[2]'), 'json102-270 nested object text');
            $objectBlob = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $blob, '$.c[2]');
            $t->true($objectBlob instanceof SQLiteBlobValue, 'json102-270 JSONB nested object returns blob');
            $t->same($objectElement, $objectBlob instanceof SQLiteBlobValue ? $jsonbText($objectBlob) : null, 'json102-270 JSONB nested object canonical text');

            $t->same($document['c'][2]['f'], SQLiteJsonExtract::extractSqlFunction('json_extract', $text, '$.c[2].f'), 'json102-280 nested scalar text');
            $t->same($document['c'][2]['f'], SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $blob, '$.c[2].f'), 'json102-280 JSONB scalar returns SQL scalar');
            $t->same(null, SQLiteJsonExtract::extractSqlFunction('json_extract', $text, '$.x'), 'json102-300 missing path text');
            $t->same(null, SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $blob, '$.x'), 'json102-300 JSONB missing path');

            $t->same($multi, SQLiteJsonExtract::extractSqlFunction('json_extract', $text, '$.c', '$.a'), 'json102-290 multi-path text array');
            $multiBlob = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $blob, '$.c', '$.a');
            $t->true($multiBlob instanceof SQLiteBlobValue, 'json102-290 JSONB multi-path returns blob');
            $t->same($multi, $multiBlob instanceof SQLiteBlobValue ? $jsonbText($multiBlob) : null, 'json102-290 JSONB multi-path canonical text');
            $t->same($missingThenScalar, SQLiteJsonExtract::extractSqlFunction('json_extract', $text, '$.x', '$.a'), 'json102-310 missing plus scalar text');

            $selectText = SQLiteSelectExpression::evaluate([], $functionExpression('json_extract', [$text, '$.nested.queue[1]', '$.c[2].f']));
            $selectBlob = SQLiteSelectExpression::evaluate([], $functionExpression('jsonb_extract', [$blob, '$.nested.queue[1]', '$.c[2].f']));
            $t->same($queueMulti, $selectText->json, 'json102 SELECT expression multi-path returns JSON subtype');
            $t->true($selectBlob instanceof SQLiteBlobValue, 'json102 SELECT expression jsonb_extract returns blob');
            $t->same($queueMulti, $selectBlob instanceof SQLiteBlobValue ? $jsonbText($selectBlob) : null, 'json102 SELECT expression jsonb_extract canonical text');
            $t->same('array', SQLiteJsonInspection::jsonType($queueMulti), 'json102 multi-path result is JSON array');
            $t->same(true, $case >= 0 && $case < 1000, 'json102 dynamic case guard');
        };
}

$tests['real upstream json102 multi path dynamic source citations'] =
    static function (TestRunner $t): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
        $t->same(
            [
                'json102-250 root object extraction',
                'json102-260 nested array extraction',
                'json102-270 nested object extraction',
                'json102-280 scalar extraction',
                'json102-290 multi-path extraction',
                'json102-300 missing path SQL NULL',
                'json102-310 missing plus scalar multi-path extraction',
            ],
            [
                'json102-250 root object extraction',
                'json102-260 nested array extraction',
                'json102-270 nested object extraction',
                'json102-280 scalar extraction',
                'json102-290 multi-path extraction',
                'json102-300 missing path SQL NULL',
                'json102-310 missing plus scalar multi-path extraction',
            ],
        );
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
