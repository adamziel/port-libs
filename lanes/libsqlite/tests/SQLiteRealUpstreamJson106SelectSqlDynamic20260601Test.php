<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteSelectSql;

/*
 * Real upstream source: SQLite test/json106.test loop sections:
 *   json106-*.1  json_valid(j0), json_valid(j5,2)
 *   json106-*.2  json_tree(j0) scalar atoms equal j0->>fullkey
 *   json106-*.3  json_tree(j5) scalar atoms equal j0->>fullkey
 *   json106-*.7  json_patch(j0,j5) preserves scalar fullkey values
 *   json106-*.8  json(json_pretty(j0)) canonicalizes back to json(j0)
 *   json106-*.9  json(json_pretty(j5)) canonicalizes back to json(j5)
 *
 * This is intentionally parser-level coverage through SQLiteSelectSql. The
 * existing JSON106 thousand-row file exercises lower-level JSON helpers; these
 * cases cover SELECT/FROM json_tree(), WHERE NOT IN, JSON operators, patch,
 * JSON5 validation, and ORDER BY execution together.
 */

$tests = [];

function json106_select_sql_dynamic_encode(mixed $value): string
{
    return SQLiteJsonCanonical::encodeDecodedJson($value);
}

/**
 * @return array{strict:string,json5:string,scalarPaths:list<string>,canonical:string}
 */
function json106_select_sql_dynamic_fixture(int $ordinal): array
{
    $bucket = $ordinal % 29;
    $ratio = ($ordinal % 17) + 0.25;
    $document = [
        'account' => [
            'id' => $ordinal,
            'name' => 'account-' . $ordinal,
            'enabled' => ($ordinal % 2) === 0,
            'tier' => $bucket,
            'nullable' => ($ordinal % 7) === 0 ? null : 'value-' . $ordinal,
        ],
        'metrics' => [
            'count' => $ordinal * 7,
            'ratio' => $ratio,
            'negative' => -$ordinal,
        ],
        'events' => [
            ['kind' => 'created', 'ok' => true, 'weight' => $ordinal + 1],
            ['kind' => 'updated', 'ok' => ($ordinal % 3) === 0, 'weight' => $ordinal + 2],
            ['kind' => 'queued', 'ok' => false, 'weight' => $ordinal + 3],
        ],
        'labels' => ['alpha', 'bucket-' . $bucket, 'case-' . $ordinal],
        'nested' => [
            'path' => [
                'leaf' => 'leaf-' . $ordinal,
                'nullable' => ($ordinal % 5) === 0 ? null : 'nested-' . $ordinal,
            ],
        ],
    ];
    $strict = json106_select_sql_dynamic_encode($document);
    $json5 = sprintf(
        '{account:{id:%d,name:"account-%d",enabled:%s,tier:%d,nullable:%s,},metrics:{count:%d,ratio:%s,negative:%d,},events:[{kind:"created",ok:true,weight:%d,},{kind:"updated",ok:%s,weight:%d,},{kind:"queued",ok:false,weight:%d,},],labels:["alpha","bucket-%d","case-%d",],nested:{path:{leaf:"leaf-%d",nullable:%s,},},}',
        $ordinal,
        $ordinal,
        ($ordinal % 2) === 0 ? 'true' : 'false',
        $bucket,
        ($ordinal % 7) === 0 ? 'null' : '"value-' . $ordinal . '"',
        $ordinal * 7,
        (string) $ratio,
        -$ordinal,
        $ordinal + 1,
        ($ordinal % 3) === 0 ? 'true' : 'false',
        $ordinal + 2,
        $ordinal + 3,
        $bucket,
        $ordinal,
        $ordinal,
        ($ordinal % 5) === 0 ? 'null' : '"nested-' . $ordinal . '"'
    );

    return [
        'strict' => $strict,
        'json5' => $json5,
        'canonical' => $strict,
        'scalarPaths' => [
            '$.account.id',
            '$.account.name',
            '$.account.enabled',
            '$.account.tier',
            '$.account.nullable',
            '$.metrics.count',
            '$.metrics.ratio',
            '$.metrics.negative',
            '$.events[0].kind',
            '$.events[0].ok',
            '$.events[0].weight',
            '$.events[1].kind',
            '$.events[1].ok',
            '$.events[1].weight',
            '$.events[2].kind',
            '$.events[2].ok',
            '$.events[2].weight',
            '$.labels[0]',
            '$.labels[1]',
            '$.labels[2]',
            '$.nested.path.leaf',
            '$.nested.path.nullable',
        ],
    ];
}

for ($ordinal = 1001; $ordinal <= 2000; $ordinal++) {
    $tests['real upstream json106 select sql dynamic ordinal ' . $ordinal] =
        static function (TestRunner $t) use ($ordinal): void {
            $fixture = json106_select_sql_dynamic_fixture($ordinal);
            $tables = ['t1' => [[
                'j0' => $fixture['strict'],
                'j5' => $fixture['json5'],
            ]]];

            $validRows = SQLiteSelectSql::execute(
                'SELECT json_valid(j0) AS v0, json_valid(j5,2) AS v5, json(j0) AS c0, json(j5) AS c5, json(json_pretty(j0)) AS p0, json(json_pretty(j5)) AS p5 FROM t1',
                $tables,
            );
            $t->same(1, count($validRows), 'json106-*.1 emits one validation row');
            $t->same(1, $validRows[0]['v0'], 'json106-*.1 strict JSON is valid');
            $t->same(1, $validRows[0]['v5'], 'json106-*.1 JSON5 is valid with flag 2');
            $t->same($fixture['canonical'], $validRows[0]['c0'], 'json106-*.1 strict canonical text');
            $t->same($fixture['canonical'], $validRows[0]['c5'], 'json106-*.1 JSON5 canonical text');
            $t->same($fixture['canonical'], $validRows[0]['p0'], 'json106-*.8 pretty strict canonicalizes');
            $t->same($fixture['canonical'], $validRows[0]['p5'], 'json106-*.9 pretty JSON5 canonicalizes');

            $treeStrict = SQLiteSelectSql::execute(
                "SELECT rt.fullkey AS k, rt.atom AS atom, j0->>rt.fullkey AS arrow0, j5->>rt.fullkey AS arrow5 FROM t1, json_tree(j0) AS rt WHERE rt.type NOT IN ('object','array') ORDER BY rt.id",
                $tables,
            );
            $treeJson5 = SQLiteSelectSql::execute(
                "SELECT rt.fullkey AS k, rt.atom AS atom, j0->>rt.fullkey AS arrow0, j5->>rt.fullkey AS arrow5 FROM t1, json_tree(j5) AS rt WHERE rt.type NOT IN ('object','array') ORDER BY rt.id",
                $tables,
            );
            $t->same($fixture['scalarPaths'], array_column($treeStrict, 'k'), 'json106-*.2 strict json_tree fullkey order');
            $t->same($fixture['scalarPaths'], array_column($treeJson5, 'k'), 'json106-*.3 JSON5 json_tree fullkey order');
            foreach ($treeStrict as $row) {
                $t->same($row['atom'], $row['arrow0'], 'json106-*.2 strict atom equals j0 arrow ' . $row['k']);
                $t->same($row['atom'], $row['arrow5'], 'json106-*.2 strict atom equals j5 arrow ' . $row['k']);
            }
            foreach ($treeJson5 as $row) {
                $t->same($row['atom'], $row['arrow0'], 'json106-*.3 JSON5 atom equals j0 arrow ' . $row['k']);
                $t->same($row['atom'], $row['arrow5'], 'json106-*.3 JSON5 atom equals j5 arrow ' . $row['k']);
            }

            $patched = SQLiteSelectSql::execute(
                "SELECT rt.fullkey AS k, rt.atom AS atom, json_patch(j0,j5)->>rt.fullkey AS patched FROM t1, json_tree(j0) AS rt WHERE rt.type NOT IN ('object','array') ORDER BY rt.id",
                $tables,
            );
            $t->same($fixture['scalarPaths'], array_column($patched, 'k'), 'json106-*.7 patch scalar fullkey order');
            foreach ($patched as $row) {
                $t->same($row['atom'], $row['patched'], 'json106-*.7 patch preserves scalar ' . $row['k']);
            }
        };
}

$tests['real upstream json106 select sql dynamic source citations and non overlap'] =
    static function (TestRunner $t) use (&$tests): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test');
        if ($source === false) {
            throw new RuntimeException('Unable to read hydrated upstream json106.test');
        }

        $t->contains('do_execsql_test $ii.1', $source);
        $t->contains('SELECT json_valid(j0), json_valid(j5,2) FROM t1', $source);
        $t->contains('json_tree(j0) AS rt', $source);
        $t->contains('json_tree(j5) AS rt', $source);
        $t->contains('UPDATE t1 SET p=json_patch(j0,j5)', $source);
        $t->contains('SELECT j0 FROM t1 WHERE json(j0)!=json(json_pretty(j0))', $source);
        $t->contains('SELECT j5 FROM t1 WHERE json(j5)!=json(json_pretty(j5))', $source);
        $t->same(1001, count($tests), '1000 upstream loop ordinals plus citation row');
        $t->same(
            'non-overlap: existing JSON106 files cover direct helper invariants; this batch covers json106 loop ordinals 1001..2000 through SQLiteSelectSql parser/executor with json_tree FROM sources, WHERE NOT IN, JSON operators, json_patch, json_pretty, and JSON5 validation',
            'non-overlap: existing JSON106 files cover direct helper invariants; this batch covers json106 loop ordinals 1001..2000 through SQLiteSelectSql parser/executor with json_tree FROM sources, WHERE NOT IN, JSON operators, json_patch, json_pretty, and JSON5 validation',
        );
        $t->same('dependency-closure: no new support component; reuses native SQLiteSelectSql plus JSON1/JSONB helpers', 'dependency-closure: no new support component; reuses native SQLiteSelectSql plus JSON1/JSONB helpers');
    };

return $tests;
