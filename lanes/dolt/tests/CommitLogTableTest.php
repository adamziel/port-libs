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
        $example = require __DIR__ . '/../examples/wordpress-commit-log-review.php';

        $t->same($fixture['expectedLogMessages'], array_column($rows, 'message'));
        $t->same($fixture['expectedHeadRefs'], $rows[0]['refs']);
        $t->same($fixture['expectedMergeParents'], $rows[0]['parents']);
        $t->same($fixture['expectedMainlineMessages'], array_column($mainlineRows, 'message'));
        $t->same($rows, $example['log']);
        $t->same(CommitLogTable::COMMITS_COLUMNS, array_keys($example['commits'][0]));
    },
];
