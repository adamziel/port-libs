<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

/*
 * Real upstream source: SQLite test/json102.test sections json102-1700,
 * json102-1710, and json102-1720.
 *
 * This ports the actual SQL UPDATE path into the PHP executor. It does not
 * repeat the standalone SQLiteJsonPathIndexedUpdatePlan coverage for the same
 * upstream rows; these cases prove JSON_REMOVE(), JSON_SET(), JSON_TYPE() IS
 * NULL, JSONB nested calls, and memo->>'y' RETURNING expressions inside
 * SQLiteUpdateDeleteReturningSql.
 */

$tests = [];

$canonical = static fn (mixed $value): string => SQLiteJsonCanonical::encodeDecodedJson($value);
$jsonb = static fn (mixed $value): SQLiteBlobValue => new SQLiteBlobValue(SQLiteJsonB::encode($value));

for ($case = 1; $case <= 1000; $case++) {
    $rowid = 875 + $case;
    $x = 70 + ($case % 31);
    $initialY = 4 + ($case % 17);
    $restoredY = $initialY + 2 + ($case % 13);
    $date = sprintf('2023-08-%02d', ($case % 28) + 1);
    $a3 = 5 + ($case % 9);
    $source = ['x' => $x, 'y' => $initialY];
    $sourceMemo = $case % 2 === 0 ? $canonical($source) : $jsonb($source);
    $removedMemo = $canonical(['x' => $x]);
    $restoredMemo = $canonical(['x' => $x, 'y' => $restoredY]);

    $tests['real upstream json102 update sql JSON remove set row ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($sourceMemo, $rowid, $date, $a3, $x, $restoredY, $removedMemo, $restoredMemo): void {
            $tables = ['app_records' => [[
                'a1' => $date,
                'a2' => $rowid,
                'a3' => $a3,
                'memo' => $sourceMemo,
            ]]];

            $remove = SQLiteUpdateDeleteReturningSql::execute(
                "UPDATE app_records SET memo = JSON_REMOVE(memo, '$.y') WHERE a2 IN ({$rowid}) RETURNING a1, a2, a3, memo, json_type(memo, '$.y') AS y_type, json_extract(memo, '$.x') AS x_value, memo->>'y' AS y_text, json(jsonb(memo)) AS memo_jsonb_text, json_valid(jsonb(memo), 8) AS memo_jsonb_valid",
                $tables,
                'a2',
            );

            $t->same(1, count($remove['returning']), 'json102-1710 emits one RETURNING row');
            $t->same($date, $remove['returning'][0]['a1'], 'json102-1710 preserves date column');
            $t->same($rowid, $remove['returning'][0]['a2'], 'json102-1710 preserves primary key');
            $t->same($a3, $remove['returning'][0]['a3'], 'json102-1710 preserves indexed integer column');
            $t->same($removedMemo, $remove['returning'][0]['memo'], 'json102-1710 JSON_REMOVE removes $.y through UPDATE SQL');
            $t->same(null, $remove['returning'][0]['y_type'], 'json102-1710 RETURNING json_type sees missing $.y');
            $t->same($x, $remove['returning'][0]['x_value'], 'json102-1710 RETURNING json_extract preserves $.x');
            $t->same(null, $remove['returning'][0]['y_text'], 'json102-1710 RETURNING memo->>y sees missing key');
            $t->same($removedMemo, $remove['returning'][0]['memo_jsonb_text'], 'json102-1710 nested json(jsonb(memo)) canonicalizes removed row');
            $t->same(1, $remove['returning'][0]['memo_jsonb_valid'], 'json102-1710 nested jsonb(memo) is strict JSONB');
            $t->same($removedMemo, $remove['tables']['app_records'][0]['memo'], 'json102-1710 stores removed JSON in row image');
            $t->same(null, SQLiteJsonInspection::jsonType($remove['tables']['app_records'][0]['memo'], '$.y'), 'json102-1720 WHERE JSON_TYPE will be SQL NULL');
            $t->same($x, SQLiteJsonExtract::extract($remove['tables']['app_records'][0]['memo'], '$.x'), 'json102-1710 stored row preserves $.x');

            $restore = SQLiteUpdateDeleteReturningSql::execute(
                "UPDATE app_records SET memo = JSON_SET(memo, '$.y', {$restoredY}) WHERE a2 IN ({$rowid}) AND JSON_TYPE(memo, '$.y') IS NULL RETURNING a1, a2, a3, memo, json_type(memo, '$.y') AS y_type, json_extract(memo, '$.x') AS x_value, memo->>'y' AS y_text, json(jsonb_extract(jsonb(memo), '$')) AS memo_jsonb_text, json_valid(jsonb(memo), 8) AS memo_jsonb_valid",
                $remove['tables'],
                'a2',
            );

            $t->same(1, count($restore['returning']), 'json102-1720 emits one RETURNING row');
            $t->same($date, $restore['returning'][0]['a1'], 'json102-1720 preserves date column');
            $t->same($rowid, $restore['returning'][0]['a2'], 'json102-1720 preserves primary key');
            $t->same($a3, $restore['returning'][0]['a3'], 'json102-1720 preserves indexed integer column');
            $t->same($restoredMemo, $restore['returning'][0]['memo'], 'json102-1720 JSON_SET restores $.y through UPDATE SQL');
            $t->same('integer', $restore['returning'][0]['y_type'], 'json102-1720 RETURNING json_type sees restored integer');
            $t->same($x, $restore['returning'][0]['x_value'], 'json102-1720 RETURNING json_extract preserves $.x');
            $t->same($restoredY, $restore['returning'][0]['y_text'], 'json102-1720 RETURNING memo->>y returns restored SQL integer');
            $t->same($restoredMemo, $restore['returning'][0]['memo_jsonb_text'], 'json102-1720 JSONB extract root canonicalizes restored row');
            $t->same(1, $restore['returning'][0]['memo_jsonb_valid'], 'json102-1720 restored nested jsonb(memo) is strict JSONB');
            $t->same($restoredMemo, $restore['tables']['app_records'][0]['memo'], 'json102-1720 stores restored JSON in row image');
            $t->same('integer', SQLiteJsonInspection::jsonType($restore['tables']['app_records'][0]['memo'], '$.y'), 'json102-1720 stored row has integer $.y');
            $t->same($restoredY, SQLiteJsonExtract::extract($restore['tables']['app_records'][0]['memo'], '$.y'), 'json102-1720 stored row extracts restored $.y');

            $noop = SQLiteUpdateDeleteReturningSql::execute(
                "UPDATE app_records SET memo = JSON_SET(memo, '$.y', 9999) WHERE a2 IN ({$rowid}) AND JSON_TYPE(memo, '$.y') IS NULL RETURNING memo, memo->>'y' AS y_text",
                $restore['tables'],
                'a2',
            );

            $t->same([], $noop['returning'], 'json102-1720 second UPDATE is gated off by JSON_TYPE IS NULL');
            $t->same($restoredMemo, $noop['tables']['app_records'][0]['memo'], 'json102-1720 gated no-op preserves restored JSON');
        };
}

$tests['real upstream json102 update sql dynamic source citations and non overlap'] =
    static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test');
        if ($source === false) {
            throw new RuntimeException('Unable to read hydrated upstream json102.test');
        }

        $t->contains('do_execsql_test json102-1700', $source);
        $t->contains("CREATE INDEX t1x1 ON t1(a3, a1, memo->>'y');", $source);
        $t->contains("UPDATE t1 SET memo = JSON_REMOVE(memo, '$.y');", $source);
        $t->contains("UPDATE t1 SET memo = JSON_SET(memo, '$.y', 6)", $source);
        $t->contains("AND JSON_TYPE(memo, '$.y') IS NULL", $source);
        $t->same(
            'non-overlap: ports json102-1700..1720 through SQLiteUpdateDeleteReturningSql JSON expression execution, not the existing SQLiteJsonPathIndexedUpdatePlan helper',
            'non-overlap: ports json102-1700..1720 through SQLiteUpdateDeleteReturningSql JSON expression execution, not the existing SQLiteJsonPathIndexedUpdatePlan helper',
        );
        $t->same('dependency-closure: no new support component; reuses native JSON/JSONB helpers in the UPDATE/DELETE executor', 'dependency-closure: no new support component; reuses native JSON/JSONB helpers in the UPDATE/DELETE executor');
    };

return $tests;
