<?php

declare(strict_types=1);

use PortLibs\Dolt\StatusTable;
use PortLibs\Dolt\TableSchema;

$schema = static fn (int $tag = 1): TableSchema => TableSchema::fromColumns([
    ['name' => 'pk', 'tag' => $tag, 'type' => 'int', 'primaryKey' => true],
    ['name' => 'value', 'tag' => $tag + 1, 'type' => 'varchar(20)'],
]);

return [
    'dolt status rows map staged and unstaged table deltas' => static function (TestRunner $t) use ($schema): void {
        $s = $schema();
        $rows = (new StatusTable())->rows(
            [
                ['name' => 't', 'schema' => $s, 'rowHash' => 'head', 'rowCount' => 1],
                ['name' => 'u', 'schema' => $s, 'rowHash' => 'same', 'rowCount' => 1],
            ],
            [
                ['name' => 't', 'schema' => $s, 'rowHash' => 'staged', 'rowCount' => 2],
                ['name' => 'u', 'schema' => $s, 'rowHash' => 'same', 'rowCount' => 1],
                ['name' => 'v', 'schema' => $s, 'rowHash' => 'new', 'rowCount' => 1],
            ],
            [
                ['name' => 't', 'schema' => $s, 'rowHash' => 'working', 'rowCount' => 3],
                ['name' => 'u', 'schema' => $s, 'rowHash' => 'same', 'rowCount' => 1],
                ['name' => 'v', 'schema' => $s, 'rowHash' => 'new', 'rowCount' => 1],
            ],
        );

        $t->same([
            ['table_name' => 'v', 'staged' => 1, 'status' => StatusTable::STATUS_NEW_TABLE],
            ['table_name' => 't', 'staged' => 1, 'status' => StatusTable::STATUS_MODIFIED],
            ['table_name' => 't', 'staged' => 0, 'status' => StatusTable::STATUS_MODIFIED],
        ], $rows);
    },
    'dolt status rows expose sql-status rename cases' => static function (TestRunner $t) use ($schema): void {
        $s = $schema();
        $status = new StatusTable();

        $unstagedRename = $status->rows(
            [['name' => 'test', 'schema' => $s, 'rowHash' => 'same', 'rowCount' => 0]],
            [['name' => 'test', 'schema' => $s, 'rowHash' => 'same', 'rowCount' => 0]],
            [['name' => 'quiz', 'schema' => $s, 'rowHash' => 'same', 'rowCount' => 0]],
        );
        $stagedNewThenRenamed = $status->rows(
            [],
            [['name' => 'test', 'schema' => $s, 'rowHash' => 'same', 'rowCount' => 0]],
            [['name' => 'test2', 'schema' => $s, 'rowHash' => 'same', 'rowCount' => 0]],
        );

        $t->same([['table_name' => 'test -> quiz', 'staged' => 0, 'status' => StatusTable::STATUS_RENAMED]], $unstagedRename);
        $t->same([
            ['table_name' => 'test', 'staged' => 1, 'status' => StatusTable::STATUS_NEW_TABLE],
            ['table_name' => 'test -> test2', 'staged' => 0, 'status' => StatusTable::STATUS_RENAMED],
        ], $stagedNewThenRenamed);
    },
    'dolt status ignored rows only mark unstaged new tables ignored' => static function (TestRunner $t) use ($schema): void {
        $s = $schema();
        $status = new StatusTable();
        $patterns = [['pattern' => 'generated_*', 'ignore' => true]];

        $rows = $status->rows(
            [['name' => 'generated_tracked', 'schema' => $s, 'rowHash' => 'head', 'rowCount' => 1]],
            [
                ['name' => 'generated_tracked', 'schema' => $s, 'rowHash' => 'head', 'rowCount' => 1],
                ['name' => 'generated_staged', 'schema' => $s, 'rowHash' => 'staged', 'rowCount' => 1],
            ],
            [
                ['name' => 'generated_tracked', 'schema' => $s, 'rowHash' => 'working', 'rowCount' => 1],
                ['name' => 'generated_staged', 'schema' => $s, 'rowHash' => 'staged', 'rowCount' => 1],
                ['name' => 'generated_working', 'schema' => $s, 'rowHash' => 'working-new', 'rowCount' => 1],
            ],
            [],
            [],
            [],
            [],
            $patterns,
        );
        $ignoredRows = $status->rowsWithIgnored(
            [['name' => 'generated_tracked', 'schema' => $s, 'rowHash' => 'head', 'rowCount' => 1]],
            [
                ['name' => 'generated_tracked', 'schema' => $s, 'rowHash' => 'head', 'rowCount' => 1],
                ['name' => 'generated_staged', 'schema' => $s, 'rowHash' => 'staged', 'rowCount' => 1],
            ],
            [
                ['name' => 'generated_tracked', 'schema' => $s, 'rowHash' => 'working', 'rowCount' => 1],
                ['name' => 'generated_staged', 'schema' => $s, 'rowHash' => 'staged', 'rowCount' => 1],
                ['name' => 'generated_working', 'schema' => $s, 'rowHash' => 'working-new', 'rowCount' => 1],
            ],
            [],
            [],
            [],
            [],
            $patterns,
        );

        $t->same(['generated_staged', 'generated_tracked'], array_column($rows, 'table_name'));
        $t->same([1, 0], array_column($rows, 'staged'));
        $ignoredByTable = array_column($ignoredRows, 'ignored', 'table_name');
        $t->same(false, $ignoredByTable['generated_staged']);
        $t->same(false, $ignoredByTable['generated_tracked']);
        $t->same(true, $ignoredByTable['generated_working']);
    },
    'dolt status rows include conflict violation schema and merged states' => static function (TestRunner $t) use ($schema): void {
        $s = $schema();
        $rows = (new StatusTable())->rows(
            [
                ['name' => 'cv', 'schema' => $s, 'rowHash' => 'old', 'rowCount' => 1],
                ['name' => 'tracked', 'schema' => $s, 'rowHash' => 'old', 'rowCount' => 1],
            ],
            [
                ['name' => 'cv', 'schema' => $s, 'rowHash' => 'new', 'rowCount' => 1],
                ['name' => 'tracked', 'schema' => $s, 'rowHash' => 'old', 'rowCount' => 1],
            ],
            [
                ['name' => 'cv', 'schema' => $s, 'rowHash' => 'newer', 'rowCount' => 1],
                ['name' => 'tracked', 'schema' => $s, 'rowHash' => 'new', 'rowCount' => 1],
            ],
            ['data_conflict'],
            ['schema_conflict'],
            ['cv'],
            ['resolved_merge'],
        );

        $t->same([
            ['table_name' => 'cv', 'staged' => 0, 'status' => StatusTable::STATUS_CONSTRAINT_VIOLATION],
            ['table_name' => 'schema_conflict', 'staged' => 0, 'status' => StatusTable::STATUS_SCHEMA_CONFLICT],
            ['table_name' => 'resolved_merge', 'staged' => 1, 'status' => StatusTable::STATUS_MERGED],
            ['table_name' => 'data_conflict', 'staged' => 0, 'status' => StatusTable::STATUS_CONFLICT],
            ['table_name' => 'tracked', 'staged' => 0, 'status' => StatusTable::STATUS_MODIFIED],
        ], $rows);
    },
    'wordpress status fixture hides generated cache while surfacing review work' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-status-review.php';
        $status = new StatusTable();

        $rows = $status->rows(
            $fixture['headTables'],
            $fixture['stagedTables'],
            $fixture['workingTables'],
            $fixture['dataConflictTables'],
            [],
            [],
            [],
            $fixture['ignorePatterns'],
        );
        $ignoredRows = $status->rowsWithIgnored(
            $fixture['headTables'],
            $fixture['stagedTables'],
            $fixture['workingTables'],
            $fixture['dataConflictTables'],
            [],
            [],
            [],
            $fixture['ignorePatterns'],
        );

        $t->same($fixture['expectedStatusRows'], $rows);
        $t->true(!in_array('wp_tmp_import_cache', array_column($rows, 'table_name'), true));
        $t->same(true, array_column($ignoredRows, 'ignored', 'table_name')['wp_tmp_import_cache']);
        $t->same('conflict', $rows[0]['status']);
    },
];
