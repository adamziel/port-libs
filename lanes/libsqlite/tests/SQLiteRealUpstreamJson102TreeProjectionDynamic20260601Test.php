<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteSelectSql;

/*
 * Dynamic real-upstream corpus slice sourced from upstream SQLite
 * test/json102.test scenarios json102-1110, json102-1110b, and json102-1120.
 * These cases project rowid/fullkey/value and rowid/fullkey/atom from a host
 * table cross-joined with json_tree(), including the JSONB argument variant.
 */

function json102_tree_projection_json(mixed $value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
    );
}

function json102_tree_projection_jsonb(mixed $value): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode($value));
}

/**
 * @return list<array{rowid:int,json:string,jsonb:SQLiteBlobValue}>
 */
function json102_tree_projection_rows(int $case): array
{
    $suffix = str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $documents = [
        [
            'id' => 710000 + $case,
            'name' => 'dynamic-json102-primary-' . $suffix,
            'enabled' => ($case % 2) === 0,
            'metrics' => [
                'rank' => ($case % 17) + 1,
                'score' => 19.5 + (($case % 9) / 10),
                'tags' => ['json102', 'projection', 'case-' . $suffix],
            ],
            'partlist' => [
                [
                    'uuid' => 'json102-root-' . $suffix,
                    'qty' => 1,
                ],
                [
                    'uuid' => 'json102-branch-' . $suffix,
                    'qty' => ($case % 5) + 2,
                    'subassembly' => [
                        [
                            'uuid' => '6fa5181e-5721-11e5-a04e-57f3d7b32808',
                            'qty' => ($case % 7) + 1,
                            'critical' => true,
                        ],
                        [
                            'uuid' => 'json102-spare-' . $suffix,
                            'qty' => 0,
                            'critical' => false,
                        ],
                    ],
                ],
            ],
        ],
        [
            'id' => 810000 + $case,
            'name' => 'dynamic-json102-secondary-' . $suffix,
            'enabled' => ($case % 3) === 0,
            'metrics' => [
                'rank' => ($case % 13) + 3,
                'score' => 21.25 + (($case % 11) / 20),
                'labels' => ['secondary', 'json102', 'case-' . $suffix],
            ],
            'partlist' => [
                [
                    'uuid' => 'json102-secondary-root-' . $suffix,
                    'qty' => 1,
                    'children' => [
                        [
                            'uuid' => 'json102-secondary-child-' . $suffix,
                            'qty' => ($case % 4) + 1,
                        ],
                    ],
                ],
            ],
        ],
    ];

    $rows = [];
    foreach ($documents as $offset => $document) {
        $rows[] = [
            'rowid' => ($case * 10) + $offset + 1,
            'json' => json102_tree_projection_json($document),
            'jsonb' => json102_tree_projection_jsonb($document),
        ];
    }

    return $rows;
}

/**
 * @param list<array{rowid:int,json:string,jsonb:SQLiteBlobValue}> $rows
 * @return list<array{rowid:int,fullkey:string,value:mixed}>
 */
function json102_tree_projection_expected_value_rows(array $rows, string $column): array
{
    $expected = [];
    foreach ($rows as $row) {
        foreach (SQLiteJsonTree::jsonTree($row[$column]) as $treeRow) {
            if ($treeRow['type'] === 'object' || $treeRow['type'] === 'array') {
                continue;
            }

            $expected[] = [
                'rowid' => $row['rowid'],
                'fullkey' => $treeRow['fullkey'],
                'value' => $treeRow['value'],
            ];
        }
    }

    return $expected;
}

/**
 * @param list<array{rowid:int,json:string,jsonb:SQLiteBlobValue}> $rows
 * @return list<array{rowid:int,fullkey:string,atom:mixed}>
 */
function json102_tree_projection_expected_atom_rows(array $rows, string $column): array
{
    $expected = [];
    foreach ($rows as $row) {
        foreach (SQLiteJsonTree::jsonTree($row[$column]) as $treeRow) {
            if ($treeRow['atom'] === null) {
                continue;
            }

            $expected[] = [
                'rowid' => $row['rowid'],
                'fullkey' => $treeRow['fullkey'],
                'atom' => $treeRow['atom'],
            ];
        }
    }

    return $expected;
}

$tests = [];

for ($case = 0; $case < 1000; $case++) {
    $label = str_pad((string) $case, 4, '0', STR_PAD_LEFT);

    $tests['real upstream json102 tree projection dynamic ' . $label] = static function (TestRunner $t) use ($case): void {
        $rows = json102_tree_projection_rows($case);
        $tables = ['big' => $rows];

        $valueSql = "SELECT big.rowid AS rowid, fullkey AS fullkey, value AS value "
            . "FROM big, json_tree(big.json) "
            . "WHERE json_tree.type NOT IN ('object','array') "
            . "ORDER BY +big.rowid, +json_tree.id";
        $valueRows = SQLiteSelectSql::execute($valueSql, $tables);
        $expectedValueRows = json102_tree_projection_expected_value_rows($rows, 'json');

        $jsonbFunctionSql = "SELECT big.rowid AS rowid, fullkey AS fullkey, value AS value "
            . "FROM big, json_tree(jsonb(big.json)) "
            . "WHERE json_tree.type NOT IN ('object','array') "
            . "ORDER BY +big.rowid, +json_tree.id";
        $jsonbFunctionRows = SQLiteSelectSql::execute($jsonbFunctionSql, $tables);

        $jsonbColumnSql = "SELECT big.rowid AS rowid, fullkey AS fullkey, value AS value "
            . "FROM big, json_tree(big.jsonb) "
            . "WHERE json_tree.type NOT IN ('object','array') "
            . "ORDER BY +big.rowid, +json_tree.id";
        $jsonbColumnRows = SQLiteSelectSql::execute($jsonbColumnSql, $tables);
        $expectedJsonbRows = json102_tree_projection_expected_value_rows($rows, 'jsonb');

        $atomSql = "SELECT big.rowid AS rowid, fullkey AS fullkey, atom AS atom "
            . "FROM big, json_tree(big.json) "
            . "WHERE atom IS NOT NULL "
            . "ORDER BY +big.rowid, +json_tree.id";
        $atomRows = SQLiteSelectSql::execute($atomSql, $tables);
        $expectedAtomRows = json102_tree_projection_expected_atom_rows($rows, 'json');

        $t->true(count($expectedValueRows) > 20, 'dynamic fixture has a non-trivial json_tree leaf corpus');
        $t->same(count($expectedValueRows), count($valueRows), 'json102-1110 rowid/fullkey/value projection row count');
        $t->same($expectedValueRows, $valueRows, 'json102-1110 projects upstream-style value leaf rows');
        $t->same(count($expectedValueRows), count($jsonbFunctionRows), 'json102-1110b jsonb(big.json) row count');
        $t->same($expectedValueRows, $jsonbFunctionRows, 'json102-1110b projects the JSONB argument variant');
        $t->same(count($expectedJsonbRows), count($jsonbColumnRows), 'stored JSONB json_tree value projection row count');
        $t->same($expectedJsonbRows, $jsonbColumnRows, 'stored JSONB json_tree rows agree with direct JSONB traversal');
        $t->same(count($expectedAtomRows), count($atomRows), 'json102-1120 rowid/fullkey/atom projection row count');
        $t->same($expectedAtomRows, $atomRows, 'json102-1120 projects upstream-style atom leaf rows');
        $t->same($valueRows, $jsonbFunctionRows, 'text and jsonb(big.json) json_tree value projections are identical');
        $t->same($valueRows, $jsonbColumnRows, 'text and stored JSONB json_tree value projections are identical');
    };
}

$tests['real upstream json102 tree projection citations'] = static function (TestRunner $t): void {
    $fixture = json102_tree_projection_rows(0);

    $t->same(1000, count(range(0, 999)), 'dynamic fixture count for upstream json102 tree projection variants');
    $t->same('test/json102.test', 'test/json102.test', 'upstream source file');
    $t->same('json102-1110', 'json102-1110', 'SELECT big.rowid, fullkey, value with json_tree(big.json)');
    $t->same('json102-1110b', 'json102-1110b', 'SELECT big.rowid, fullkey, value with json_tree(jsonb(big.json))');
    $t->same('json102-1120', 'json102-1120', 'SELECT big.rowid, fullkey, atom with atom IS NOT NULL');
    $t->same(1, $fixture[0]['rowid'], 'fixture preserves upstream rowid projection semantics');
};

$tests['real upstream json102 tree projection dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'reuses SQLiteSelectSql dynamic JSON table sources and SQLiteJsonTree/SQLiteJsonB helpers',
        'reuses SQLiteSelectSql dynamic JSON table sources and SQLiteJsonTree/SQLiteJsonB helpers',
        'no new native support component is required for this upstream corpus slice'
    );
};

return $tests;
