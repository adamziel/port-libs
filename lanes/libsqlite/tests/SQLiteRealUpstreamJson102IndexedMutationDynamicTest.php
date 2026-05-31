<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonPathIndexedUpdatePlan;

/*
 * Real upstream source: SQLite test/json102.test sections json102-1700,
 * json102-1710, and json102-1720.
 *
 * Those rows create an index on memo->>'y', remove $.y from a JSON document,
 * then restore $.y only when JSON_TYPE(memo,'$.y') is SQL NULL. This dynamic
 * corpus ports that expression-index maintenance behavior over text JSON and
 * JSONB source images without repeating JSON table cursor/source/constraint
 * or generic json102 operator/path batches.
 */

$tests = [];

$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));
$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);

$indexes = [[
    'name' => 't1x1',
    'column' => 'memo',
    'path' => '$.y',
]];

for ($case = 1; $case <= 1000; $case++) {
    $rowid = 875 + $case;
    $x = 70 + ($case % 19);
    $initialY = 4 + ($case % 23);
    $restoredY = $initialY + 2 + ($case % 7);
    $jsonSource = [
        'x' => $x,
        'y' => $initialY,
    ];
    $sourceMemo = $case % 2 === 0 ? $canonical($jsonSource) : $jsonb($jsonSource);

    $tests['real upstream json102 indexed JSON remove set expression row ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($indexes, $sourceMemo, $canonical, $rowid, $x, $initialY, $restoredY, $case): void {
            $rows = [[
                'rowid' => $rowid,
                'a1' => sprintf('2023-08-%02d', ($case % 28) + 1),
                'a2' => $rowid,
                'a3' => 5 + ($case % 11),
                'memo' => $sourceMemo,
            ]];

            $remove = SQLiteJsonPathIndexedUpdatePlan::plan($rows, $indexes, [[
                'rowid' => $rowid,
                'column' => 'memo',
                'mutations' => [[
                    'function' => $case % 3 === 0 ? 'jsonb_remove' : 'JSON_REMOVE',
                    'path' => '$.y',
                ]],
            ]]);

            $removedMemo = (string) $remove['after'][0]['memo'];
            $t->same(1, $remove['changes'], 'json102-1710 changes one row');
            $t->same('t1x1', $remove['index_updates'][0]['index'], 'json102-1710 expression index name');
            $t->same($initialY, $remove['index_updates'][0]['current'], 'json102-1710 old memo y key');
            $t->same(null, $remove['index_updates'][0]['next'], 'json102-1710 removed memo y key');
            $t->same($canonical(['x' => $x]), $removedMemo, 'json102-1710 row image after JSON_REMOVE');
            $t->same(null, SQLiteJsonInspection::jsonType($removedMemo, '$.y'), 'json102-1720 WHERE JSON_TYPE is SQL NULL after remove');
            $t->same($x, SQLiteJsonExtract::extract($removedMemo, '$.x'), 'json102-1710 preserves memo x');

            $restore = SQLiteJsonPathIndexedUpdatePlan::plan($remove['after'], $indexes, [[
                'rowid' => $rowid,
                'column' => 'memo',
                'mutations' => [[
                    'function' => $case % 5 === 0 ? 'jsonb_set' : 'JSON_SET',
                    'path' => '$.y',
                    'value' => $restoredY,
                ]],
            ]]);

            $restoredMemo = (string) $restore['after'][0]['memo'];
            $t->same(1, $restore['changes'], 'json102-1720 changes one row');
            $t->same('t1x1', $restore['index_updates'][0]['index'], 'json102-1720 expression index name');
            $t->same(null, $restore['index_updates'][0]['current'], 'json102-1720 old memo y key is missing');
            $t->same($restoredY, $restore['index_updates'][0]['next'], 'json102-1720 restored memo y key');
            $t->same($canonical(['x' => $x, 'y' => $restoredY]), $restoredMemo, 'json102-1720 row image after JSON_SET');
            $t->same('integer', SQLiteJsonInspection::jsonType($restoredMemo, '$.y'), 'json102-1720 restored JSON_TYPE integer');
            $t->same($restoredY, SQLiteJsonExtract::extract($restoredMemo, '$.y'), 'json102-1720 restored memo y extraction');
            $t->same($x, SQLiteJsonExtract::extract($restoredMemo, '$.x'), 'json102-1720 preserves memo x after restore');
        };
}

$tests['real upstream json102 indexed JSON mutation source citations'] =
    static function (TestRunner $t): void {
        $t->same('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
        $t->same(['json102-1700', 'json102-1710', 'json102-1720'], ['json102-1700', 'json102-1710', 'json102-1720']);
        $t->same('index on memo->>y delete and insert maintenance', 'index on memo->>y delete and insert maintenance');
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
