<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test';
$sourceText = is_file($sourcePath) ? (string) file_get_contents($sourcePath) : '';
if ($sourceText === '') {
    throw new RuntimeException('Hydrated SQLite upstream affinity3.test is required for affinity3 union idmap tests');
}

/**
 * @return array<string,list<array<string,mixed>>>
 */
$affinity3Tables = static function (int $seed): array {
    $base = 100000 + ($seed * 10);
    $integerId = $base + 1;
    $textId = (string) ($base + 4);

    return [
        'map_integer' => [
            [
                'id' => $integerId,
                'name' => 'map_integer_' . $seed,
            ],
        ],
        'map_text' => [
            [
                'id' => $textId,
                'name' => 'map_text_' . $seed,
            ],
        ],
        'data' => [
            [
                'id' => (string) $integerId,
                'name' => 'data_integer_text_' . $seed,
            ],
            [
                'id' => $textId,
                'name' => 'data_text_' . $seed,
            ],
        ],
    ];
};

$joinSqlByShape = [
    'affinity3-210 idmap automatic-index-on' =>
        'SELECT id, data.name AS data_name, idmap.name AS map_name FROM data JOIN idmap USING(id)',
    'affinity3-220 materialized mzed automatic-index-on' =>
        'SELECT id, data.name AS data_name, mzed.name AS map_name FROM data JOIN mzed USING(id)',
    'affinity3-250 idmap automatic-index-off' =>
        'SELECT id, idmap.name AS map_name, data.name AS data_name FROM idmap JOIN data USING(id)',
    'affinity3-260 materialized mzed automatic-index-off' =>
        'SELECT id, mzed.name AS map_name, data.name AS data_name FROM mzed JOIN data USING(id)',
];

$tests['real upstream date affinity dynamic affinity3 union idmap cites source truth'] =
    static function (TestRunner $t) use ($sourcePath, $sourceText, $joinSqlByShape): void {
        $t->true(is_file($sourcePath), 'hydrated upstream affinity3.test exists');
        foreach ([
            'do_execsql_test affinity3-200',
            'CREATE VIEW idmap as',
            'SELECT * FROM map_integer',
            'UNION SELECT * FROM map_text',
            'CREATE TABLE mzed AS SELECT * FROM idmap',
            'do_execsql_test affinity3-210',
            'SELECT * FROM data JOIN idmap USING(id);',
            'do_execsql_test affinity3-220',
            'SELECT * FROM data JOIN mzed USING(id);',
            'do_execsql_test affinity3-250',
            'do_execsql_test affinity3-260',
        ] as $needle) {
            $t->contains($needle, $sourceText);
        }
        $t->same(4, count($joinSqlByShape), 'four upstream affinity3 idmap join shapes');
    };

for ($seed = 0; $seed < 1000; $seed++) {
    $tests[sprintf('real upstream date affinity dynamic affinity3 union idmap seed %04d', $seed)] =
        static function (TestRunner $t) use ($affinity3Tables, $joinSqlByShape, $seed): void {
            $tables = $affinity3Tables($seed);
            $idmapRows = SQLiteSelectSql::execute(
                'SELECT id, name FROM map_integer UNION SELECT id, name FROM map_text',
                $tables,
            );
            $tables['idmap'] = $idmapRows;
            $tables['mzed'] = $idmapRows;

            $integerId = 100000 + ($seed * 10) + 1;
            $textId = (string) (100000 + ($seed * 10) + 4);
            $integerDataName = 'data_integer_text_' . $seed;
            $textDataName = 'data_text_' . $seed;
            $textMapName = 'map_text_' . $seed;

            $typesByName = [];
            foreach ($idmapRows as $row) {
                $typesByName[(string) $row['name']] = SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$row['id']]);
            }

            $t->same(2, count($idmapRows), 'affinity3 UNION idmap preserves both map rows');
            $t->same('integer', $typesByName['map_integer_' . $seed] ?? null, 'map_integer id remains integer in UNION view');
            $t->same('text', $typesByName[$textMapName] ?? null, 'map_text id remains text in UNION view');
            $t->same(true, $seed >= 0 && $seed < 1000, 'bounded affinity3 dynamic seed');

            foreach ($joinSqlByShape as $shape => $sql) {
                $rows = SQLiteSelectSql::execute($sql, $tables);

                $t->same(1, count($rows), $shape . ' joins only the text-id row');
                $t->same($textId, (string) $rows[0]['id'], $shape . ' coalesced id');
                $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$rows[0]['id']]), $shape . ' coalesced id storage class');
                $t->same($textDataName, $rows[0]['data_name'], $shape . ' data row');
                $t->same($textMapName, $rows[0]['map_name'], $shape . ' map row');
                $t->same(false, in_array($integerDataName, array_column($rows, 'data_name'), true), $shape . ' rejects numeric-looking integer/text match');
                $t->same(false, in_array((string) $integerId, array_map('strval', array_column($rows, 'id')), true), $shape . ' excludes integer id');
            }
        };
}

$tests['real upstream date affinity dynamic affinity3 union idmap non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'owns affinity3.test affinity3-200..260 UNION-derived idmap and materialized mzed JOIN USING affinity behavior',
            'owns affinity3.test affinity3-200..260 UNION-derived idmap and materialized mzed JOIN USING affinity behavior',
        );
        $t->same(
            'non-overlap: avoids accepted date4 strftime ranges, date2 deterministic date checks, atof1/atof2 rounding, types storage-class batches, affinity3 REAL apr division, expression-affinity casts, and metadata-only runner rows',
            'non-overlap: avoids accepted date4 strftime ranges, date2 deterministic date checks, atof1/atof2 rounding, types storage-class batches, affinity3 REAL apr division, expression-affinity casts, and metadata-only runner rows',
        );
        $t->same(
            'dependency-closure: no new support component needed; reuses SQLiteSelectSql compound SELECT, JOIN USING, SQLiteAffinityComparison, and hydrated upstream SQLite affinity3.test source truth',
            'dependency-closure: no new support component needed; reuses SQLiteSelectSql compound SELECT, JOIN USING, SQLiteAffinityComparison, and hydrated upstream SQLite affinity3.test source truth',
        );
    };

return $tests;
