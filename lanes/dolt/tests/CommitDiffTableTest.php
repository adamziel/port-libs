<?php

declare(strict_types=1);

use PortLibs\Dolt\CommitDiffTable;
use PortLibs\Dolt\TableDiff;

$sqlCommitDiffSnapshots = static function (): array {
    $initial = [
        ['pk' => 10, 'v' => 10],
        ['pk' => 11, 'v' => 11],
        ['pk' => 20, 'v' => 20],
        ['pk' => 21, 'v' => 21],
        ['pk' => 30, 'v' => 30],
        ['pk' => 31, 'v' => 31],
    ];
    $update = [
        ['pk' => 10, 'v' => 10],
        ['pk' => 11, 'v' => 0],
        ['pk' => 12, 'v' => 12],
        ['pk' => 21, 'v' => 0],
        ['pk' => 22, 'v' => 22],
        ['pk' => 31, 'v' => 0],
        ['pk' => 32, 'v' => 32],
    ];
    $initialTwoPk = [
        ['pk1' => 1, 'pk2' => 10, 'v' => null],
        ['pk1' => 1, 'pk2' => 11, 'v' => null],
        ['pk1' => 2, 'pk2' => 20, 'v' => null],
        ['pk1' => 2, 'pk2' => 21, 'v' => null],
        ['pk1' => 3, 'pk2' => 30, 'v' => null],
        ['pk1' => 3, 'pk2' => 31, 'v' => null],
    ];
    $updateTwoPk = [
        ['pk1' => 1, 'pk2' => 11, 'v' => 1],
        ['pk1' => 1, 'pk2' => 12, 'v' => null],
        ['pk1' => 2, 'pk2' => 21, 'v' => 1],
        ['pk1' => 2, 'pk2' => 22, 'v' => null],
        ['pk1' => 3, 'pk2' => 31, 'v' => 1],
        ['pk1' => 3, 'pk2' => 32, 'v' => null],
    ];

    return [
        'test' => [
            ['commit_hash' => 'create', 'commit_date' => '2026-05-22 15:00:00', 'rows' => []],
            ['commit_hash' => 'initial', 'commit_date' => '2026-05-22 15:01:00', 'rows' => $initial],
            ['commit_hash' => 'update', 'commit_date' => '2026-05-22 15:02:00', 'rows' => $update],
        ],
        'test_two_pk' => [
            ['commit_hash' => 'create', 'commit_date' => '2026-05-22 15:00:00', 'rows' => []],
            ['commit_hash' => 'initial', 'commit_date' => '2026-05-22 15:01:00', 'rows' => $initialTwoPk],
            ['commit_hash' => 'update', 'commit_date' => '2026-05-22 15:02:00', 'rows' => $updateTwoPk],
        ],
    ];
};

return [
    'dolt commit diff rows require one to_commit and one from_commit' => static function (TestRunner $t) use ($sqlCommitDiffSnapshots): void {
        $table = new CommitDiffTable();
        $snapshots = $sqlCommitDiffSnapshots()['test'];

        try {
            $table->rows($snapshots, 'pk', ['from_commit' => 'initial'], ['pk', 'v']);
            $t->true(false, 'Expected missing to_commit to throw.');
        } catch (InvalidArgumentException $e) {
            $t->contains("filtered to a single 'to_commit'", $e->getMessage());
        }

        try {
            $table->rows($snapshots, 'pk', ['to_commit' => 'update', 'from_commit' => ['initial', 'create']], ['pk', 'v']);
            $t->true(false, 'Expected multiple from_commit filters to throw.');
        } catch (InvalidArgumentException $e) {
            $t->contains("filtered to a single 'from_commit'", $e->getMessage());
        }

        $t->throws(RuntimeException::class, static fn () => $table->rows(
            $snapshots,
            'pk',
            ['to_commit' => 'missing', 'from_commit' => 'initial'],
            ['pk', 'v'],
        ));
    },
    'dolt commit diff filters range predicates on to primary key columns' => static function (TestRunner $t) use ($sqlCommitDiffSnapshots): void {
        $table = new CommitDiffTable();
        $snapshots = $sqlCommitDiffSnapshots();

        $rows = $table->rows(
            $snapshots['test'],
            'pk',
            ['from_commit' => 'initial', 'to_commit' => 'update'],
            ['pk', 'v'],
            'to_pk > 12 AND to_pk < 30',
        );
        $compoundRows = $table->rows(
            $snapshots['test_two_pk'],
            ['pk1', 'pk2'],
            ['from_commit' => 'initial', 'to_commit' => 'update'],
            ['pk1', 'pk2', 'v'],
            'to_pk1 = 2',
        );

        $t->same([TableDiff::DIFF_MODIFIED, TableDiff::DIFF_ADDED], array_column($rows, 'diff_type'));
        $t->same([[21, 21], [null, 22]], array_map(
            static fn (array $row): array => [$row['from_pk'], $row['to_pk']],
            $rows,
        ));
        $t->same([TableDiff::DIFF_MODIFIED, TableDiff::DIFF_ADDED], array_column($compoundRows, 'diff_type'));
        $t->same([[2, 21, 2, 21], [null, null, 2, 22]], array_map(
            static fn (array $row): array => [$row['from_pk1'], $row['from_pk2'], $row['to_pk1'], $row['to_pk2']],
            $compoundRows,
        ));
    },
    'dolt commit diff filters range predicates on from primary key columns' => static function (TestRunner $t) use ($sqlCommitDiffSnapshots): void {
        $table = new CommitDiffTable();
        $snapshots = $sqlCommitDiffSnapshots();

        $rows = $table->rows(
            $snapshots['test'],
            'pk',
            ['from_commit' => 'initial', 'to_commit' => 'update'],
            ['pk', 'v'],
            'from_pk > 12 AND from_pk < 30',
        );
        $compoundRows = $table->rows(
            $snapshots['test_two_pk'],
            ['pk1', 'pk2'],
            ['from_commit' => 'initial', 'to_commit' => 'update'],
            ['pk1', 'pk2', 'v'],
            'from_pk1 = 2',
        );

        $t->same([TableDiff::DIFF_REMOVED, TableDiff::DIFF_MODIFIED], array_column($rows, 'diff_type'));
        $t->same([[20, null], [21, 21]], array_map(
            static fn (array $row): array => [$row['from_pk'], $row['to_pk']],
            $rows,
        ));
        $t->same([TableDiff::DIFF_REMOVED, TableDiff::DIFF_MODIFIED], array_column($compoundRows, 'diff_type'));
        $t->same([[2, 20, null, null], [2, 21, 2, 21]], array_map(
            static fn (array $row): array => [$row['from_pk1'], $row['from_pk2'], $row['to_pk1'], $row['to_pk2']],
            $compoundRows,
        ));
    },
    'dolt commit diff rows retain from and to commit metadata' => static function (TestRunner $t) use ($sqlCommitDiffSnapshots): void {
        $rows = (new CommitDiffTable())->rows(
            $sqlCommitDiffSnapshots()['test'],
            'pk',
            ['from_commit' => ['initial'], 'to_commit' => ['update']],
            ['pk', 'v'],
            'to_pk = 22',
            1,
        );

        $t->same(1, count($rows));
        $t->same('initial', $rows[0]['from_commit']);
        $t->same('2026-05-22 15:01:00', $rows[0]['from_commit_date']);
        $t->same('update', $rows[0]['to_commit']);
        $t->same('2026-05-22 15:02:00', $rows[0]['to_commit_date']);
        $t->same(TableDiff::DIFF_ADDED, $rows[0]['diff_type']);
    },
    'wordpress commit diff fixture narrows review rows between import commits' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-commit-diff-review.php';
        $rows = (new CommitDiffTable())->rows(
            $fixture['snapshots'],
            $fixture['primaryKey'],
            $fixture['filters'],
            $fixture['columns'],
            $fixture['where'],
        );

        $t->same($fixture['expectedChangedIds'], array_map(
            static fn (array $row): int => (int) ($row['to_ID'] ?? $row['from_ID']),
            $rows,
        ));
        $t->same($fixture['expectedDiffTypes'], array_column($rows, 'diff_type'));
        $t->same('wp-import-base', $rows[0]['from_commit']);
        $t->same('wp-import-review', $rows[0]['to_commit']);
        $t->same('Imported resource hub', $rows[1]['to_post_title']);
    },
];
