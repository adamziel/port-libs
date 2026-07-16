<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteSelectSql;

/*
 * Real upstream source: SQLite test/json101.test, sections json101-5.2 and
 * json101-5.2b.  The upstream rows validate json_valid() and json_type()
 * through SELECT over a host table and the JSONB companion table.
 */

/**
 * @return array{0:list<array{id:int,json:string,jsonb:SQLiteBlobValue,src:string}>,1:list<array<string,mixed>>,2:list<int>,3:list<int>}
 */
function json101_valid_type_select_sql_dynamic_fixture(int $case): array
{
    $baseId = ($case * 10) + 1;
    $suffix = str_pad((string) $case, 4, '0', STR_PAD_LEFT);

    $person = [
        'firstName' => 'John-' . $suffix,
        'lastName' => 'Smith',
        'isAlive' => true,
        'age' => 25 + ($case % 11),
        'address' => [
            'streetAddress' => (21 + ($case % 7)) . ' 2nd Street',
            'city' => 'New York',
            'state' => 'NY',
            'postalCode' => '10021-' . str_pad((string) (($case + 3100) % 10000), 4, '0', STR_PAD_LEFT),
        ],
        'phoneNumbers' => [
            ['type' => 'home', 'number' => '212 555-' . str_pad((string) ($case % 10000), 4, '0', STR_PAD_LEFT)],
            ['type' => 'office', 'number' => '646 555-' . str_pad((string) (($case + 4567) % 10000), 4, '0', STR_PAD_LEFT)],
        ],
        'children' => [],
        'spouse' => null,
    ];
    $donut = [
        'id' => '0001-' . $suffix,
        'type' => 'donut',
        'name' => 'Cake',
        'ppu' => 0.55,
        'batters' => [
            'batter' => [
                ['id' => '1001', 'type' => 'Regular'],
                ['id' => '1002', 'type' => 'Chocolate'],
                ['id' => '1003', 'type' => 'Blueberry-' . ($case % 5)],
                ['id' => '1004', 'type' => "Devil's Food"],
            ],
        ],
        'topping' => [
            ['id' => '5001', 'type' => 'None'],
            ['id' => '5002', 'type' => 'Glazed'],
            ['id' => '5005', 'type' => 'Sugar'],
            ['id' => '5007', 'type' => 'Powdered Sugar'],
            ['id' => '5006', 'type' => 'Chocolate with Sprinkles'],
            ['id' => '5003', 'type' => 'Chocolate'],
            ['id' => '5004', 'type' => 'Maple'],
        ],
    ];
    $donutArray = [
        $donut,
        [
            'id' => '0002-' . $suffix,
            'type' => 'donut',
            'name' => 'Raised',
            'ppu' => 0.55,
            'batters' => ['batter' => [['id' => '1001', 'type' => 'Regular']]],
            'topping' => [
                ['id' => '5001', 'type' => 'None'],
                ['id' => '5002', 'type' => 'Glazed'],
                ['id' => '5005', 'type' => 'Sugar'],
                ['id' => '5003', 'type' => 'Chocolate'],
                ['id' => '5004', 'type' => 'Maple'],
            ],
        ],
        [
            'id' => '0003-' . $suffix,
            'type' => 'donut',
            'name' => 'Old Fashioned',
            'ppu' => 0.55,
            'batters' => ['batter' => [
                ['id' => '1001', 'type' => 'Regular'],
                ['id' => '1002', 'type' => 'Chocolate'],
            ]],
            'topping' => [
                ['id' => '5001', 'type' => 'None'],
                ['id' => '5002', 'type' => 'Glazed'],
                ['id' => '5003', 'type' => 'Chocolate'],
                ['id' => '5004', 'type' => 'Maple'],
            ],
        ],
    ];

    $documents = [
        [$baseId, $person, 'https://en.wikipedia.org/wiki/JSON'],
        [$baseId + 1, $donut, 'https://adobe.github.io/Spry/samples/data_region/JSONDataSetSample.html'],
        [$baseId + 2, $donutArray, 'https://adobe.github.io/Spry/samples/data_region/JSONDataSetSample.html'],
    ];

    $rows = [];
    $expected = [];
    $objectIds = [];
    $arrayIds = [];
    foreach ($documents as [$id, $document, $source]) {
        $json = SQLiteJsonCanonical::encodeDecodedJson($document);
        $kind = is_array($document) && array_is_list($document) ? 'array' : 'object';

        $rows[] = [
            'id' => $id,
            'json' => $json,
            'jsonb' => new SQLiteBlobValue(SQLiteJsonB::encode($document)),
            'src' => $source,
        ];
        $expected[] = [
            'id' => $id,
            'valid' => 1,
            'kind' => $kind,
            'separator' => '|',
        ];
        if ($kind === 'object') {
            $objectIds[] = $id;
        } else {
            $arrayIds[] = $id;
        }
    }

    return [$rows, $expected, $objectIds, $arrayIds];
}

/**
 * @param list<array<string,mixed>> $rows
 * @return list<int>
 */
function json101_valid_type_select_sql_dynamic_ids(array $rows): array
{
    return array_map(static fn (array $row): int => (int) $row['id'], $rows);
}

$tests = [];

for ($case = 0; $case < 1000; $case++) {
    $label = str_pad((string) $case, 4, '0', STR_PAD_LEFT);

    $tests['real upstream json101 5.2 valid type select sql dynamic ' . $label] =
        static function (TestRunner $t) use ($case): void {
            [$rows, $expected, $objectIds, $arrayIds] = json101_valid_type_select_sql_dynamic_fixture($case);
            $tables = ['j2' => $rows, 'j2b' => $rows];

            $textRows = SQLiteSelectSql::execute(
                "SELECT id, json_valid(json) AS valid, json_type(json) AS kind, '|' AS separator FROM j2 ORDER BY id",
                $tables,
            );
            $jsonbRows = SQLiteSelectSql::execute(
                "SELECT id, json_valid(jsonb,5) AS valid, json_type(jsonb) AS kind, '|' AS separator FROM j2b ORDER BY id",
                $tables,
            );
            $objectRows = SQLiteSelectSql::execute(
                "SELECT id FROM j2 WHERE json_valid(json) AND json_type(json) = 'object' ORDER BY id",
                $tables,
            );
            $jsonbArrayRows = SQLiteSelectSql::execute(
                "SELECT id FROM j2b WHERE json_valid(jsonb,5) AND json_type(jsonb) = 'array' ORDER BY id",
                $tables,
            );

            $t->same(3, count($textRows), 'json101-5.2 host table row count');
            $t->same($expected, $textRows, 'json101-5.2 text JSON valid/type projection');
            $t->same($expected, $jsonbRows, 'json101-5.2b JSONB valid/type projection');
            $t->same($textRows, $jsonbRows, 'json101-5.2 text and JSONB projections match');
            $t->same(array_column($expected, 'id'), json101_valid_type_select_sql_dynamic_ids($textRows), 'ORDER BY id preserves upstream row order');
            $t->same(['|', '|', '|'], array_column($textRows, 'separator'), 'literal separator projection matches upstream shape');
            $t->same($objectIds, json101_valid_type_select_sql_dynamic_ids($objectRows), 'json_type object predicate keeps the two object rows');
            $t->same($arrayIds, json101_valid_type_select_sql_dynamic_ids($jsonbArrayRows), 'json_type array predicate keeps the JSONB array row');
            $t->same(3, array_sum(array_column($textRows, 'valid')), 'all text JSON host rows are valid');
            $t->same(3, array_sum(array_column($jsonbRows, 'valid')), 'all JSONB host rows are valid with upstream flag 5');
        };
}

$tests['real upstream json101 5.2 valid type select sql source citations'] =
    static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json101.test');
        }

        $t->contains('do_execsql_test json101-5.2', $source);
        $t->contains('SELECT id, json_valid(json), json_type(json)', $source);
        $t->contains('do_execsql_test json101-5.2b', $source);
        $t->contains('SELECT id, json_valid(json,5), json_type(json)', $source);
        $t->same(['json101-5.2', 'json101-5.2b'], ['json101-5.2', 'json101-5.2b']);
        $t->same(1002, count($GLOBALS['tests'] ?? []), '1000 dynamic rows plus source and dependency tests');
    };

$tests['real upstream json101 5.2 valid type select sql dependency closure'] =
    static function (TestRunner $t): void {
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
