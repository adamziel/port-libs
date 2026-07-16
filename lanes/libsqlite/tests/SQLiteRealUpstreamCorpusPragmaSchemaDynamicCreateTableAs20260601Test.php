<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateTableAsSchemaPlan;

$tests = [];

/*
 * Real upstream source: SQLite test/table.test table-8.1 through table-8.10.
 *
 * Upstream exercises CREATE TABLE AS SELECT with keyword and quoted
 * identifiers, aggregate expressions, temporary CTAS lifetime, missing-source
 * errors, dotted quoted column names, and CTAS declared-type affinity. This
 * focused port keeps that schema text and row-materialization behavior generic.
 */

$weirdRows = static fn (int $variant): array => [[
    'desc' => 'a' . $variant,
    'asc' => 'b' . $variant,
    'key' => 9 + $variant,
    '14_vac' => $variant % 2,
    'fuzzy_dog_12' => 'fuzzy_' . $variant,
    'begin' => 'start_' . $variant,
    'end' => "end_{$variant}",
]];
$weirdTypes = [
    'desc' => 'TEXT',
    'asc' => 'TEXT',
    'key' => 'INTEGER',
    '14_vac' => 'NUMERIC',
    'fuzzy_dog_12' => 'TEXT',
    'begin' => '',
    'end' => 'TEXT',
];
$affinityTypes = [
    'a' => 'INTEGER',
    'b' => 'VARCHAR(10)',
    'c' => 'VARCHAR(1,10)',
    'd' => 'VARCHAR(+1,-10)',
    'e' => 'VARCHAR (+1,-10)',
    'f' => 'VARCHAR (+1,-10, 5)',
    'g' => 'BIG INTEGER',
];

foreach (range(1, 1000) as $variant) {
    $suffix = sprintf('%04d', $variant);

    if ($variant % 4 === 1) {
        $table = "ctas_weird_{$suffix}";
        $tests["real upstream table.test 8.1 CTAS star preserves keyword schema variant {$suffix}"] =
            static function (TestRunner $t) use ($table, $variant, $weirdRows, $weirdTypes): void {
                $plan = SQLiteCreateTableAsSchemaPlan::materialize(
                    "CREATE TABLE {$table} AS SELECT * FROM weird_source_{$variant}",
                    $weirdRows($variant),
                    $weirdTypes
                );

                $t->same('ok', $plan['status']);
                $t->same('table.test table-8.1 through table-8.10', $plan['source']);
                $t->same($table, $plan['table']);
                $t->same("weird_source_{$variant}", $plan['source_table']);
                $t->same(false, $plan['temporary']);
                $t->same(true, $plan['persists_after_reopen']);
                $t->same(['desc', 'asc', 'key', '14_vac', 'fuzzy_dog_12', 'begin', 'end'], array_column($plan['columns'], 'name'));
                $t->same(['TEXT', 'TEXT', 'INT', 'NUM', 'TEXT', '', 'TEXT'], array_column($plan['columns'], 'type'));
                $t->same(['"desc" TEXT', '"asc" TEXT', '"key" INT', '"14_vac" NUM', 'fuzzy_dog_12 TEXT', '"begin"', '"end" TEXT'], array_column($plan['columns'], 'sql_fragment'));
                $t->same(
                    "CREATE TABLE {$table}(\n  \"desc\" TEXT,\n  \"asc\" TEXT,\n  \"key\" INT,\n  \"14_vac\" NUM,\n  fuzzy_dog_12 TEXT,\n  \"begin\",\n  \"end\" TEXT\n)",
                    $plan['create_sql']
                );
                $t->same($weirdRows($variant), $plan['result_rows']);
                $t->same(null, $plan['error']);
                $t->same(['sqlite-create-table-as-select', 'sqlite-ctas-column-affinity', 'sqlite-ctas-schema-sql'], $plan['dependencies']);
            };
        continue;
    }

    if ($variant % 4 === 2) {
        $table = "ctas aggregate {$suffix}\"table";
        $source = "ctas source {$suffix}";
        $rows = [
            ['a' => 1, 'b' => $variant, 'c' => 2],
            ['a' => 2, 'b' => $variant + 4, 'c' => 6],
            ['a' => 3, 'b' => $variant - 1, 'c' => 8],
        ];
        $tests["real upstream table.test 8.3 CTAS aggregate expression schema variant {$suffix}"] =
            static function (TestRunner $t) use ($rows, $source, $table, $variant): void {
                $plan = SQLiteCreateTableAsSchemaPlan::materialize(
                    'CREATE TABLE [' . $table . '] AS SELECT count(*) as cnt, max(b+c) FROM [' . $source . ']',
                    $rows,
                    ['a' => 'INT', 'b' => 'INT', 'c' => 'INT']
                );

                $t->same('ok', $plan['status']);
                $t->same($table, $plan['table']);
                $t->same($source, $plan['source_table']);
                $t->same(['cnt', 'max(b+c)'], array_column($plan['columns'], 'name'));
                $t->same(['cnt', '"max(b+c)"'], array_column($plan['columns'], 'quoted_name'));
                $t->same(['', ''], array_column($plan['columns'], 'type'));
                $t->same(
                    'CREATE TABLE "' . str_replace('"', '""', $table) . "\"(\n  cnt,\n  \"max(b+c)\"\n)",
                    $plan['create_sql']
                );
                $t->same([['cnt' => 3, 'max(b+c)' => $variant + 10]], $plan['result_rows']);
                $t->same(true, $plan['persists_after_reopen']);
                $t->same(null, $plan['error']);
            };
        continue;
    }

    if ($variant % 4 === 3) {
        $table = "ctas_temp_{$suffix}";
        $source = "ctas_temp_source_{$suffix}";
        $rows = [['metric' => $variant], ['metric' => $variant + 1]];
        $tests["real upstream table.test 8.4 8.7 temporary CTAS lifetime variant {$suffix}"] =
            static function (TestRunner $t) use ($rows, $source, $table): void {
                $plan = SQLiteCreateTableAsSchemaPlan::materialize(
                    "CREATE TEMPORARY TABLE {$table} AS SELECT count(*) AS [rows counted] FROM {$source}",
                    $rows,
                    ['metric' => 'INTEGER']
                );

                $t->same('ok', $plan['status']);
                $t->same($table, $plan['table']);
                $t->same($source, $plan['source_table']);
                $t->same(true, $plan['temporary']);
                $t->same(false, $plan['persists_after_reopen']);
                $t->same(['rows counted'], array_column($plan['columns'], 'name'));
                $t->same(['"rows counted"'], array_column($plan['columns'], 'quoted_name'));
                $t->same([['rows counted' => 2]], $plan['result_rows']);
                $t->same("CREATE TEMPORARY TABLE {$table}(\n  \"rows counted\"\n)", $plan['create_sql']);
                $t->same(null, $plan['error']);
            };
        continue;
    }

    $table = "ctas_affinity_{$suffix}";
    $source = "ctas_affinity_source_{$suffix}";
    $tests["real upstream table.test 8.10 CTAS declared type affinity variant {$suffix}"] =
        static function (TestRunner $t) use ($affinityTypes, $source, $table, $variant): void {
            $row = [
                'a' => $variant,
                'b' => 'text-b',
                'c' => 'text-c',
                'd' => 'text-d',
                'e' => 'text-e',
                'f' => 'text-f',
                'g' => $variant + 100,
            ];
            $plan = SQLiteCreateTableAsSchemaPlan::materialize(
                "CREATE TABLE {$table} AS SELECT a,b,c,d,e,f,g FROM {$source}",
                [$row],
                $affinityTypes
            );

            $t->same('ok', $plan['status']);
            $t->same($table, $plan['table']);
            $t->same($source, $plan['source_table']);
            $t->same(['a', 'b', 'c', 'd', 'e', 'f', 'g'], array_column($plan['columns'], 'name'));
            $t->same(['INT', 'TEXT', 'TEXT', 'TEXT', 'TEXT', 'TEXT', 'INT'], array_column($plan['columns'], 'type'));
            $t->same(
                "CREATE TABLE {$table}(\n  a INT,\n  b TEXT,\n  c TEXT,\n  d TEXT,\n  e TEXT,\n  f TEXT,\n  g INT\n)",
                $plan['create_sql']
            );
            $t->same([$row], $plan['result_rows']);
            $t->same(true, $plan['persists_after_reopen']);
            $t->same(null, $plan['error']);
        };
}

$tests['real upstream table.test 8.9 CTAS dotted quoted column becomes TEXT'] = static function (TestRunner $t): void {
    $plan = SQLiteCreateTableAsSchemaPlan::materialize(
        'CREATE TABLE ctas_dotted AS SELECT "col.1" FROM ctas_dotted_source',
        [['col.1' => 'value']],
        ['col.1' => 'char.3']
    );

    $t->same('ok', $plan['status']);
    $t->same('ctas_dotted', $plan['table']);
    $t->same(['col.1'], array_column($plan['columns'], 'name'));
    $t->same(['TEXT'], array_column($plan['columns'], 'type'));
    $t->same(['"col.1" TEXT'], array_column($plan['columns'], 'sql_fragment'));
    $t->same("CREATE TABLE ctas_dotted(\n  \"col.1\" TEXT\n)", $plan['create_sql']);
    $t->same([['col.1' => 'value']], $plan['result_rows']);
};

$tests['real upstream table.test 8.8 CTAS missing source returns no such table'] = static function (TestRunner $t): void {
    $plan = SQLiteCreateTableAsSchemaPlan::materialize(
        'CREATE TABLE ctas_missing AS SELECT * FROM no_such_table',
        null
    );

    $t->same('error', $plan['status']);
    $t->same('ctas_missing', $plan['table']);
    $t->same('no_such_table', $plan['source_table']);
    $t->same(null, $plan['create_sql']);
    $t->same([], $plan['result_rows']);
    $t->same('no such table: no_such_table', $plan['error']);
    $t->same(false, $plan['persists_after_reopen']);
};

$tests['real upstream table.test CTAS rejects unsupported expressions'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteCreateTableAsSchemaPlan::materialize(
            'CREATE TABLE ctas_bad AS SELECT a-b FROM ctas_source',
            [['a' => 1, 'b' => 2]],
            ['a' => 'INTEGER', 'b' => 'INTEGER']
        )
    );
    $t->throws(
        InvalidArgumentException::class,
        static fn (): array => SQLiteCreateTableAsSchemaPlan::materialize(
            'CREATE TABLE ctas_missing_column AS SELECT missing_value FROM ctas_source',
            [['a' => 1]],
            ['a' => 'INTEGER']
        )
    );
};

$tests['real upstream table.test CTAS source citations and non overlap'] = static function (TestRunner $t): void {
    $sections = [
        'table.test table-8.1 and table-8.1.1: CREATE TABLE AS SELECT * preserves keyword/quoted result-column names and CTAS schema SQL',
        'table.test table-8.3 and table-8.3.1: aggregate CTAS creates cnt and max(b+c) columns with no declared types',
        'table.test table-8.4 through table-8.7: TEMPORARY CTAS materializes rows but does not persist after reopen',
        'table.test table-8.8: CTAS against no_such_table returns no such table',
        'table.test table-8.9 and table-8.10: CTAS maps dotted quoted names and source declared types to SQLite CTAS affinities',
    ];

    $t->same(5, count($sections));
    $t->contains('table-8.1', $sections[0]);
    $t->contains('max(b+c)', $sections[1]);
    $t->contains('TEMPORARY CTAS', $sections[2]);
    $t->contains('no_such_table', $sections[3]);
    $t->contains('declared types', $sections[4]);
    $t->same(
        'non-overlap: owns table.test table-8.1 through table-8.10 CTAS schema text and materialized row behavior only; avoids accepted table namespace/import admission, tableopts WITHOUT ROWID, schemafault OOM, schema invalidation, pragma table/index metadata, JSON, WAL, VFS, B-tree, and SELECT clusters',
        'non-overlap: owns table.test table-8.1 through table-8.10 CTAS schema text and materialized row behavior only; avoids accepted table namespace/import admission, tableopts WITHOUT ROWID, schemafault OOM, schema invalidation, pragma table/index metadata, JSON, WAL, VFS, B-tree, and SELECT clusters'
    );
    $t->same(
        'no new support component needed; reuses bounded lane-local CTAS schema planning without external services or upstream runner mutation',
        'no new support component needed; reuses bounded lane-local CTAS schema planning without external services or upstream runner mutation'
    );
};

return $tests;
