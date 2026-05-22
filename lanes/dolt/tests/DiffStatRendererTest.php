<?php

declare(strict_types=1);

use PortLibs\Dolt\DiffStatRenderer;
use PortLibs\Dolt\TableDeltaMatcher;
use PortLibs\Dolt\TableDiff;
use PortLibs\Dolt\TableSchema;

$statTables = static function (): array {
    $schema = TableSchema::fromColumns([
        ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ['name' => 'v', 'tag' => 2, 'type' => 'int'],
    ]);
    $differ = new TableDiff();
    $makeStat = static function (string $tableName, int $baseValue, int $addedValue) use ($differ, $schema): array {
        $warnings = [];
        $row = $differ->diffStatRow(
            $tableName,
            [['id' => 1, 'v' => $baseValue]],
            [
                ['id' => 1, 'v' => $baseValue],
                ['id' => 2, 'v' => $addedValue],
            ],
            'id',
            $schema,
            $schema,
            $warnings,
        );

        return [
            'from_table_name' => $tableName,
            'to_table_name' => $tableName,
            'diff_type' => TableDeltaMatcher::DIFF_MODIFIED,
            'from_schema' => $schema,
            'to_schema' => $schema,
            'statRows' => [$row],
        ];
    };

    return [
        $makeStat('aaa', 10, 20),
        $makeStat('zzz', 100, 200),
    ];
};

return [
    'dolt diff stat renderer matches upstream table-specific CLI output' => static function (TestRunner $t) use ($statTables): void {
        $renderer = new DiffStatRenderer();

        $t->same(implode("\n", [
            'diff --dolt a/aaa b/aaa',
            '--- a/aaa',
            '+++ b/aaa',
            '1 Row Unmodified (100.00%)',
            '1 Row Added (100.00%)',
            '0 Rows Deleted (0.00%)',
            '0 Rows Modified (0.00%)',
            '2 Cells Added (100.00%)',
            '0 Cells Deleted (0.00%)',
            '0 Cells Modified (0.00%)',
            '(1 Row Entry vs 2 Row Entries)',
            '',
            'diff --dolt a/zzz b/zzz',
            '--- a/zzz',
            '+++ b/zzz',
            '1 Row Unmodified (100.00%)',
            '1 Row Added (100.00%)',
            '0 Rows Deleted (0.00%)',
            '0 Rows Modified (0.00%)',
            '2 Cells Added (100.00%)',
            '0 Cells Deleted (0.00%)',
            '0 Cells Modified (0.00%)',
            '(1 Row Entry vs 2 Row Entries)',
        ]), $renderer->render($statTables()));

        $t->same(implode("\n", [
            'diff --dolt a/zzz b/zzz',
            '--- a/zzz',
            '+++ b/zzz',
            '1 Row Unmodified (100.00%)',
            '1 Row Added (100.00%)',
            '0 Rows Deleted (0.00%)',
            '0 Rows Modified (0.00%)',
            '2 Cells Added (100.00%)',
            '0 Cells Deleted (0.00%)',
            '0 Cells Modified (0.00%)',
            '(1 Row Entry vs 2 Row Entries)',
        ]), $renderer->render($statTables(), ['tableNames' => ['zzz']]));
        $t->same('', $renderer->render($statTables(), ['tableNames' => ['same']]));
    },
    'dolt diff stat table args do not use summary short circuit boundary' => static function (TestRunner $t) use ($statTables): void {
        $output = (new DiffStatRenderer())->render($statTables(), ['tableNames' => ['zzz']]);

        $t->true(!str_contains($output, 'a/aaa b/aaa'));
        $t->contains('diff --dolt a/zzz b/zzz', $output);
    },
    'dolt diff stat json renderer matches upstream result format output' => static function (TestRunner $t) use ($statTables): void {
        $renderer = new DiffStatRenderer();

        $t->same(
            '{"tables":[{"name":"aaa","stats":{"rows_added":1,"rows_deleted":0,"rows_modified":0,"rows_unmodified":1,"cells_added":2,"cells_deleted":0,"cells_modified":0}},{"name":"zzz","stats":{"rows_added":1,"rows_deleted":0,"rows_modified":0,"rows_unmodified":1,"cells_added":2,"cells_deleted":0,"cells_modified":0}}]}',
            $renderer->renderJson($statTables())
        );
        $t->same(
            '{"tables":[{"name":"zzz","stats":{"rows_added":1,"rows_deleted":0,"rows_modified":0,"rows_unmodified":1,"cells_added":2,"cells_deleted":0,"cells_modified":0}}]}',
            $renderer->renderJson($statTables(), ['tableNames' => ['zzz']])
        );
        $t->same('', $renderer->renderJson($statTables(), ['tableNames' => ['same']]));
    },
    'dolt diff stat renderer maps keyless CLI text and JSON quirks' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int'],
            ['name' => 'c1', 'tag' => 2, 'type' => 'varchar(20)'],
            ['name' => 'c2', 'tag' => 3, 'type' => 'varchar(20)'],
        ]);
        $warnings = [];
        $row = (new TableDiff())->diffStatRow(
            'keyless',
            [['id' => 1, 'c1' => 'one', 'c2' => 'two']],
            [
                ['id' => 1, 'c1' => 'uno', 'c2' => 'dos'],
                ['id' => 2, 'c1' => 'two', 'c2' => 'three'],
                ['id' => 3, 'c1' => 'three', 'c2' => 'four'],
            ],
            null,
            $schema,
            $schema,
            $warnings,
            true,
            true,
        );
        $tables = [[
            'from_table_name' => 'keyless',
            'to_table_name' => 'keyless',
            'diff_type' => TableDeltaMatcher::DIFF_MODIFIED,
            'from_schema' => $schema,
            'to_schema' => $schema,
            'keyless' => true,
            'statRows' => [$row],
        ]];
        $renderer = new DiffStatRenderer();

        $t->same(implode("\n", [
            'diff --dolt a/keyless b/keyless',
            '--- a/keyless',
            '+++ b/keyless',
            '3 Rows Added',
            '1 Row Deleted',
        ]), $renderer->render($tables));
        $t->same(
            '{"tables":[{"name":"keyless","stats":{"rows_added":3,"rows_deleted":1,"rows_modified":0,"rows_unmodified":18446744073709551615,"cells_added":9,"cells_deleted":9,"cells_modified":0}}]}',
            $renderer->renderJson($tables)
        );
        $t->same([], $warnings);
    },
    'dolt diff stat renderer reports schema only tables as no data changes' => static function (TestRunner $t): void {
        $schemaBefore = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'v', 'tag' => 2, 'type' => 'int'],
        ]);
        $schemaAfter = TableSchema::fromColumns([
            ['name' => 'id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'v', 'tag' => 2, 'type' => 'int'],
            ['name' => 'vv', 'tag' => 3, 'type' => 'int'],
        ]);

        $output = (new DiffStatRenderer())->render([
            [
                'from_table_name' => 'same',
                'to_table_name' => 'same',
                'diff_type' => TableDeltaMatcher::DIFF_MODIFIED,
                'from_schema' => $schemaBefore,
                'to_schema' => $schemaAfter,
                'statRows' => [],
            ],
        ]);

        $t->same(implode("\n", [
            'diff --dolt a/same b/same',
            '--- a/same',
            '+++ b/same',
            'No data changes. See schema changes by using -s or --schema.',
        ]), $output);

        $t->same(
            '{"tables":[{"name":"same","stats":{}}]}',
            (new DiffStatRenderer())->renderJson([
                [
                    'from_table_name' => 'same',
                    'to_table_name' => 'same',
                    'diff_type' => TableDeltaMatcher::DIFF_MODIFIED,
                    'from_schema' => $schemaBefore,
                    'to_schema' => $schemaAfter,
                    'statRows' => [],
                ],
            ])
        );
    },
    'wordpress diff stat cli example renders table-specific review stats' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-diff-stat-cli.php';

        $t->contains('diff --dolt a/wp_import_audit b/wp_import_audit', $output['all']);
        $t->contains('diff --dolt a/wp_posts b/wp_posts', $output['all']);
        $t->contains('1 Row Deleted (33.33%)', $output['postsOnly']);
        $t->contains('2 Cells Modified (16.67%)', $output['postsOnly']);
        $t->true(!str_contains($output['postsOnly'], 'wp_import_audit'));
        $t->contains('No data changes. See schema changes by using -s or --schema.', $output['schemaOnlyOptions']);
        $t->same('{"tables":[{"name":"wp_posts","stats":{"rows_added":1,"rows_deleted":1,"rows_modified":1,"rows_unmodified":1,"cells_added":4,"cells_deleted":4,"cells_modified":2}}]}', $output['postsJson']);
        $t->same('{"tables":[{"name":"wp_options","stats":{}}]}', $output['schemaOnlyOptionsJson']);
        $t->contains('3 Rows Added', $output['keylessLog']);
        $t->contains('1 Row Deleted', $output['keylessLog']);
        $t->true(!str_contains($output['keylessLog'], 'Cells Added'));
        $t->same('{"tables":[{"name":"wp_import_log","stats":{"rows_added":3,"rows_deleted":1,"rows_modified":0,"rows_unmodified":18446744073709551615,"cells_added":9,"cells_deleted":9,"cells_modified":0}}]}', $output['keylessLogJson']);
        $t->same('', $output['unchangedUsers']);
        $t->same('', $output['unchangedUsersJson']);
        $t->same([], $output['warnings']);
    },
];
