<?php

declare(strict_types=1);

use PortLibs\Dolt\SchemaHistoryTable;
use PortLibs\Dolt\TableDiff;

$schemaObject = static function (string $type, string $name, string $fragment, mixed $extra = null): array {
    return [
        'type' => $type,
        'name' => $name,
        'fragment' => $fragment,
        'extra' => $extra,
        'sql_mode' => '',
    ];
};

$historyCommits = static function () use ($schemaObject): array {
    $viewV1 = $schemaObject('view', 'test_view', 'CREATE VIEW test_view AS SELECT 1 as col1');
    $viewV2 = $schemaObject('view', 'test_view', 'CREATE VIEW test_view AS SELECT 1 as col1, 2 as col2');
    $trigger = $schemaObject('trigger', 'test_trigger', 'CREATE TRIGGER test_trigger BEFORE INSERT ON test_table FOR EACH ROW SET NEW.name = UPPER(NEW.name)');
    $event = $schemaObject('event', 'test_event', 'CREATE EVENT test_event ON SCHEDULE EVERY 1 DAY DO INSERT INTO test_table VALUES (1, "daily")');

    return [
        [
            'commit_hash' => 'dolt-schemas-c1',
            'committer' => 'Dolt Tester <dolt@example.com>',
            'commit_date' => '2026-05-22 10:00:00',
            'schemas' => [$viewV1],
        ],
        [
            'commit_hash' => 'dolt-schemas-c2',
            'committer' => 'Dolt Tester <dolt@example.com>',
            'commit_date' => '2026-05-22 10:01:00',
            'schemas' => [$viewV1, $trigger],
        ],
        [
            'commit_hash' => 'dolt-schemas-c3',
            'committer' => 'Dolt Tester <dolt@example.com>',
            'commit_date' => '2026-05-22 10:02:00',
            'schemas' => [$viewV2, $trigger],
        ],
        [
            'commit_hash' => 'dolt-schemas-c4',
            'committer' => 'Dolt Tester <dolt@example.com>',
            'commit_date' => '2026-05-22 10:03:00',
            'schemas' => [$viewV2, $trigger, $event],
        ],
    ];
};

$diffBaseCommit = static function () use ($schemaObject): array {
    return [
        [
            'commit_hash' => 'schema-base',
            'committer' => 'Dolt Tester <dolt@example.com>',
            'commit_date' => '2026-05-22 11:00:00',
            'schemas' => [
                $schemaObject('view', 'original_view', 'CREATE VIEW original_view AS SELECT 1 as id'),
                $schemaObject('trigger', 'original_trigger', 'CREATE TRIGGER original_trigger BEFORE INSERT ON diff_table FOR EACH ROW SET NEW.id = NEW.id + 1'),
            ],
        ],
    ];
};

$diffWorkingSchemas = static function () use ($schemaObject): array {
    return [
        $schemaObject('view', 'original_view', "CREATE VIEW original_view AS SELECT 1 as id, 'modified' as status"),
        $schemaObject('view', 'new_view', "CREATE VIEW new_view AS SELECT 'added' as status"),
        $schemaObject('event', 'new_event', 'CREATE EVENT new_event ON SCHEDULE EVERY 1 HOUR DO SELECT 1'),
    ];
};

return [
    'dolt schemas history rows append commit metadata to every schema object' => static function (TestRunner $t) use ($historyCommits): void {
        $rows = (new SchemaHistoryTable())->historyRows($historyCommits());

        $t->same(SchemaHistoryTable::HISTORY_COLUMNS, array_keys($rows[0]));
        $t->same(8, count($rows));
        $t->same(4, count(array_filter($rows, static fn (array $row): bool => $row['type'] === 'view')));
        $t->same(3, count(array_filter($rows, static fn (array $row): bool => $row['type'] === 'trigger')));
        $t->same(1, count(array_filter($rows, static fn (array $row): bool => $row['type'] === 'event')));
        $t->same(8, count(array_filter($rows, static fn (array $row): bool => $row['commit_hash'] !== '' && $row['committer'] !== '')));

        $latestObjects = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['commit_hash'] === 'dolt-schemas-c4'
                && in_array($row['type'], ['trigger', 'event'], true)
        ));
        usort($latestObjects, static fn (array $a, array $b): int => [$a['type'], $a['name']] <=> [$b['type'], $b['name']]);

        $t->same([['event', 'test_event'], ['trigger', 'test_trigger']], array_map(
            static fn (array $row): array => [$row['type'], $row['name']],
            $latestObjects
        ));
    },
    'dolt schemas diff rows include initial commit and working schema changes' => static function (TestRunner $t) use ($diffBaseCommit, $diffWorkingSchemas): void {
        $rows = (new SchemaHistoryTable())->diffRows($diffBaseCommit(), $diffWorkingSchemas());

        $t->same(SchemaHistoryTable::DIFF_COLUMNS, array_keys($rows[0]));
        $t->same(6, count($rows));
        $t->same(4, count(array_filter($rows, static fn (array $row): bool => $row['diff_type'] === TableDiff::DIFF_ADDED)));
        $t->same(4, count(array_filter($rows, static fn (array $row): bool => $row['to_commit'] === SchemaHistoryTable::WORKING_COMMIT)));
        $t->same(2, count(array_filter($rows, static fn (array $row): bool => $row['to_commit'] !== SchemaHistoryTable::WORKING_COMMIT)));

        $modified = array_values(array_filter($rows, static fn (array $row): bool => $row['diff_type'] === TableDiff::DIFF_MODIFIED));
        $removed = array_values(array_filter($rows, static fn (array $row): bool => $row['diff_type'] === TableDiff::DIFF_REMOVED));

        $t->same(['original_view'], array_column($modified, 'to_name'));
        $t->same(['original_trigger'], array_column($removed, 'from_name'));
        $t->same([SchemaHistoryTable::WORKING_COMMIT], array_values(array_unique(array_column(
            array_filter($rows, static fn (array $row): bool => $row['to_commit'] === SchemaHistoryTable::WORKING_COMMIT),
            'to_commit'
        ))));
    },
    'dolt schemas diff compares JSON extra and keys modified rows case-insensitively' => static function (TestRunner $t) use ($schemaObject): void {
        $table = new SchemaHistoryTable();
        $commits = [[
            'commit_hash' => 'case-base',
            'committer' => 'Dolt Tester <dolt@example.com>',
            'commit_date' => '2026-05-22 12:00:00',
            'schemas' => [[
                'type' => 'VIEW',
                'name' => 'Object_View',
                'fragment' => 'CREATE VIEW Object_View AS SELECT 1',
                'extra' => ['b' => ['z' => 2, 'y' => 1], 'a' => true],
                'sql_mode' => '',
            ]],
        ]];

        $sameWorking = [[
            'type' => 'VIEW',
            'name' => 'Object_View',
            'fragment' => 'CREATE VIEW Object_View AS SELECT 1',
            'extra' => ['a' => true, 'b' => ['y' => 1, 'z' => 2]],
            'sql_mode' => '',
        ]];
        $changedWorking = [$schemaObject('view', 'object_view', 'CREATE VIEW object_view AS SELECT 2')];

        $unchangedRows = array_values(array_filter(
            $table->diffRows($commits, $sameWorking),
            static fn (array $row): bool => $row['to_commit'] === SchemaHistoryTable::WORKING_COMMIT
        ));
        $changedRows = array_values(array_filter(
            $table->diffRows($commits, $changedWorking),
            static fn (array $row): bool => $row['to_commit'] === SchemaHistoryTable::WORKING_COMMIT
        ));

        $t->same([], $unchangedRows);
        $t->same(1, count($changedRows));
        $t->same(TableDiff::DIFF_MODIFIED, $changedRows[0]['diff_type']);
        $t->same('Object_View', $changedRows[0]['from_name']);
        $t->same('object_view', $changedRows[0]['to_name']);
    },
    'wordpress schema history fixture surfaces migration views triggers and events' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-schema-history.php';
        $table = new SchemaHistoryTable();

        $historyRows = $table->historyRows($fixture['commits']);
        $diffRows = $table->diffRows($fixture['commits'], $fixture['workingSchemas']);
        $workingRows = array_values(array_filter(
            $diffRows,
            static fn (array $row): bool => $row['to_commit'] === SchemaHistoryTable::WORKING_COMMIT
        ));

        $counts = [];
        foreach ($historyRows as $row) {
            $counts[$row['type']] = ($counts[$row['type']] ?? 0) + 1;
        }

        $t->same($fixture['expectedHistoryCounts']['total'], count($historyRows));
        $t->same($fixture['expectedHistoryCounts']['view'], $counts['view']);
        $t->same($fixture['expectedHistoryCounts']['trigger'], $counts['trigger']);
        $t->same($fixture['expectedWorkingDiffTypes'], array_column($workingRows, 'diff_type'));
        $t->same($fixture['expectedWorkingObjectNames'], array_map(
            static fn (array $row): string => $row['to_name'] ?? $row['from_name'],
            $workingRows
        ));
    },
];
