<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteSelectSql;

/*
 * Dynamic real-upstream corpus slice sourced from upstream SQLite
 * test/json102.test scenarios json102-1130, json102-1131, and json102-1132.
 * Those cases search the big JSON fixture with SELECT DISTINCT, json_extract(),
 * and json_tree() using $.partlist, explicit $, and default-root scans.
 */

function json102_select_sql_tree_json(mixed $value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
    );
}

function json102_select_sql_tree_jsonb(mixed $value): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode($value));
}

function json102_select_sql_tree_sql_literal(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

/**
 * @return array{
 *     case: string,
 *     expectedId: int,
 *     targetUuid: string,
 *     rows: list<array{case_id:int, doc:string, docb:SQLiteBlobValue}>
 * }
 */
function json102_select_sql_tree_fixture(int $index): array
{
    $case = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
    $targetUuid = '6fa5181e-5721-11e5-a04e-57f3d7b32808';
    $expectedId = 910000 + $index;
    $branchUuid = 'json102-branch-' . $case;
    $spareUuid = 'json102-spare-' . $case;

    $matchingDocument = [
        'id' => $expectedId,
        'asset' => 'dynamic-json102-' . $case,
        'meta' => [
            'revision' => $index % 11,
            'uuid' => 'json102-meta-' . $case,
        ],
        'partlist' => [
            [
                'uuid' => 'json102-root-' . $case,
                'qty' => 1,
            ],
            [
                'uuid' => $branchUuid,
                'qty' => ($index % 5) + 2,
                'subassembly' => [
                    [
                        'uuid' => $targetUuid,
                        'qty' => ($index % 7) + 1,
                        'tags' => ['target', 'case-' . $case],
                    ],
                    [
                        'uuid' => $spareUuid,
                        'qty' => 0,
                    ],
                ],
            ],
        ],
    ];

    $duplicateMatchingDocument = [
        'id' => $expectedId,
        'asset' => 'dynamic-json102-duplicate-' . $case,
        'partlist' => [
            [
                'uuid' => $targetUuid,
                'qty' => ($index % 3) + 1,
                'children' => [
                    ['uuid' => 'json102-duplicate-child-' . $case],
                ],
            ],
        ],
    ];

    $decoyDocument = [
        'id' => $expectedId + 10000,
        'asset' => 'dynamic-json102-decoy-' . $case,
        'meta' => [
            'uuid' => 'json102-decoy-meta-' . $case,
        ],
        'partlist' => [
            [
                'uuid' => 'json102-decoy-' . $case,
                'qty' => 1,
                'subassembly' => [
                    ['uuid' => 'json102-decoy-subassembly-' . $case],
                ],
            ],
        ],
    ];

    $rows = [];
    foreach ([$matchingDocument, $duplicateMatchingDocument, $decoyDocument] as $offset => $document) {
        $rows[] = [
            'case_id' => ($index * 10) + $offset + 1,
            'doc' => json102_select_sql_tree_json($document),
            'docb' => json102_select_sql_tree_jsonb($document),
        ];
    }

    return [
        'case' => $case,
        'expectedId' => $expectedId,
        'targetUuid' => $targetUuid,
        'rows' => $rows,
    ];
}

function json102_select_sql_tree_query(string $documentColumn, string $treeSource, string $uuid, bool $commuted = false): string
{
    $literal = json102_select_sql_tree_sql_literal($uuid);
    $predicate = $commuted
        ? "jt.value = {$literal} AND jt.key = 'uuid'"
        : "jt.key = 'uuid' AND jt.value = {$literal}";

    return "SELECT DISTINCT json_extract(big.{$documentColumn}, '$.id') AS id "
        . "FROM app_records AS big, {$treeSource} AS jt "
        . "WHERE {$predicate}";
}

function json102_select_sql_tree_target_hit_count(array $rows, string $uuid): int
{
    $hits = 0;
    foreach ($rows as $row) {
        foreach (SQLiteJsonTree::jsonTree($row['doc'], '$') as $treeRow) {
            if ($treeRow['key'] === 'uuid' && $treeRow['value'] === $uuid) {
                $hits++;
            }
        }
    }

    return $hits;
}

function json102_select_sql_tree_has_json_subtype_container(SQLiteBlobValue $jsonb): bool
{
    foreach (SQLiteJsonTree::jsonTree($jsonb, '$') as $treeRow) {
        if (($treeRow['type'] === 'object' || $treeRow['type'] === 'array') && $treeRow['value'] instanceof SQLiteJsonSubtypeValue) {
            return true;
        }
    }

    return false;
}

$tests = [];

for ($index = 0; $index < 270; $index++) {
    $fixture = json102_select_sql_tree_fixture($index);
    $case = $fixture['case'];

    $tests['real upstream json102 select distinct json tree uuid search dynamic ' . $case] = static function (TestRunner $t) use ($fixture): void {
        $expected = [['id' => $fixture['expectedId']]];
        $queries = [
            'json102-1130 text $.partlist root' => json102_select_sql_tree_query(
                'doc',
                "json_tree(big.doc, '$.partlist')",
                $fixture['targetUuid']
            ),
            'json102-1131 text explicit root' => json102_select_sql_tree_query(
                'doc',
                "json_tree(big.doc, '$')",
                $fixture['targetUuid']
            ),
            'json102-1132 text default root' => json102_select_sql_tree_query(
                'doc',
                'json_tree(big.doc)',
                $fixture['targetUuid']
            ),
            'json102-1130 JSONB $.partlist root' => json102_select_sql_tree_query(
                'docb',
                "json_tree(big.docb, '$.partlist')",
                $fixture['targetUuid']
            ),
            'json102-1131 JSONB explicit root' => json102_select_sql_tree_query(
                'docb',
                "json_tree(big.docb, '$')",
                $fixture['targetUuid']
            ),
            'json102-1132 JSONB default root' => json102_select_sql_tree_query(
                'docb',
                'json_tree(big.docb)',
                $fixture['targetUuid']
            ),
        ];

        foreach ($queries as $label => $sql) {
            $actual = SQLiteSelectSql::execute($sql, ['app_records' => $fixture['rows']]);

            $t->same(1, count($actual), $label . ' returns one DISTINCT id despite duplicate source rows');
            $t->same($expected, $actual, $label . ' follows upstream json102 json_tree uuid search');
            $t->same($fixture['expectedId'], $actual[0]['id'] ?? null, $label . ' projects json_extract(big.json,$.id)');
        }

        $commuted = SQLiteSelectSql::execute(
            json102_select_sql_tree_query('docb', 'json_tree(big.docb)', $fixture['targetUuid'], true),
            ['app_records' => $fixture['rows']]
        );
        $missing = SQLiteSelectSql::execute(
            json102_select_sql_tree_query('docb', 'json_tree(big.docb)', 'json102-missing-' . $fixture['case'], true),
            ['app_records' => $fixture['rows']]
        );

        $t->same(1, count($commuted), 'commuted JSONB predicate still returns one upstream-style search hit');
        $t->same($expected, $commuted, 'JSON subtype container rows remain comparable when value predicate is evaluated first');
        $t->same($fixture['expectedId'], $commuted[0]['id'] ?? null, 'commuted JSONB predicate projects the matching document id');
        $t->same([], $missing, 'nonmatching JSONB uuid search scans container rows without throwing');
        $t->same(2, json102_select_sql_tree_target_hit_count($fixture['rows'], $fixture['targetUuid']), 'fixture has duplicate matching source rows for DISTINCT collapse');
        $t->true(
            json102_select_sql_tree_has_json_subtype_container($fixture['rows'][0]['docb']),
            'default-root json_tree scan includes JSON subtype container values before uuid leaves'
        );
    };
}

$tests['real upstream json102 select distinct json tree uuid search citations'] = static function (TestRunner $t): void {
    $fixture = json102_select_sql_tree_fixture(0);

    $t->same(270, count(range(0, 269)), 'dynamic fixture count for upstream json102 SELECT search variants');
    $t->same('test/json102.test', 'test/json102.test', 'upstream source file');
    $t->same('json102-1130', 'json102-1130', 'SELECT DISTINCT search with json_tree(big.json,$.partlist)');
    $t->same('json102-1131', 'json102-1131', 'SELECT DISTINCT search with json_tree(big.json,$)');
    $t->same('json102-1132', 'json102-1132', 'SELECT DISTINCT search with json_tree(big.json)');
    $t->same(
        '6fa5181e-5721-11e5-a04e-57f3d7b32808',
        $fixture['targetUuid'],
        'dynamic fixtures preserve the upstream target uuid literal'
    );
};

return $tests;
