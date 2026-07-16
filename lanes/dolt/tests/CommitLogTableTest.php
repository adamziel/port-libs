<?php

declare(strict_types=1);

use PortLibs\Dolt\CommitLogTable;

$commitLogGraph = static function (): array {
    return [
        [
            'commit_hash' => 'init',
            'committer' => 'billy bob',
            'email' => 'bigbillieb@fake.horse',
            'date' => '2026-05-22 10:00:00',
            'message' => 'Initialize data repository',
            'parents' => [],
        ],
        [
            'commit_hash' => 'main-1',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:01:00',
            'message' => 'creating table t',
            'parents' => ['init'],
        ],
        [
            'commit_hash' => 'main-2',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:02:00',
            'message' => 'inserting into t',
            'parents' => ['main-1'],
        ],
        [
            'commit_hash' => 'feature-1',
            'author' => 'John Doe',
            'author_email' => 'johndoe@example.com',
            'date' => '2026-05-22 10:03:00',
            'message' => 'feature row',
            'parents' => ['main-1'],
            'refs' => ['refs/heads/feature'],
            'signature' => 'verified feature signature',
        ],
        [
            'commit_hash' => 'merge-1',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:04:00',
            'message' => 'merge feature',
            'parents' => ['main-2', 'feature-1'],
            'refs' => ['refs/heads/main', 'refs/tags/v1'],
        ],
    ];
};

$revisionRangeGraph = static function (): array {
    return [
        [
            'commit_hash' => 'init',
            'committer' => 'billy bob',
            'email' => 'bigbillieb@fake.horse',
            'date' => '2026-05-22 10:00:00',
            'message' => 'Initialize data repository',
            'parents' => [],
        ],
        [
            'commit_hash' => 'main-1',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:01:00',
            'message' => 'commit 1 MAIN [1M]',
            'parents' => ['init'],
        ],
        [
            'commit_hash' => 'main-2',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:02:00',
            'message' => 'commit 2 MAIN [2M]',
            'parents' => ['main-1'],
            'refs' => ['refs/tags/tagM'],
        ],
        [
            'commit_hash' => 'branch-a-1',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:03:00',
            'message' => 'commit 1 BRANCHA [1A]',
            'parents' => ['main-2'],
        ],
        [
            'commit_hash' => 'branch-a-2',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:04:00',
            'message' => 'commit 2 BRANCHA [2A]',
            'parents' => ['branch-a-1'],
        ],
        [
            'commit_hash' => 'branch-b-1',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:05:00',
            'message' => 'commit 1 BRANCHB [1B]',
            'parents' => ['branch-a-2'],
            'refs' => ['refs/heads/branchB'],
        ],
        [
            'commit_hash' => 'branch-a-3',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:06:00',
            'message' => 'commit 3 BRANCHA [3A]',
            'parents' => ['branch-a-2'],
            'refs' => ['refs/heads/branchA'],
        ],
        [
            'commit_hash' => 'main-3',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:07:00',
            'message' => 'commit 3 AFTER [3M]',
            'parents' => ['main-2'],
            'refs' => ['refs/heads/main'],
        ],
    ];
};

$tableFilterGraph = static function (): array {
    return [
        [
            'commit_hash' => 'init',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:00:00',
            'message' => 'Initialize data repository',
            'parents' => [],
            'tableHashes' => [],
        ],
        [
            'commit_hash' => 'main-1',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:01:00',
            'message' => 'created table test [1M]',
            'parents' => ['init'],
            'tableHashes' => ['test' => 'test-v1'],
        ],
        [
            'commit_hash' => 'main-2',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:02:00',
            'message' => 'created table test2 [2M]',
            'parents' => ['main-1'],
            'tableHashes' => ['test' => 'test-v1', 'test2' => 'test2-v1'],
        ],
        [
            'commit_hash' => 'branch-1',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:03:00',
            'message' => 'inserted 0 into test [1TB]',
            'parents' => ['main-2'],
            'tableHashes' => ['test' => 'test-v2', 'test2' => 'test2-v1'],
        ],
        [
            'commit_hash' => 'branch-2',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:04:00',
            'message' => 'created table test3 [2TB]',
            'parents' => ['branch-1'],
            'refs' => ['refs/heads/test-branch'],
            'tableHashes' => ['test' => 'test-v2', 'test2' => 'test2-v1', 'test3' => 'test3-v1'],
        ],
        [
            'commit_hash' => 'main-3',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:05:00',
            'message' => 'inserted 1 into test [3M]',
            'parents' => ['main-2'],
            'tableHashes' => ['test' => 'test-v3', 'test2' => 'test2-v1'],
        ],
        [
            'commit_hash' => 'main-4',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:06:00',
            'message' => 'merged test-branch [4M]',
            'parents' => ['main-3', 'branch-2'],
            'tableHashes' => ['test' => 'test-v4', 'test2' => 'test2-v1', 'test3' => 'test3-v1'],
        ],
        [
            'commit_hash' => 'main-5',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:07:00',
            'message' => 'dropped table test3 [5M]',
            'parents' => ['main-4'],
            'tableHashes' => ['test' => 'test-v4', 'test2' => 'test2-v1'],
        ],
        [
            'commit_hash' => 'main-6',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:08:00',
            'message' => 'inserted 2 into test [6M]',
            'parents' => ['main-5'],
            'refs' => ['refs/heads/main'],
            'tableHashes' => ['test' => 'test-v5', 'test2' => 'test2-v1'],
        ],
    ];
};

$allBranchesGraph = static function (): array {
    return [
        [
            'commit_hash' => 'init',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:00:00',
            'message' => 'Initialize data repository',
            'parents' => [],
            'refs' => ['refs/heads/main'],
            'changedTables' => [],
        ],
        [
            'commit_hash' => 'br1',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:01:00',
            'message' => 'commit 1 br1',
            'parents' => ['init'],
            'refs' => ['refs/heads/br1'],
            'changedTables' => ['test'],
        ],
        [
            'commit_hash' => 'br2',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:02:00',
            'message' => 'commit 1 br2',
            'parents' => ['init'],
            'refs' => ['refs/heads/br2'],
            'changedTables' => [],
        ],
        [
            'commit_hash' => 'remote',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:03:00',
            'message' => 'commit 1 remote',
            'parents' => ['init'],
            'refs' => ['refs/remotes/origin/remote'],
            'changedTables' => ['test'],
        ],
        [
            'commit_hash' => 'scratch',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:04:00',
            'message' => 'unreferenced scratch checkpoint',
            'parents' => ['init'],
            'changedTables' => ['test'],
        ],
    ];
};

$denseMergeFanInGraph = static function (): array {
    $commit = static fn (
        string $hash,
        string $date,
        string $message,
        array $parents = [],
        array $refs = [],
    ): array => [
        'commit_hash' => $hash,
        'committer' => 'Bats Tests',
        'email' => 'bats@email.fake',
        'date' => $date,
        'message' => $message,
        'parents' => $parents,
        'refs' => $refs,
    ];

    return [
        $commit('init', '2026-05-22 21:03:42', 'Initialize data repository'),
        $commit('main-1', '2026-05-22 21:03:42', 'commit 1 MAIN', ['init']),
        $commit('branch-a-1', '2026-05-22 21:03:43', 'commit 1 BRANCHA', ['main-1'], ['refs/heads/branchA']),
        $commit('branch-b-1', '2026-05-22 21:03:44', 'commit 1 branchB', ['main-1'], ['refs/heads/branchB']),
        $commit('branch-c-1', '2026-05-22 21:03:44', 'commit 1 branchC', ['main-1'], ['refs/heads/branchC']),
        $commit('branch-d-1', '2026-05-22 21:03:45', 'commit 1 branchD', ['main-1'], ['refs/heads/branchD']),
        $commit('main-2', '2026-05-22 21:03:47', 'insert into testtable', ['main-1']),
        $commit('merge-a', '2026-05-22 21:03:47', 'Merge branchA into main', ['main-2', 'branch-a-1']),
        $commit('merge-b', '2026-05-22 21:03:47', 'Merge branchB into main', ['merge-a', 'branch-b-1']),
        $commit('merge-c', '2026-05-22 21:03:47', 'Merge branchC into main', ['merge-b', 'branch-c-1']),
        $commit('merge-d', '2026-05-22 21:03:48', 'Merge branchD into main', ['merge-c', 'branch-d-1'], ['refs/heads/main']),
    ];
};

return [
    'dolt log rows keep fixed columns and null opt-in fields by default' => static function (TestRunner $t) use ($commitLogGraph): void {
        $rows = (new CommitLogTable())->logRows($commitLogGraph(), ['headHash' => 'merge-1']);

        $t->same(CommitLogTable::LOG_COLUMNS, array_keys($rows[0]));
        $t->same(['merge feature', 'feature row', 'inserting into t', 'creating table t', 'Initialize data repository'], array_column($rows, 'message'));
        $t->same(4, $rows[0]['commit_order']);
        $t->same(null, $rows[0]['parents']);
        $t->same(null, $rows[0]['signature']);
        $t->same('HEAD -> main, tag: v1', $rows[0]['refs']);
        $t->same('feature', $rows[1]['refs']);
    },
    'dolt log projection controls parents and signature columns' => static function (TestRunner $t) use ($commitLogGraph): void {
        $table = new CommitLogTable();
        $projected = $table->logRows($commitLogGraph(), [
            'headHash' => 'merge-1',
            'projectedColumns' => ['commit_hash', 'parents', 'signature'],
        ]);
        $fullDecorated = $table->logRows($commitLogGraph(), [
            'headHash' => 'merge-1',
            'showParents' => true,
            'showSignature' => true,
            'decorate' => 'full',
            'limit' => 2,
        ]);
        $noDecorations = $table->logRows($commitLogGraph(), [
            'headHash' => 'merge-1',
            'showParents' => true,
            'decorate' => 'no',
            'limit' => 1,
        ]);

        $t->same('main-2, feature-1', $projected[0]['parents']);
        $t->same('', $projected[0]['signature']);
        $t->same('verified feature signature', $projected[1]['signature']);
        $t->same('HEAD -> refs/heads/main, tag: refs/tags/v1', $fullDecorated[0]['refs']);
        $t->same('main-2, feature-1', $fullDecorated[0]['parents']);
        $t->same('', $noDecorations[0]['refs']);
    },
    'dolt log number aliases follow upstream n limit boundaries' => static function (TestRunner $t) use ($commitLogGraph, $tableFilterGraph): void {
        $table = new CommitLogTable();
        $commits = $commitLogGraph();
        $messages = static fn (array $rows): array => array_column($rows, 'message');

        $numberRows = $table->logRows($commits, [
            'headHash' => 'merge-1',
            'number' => 1,
        ]);
        $shortRows = $table->logRows($commits, [
            'headHash' => 'merge-1',
            'n' => 2,
        ]);
        $tableRows = $table->logRows($tableFilterGraph(), [
            'headHash' => 'main-6',
            'tableNames' => ['test'],
            'n' => 1,
        ]);

        $t->same(['merge feature'], $messages($numberRows));
        $t->same(['merge feature', 'feature row'], $messages($shortRows));
        $t->same([], $table->logRows($commits, ['headHash' => 'merge-1', 'n' => 0]));
        $t->same([], $table->commitsRows($commits, 0));
        $t->same('merge-1 (HEAD -> main, tag: v1) merge feature', $table->renderLog($commits, [
            'headHash' => 'merge-1',
            'oneline' => true,
            'number' => 1,
        ]));
        $t->same(['inserted 2 into test [6M]'], $messages($tableRows));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commits, [
            'headHash' => 'merge-1',
            'number' => '1',
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commits, [
            'headHash' => 'merge-1',
            'limit' => 1,
            'number' => 2,
        ]));
    },
    'dolt commits rows expose all branch commits without log-only columns' => static function (TestRunner $t) use ($commitLogGraph): void {
        $rows = (new CommitLogTable())->commitsRows($commitLogGraph());

        $t->same(CommitLogTable::COMMITS_COLUMNS, array_keys($rows[0]));
        $t->same(['merge feature', 'feature row', 'inserting into t'], array_slice(array_column($rows, 'message'), 0, 3));
        $t->same('John Doe', $rows[1]['committer']);
        $t->same('johndoe@example.com', $rows[1]['email']);
        $t->true(!array_key_exists('parents', $rows[0]));
        $t->true(!array_key_exists('refs', $rows[0]));
    },
    'dolt log can restrict rows to a selected head ancestry' => static function (TestRunner $t) use ($commitLogGraph): void {
        $rows = (new CommitLogTable())->logRows($commitLogGraph(), [
            'headHash' => 'main-2',
            'showParents' => true,
        ]);

        $t->same(['inserting into t', 'creating table t', 'Initialize data repository'], array_column($rows, 'message'));
        $t->same('main-1', $rows[0]['parents']);
        $t->same('', $rows[0]['refs']);
    },
    'dolt log filters merge commits with merges and min parents options' => static function (TestRunner $t) use ($commitLogGraph): void {
        $table = new CommitLogTable();
        $commits = $commitLogGraph();

        $merges = $table->logRows($commits, [
            'headHash' => 'merge-1',
            'merges' => true,
        ]);
        $minTwoParents = $table->logRows($commits, [
            'headHash' => 'merge-1',
            'minParents' => 2,
        ]);
        $minOneParent = $table->logRows($commits, [
            'headHash' => 'merge-1',
            'minParents' => 1,
        ]);
        $mergedOverride = $table->logRows($commits, [
            'headHash' => 'merge-1',
            'minParents' => 1,
            'merges' => true,
            'showParents' => true,
        ]);

        $t->same(['merge feature'], array_column($merges, 'message'));
        $t->same(['merge feature'], array_column($minTwoParents, 'message'));
        $t->same(['merge feature', 'feature row', 'inserting into t', 'creating table t'], array_column($minOneParent, 'message'));
        $t->same(['merge feature'], array_column($mergedOverride, 'message'));
        $t->same('main-2, feature-1', $mergedOverride[0]['parents']);
        $t->same([], $table->logRows($commits, ['headHash' => 'merge-1', 'min_parents' => 5]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commits, [
            'headHash' => 'merge-1',
            'minParents' => -1,
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commits, [
            'headHash' => 'merge-1',
            'minParents' => null,
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commits, [
            'headHash' => 'merge-1',
            'merges' => 'true',
        ]));
    },
    'dolt log oneline rendering matches upstream compact boundaries' => static function (TestRunner $t) use ($commitLogGraph): void {
        $table = new CommitLogTable();
        $commits = $commitLogGraph();
        $commits[4]['message'] = "merge feature\nwith reviewer note";

        $output = $table->renderLog($commits, [
            'headHash' => 'merge-1',
            'oneline' => true,
            'decorate' => 'full',
        ]);
        $lines = explode("\n", $output);
        $withParents = $table->renderLog($commits, [
            'headHash' => 'merge-1',
            'oneline' => true,
            'parents' => true,
            'limit' => 1,
        ]);

        $t->same(5, count($lines));
        $t->same('merge-1 (HEAD -> refs/heads/main, tag: refs/tags/v1) merge feature with reviewer note', $lines[0]);
        $t->same('feature-1 (refs/heads/feature) feature row', $lines[1]);
        $t->true(!str_contains($output, 'Author:'));
        $t->true(!str_contains($output, 'Date:'));
        $t->true(!str_contains($output, 'commit '));
        $t->same('merge-1 main-2 feature-1 (HEAD -> main, tag: v1) merge feature with reviewer note', $withParents);
    },
    'dolt log stat rendering matches upstream modified add delete and merge boundaries' => static function (TestRunner $t) use ($commitLogGraph): void {
        $table = new CommitLogTable();
        $diffStats = [
            'main-1' => [
                ['table' => 'test', 'operation' => 'added'],
            ],
            'main-2' => [
                ['table' => 'test', 'operation' => 'modified', 'adds' => 1, 'modifications' => 0, 'deletes' => 0],
            ],
            'merge-1' => [
                ['table' => 'test', 'operation' => 'modified', 'adds' => 99],
            ],
        ];

        $onelineStat = $table->renderLog($commitLogGraph(), [
            'headHash' => 'main-2',
            'oneline' => true,
            'stat' => true,
            'diffStatsByCommit' => $diffStats,
        ]);
        $lines = explode("\n", $onelineStat);
        $mergeOutput = $table->renderLog($commitLogGraph(), [
            'headHash' => 'merge-1',
            'stat' => true,
            'limit' => 1,
            'diffStatsByCommit' => $diffStats,
        ]);

        $t->same(6, count($lines));
        $t->same('main-2 inserting into t', $lines[0]);
        $t->same(' test | 1 +', $lines[1]);
        $t->same(' 1 tables changed, 1 rows added(+), 0 rows modified(*), 0 rows deleted(-)', $lines[2]);
        $t->same('main-1 creating table t', $lines[3]);
        $t->same(' test added', $lines[4]);
        $t->same('init Initialize data repository', $lines[5]);
        $t->contains('merge feature', $mergeOutput);
        $t->true(!str_contains($mergeOutput, '99 rows added'));
        $t->true(!str_contains($mergeOutput, 'test | 99'));
        $t->throws(InvalidArgumentException::class, static fn () => $table->renderLog($commitLogGraph(), [
            'headHash' => 'main-2',
            'stat' => true,
            'diffStatsByCommit' => ['main-2' => [['table' => 'test', 'operation' => 'mystery']]],
        ]));
    },
    'dolt log graph rendering matches upstream linear and merge boundaries' => static function (TestRunner $t) use ($commitLogGraph): void {
        $table = new CommitLogTable();
        $linearOutput = $table->renderLog($commitLogGraph(), [
            'headHash' => 'main-2',
            'graph' => true,
        ]);
        $linearLines = explode("\n", $linearOutput);
        $mergeGraph = [
            [
                'commit_hash' => 'init',
                'committer' => 'root',
                'email' => 'root@localhost',
                'date' => '2026-05-22 10:00:00',
                'message' => 'Initialize data repository',
                'parents' => [],
            ],
            [
                'commit_hash' => 'main-1',
                'committer' => 'root',
                'email' => 'root@localhost',
                'date' => '2026-05-22 10:01:00',
                'message' => 'commit 1 MAIN',
                'parents' => ['init'],
            ],
            [
                'commit_hash' => 'branchA-1',
                'committer' => 'root',
                'email' => 'root@localhost',
                'date' => '2026-05-22 10:02:00',
                'message' => 'commit 1 BRANCHA',
                'parents' => ['main-1'],
            ],
            [
                'commit_hash' => 'main-2',
                'committer' => 'root',
                'email' => 'root@localhost',
                'date' => '2026-05-22 10:03:00',
                'message' => 'commit 2 MAIN',
                'parents' => ['main-1'],
            ],
            [
                'commit_hash' => 'merge-1',
                'committer' => 'root',
                'email' => 'root@localhost',
                'date' => '2026-05-22 10:04:00',
                'message' => 'Merge branchA into main',
                'parents' => ['main-2', 'branchA-1'],
                'refs' => ['refs/heads/main'],
            ],
        ];
        $mergeOutput = $table->renderLog($mergeGraph, [
            'headHash' => 'merge-1',
            'graph' => true,
        ]);
        $mergeLines = explode("\n", $mergeOutput);
        $mergeOneline = $table->renderLog($mergeGraph, [
            'headHash' => 'merge-1',
            'graph' => true,
            'oneline' => true,
        ]);
        $mergeOnelineLines = explode("\n", $mergeOneline);

        $t->same('* commit main-2', $linearLines[0]);
        $t->same('| Author: root <root@localhost>', $linearLines[1]);
        $t->same('| Date: 2026-05-22 10:02:00', $linearLines[2]);
        $t->same('|', $linearLines[3]);
        $t->contains('inserting into t', $linearLines[4]);
        $t->same('* commit main-1', $linearLines[6]);
        $t->same('*   commit merge-1(HEAD -> main) ', $mergeLines[0]);
        $t->same('|\\  Merge: main-2 branchA-1', $mergeLines[1]);
        $t->same('* | commit main-2', $mergeLines[7]);
        $t->same('| * commit branchA-1', $mergeLines[13]);
        $t->same('|/', $mergeLines[18]);
        $t->same('* commit merge-1 (HEAD -> main) Merge branchA into main', $mergeOnelineLines[0]);
        $t->same('*\\ commit main-2  commit 2 MAIN', $mergeOnelineLines[1]);
        $t->same('| * commit branchA-1  commit 1 BRANCHA', $mergeOnelineLines[2]);
        $t->same('|/', $mergeOnelineLines[3]);
        $t->throws(InvalidArgumentException::class, static fn () => $table->renderLog($mergeGraph, [
            'headHash' => 'merge-1',
            'graph' => 'true',
        ]));
    },
    'dolt log graph rendering maps upstream dense branch fan in' => static function (TestRunner $t) use ($denseMergeFanInGraph): void {
        $output = (new CommitLogTable())->renderLog($denseMergeFanInGraph(), [
            'headHash' => 'merge-d',
            'graph' => true,
        ]);

        $t->same([
            '*   commit merge-d(HEAD -> main) ',
            '|\\  Merge: merge-c branch-d-1',
            '| | Author: Bats Tests <bats@email.fake>',
            '| | Date: 2026-05-22 21:03:48',
            '| |',
            "| | \tMerge branchD into main",
            '| |',
            '* |   commit merge-c',
            '|\\|   Merge: merge-b branch-c-1',
            '| \\   Author: Bats Tests <bats@email.fake>',
            '| |\\  Date: 2026-05-22 21:03:47',
            '| | |',
            "| | | \tMerge branchC into main",
            '| | |',
            '* | |   commit merge-b',
            '|\\| |   Merge: merge-a branch-b-1',
            '| \\ |   Author: Bats Tests <bats@email.fake>',
            '| |\\|   Date: 2026-05-22 21:03:47',
            '| | \\',
            "| | |\\  \tMerge branchB into main",
            '| | | |',
            '* | | | commit merge-a',
            '|\\| | | Merge: main-2 branch-a-1',
            '| \\ | | Author: Bats Tests <bats@email.fake>',
            '| |\\| | Date: 2026-05-22 21:03:47',
            '| | \\ |',
            "| | |\\| \tMerge branchA into main",
            '| | | \\',
            '* | | |\\  commit main-2',
            '| | | | | Author: Bats Tests <bats@email.fake>',
            '| | | | | Date: 2026-05-22 21:03:47',
            '| | | | |',
            "| | | | | \tinsert into testtable",
            '| | | | |',
            '| * | | | commit branch-d-1(branchD) ',
            '| | | | | Author: Bats Tests <bats@email.fake>',
            '| | | | | Date: 2026-05-22 21:03:45',
            '| | | | |',
            "| | | | | \tcommit 1 branchD",
            '| | | | |',
            '| | * | | commit branch-c-1(branchC) ',
            '| | | | | Author: Bats Tests <bats@email.fake>',
            '| | | | | Date: 2026-05-22 21:03:44',
            '| | | | |',
            "| | | | | \tcommit 1 branchC",
            '| | | | |',
            '| | | * | commit branch-b-1(branchB) ',
            '| | | | | Author: Bats Tests <bats@email.fake>',
            '| | | | | Date: 2026-05-22 21:03:44',
            '| | | | |',
            "| | | | | \tcommit 1 branchB",
            '| | | | |',
            '| | | | * commit branch-a-1(branchA) ',
            '| | |/ /  Author: Bats Tests <bats@email.fake>',
            '| | / /   Date: 2026-05-22 21:03:43',
            '| |/ /',
            "| / /     \tcommit 1 BRANCHA",
            '|/ /',
            '*-- commit main-1',
            '|   Author: Bats Tests <bats@email.fake>',
            '|   Date: 2026-05-22 21:03:42',
            '|',
            "|   \tcommit 1 MAIN",
            '|',
            '* commit init',
            '  Author: Bats Tests <bats@email.fake>',
            '  Date: 2026-05-22 21:03:42',
            '',
            "\tInitialize data repository",
        ], explode("\n", $output));
    },
    'dolt log graph oneline rendering maps upstream dense branch fan in spacing' => static function (TestRunner $t) use ($denseMergeFanInGraph): void {
        $output = (new CommitLogTable())->renderLog($denseMergeFanInGraph(), [
            'headHash' => 'merge-d',
            'graph' => true,
            'oneline' => true,
            'decorate' => 'short',
        ]);
        $expected = <<<'GRAPH'
* commit merge-d (HEAD -> main) Merge branchD into main
*\ commit merge-c  Merge branchC into main
*\| commit merge-b  Merge branchB into main
*\\ commit merge-a  Merge branchA into main
*\\\ commit main-2  insert into testtable
| *\| commit branch-d-1  (branchD) commit 1 branchD
| |\* commit branch-c-1  (branchC) commit 1 branchC
| | \\
| | |\* commit branch-b-1  (branchB) commit 1 branchB
| | | \
| | | |\
| | | | * commit branch-a-1  (branchA) commit 1 BRANCHA
| | | |/
| | | /
| | |/
| | /
| |/
| /
|/
* commit main-1  commit 1 MAIN
* commit init  Initialize data repository
GRAPH;

        $t->same(explode("\n", rtrim($expected, "\n")), explode("\n", $output));
    },
    'dolt log decorate auto follows upstream tty boundary in CLI rendering' => static function (TestRunner $t) use ($commitLogGraph): void {
        $table = new CommitLogTable();
        $captured = $table->renderLog($commitLogGraph(), [
            'headHash' => 'merge-1',
            'oneline' => true,
            'decorate' => 'auto',
            'stdoutIsTty' => false,
            'limit' => 1,
        ]);
        $tty = $table->renderLog($commitLogGraph(), [
            'headHash' => 'merge-1',
            'oneline' => true,
            'decorate' => 'auto',
            'stdoutIsTty' => true,
            'limit' => 1,
        ]);

        $t->same('merge-1 merge feature', $captured);
        $t->same('merge-1 (HEAD -> main, tag: v1) merge feature', $tty);
        $t->throws(InvalidArgumentException::class, static fn () => $table->renderLog($commitLogGraph(), [
            'headHash' => 'merge-1',
            'decorate' => 'auto',
            'stdoutIsTty' => 'yes',
        ]));
    },
    'dolt log revision ranges map two-dot caret and not exclusions' => static function (TestRunner $t) use ($revisionRangeGraph): void {
        $table = new CommitLogTable();
        $commits = $revisionRangeGraph();
        $messages = static fn (array $rows): array => array_column($rows, 'message');

        $twoDot = $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['main..branchA'],
        ]);
        $caret = $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['^main', 'branchA'],
        ]);
        $not = $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['branchA'],
            'notRevisionSpecs' => ['main'],
        ]);
        $reverse = $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['branchA..main'],
        ]);
        $selfRange = $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['main..main'],
        ]);
        $parentRange = $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['^main~', 'main'],
        ]);

        $expectedBranchOnly = ['commit 3 BRANCHA [3A]', 'commit 2 BRANCHA [2A]', 'commit 1 BRANCHA [1A]'];
        $t->same($expectedBranchOnly, $messages($twoDot));
        $t->same($expectedBranchOnly, $messages($caret));
        $t->same($expectedBranchOnly, $messages($not));
        $t->same('HEAD -> branchA', $twoDot[0]['refs']);
        $t->same(['commit 3 AFTER [3M]'], $messages($reverse));
        $t->same([], $selfRange);
        $t->same(['commit 3 AFTER [3M]'], $messages($parentRange));
    },
    'dolt log revision ranges map three-dot and multiple revision unions' => static function (TestRunner $t) use ($revisionRangeGraph): void {
        $table = new CommitLogTable();
        $commits = $revisionRangeGraph();
        $messages = static fn (array $rows): array => array_column($rows, 'message');

        $threeDot = $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['main...branchA'],
        ]);
        $twoBranches = $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['branchB', 'branchA'],
        ]);
        $mainAndBranch = $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['main', 'branchA'],
        ]);
        $excludedBranch = $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['branchB', 'main', '^branchA'],
        ]);
        $tagRange = $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['tagM..branchB'],
        ]);
        $headRange = $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['HEAD..branchB'],
        ]);

        $t->same([
            'commit 3 BRANCHA [3A]',
            'commit 2 BRANCHA [2A]',
            'commit 3 AFTER [3M]',
            'commit 1 BRANCHA [1A]',
        ], $messages($threeDot));
        $t->same([
            'commit 3 BRANCHA [3A]',
            'commit 1 BRANCHB [1B]',
            'commit 2 BRANCHA [2A]',
            'commit 1 BRANCHA [1A]',
            'commit 2 MAIN [2M]',
            'commit 1 MAIN [1M]',
            'Initialize data repository',
        ], $messages($twoBranches));
        $t->same([
            'commit 3 BRANCHA [3A]',
            'commit 2 BRANCHA [2A]',
            'commit 3 AFTER [3M]',
            'commit 1 BRANCHA [1A]',
            'commit 2 MAIN [2M]',
            'commit 1 MAIN [1M]',
            'Initialize data repository',
        ], $messages($mainAndBranch));
        $t->same(['commit 1 BRANCHB [1B]', 'commit 3 AFTER [3M]'], $messages($excludedBranch));
        $t->same(['commit 1 BRANCHB [1B]', 'commit 2 BRANCHA [2A]', 'commit 1 BRANCHA [1A]'], $messages($tagRange));
        $t->same($messages($tagRange), $messages($headRange));
    },
    'dolt log revision range validation follows upstream argument boundaries' => static function (TestRunner $t) use ($revisionRangeGraph): void {
        $table = new CommitLogTable();
        $commits = $revisionRangeGraph();

        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['main..branchA', 'main'],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['main..branchA'],
            'notRevisionSpecs' => ['main'],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['^main..branchA'],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['main'],
            'notRevisionSpecs' => ['^branchA'],
        ]));
        $t->throws(RuntimeException::class, static fn () => $table->logRows($commits, [
            'headHash' => 'main-3',
            'revisionSpecs' => ['missing'],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commits, [
            'revisionSpecs' => ['^main'],
        ]));
    },
    'dolt log table filters use root-value style table hash changes' => static function (TestRunner $t) use ($tableFilterGraph): void {
        $table = new CommitLogTable();
        $commits = $tableFilterGraph();
        $messages = static fn (array $rows): array => array_column($rows, 'message');

        $testRows = $table->logRows($commits, [
            'headHash' => 'main-6',
            'tableNames' => ['test'],
        ]);
        $test2Rows = $table->logRows($commits, [
            'headHash' => 'main-6',
            'tableNames' => ['test2'],
        ]);
        $test3Rows = $table->logRows($commits, [
            'headHash' => 'main-6',
            'tableNames' => ['test3'],
        ]);
        $multiRows = $table->logRows($commits, [
            'headHash' => 'main-6',
            'tableNames' => ['test', 'test2'],
        ]);
        $branchRows = $table->logRows($commits, [
            'headHash' => 'main-6',
            'revisionSpecs' => ['test-branch'],
            'tableNames' => ['test'],
        ]);

        $t->same([
            'inserted 2 into test [6M]',
            'merged test-branch [4M]',
            'inserted 1 into test [3M]',
            'inserted 0 into test [1TB]',
            'created table test [1M]',
        ], $messages($testRows));
        $t->same(['created table test2 [2M]'], $messages($test2Rows));
        $t->same(['dropped table test3 [5M]', 'created table test3 [2TB]'], $messages($test3Rows));
        $t->same([
            'inserted 2 into test [6M]',
            'merged test-branch [4M]',
            'inserted 1 into test [3M]',
            'inserted 0 into test [1TB]',
            'created table test2 [2M]',
            'created table test [1M]',
        ], $messages($multiRows));
        $t->same(['inserted 0 into test [1TB]', 'created table test [1M]'], $messages($branchRows));
    },
    'dolt log table filters support changed table metadata and upstream boundaries' => static function (TestRunner $t): void {
        $commits = [
            [
                'commit_hash' => 'init',
                'committer' => 'root',
                'email' => 'root@localhost',
                'date' => '2026-05-22 10:00:00',
                'message' => 'Initialize data repository',
                'parents' => [],
            ],
            [
                'commit_hash' => 'create-test',
                'committer' => 'root',
                'email' => 'root@localhost',
                'date' => '2026-05-22 10:01:00',
                'message' => 'create test table',
                'parents' => ['init'],
                'changedTables' => ['test'],
            ],
            [
                'commit_hash' => 'empty',
                'committer' => 'root',
                'email' => 'root@localhost',
                'date' => '2026-05-22 10:02:00',
                'message' => 'empty checkpoint',
                'parents' => ['create-test'],
                'changedTables' => [],
            ],
            [
                'commit_hash' => 'create-other',
                'committer' => 'root',
                'email' => 'root@localhost',
                'date' => '2026-05-22 10:03:00',
                'message' => 'create unrelated table',
                'parents' => ['empty'],
                'changedTables' => ['other'],
            ],
        ];
        $table = new CommitLogTable();

        $rows = $table->logRows($commits, ['headHash' => 'create-other', 'tables' => ['test']]);

        $t->same(['create test table'], array_column($rows, 'message'));
        $t->same([], $table->logRows($commits, ['headHash' => 'create-other', 'tableNames' => ['missing']]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commits, [
            'headHash' => 'create-other',
            'tableNames' => [],
        ]));
        $t->throws(RuntimeException::class, static fn () => $table->logRows([
            $commits[0],
            [
                'commit_hash' => 'no-metadata',
                'committer' => 'root',
                'email' => 'root@localhost',
                'date' => '2026-05-22 10:01:00',
                'message' => 'missing changed-table metadata',
                'parents' => ['init'],
            ],
        ], ['headHash' => 'no-metadata', 'tableNames' => ['test']]));
    },
    'dolt log all traverses branch heads and table filters like upstream CLI all' => static function (TestRunner $t) use ($allBranchesGraph): void {
        $table = new CommitLogTable();
        $commits = $allBranchesGraph();

        $allRows = $table->logRows($commits, [
            'headHash' => 'init',
            'includeAll' => true,
        ]);
        $allAliasRows = $table->logRows($commits, [
            'headHash' => 'init',
            'all' => true,
        ]);
        $tableRows = $table->logRows($commits, [
            'headHash' => 'init',
            'includeAll' => true,
            'tableNames' => ['test'],
        ]);
        $defaultRows = $table->logRows($commits, [
            'headHash' => 'init',
        ]);

        $t->same(['commit 1 remote', 'commit 1 br2', 'commit 1 br1', 'Initialize data repository'], array_column($allRows, 'message'));
        $t->same(array_column($allRows, 'message'), array_column($allAliasRows, 'message'));
        $t->same(['commit 1 remote', 'commit 1 br1'], array_column($tableRows, 'message'));
        $t->same(['Initialize data repository'], array_column($defaultRows, 'message'));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commits, [
            'headHash' => 'init',
            'includeAll' => 'true',
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commits, [
            'headHash' => 'init',
            'includeAll' => false,
            'all' => true,
        ]));
    },
    'dolt log validates duplicate commits and broken graphs' => static function (TestRunner $t) use ($commitLogGraph): void {
        $table = new CommitLogTable();
        $duplicate = $commitLogGraph();
        $duplicate[] = $duplicate[0];
        $missingParent = $commitLogGraph();
        $missingParent[1]['parents'] = ['missing'];
        $cycle = [
            [
                'commit_hash' => 'a',
                'committer' => 'root',
                'email' => 'root@localhost',
                'date' => '2026-05-22 10:00:00',
                'message' => 'a',
                'parents' => ['b'],
            ],
            [
                'commit_hash' => 'b',
                'committer' => 'root',
                'email' => 'root@localhost',
                'date' => '2026-05-22 10:01:00',
                'message' => 'b',
                'parents' => ['a'],
            ],
        ];

        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($duplicate));
        $t->throws(RuntimeException::class, static fn () => $table->logRows($missingParent));
        $t->throws(RuntimeException::class, static fn () => $table->logRows($cycle));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commitLogGraph(), ['decorate' => 'invalid']));
        $t->throws(InvalidArgumentException::class, static fn () => $table->logRows($commitLogGraph(), ['limit' => -1]));
    },
    'wordpress commit log fixture surfaces import branch refs and merge parents' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-commit-log-review.php';
        $table = new CommitLogTable();
        $rows = $table->logRows($fixture['commits'], [
            'headHash' => $fixture['headHash'],
            'showParents' => true,
            'decorate' => 'short',
        ]);
        $mainlineRows = $table->logRows($fixture['commits'], [
            'headHash' => 'wp-review-main',
            'showParents' => true,
        ]);
        $reviewRangeRows = $table->logRows($fixture['commits'], [
            'headHash' => $fixture['headHash'],
            'revisionSpecs' => ['wp-import-base..wp-merge-media'],
            'showParents' => true,
        ]);
        $mediaPromotionRows = $table->logRows($fixture['commits'], [
            'headHash' => $fixture['headHash'],
            'revisionSpecs' => ['wp-review-main..wp-merge-media'],
            'showParents' => true,
        ]);
        $postTableRows = $table->logRows($fixture['commits'], [
            'headHash' => $fixture['headHash'],
            'tableNames' => ['wp_posts'],
        ]);
        $postMetaTableRows = $table->logRows($fixture['commits'], [
            'headHash' => $fixture['headHash'],
            'tableNames' => ['wp_postmeta'],
        ]);
        $mergeOnlyRows = $table->logRows($fixture['commits'], [
            'headHash' => $fixture['headHash'],
            'merges' => true,
            'showParents' => true,
        ]);
        $checkpointRows = $table->logRows($fixture['commits'], [
            'headHash' => $fixture['headHash'],
            'minParents' => 1,
        ]);
        $allBranchRows = $table->logRows($fixture['commits'], [
            'headHash' => $fixture['headHash'],
            'includeAll' => true,
        ]);
        $allBranchPostRows = $table->logRows($fixture['commits'], [
            'headHash' => $fixture['headHash'],
            'includeAll' => true,
            'tableNames' => ['wp_posts'],
        ]);
        $example = require __DIR__ . '/../examples/wordpress-commit-log-review.php';

        $t->same($fixture['expectedLogMessages'], array_column($rows, 'message'));
        $t->same($fixture['expectedHeadRefs'], $rows[0]['refs']);
        $t->same($fixture['expectedMergeParents'], $rows[0]['parents']);
        $t->same($fixture['expectedLatestReviewMessages'], array_column($example['latestReviewLog'], 'message'));
        $t->same($fixture['expectedMainlineMessages'], array_column($mainlineRows, 'message'));
        $t->same($fixture['expectedReviewRangeMessages'], array_column($reviewRangeRows, 'message'));
        $t->same($fixture['expectedMediaPromotionMessages'], array_column($mediaPromotionRows, 'message'));
        $t->same($fixture['expectedPostTableLogMessages'], array_column($postTableRows, 'message'));
        $t->same($fixture['expectedPostMetaTableLogMessages'], array_column($postMetaTableRows, 'message'));
        $t->same($fixture['expectedMergeOnlyMessages'], array_column($mergeOnlyRows, 'message'));
        $t->same($fixture['expectedMergeParents'], $mergeOnlyRows[0]['parents']);
        $t->same($fixture['expectedCheckpointMessages'], array_column($checkpointRows, 'message'));
        $t->same($fixture['expectedAllBranchMessages'], array_column($allBranchRows, 'message'));
        $t->same($fixture['expectedAllBranchPostMessages'], array_column($allBranchPostRows, 'message'));
        $t->same($rows, $example['log']);
        $t->same($fixture['expectedLatestReviewMessages'], array_column($example['latestReviewLog'], 'message'));
        $t->same($reviewRangeRows, $example['reviewRange']);
        $t->same($mediaPromotionRows, $example['mediaPromotionRange']);
        $t->same($postTableRows, $example['postTableLog']);
        $t->same($postMetaTableRows, $example['postMetaTableLog']);
        $t->same($mergeOnlyRows, $example['mergeOnlyLog']);
        $t->same($checkpointRows, $example['checkpointLog']);
        $t->same($allBranchRows, $example['allBranchLog']);
        $t->same($allBranchPostRows, $example['allBranchPostTableLog']);
        $t->same(CommitLogTable::COMMITS_COLUMNS, array_keys($example['commits'][0]));
        $t->same($fixture['expectedCliOnelineStatLines'], explode("\n", $example['cliOnelineStat']));
        $t->same($fixture['expectedCliGraphOnelineLines'], explode("\n", $example['cliGraphOneline']));
        $t->true(!str_contains($example['cliOnelineStat'], '99 rows added'));
    },
    'wordpress fan in commit graph fixture renders default import review lanes' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-commit-log-fan-in-review.php';
        $example = require __DIR__ . '/../examples/wordpress-commit-log-fan-in-review.php';
        $table = new CommitLogTable();

        $graph = $table->renderLog($fixture['commits'], [
            'headHash' => $fixture['headHash'],
            'graph' => true,
            'decorate' => 'short',
        ]);
        $graphOneline = $table->renderLog($fixture['commits'], [
            'headHash' => $fixture['headHash'],
            'graph' => true,
            'oneline' => true,
            'decorate' => 'short',
        ]);

        $t->same($fixture['expectedGraphLines'], explode("\n", $graph));
        $t->same($fixture['expectedGraphOnelineLines'], explode("\n", $graphOneline));
        $t->same($graph, $example['cliGraph']);
        $t->same($graphOneline, $example['cliGraphOneline']);
        $t->same('wp-merge-media, wp-redirects', $example['log'][0]['parents']);
        $t->same('HEAD -> main, tag: import-reviewed', $example['log'][0]['refs']);
        $t->same('product-import', $example['log'][7]['refs']);
    },
];
