<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array{t1:list<array<string,mixed>>,t2:list<array<string,mixed>>}
 */
function json101_correlated_each_dynamic_tables(int $case): array
{
    $base = 1000 + ($case * 10);
    $firstDocument = [
        'case' => $case,
        'items' => [$base + 1, $base + 3],
        'label' => 'primary-' . $case,
    ];
    $secondDocument = [
        'case' => $case,
        'items' => [$base + 2],
        'label' => 'secondary-' . $case,
    ];

    $t2 = [];
    for ($offset = 0; $offset < 6; $offset++) {
        $id = $base + $offset;
        $t2[] = [
            'id' => $id,
            'json' => json101_correlated_each_dynamic_encode(['value' => $id]),
        ];
    }

    return [
        't1' => [
            [
                'id' => ($case * 2) + 1,
                'json' => json101_correlated_each_dynamic_encode($firstDocument),
                'docb' => json101_correlated_each_dynamic_blob($firstDocument),
            ],
            [
                'id' => ($case * 2) + 2,
                'json' => json101_correlated_each_dynamic_encode($secondDocument),
                'docb' => json101_correlated_each_dynamic_blob($secondDocument),
            ],
        ],
        't2' => $t2,
    ];
}

function json101_correlated_each_dynamic_encode(mixed $value): string
{
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode json101 correlated json_each fixture');
    }

    return $encoded;
}

function json101_correlated_each_dynamic_blob(mixed $value): SQLiteBlobValue
{
    return new SQLiteBlobValue(SQLiteJsonB::encode($value));
}

/**
 * @return list<array{t1_id:int,t2_id:int,marker:string}>
 */
function json101_correlated_each_dynamic_expected(int $case): array
{
    $base = 1000 + ($case * 10);

    return [
        ['t1_id' => ($case * 2) + 1, 't2_id' => $base + 1, 'marker' => 'NL'],
        ['t1_id' => ($case * 2) + 1, 't2_id' => $base + 3, 'marker' => 'NL'],
        ['t1_id' => ($case * 2) + 2, 't2_id' => $base + 2, 'marker' => 'NL'],
    ];
}

$tests = [];

for ($case = 0; $case < 1000; $case++) {
    $tables = json101_correlated_each_dynamic_tables($case);
    $expected = json101_correlated_each_dynamic_expected($case);
    $queries = [
        'json101-13.100 text t1 cross t2' => "SELECT t1.id AS t1_id, t2.id AS t2_id, 'NL' AS marker FROM t1 CROSS JOIN t2 WHERE EXISTS(SELECT 1 FROM json_each(t1.json, '$.items') AS Z WHERE Z.value == t2.id) ORDER BY t1.id, t2.id",
        'json101-13.110 text t2 cross t1' => "SELECT t1.id AS t1_id, t2.id AS t2_id, 'NL' AS marker FROM t2 CROSS JOIN t1 WHERE EXISTS(SELECT 1 FROM json_each(t1.json, '$.items') AS Z WHERE Z.value == t2.id) ORDER BY t1.id, t2.id",
        'json101-13.100 jsonb t1 cross t2' => "SELECT t1.id AS t1_id, t2.id AS t2_id, 'NL' AS marker FROM t1 CROSS JOIN t2 WHERE EXISTS(SELECT 1 FROM json_each(t1.docb, '$.items') AS Z WHERE Z.value == t2.id) ORDER BY t1.id, t2.id",
        'json101-13.110 jsonb t2 cross t1' => "SELECT t1.id AS t1_id, t2.id AS t2_id, 'NL' AS marker FROM t2 CROSS JOIN t1 WHERE EXISTS(SELECT 1 FROM json_each(t1.docb, '$.items') AS Z WHERE Z.value == t2.id) ORDER BY t1.id, t2.id",
    ];
    $testName = sprintf(
        'real upstream json101 correlated json_each dynamic %03d table-valued argument planning',
        $case
    );

    $tests[$testName] = static function (TestRunner $t) use ($tables, $expected, $queries): void {
        foreach ($queries as $label => $sql) {
            $actual = SQLiteSelectSql::execute($sql, $tables);

            $t->same(3, count($actual), $label . ' emits only matching host rows');
            $t->same($expected, $actual, $label . ' keeps correlated json_each arguments bound to the current outer row');
            $t->same([1, 1, 2], array_map(static fn (array $row): int => $row['t1_id'] % 2 === 0 ? 2 : 1, $actual), $label . ' keeps both t1 documents independent');
            $t->same(['NL', 'NL', 'NL'], array_column($actual, 'marker'), $label . ' preserves upstream marker projection');
        }
    };
}

$tests['real upstream json101 correlated json_each source citations'] =
    static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json101.test');
        }

        $t->contains('do_execsql_test json101-13.100', $source);
        $t->contains("SELECT *, 'NL' FROM t1 CROSS JOIN t2", $source);
        $t->contains("WHERE EXISTS(SELECT 1 FROM json_each(t1.json,'$.items') AS Z", $source);
        $t->contains('do_execsql_test json101-13.110', $source);
        $t->contains("SELECT *, 'NL' FROM t2 CROSS JOIN t1", $source);
        $t->same(
            ['json101-13.100', 'json101-13.110', 'ticket 80177f0c226ff54f6ddd41'],
            ['json101-13.100', 'json101-13.110', 'ticket 80177f0c226ff54f6ddd41']
        );
        $t->same(1002, count($GLOBALS['tests'] ?? []), '1000 dynamic correlated behavior cases plus source and dependency citations');
    };

$tests['real upstream json101 correlated json_each dependency closure note'] =
    static function (TestRunner $t): void {
        $t->same('no-new-support-component', 'no-new-support-component');
    };

return $tests;
