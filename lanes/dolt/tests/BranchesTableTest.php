<?php

declare(strict_types=1);

use PortLibs\Dolt\BranchesTable;

$branchRows = static function (): array {
    return [
        [
            'name' => 'main',
            'hash' => 'main-hash',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:10:00',
            'message' => 'empty commit',
            'remote' => 'origin',
            'branch' => 'main',
            'dirty' => false,
        ],
        [
            'name' => 'branch1',
            'hash' => 'branch1-hash',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:00:00',
            'message' => 'checkpoint enginetest database mydb',
        ],
        [
            'name' => 'feature',
            'hash' => 'feature-hash',
            'author' => 'Test Author',
            'author_email' => 'author@example.com',
            'date' => '2026-05-22 10:20:00',
            'message' => 'author commit',
            'dirty' => true,
        ],
    ];
};

$rangeRows = static function (): array {
    $base = [
        'hash' => 'hash',
        'committer' => 'root',
        'email' => 'root@localhost',
        'date' => '2026-05-22 10:00:00',
        'message' => 'checkpoint enginetest database mydb',
    ];

    return array_map(static fn (string $name): array => ['name' => $name] + $base, [
        'branch1',
        'branch2',
        'branch3',
        'main',
    ]);
};

return [
    'dolt branches rows expose upstream local columns and latest metadata' => static function (TestRunner $t) use ($branchRows): void {
        $rows = (new BranchesTable())->rows($branchRows());

        $t->same(BranchesTable::BRANCHES_COLUMNS, array_keys($rows[0]));
        $t->same(['branch1', 'feature', 'main'], array_column($rows, 'name'));
        $t->same(['checkpoint enginetest database mydb', 'author commit', 'empty commit'], array_column($rows, 'latest_commit_message'));
        $t->same(['root', 'Test Author', 'root'], array_column($rows, 'latest_committer'));
        $t->same(['root@localhost', 'author@example.com', 'root@localhost'], array_column($rows, 'latest_committer_email'));
        $t->same([false, true, false], array_column($rows, 'dirty'));
        $t->same('origin', $rows[2]['remote']);
        $t->same('main', $rows[2]['branch']);
    },
    'dolt branches name filters follow upstream range index bounds' => static function (TestRunner $t) use ($rangeRows): void {
        $table = new BranchesTable();
        $branches = $rangeRows();

        $t->same(['branch1'], array_column($table->rows($branches, ['name' => 'branch1']), 'name'));
        $t->same(['branch2', 'branch3', 'main'], array_column($table->rows($branches, ['lowerBound' => 'branch1']), 'name'));
        $t->same(['branch2', 'branch3', 'main'], array_column($table->rows($branches, ['lowerBound' => 'branch2', 'lowerInclusive' => true]), 'name'));
        $t->same(['branch1'], array_column($table->rows($branches, ['upperBound' => 'branch2']), 'name'));
        $t->same(['branch1', 'branch2'], array_column($table->rows($branches, ['upperBound' => 'branch2', 'upperInclusive' => true]), 'name'));
        $t->same(['branch3'], array_column($table->rows($branches, [
            'lowerBound' => 'branch2',
            'upperBound' => 'branch3',
            'upperInclusive' => true,
        ]), 'name'));
        $t->same([], $table->rows($branches, [
            'lowerBound' => 'branch3',
            'upperBound' => 'branch1',
            'upperInclusive' => true,
        ]));
    },
    'dolt remote branches prefix names and omit local tracking columns' => static function (TestRunner $t): void {
        $rows = (new BranchesTable())->remoteRows([
            [
                'name' => 'origin/main',
                'hash' => 'remote-main',
                'committer' => 'root',
                'email' => 'root@localhost',
                'date' => '2026-05-22 10:00:00',
                'message' => 'remote main',
            ],
            [
                'name' => 'refs/remotes/upstream/review',
                'hash' => 'remote-review',
                'committer' => 'reviewer',
                'email' => 'reviewer@example.test',
                'date' => '2026-05-22 10:10:00',
                'message' => 'remote review',
            ],
        ]);

        $t->same(BranchesTable::REMOTE_BRANCHES_COLUMNS, array_keys($rows[0]));
        $t->same(['remotes/origin/main', 'remotes/upstream/review'], array_column($rows, 'name'));
        $t->true(!array_key_exists('remote', $rows[0]));
        $t->true(!array_key_exists('dirty', $rows[0]));
    },
    'active branch resolves current branch case insensitively and detached heads to null' => static function (TestRunner $t): void {
        $table = new BranchesTable();
        $branches = [
            ['name' => 'refs/heads/main'],
            ['name' => 'refs/heads/Feature'],
        ];

        $t->same('Feature', $table->activeBranch($branches, 'feature'));
        $t->same('main', $table->activeBranch($branches, 'MAIN'));
        $t->same(null, $table->activeBranch($branches, 'missing'));
        $t->same(null, $table->activeBranch($branches, 'main', true));
        $t->same(null, $table->activeBranch($branches, null));
    },
    'dolt branch activity rows include current branches and active session counts' => static function (TestRunner $t): void {
        $table = new BranchesTable();
        $rows = $table->activityRows(
            ['feature2', 'main', 'feature1'],
            [
                ['branch' => 'main', 'last_read' => '2026-05-22 10:05:00.000000', 'last_write' => null],
                ['branch' => 'feature1', 'last_read' => null, 'last_write' => '2026-05-22 10:06:00.000000'],
                ['branch' => 'HEAD', 'last_read' => '2026-05-22 10:07:00.000000', 'last_write' => null],
                ['branch' => 'deleted', 'last_read' => '2026-05-22 10:08:00.000000', 'last_write' => null],
            ],
            [
                'systemStartTime' => '2026-05-22 10:00:00.000000',
                'activeSessions' => ['feature2' => 3, 'main' => 2],
            ],
        );

        $t->same(BranchesTable::BRANCH_ACTIVITY_COLUMNS, array_keys($rows[0]));
        $t->same(['feature1', 'feature2', 'main'], array_column($rows, 'branch'));
        $t->same(['2026-05-22 10:06:00.000000', null, null], array_column($rows, 'last_write'));
        $t->same([0, 3, 2], array_column($rows, 'active_sessions'));
        $t->throws(RuntimeException::class, static fn () => $table->activityRows(['main'], [], [
            'trackingEnabled' => false,
            'systemStartTime' => '2026-05-22 10:00:00.000000',
        ]));
    },
    'wordpress branch review fixture surfaces migration branch metadata and activity' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-branch-review.php';
        $table = new BranchesTable();
        $branchRows = $table->rows($fixture['branches']);
        $activityRows = $table->activityRows($fixture['branches'], $fixture['activity'], [
            'systemStartTime' => $fixture['systemStartTime'],
            'activeSessions' => $fixture['activeSessions'],
        ]);
        $example = require __DIR__ . '/../examples/wordpress-branch-review.php';

        $t->same($fixture['expectedBranchRows'], $branchRows);
        $t->same($fixture['expectedActivityRows'], $activityRows);
        $t->same($fixture['expectedActiveBranch'], $table->activeBranch($fixture['branches'], $fixture['currentBranch']));
        $t->same($branchRows, $example['branches']);
        $t->same($activityRows, $example['activity']);
        $t->same($fixture['expectedReviewQueue'], $example['reviewQueue']);
    },
];
