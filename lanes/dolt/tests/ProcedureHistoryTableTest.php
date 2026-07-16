<?php

declare(strict_types=1);

use PortLibs\Dolt\ProcedureHistoryTable;
use PortLibs\Dolt\TableDiff;

$procedure = static function (
    string $name,
    string $createStmt,
    string $createdAt = '2026-05-22 09:00:00',
    ?string $modifiedAt = null,
    ?string $sqlMode = '',
): array {
    return [
        'name' => $name,
        'create_stmt' => $createStmt,
        'created_at' => $createdAt,
        'modified_at' => $modifiedAt ?? $createdAt,
        'sql_mode' => $sqlMode,
    ];
};

$historyCommits = static function () use ($procedure): array {
    $proc1V1 = $procedure('test_proc1', 'CREATE PROCEDURE test_proc1() SELECT 1');
    $proc1V2 = $procedure(
        'test_proc1',
        "CREATE PROCEDURE test_proc1() SELECT 'modified'",
        '2026-05-22 09:00:00',
        '2026-05-22 09:02:00'
    );
    $proc2 = $procedure('test_proc2', 'CREATE PROCEDURE test_proc2(x INT) SELECT x * 2', '2026-05-22 09:01:00');
    $proc3 = $procedure('test_proc3', "CREATE PROCEDURE test_proc3() SELECT 'hello world' as result", '2026-05-22 09:03:00');

    return [
        [
            'commit_hash' => 'dolt-procedures-c1',
            'committer' => 'Dolt Tester <dolt@example.com>',
            'commit_date' => '2026-05-22 10:00:00',
            'procedures' => [$proc1V1],
        ],
        [
            'commit_hash' => 'dolt-procedures-c2',
            'committer' => 'Dolt Tester <dolt@example.com>',
            'commit_date' => '2026-05-22 10:01:00',
            'procedures' => [$proc1V1, $proc2],
        ],
        [
            'commit_hash' => 'dolt-procedures-c3',
            'committer' => 'Dolt Tester <dolt@example.com>',
            'commit_date' => '2026-05-22 10:02:00',
            'procedures' => [$proc1V2, $proc2],
        ],
        [
            'commit_hash' => 'dolt-procedures-c4',
            'committer' => 'Dolt Tester <dolt@example.com>',
            'commit_date' => '2026-05-22 10:03:00',
            'procedures' => [$proc1V2, $proc2, $proc3],
        ],
    ];
};

$diffBaseCommit = static function () use ($procedure): array {
    return [[
        'commit_hash' => 'procedures-base',
        'committer' => 'Dolt Tester <dolt@example.com>',
        'commit_date' => '2026-05-22 11:00:00',
        'procedures' => [
            $procedure('original_proc', 'CREATE PROCEDURE original_proc(x INT) SELECT x * 2 as result'),
            $procedure('helper_proc', "CREATE PROCEDURE helper_proc() SELECT 'helper' as message"),
        ],
    ]];
};

$diffWorkingProcedures = static function () use ($procedure): array {
    return [
        $procedure(
            'original_proc',
            'CREATE PROCEDURE original_proc(x INT, y INT) SELECT x + y as sum',
            '2026-05-22 09:00:00',
            '2026-05-22 11:05:00'
        ),
        $procedure(
            'new_proc',
            "CREATE PROCEDURE new_proc(name VARCHAR(50)) SELECT CONCAT('Hello, ', name) as greeting",
            '2026-05-22 11:05:00'
        ),
    ];
};

return [
    'dolt procedures history rows append commit metadata to every procedure' => static function (TestRunner $t) use ($historyCommits): void {
        $rows = (new ProcedureHistoryTable())->historyRows($historyCommits());

        $t->same(ProcedureHistoryTable::HISTORY_COLUMNS, array_keys($rows[0]));
        $t->same(8, count($rows));
        $t->same(4, count(array_filter($rows, static fn (array $row): bool => $row['name'] === 'test_proc1')));
        $t->same(3, count(array_filter($rows, static fn (array $row): bool => $row['name'] === 'test_proc2')));
        $t->same(1, count(array_filter($rows, static fn (array $row): bool => $row['name'] === 'test_proc3')));
        $t->same(8, count(array_filter($rows, static fn (array $row): bool => $row['commit_hash'] !== '' && $row['committer'] !== '')));
        $t->same(8, count(array_filter($rows, static fn (array $row): bool => str_contains($row['create_stmt'], 'PROCEDURE'))));

        $latestNames = array_column(array_filter(
            $rows,
            static fn (array $row): bool => $row['commit_hash'] === 'dolt-procedures-c4'
        ), 'name');
        sort($latestNames);

        $t->same(['test_proc1', 'test_proc2', 'test_proc3'], $latestNames);
    },
    'dolt procedures diff rows include initial commit and working procedure changes' => static function (TestRunner $t) use ($diffBaseCommit, $diffWorkingProcedures): void {
        $rows = (new ProcedureHistoryTable())->diffRows($diffBaseCommit(), $diffWorkingProcedures());

        $t->same(ProcedureHistoryTable::DIFF_COLUMNS, array_keys($rows[0]));
        $t->same(5, count($rows));
        $t->same(3, count(array_filter($rows, static fn (array $row): bool => $row['to_commit'] === ProcedureHistoryTable::WORKING_COMMIT)));
        $t->same(2, count(array_filter($rows, static fn (array $row): bool => $row['to_commit'] !== ProcedureHistoryTable::WORKING_COMMIT)));
        $t->same(3, count(array_filter($rows, static fn (array $row): bool => $row['diff_type'] === TableDiff::DIFF_ADDED)));

        $modified = array_values(array_filter($rows, static fn (array $row): bool => $row['diff_type'] === TableDiff::DIFF_MODIFIED));
        $removed = array_values(array_filter($rows, static fn (array $row): bool => $row['diff_type'] === TableDiff::DIFF_REMOVED));

        $t->same(['original_proc'], array_column($modified, 'to_name'));
        $t->same(['helper_proc'], array_column($removed, 'from_name'));
        $t->contains('x + y', $modified[0]['to_create_stmt']);
        $t->same(['original_proc', 'original_proc'], [$modified[0]['from_name'], $modified[0]['to_name']]);
    },
    'dolt procedures diff keys procedure names case-insensitively' => static function (TestRunner $t) use ($procedure): void {
        $table = new ProcedureHistoryTable();
        $commits = [[
            'commit_hash' => 'case-base',
            'committer' => 'Dolt Tester <dolt@example.com>',
            'commit_date' => '2026-05-22 12:00:00',
            'procedures' => [$procedure('Import_Proc', 'CREATE PROCEDURE Import_Proc() SELECT 1')],
        ]];
        $working = [$procedure('import_proc', 'CREATE PROCEDURE import_proc() SELECT 2')];

        $rows = array_values(array_filter(
            $table->diffRows($commits, $working),
            static fn (array $row): bool => $row['to_commit'] === ProcedureHistoryTable::WORKING_COMMIT
        ));

        $t->same(1, count($rows));
        $t->same(TableDiff::DIFF_MODIFIED, $rows[0]['diff_type']);
        $t->same('Import_Proc', $rows[0]['from_name']);
        $t->same('import_proc', $rows[0]['to_name']);
        $t->throws(InvalidArgumentException::class, static fn () => $table->historyRows([[
            'commit_hash' => 'dupe',
            'committer' => 'Dolt Tester <dolt@example.com>',
            'commit_date' => '2026-05-22 12:01:00',
            'procedures' => [
                $procedure('Import_Proc', 'CREATE PROCEDURE Import_Proc() SELECT 1'),
                $procedure('import_proc', 'CREATE PROCEDURE import_proc() SELECT 1'),
            ],
        ]]));
    },
    'wordpress procedure history fixture surfaces import routines' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-procedure-history.php';
        $table = new ProcedureHistoryTable();

        $historyRows = $table->historyRows($fixture['commits']);
        $diffRows = $table->diffRows($fixture['commits'], $fixture['workingProcedures']);
        $workingRows = array_values(array_filter(
            $diffRows,
            static fn (array $row): bool => $row['to_commit'] === ProcedureHistoryTable::WORKING_COMMIT
        ));

        $t->same($fixture['expectedHistoryTotal'], count($historyRows));
        $t->same($fixture['expectedWorkingDiffTypes'], array_column($workingRows, 'diff_type'));
        $t->same($fixture['expectedWorkingProcedureNames'], array_map(
            static fn (array $row): string => $row['to_name'] ?? $row['from_name'],
            $workingRows
        ));
        $added = array_values(array_filter(
            $workingRows,
            static fn (array $row): bool => $row['diff_type'] === TableDiff::DIFF_ADDED
        ));
        $t->contains('wp_import_review_cursor', $added[0]['to_create_stmt']);
    },
];
